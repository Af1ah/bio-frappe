<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\Organisation;
use App\Services\Attendance\DeviceCommandBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceControlController extends Controller
{
    public function unlock(Request $request, string $device): JsonResponse
    {
        return $this->executeDoorAction($request, $device, 'unlockDoor', 'unlock');
    }

    public function lock(Request $request, string $device): JsonResponse
    {
        return $this->executeDoorAction($request, $device, 'lockDoor', 'lock');
    }

    public function normalize(Request $request, string $device): JsonResponse
    {
        return $this->executeDoorAction($request, $device, 'normalizeDoor', 'normalize');
    }

    public function status(Request $request, string $device): JsonResponse
    {
        $target = $this->resolveDevice($request, $device);

        if (! $target) {
            return response()->json([
                'success' => false,
                'message' => "Device [{$device}] not found.",
            ], 404);
        }

        return response()->json([
            'success' => true,
            'device' => [
                'id' => $target->id,
                'name' => $target->name,
                'serial_number' => $target->serial_number,
                'vendor' => $target->vendor,
                'ip_address' => $target->ip_address,
                'port' => $target->port,
                'status' => $target->isOnline() ? 'online' : 'offline',
                'last_activity_at' => $target->last_activity_at?->toIso8601String(),
            ],
        ]);
    }

    protected function executeDoorAction(Request $request, string $deviceIdentifier, string $method, string $actionName): JsonResponse
    {
        $target = $this->resolveDevice($request, $deviceIdentifier);

        if (! $target) {
            return response()->json([
                'success' => false,
                'message' => "Device [{$deviceIdentifier}] not found.",
            ], 404);
        }

        $builder = app(DeviceCommandBuilder::class);
        $command = $builder->$method($target);

        $isSuccess = $command->status !== 'failed';

        return response()->json([
            'success' => $isSuccess,
            'action' => $actionName,
            'device_id' => $target->id,
            'device_name' => $target->name ?: $target->serial_number,
            'vendor' => $target->vendor,
            'command_id' => $command->id,
            'status' => $command->status,
            'message' => $command->response ?: "Door {$actionName} command processed.",
        ], $isSuccess ? 200 : 400);
    }

    protected function resolveDevice(Request $request, string $identifier): ?Device
    {
        if (tenancy()->initialized) {
            return is_numeric($identifier)
                ? Device::find($identifier)
                : Device::where('serial_number', $identifier)->first();
        }

        // If specific tenant provided
        $tenantId = $request->header('X-Tenant') ?: $request->query('tenant');
        if ($tenantId) {
            $org = Organisation::find($tenantId);
            if ($org) {
                tenancy()->initialize($org);
                return is_numeric($identifier)
                    ? Device::find($identifier)
                    : Device::where('serial_number', $identifier)->first();
            }
        }

        // Search across organisations
        foreach (Organisation::all() as $org) {
            tenancy()->initialize($org);
            $found = is_numeric($identifier)
                ? Device::find($identifier)
                : Device::where('serial_number', $identifier)->first();

            if ($found) {
                return $found;
            }
            tenancy()->end();
        }

        return null;
    }
}
