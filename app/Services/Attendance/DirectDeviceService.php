<?php

namespace App\Services\Attendance;

use App\Models\Device;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Rats\Zkteco\Lib\ZKTeco;

class DirectDeviceService
{
    /**
     * Connects to a device via UDP port 4370 (ZKTeco Protocol), fetches all users,
     * checks their registered fingerprints, and syncs them to the database.
     *
     * @param Device $device
     * @return array Result of the sync operation
     */
    public function syncUsersFromDevice(Device $device): array
    {
        if (empty($device->ip_address)) {
            return ['status' => false, 'message' => 'Device has no IP address configured.'];
        }

        $zk = new ZKTeco($device->ip_address, 4370);
        
        if (!$zk->connect()) {
            Log::error("Failed to connect to device via ZKLib", ['device_id' => $device->id, 'ip' => $device->ip_address]);
            return ['status' => false, 'message' => "Could not connect to device at {$device->ip_address}:4370."];
        }

        try {
            $deviceUsers = $zk->getUser();

            if (! is_array($deviceUsers)) {
                return ['status' => false, 'message' => 'The device returned an invalid user response.'];
            }

            // The library defaults to a 60-second socket timeout. Fingerprint reads are
            // optional metadata, so a missing template must never hold up every user.
            socket_set_option($zk->_zkclient, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 2, 'usec' => 0]);

            $syncedCount = 0;
            $fingerCount = 0;
            $fingerReadFailures = 0;
            $userModelClass = config('zkteco-adms.models.user', User::class);

            foreach ($deviceUsers as $zkUser) {
                $pin = trim((string) ($zkUser['userid'] ?? ''));

                // ZKLib sometimes returns empty user slots. Skip only those slots.
                if ($pin === '') {
                    continue;
                }

                $existingUser = $userModelClass::where('pin', $pin)->first();
                $user = $userModelClass::updateOrCreate(
                    ['pin' => $pin],
                    [
                        'name' => ($zkUser['name'] ?? '') ?: ($existingUser?->name ?: "User {$pin}"),
                        'card_number' => $this->normaliseCardNumber($zkUser['cardno'] ?? null),
                        'privilege' => $zkUser['role'] ?? 0,
                    ],
                );
                $syncedCount++;

                // getUser() is keyed by PIN, not by the internal device UID. The UID is
                // embedded in each result and is the only valid identifier for templates.
                $uid = $zkUser['uid'] ?? null;
                if (! is_numeric($uid)) {
                    $fingerReadFailures++;
                    continue;
                }

                try {
                    $deviceFingers = $zk->getFingerprint((int) $uid);
                } catch (\Throwable $exception) {
                    $fingerReadFailures++;
                    Log::warning('Could not read user fingerprints from direct device.', [
                        'device_id' => $device->id,
                        'pin' => $pin,
                        'uid' => $uid,
                        'error' => $exception->getMessage(),
                    ]);
                    continue;
                }

                if (! is_array($deviceFingers) || $deviceFingers === []) {
                    continue;
                }

                $fingerprints = [];
                foreach ($deviceFingers as $fingerId => $fingerData) {
                    $fingerprints[$fingerId] = [
                        'size' => strlen($fingerData),
                        'valid' => 1,
                        'template' => base64_encode($fingerData),
                    ];
                }

                $user->update(['fingerprints' => $fingerprints]);
                $fingerCount += count($fingerprints);
            }

            $zk->disconnect();
            return [
                'status' => true,
                'message' => "Synced {$syncedCount} users and {$fingerCount} fingerprint templates from the device."
                    . ($fingerReadFailures > 0 ? " {$fingerReadFailures} fingerprint reads could not be completed." : ''),
            ];

        } catch (\Exception $e) {
            $zk->disconnect();
            Log::error("Error syncing users via ZKLib", [
                'device_id' => $device->id,
                'error' => $e->getMessage()
            ]);
            return ['status' => false, 'message' => "Error while syncing: " . $e->getMessage()];
        }
    }

    private function normaliseCardNumber(mixed $card): ?string
    {
        $card = trim((string) $card);

        return $card === '' || preg_match('/^0+$/', $card) ? null : $card;
    }

    /**
     * Connects to a device via UDP port 4370 (ZKTeco Protocol) and pushes the selected users,
     * including their fingerprints, RFID cards, and passwords.
     *
     * @param Device $device
     * @param iterable $users
     * @return array Result of the push operation
     */
    public function pushUsersToDevice(Device $device, iterable $users): array
    {
        if (empty($device->ip_address)) {
            return ['status' => false, 'message' => 'Device has no IP address configured.'];
        }

        $zk = new ZKTeco($device->ip_address, 4370);
        
        if (!$zk->connect()) {
            Log::error("Failed to connect to device via ZKLib for push", ['device_id' => $device->id]);
            return ['status' => false, 'message' => "Could not connect to device at {$device->ip_address}:4370."];
        }

        try {
            $deviceUsers = $zk->getUser();
            $deviceUsersByPin = [];
            $maxUid = 0;
            if (is_array($deviceUsers)) {
                foreach ($deviceUsers as $zkUser) {
                    $pin = trim((string) ($zkUser['userid'] ?? ''));
                    $uid = $zkUser['uid'] ?? null;

                    if ($pin === '' || ! is_numeric($uid)) {
                        continue;
                    }

                    $uid = (int) $uid;
                    $deviceUsersByPin[$pin] = $uid;
                    if ($uid > $maxUid) {
                        $maxUid = $uid;
                    }
                }
            }

            $syncedCount = 0;
            foreach ($users as $user) {
                $pin = (string)$user->pin;
                if (isset($deviceUsersByPin[$pin])) {
                    $uid = $deviceUsersByPin[$pin];
                } else {
                    $maxUid++;
                    $uid = $maxUid;
                }

                $role = (int)$user->privilege;
                $card = $user->card_number ? (int)$user->card_number : 0;
                $password = $user->device_password ?? '';
                
                // Set User Profile
                $zk->setUser($uid, $pin, $user->name, $password, $role, $card);

                // Set Fingerprints if any
                if (!empty($user->fingerprints) && is_array($user->fingerprints)) {
                    $fingerDataArray = [];
                    foreach ($user->fingerprints as $fingerId => $data) {
                        if (isset($data['template'])) {
                            $fingerDataArray[$fingerId] = base64_decode($data['template']);
                        }
                    }
                    if (!empty($fingerDataArray)) {
                        $zk->setFingerprint($uid, $fingerDataArray);
                    }
                }
                
                $syncedCount++;
            }

            $zk->disconnect();
            return ['status' => true, 'message' => "Successfully pushed {$syncedCount} users to the device."];
        } catch (\Exception $e) {
            $zk->disconnect();
            Log::error("Error pushing users via ZKLib", ['error' => $e->getMessage()]);
            return ['status' => false, 'message' => "Error while pushing: " . $e->getMessage()];
        }
    }

    /**
     * Test local network connection to a ZKTeco device.
     *
     * @param Device $device
     * @return array
     */
    public function testConnection(Device $device): array
    {
        if (empty($device->ip_address)) {
            return ['status' => false, 'message' => 'Device has no IP address configured. Please edit the device and set its local IP.'];
        }

        $zk = new ZKTeco($device->ip_address, 4370);
        
        if (!$zk->connect()) {
            return ['status' => false, 'message' => "Could not connect to device at {$device->ip_address}:4370."];
        }

        $deviceName = $zk->deviceName();
        $zk->disconnect();

        return ['status' => true, 'message' => "Successfully connected to device. Device Name: {$deviceName}"];
    }

    /**
     * Sync attendance logs from a local ZKTeco device.
     *
     * @param Device $device
     * @return array
     */
    public function syncAttendanceLogs(Device $device): array
    {
        if (empty($device->ip_address)) {
            return ['status' => false, 'message' => 'Device has no IP address configured. Please edit the device and set its local IP.'];
        }

        $zk = new ZKTeco($device->ip_address, 4370);
        
        if (!$zk->connect()) {
            return ['status' => false, 'message' => "Could not connect to device at {$device->ip_address}:4370."];
        }

        try {
            $attendanceLogs = $zk->getAttendance();

            if (!is_array($attendanceLogs) || empty($attendanceLogs)) {
                $zk->disconnect();

                return ['status' => true, 'message' => 'No attendance logs found on the device.'];
            }

            $syncedCount = 0;
            $attendanceLogModelClass = config('zkteco-adms.models.attendance_log', \App\Models\AttendanceLog::class);

            foreach ($attendanceLogs as $log) {
                $pin = (string)$log['id'];
                $timestamp = $log['timestamp'];
                $state = $log['state'] ?? 1; 
                $type = $log['type'] ?? 1; 

                $attendanceLogModelClass::firstOrCreate([
                    'device_id' => $device->id,
                    'pin' => $pin,
                    'punched_at' => $timestamp,
                ], [
                    'status' => $state,
                    'verify_type' => $type,
                    'raw_data' => $log,
                ]);
                $syncedCount++;
            }

            $zk->disconnect();

            return ['status' => true, 'message' => "Successfully synced {$syncedCount} attendance logs."];

        } catch (\Exception $e) {
            $zk->disconnect();
            Log::error("Error syncing attendance logs via ZKLib", ['error' => $e->getMessage()]);
            return ['status' => false, 'message' => "Error while syncing logs: " . $e->getMessage()];
        }
    }
}
