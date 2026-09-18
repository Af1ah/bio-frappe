<?php

namespace App\Services;

use App\Enums\DeviceTransport;
use App\Models\Device;

class DeviceCommandCapabilities
{
    /** @return array<string, string> */
    public function options(Device $device): array
    {
        $transport = DeviceTransport::forDevice($device);

        $options = match ($transport) {
            DeviceTransport::Ebio => [
                'reboot' => 'Reboot Device',
                'clear_logs' => 'Clear Attendance Logs',
                'reset_transaction_stamp' => 'Reset Transaction Stamp',
                'reset_op_stamp' => 'Reset OP Stamp',
            ],
            DeviceTransport::Direct => [
                'sync_time' => 'Sync Device Time',
                'reboot' => 'Reboot Device',
                'clear_logs' => 'Clear Attendance Logs',
                'reset_transaction_stamp' => 'Reset Transaction Stamp',
                'reset_op_stamp' => 'Reset OP Stamp',
            ],
            DeviceTransport::Adms => [
                'device_info' => 'Fetch Device Info',
                'check' => 'Check Connection',
                'reboot' => 'Reboot Device',
                'clear_logs' => 'Clear Device Logs',
                'reset_transaction_stamp' => 'Reset Transaction Stamp',
                'reset_op_stamp' => 'Reset OP Stamp',
                'fetch_users' => 'Fetch Users from Device',
                'delete_user' => 'Delete User from Device',
            ],
        };

        if ($transport === DeviceTransport::Adms) {
            if ($device->supportsEnrollment('fingerprint')) {
                $options['enroll_fingerprint'] = 'Trigger Fingerprint Enrollment';
            }
            if ($device->supportsEnrollment('face') || $device->supportsEnrollment('face_v2')) {
                $options['enroll_face'] = 'Trigger Face Enrollment';
            }
        }

        // Isolation rule: Door unlock is only enabled for door-based devices
        // (Legacy eBio devices without an explicit type retain default access)
        $isDoor = $device->isDoorBased() || ($transport === DeviceTransport::Ebio && ! isset($device->options['type']));
        if ($isDoor && $transport !== DeviceTransport::Direct) {
            $options['unlock_door'] = 'Unlock Door';
            $options['lock_door'] = 'Lock Door';
            $options['normal_door'] = 'Normal Door';
        }

        return $options;
    }

    public function supports(Device $device, string $command): bool
    {
        return array_key_exists($command, $this->options($device));
    }
}
