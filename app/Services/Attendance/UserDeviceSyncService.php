<?php

namespace App\Services\Attendance;

use App\Enums\DeviceTransport;
use App\Jobs\DeleteEbioUserJob;
use App\Jobs\DirectDeviceDataSyncJob;
use App\Jobs\PushEbioUserJob;
use App\Models\Device;
use App\Models\DeviceCommand;
use App\Models\DeviceUser;
use App\Models\User;
use App\Services\DeviceCommandDispatcher;

class UserDeviceSyncService
{
    public function __construct(
        private readonly DeviceCommandDispatcher $dispatcher,
    ) {}

    /**
     * Delete a user from a specific biometric device.
     */
    public function deleteUserFromDevice(Device $device, User|string $userOrPin): ?DeviceCommand
    {
        $pin = $userOrPin instanceof User ? (string) $userOrPin->pin : (string) $userOrPin;
        $transport = DeviceTransport::forDevice($device);

        // Remove from local device_users registry
        DeviceUser::query()
            ->where('device_id', $device->id)
            ->where('pin', $pin)
            ->delete();

        if ($transport === DeviceTransport::Adms) {
            $command = DeviceCommand::create([
                'device_id' => $device->id,
                'command_type' => 'delete_user',
                'command_content' => json_encode(['pin' => $pin]),
                'status' => 'pending',
            ]);

            $this->dispatcher->dispatch($device, $command);

            return $command;
        }

        if ($transport === DeviceTransport::Ebio) {
            $location = $device->options['location'] ?? '';
            DeleteEbioUserJob::dispatch(tenant(), $pin, $location);

            return null;
        }

        if ($transport === DeviceTransport::Direct) {
            $command = DeviceCommand::create([
                'device_id' => $device->id,
                'command_type' => 'delete_user',
                'command_content' => json_encode(['pin' => $pin]),
                'status' => 'pending',
            ]);

            $this->dispatcher->dispatch($device, $command);

            return $command;
        }

        return null;
    }

    /**
     * Upload a user and their selected credentials to a target device.
     *
     * @param  array<string>  $selectedCredentials  ['pin', 'card', 'fingerprint', 'face']
     * @return array<string, mixed>
     */
    public function uploadUserToDevice(Device $device, User $user, array $selectedCredentials = ['pin', 'card', 'fingerprint', 'face']): array
    {
        $transport = DeviceTransport::forDevice($device);
        $pin = (string) $user->pin;
        $queuedCommands = [];
        $skipped = [];

        // Check capabilities
        $includePin = in_array('pin', $selectedCredentials, true);
        $includeCard = in_array('card', $selectedCredentials, true);
        $includeFp = in_array('fingerprint', $selectedCredentials, true);
        $includeFace = in_array('face', $selectedCredentials, true);

        if ($includeFp && ! $device->supportsEnrollment('fingerprint')) {
            $includeFp = false;
            $skipped[] = 'Device does not support fingerprint enrollment.';
        }

        if ($includeCard && ! $device->supportsEnrollment('rfid')) {
            $includeCard = false;
            $skipped[] = 'Device does not support RFID card enrollment.';
        }

        $supportsFaceV1 = $device->supportsEnrollment('face');
        $supportsFaceV2 = $device->supportsEnrollment('face_v2');
        if ($includeFace && ! $supportsFaceV1 && ! $supportsFaceV2) {
            $includeFace = false;
            $skipped[] = 'Device does not support face enrollment.';
        }

        if ($transport === DeviceTransport::Adms) {
            // 1. Base User Info Command
            $userPayload = [
                'pin' => $pin,
                'name' => $user->name ?: "User {$pin}",
                'privilege' => (int) ($user->privilege ?? 0),
            ];

            if ($includeCard && filled($user->card_number)) {
                $userPayload['card'] = $user->card_number;
            }

            if ($includePin && filled($user->device_password)) {
                $userPayload['password'] = $user->device_password;
            }

            $userCmd = DeviceCommand::create([
                'device_id' => $device->id,
                'command_type' => 'upload_user',
                'command_content' => json_encode($userPayload),
                'status' => 'pending',
            ]);
            $this->dispatcher->dispatch($device, $userCmd);
            $queuedCommands[] = $userCmd;

            // 2. Fingerprint templates
            if ($includeFp && $user->hasFingerprints()) {
                $fingerprints = (array) $user->fingerprints;
                foreach ($fingerprints as $idx => $fp) {
                    $template = is_array($fp) ? ($fp['template'] ?? '') : (string) $fp;
                    $fid = is_array($fp) ? ($fp['fid'] ?? $idx) : $idx;
                    $size = is_array($fp) ? ($fp['size'] ?? strlen($template)) : strlen($template);

                    if (filled($template)) {
                        $fpCmd = DeviceCommand::create([
                            'device_id' => $device->id,
                            'command_type' => 'upload_fingerprint',
                            'command_content' => json_encode([
                                'pin' => $pin,
                                'fid' => (int) $fid,
                                'size' => (int) $size,
                                'template' => $template,
                                'valid' => 1,
                            ]),
                            'status' => 'pending',
                        ]);
                        $this->dispatcher->dispatch($device, $fpCmd);
                        $queuedCommands[] = $fpCmd;
                    }
                }
            }

            // 3. Face / Face v2 templates
            if ($includeFace) {
                if ($supportsFaceV2 && $user->hasFaceV2()) {
                    $v2Templates = (array) $user->face_v2_templates;
                    foreach ($v2Templates as $v2) {
                        $payload = is_array($v2) ? $v2 : ['template' => (string) $v2];
                        $payload['pin'] = $pin;

                        $faceCmd = DeviceCommand::create([
                            'device_id' => $device->id,
                            'command_type' => 'upload_face_v2',
                            'command_content' => json_encode($payload),
                            'status' => 'pending',
                        ]);
                        $this->dispatcher->dispatch($device, $faceCmd);
                        $queuedCommands[] = $faceCmd;
                    }
                } elseif (($supportsFaceV1 || $supportsFaceV2) && $user->hasFace()) {
                    $faces = (array) $user->face_templates;
                    foreach ($faces as $idx => $face) {
                        $template = is_array($face) ? ($face['template'] ?? '') : (string) $face;
                        $size = is_array($face) ? ($face['size'] ?? strlen($template)) : strlen($template);

                        if (filled($template)) {
                            $faceCmd = DeviceCommand::create([
                                'device_id' => $device->id,
                                'command_type' => 'upload_face',
                                'command_content' => json_encode([
                                    'pin' => $pin,
                                    'fid' => 0,
                                    'size' => (int) $size,
                                    'template' => $template,
                                    'valid' => 1,
                                ]),
                                'status' => 'pending',
                            ]);
                            $this->dispatcher->dispatch($device, $faceCmd);
                            $queuedCommands[] = $faceCmd;
                        }
                    }
                }
            }

            // Update or create local device_users tracking record
            DeviceUser::query()->updateOrCreate(
                ['device_id' => $device->id, 'pin' => $pin],
                [
                    'name' => $user->name,
                    'card_number' => $includeCard ? $user->card_number : null,
                    'privilege' => (int) ($user->privilege ?? 0),
                    'fingerprint_count' => $includeFp ? count((array) ($user->fingerprints ?? [])) : 0,
                    'face_count' => $includeFace ? (count((array) ($user->face_templates ?? [])) + count((array) ($user->face_v2_templates ?? []))) : 0,
                    'enrolled_methods' => $selectedCredentials,
                    'last_seen_at' => now(),
                ],
            );
        } elseif ($transport === DeviceTransport::Ebio) {
            $location = $device->options['location'] ?? '';
            PushEbioUserJob::dispatch(tenant(), $user->id, $location);
        } elseif ($transport === DeviceTransport::Direct) {
            DirectDeviceDataSyncJob::dispatch(tenant(), $device->id, 'push_users', [$user->id]);
        }

        return [
            'success' => true,
            'commands_queued' => count($queuedCommands),
            'skipped_warnings' => $skipped,
        ];
    }

    /**
     * Trigger on-device enrollment for a user.
     */
    public function triggerOnDeviceEnrollment(Device $device, User|string $userOrPin, string $method, int $fingerIndex = 0): ?DeviceCommand
    {
        $transport = DeviceTransport::forDevice($device);
        $pin = $userOrPin instanceof User ? (string) $userOrPin->pin : (string) $userOrPin;

        if ($transport === DeviceTransport::Adms) {
            $commandType = match ($method) {
                'fingerprint', 'finger' => 'enroll_fingerprint',
                'face' => 'enroll_face',
                default => 'enroll_fingerprint',
            };

            $payload = [
                'pin' => $pin,
                'fid' => $fingerIndex,
                'retry' => 3,
                'overwrite' => 1,
            ];

            $command = DeviceCommand::create([
                'device_id' => $device->id,
                'command_type' => $commandType,
                'command_content' => json_encode($payload),
                'status' => 'pending',
            ]);

            $this->dispatcher->dispatch($device, $command);

            return $command;
        }

        return null;
    }
}
