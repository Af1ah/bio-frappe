<?php

namespace App\Services\Attendance;

use App\Models\Device;
use Rats\Zkteco\Lib\ZKTeco;

class DirectDeviceCommandService
{
    /** @return array{status: bool, message: string} */
    public function execute(Device $device, string $commandType): array
    {
        if (blank($device->ip_address)) {
            return ['status' => false, 'message' => 'Device IP address is not configured.'];
        }

        $zk = new ZKTeco($device->ip_address, 4370);

        if (! $zk->connect()) {
            return ['status' => false, 'message' => "Could not connect to {$device->ip_address}:4370."];
        }

        try {
            $result = match (strtolower($commandType)) {
                'check' => $zk->deviceName() !== false,
                'info' => $zk->serialNumber() !== false,
                'sync_time' => $zk->setTime(now((string) data_get($device->options, 'timezone', config('app.timezone')))->format('Y-m-d H:i:s')),
                'reboot' => $zk->restart(),
                'clear', 'clear_logs' => $zk->clearAttendance(),
                'reset_transaction_stamp' => $this->resetStamp($device, 'att_stamp'),
                'reset_op_stamp' => $this->resetStamp($device, 'op_stamp'),
                default => null,
            };

            if ($result === null) {
                return [
                    'status' => false,
                    'message' => "{$commandType} is not supported by the direct ZKTeco protocol for this device. It was not sent to eBioServer.",
                ];
            }

            return $result
                ? ['status' => true, 'message' => "Direct {$commandType} command completed."]
                : ['status' => false, 'message' => "Direct {$commandType} command was rejected by the device."];
        } finally {
            $zk->disconnect();
        }
    }

    private function resetStamp(Device $device, string $column): bool
    {
        $device->update([$column => 0]);

        return true;
    }
}
