<?php

namespace App\Services;

use App\Models\Device;
use Exception;
use Illuminate\Support\Facades\Log;
use MehediJaman\LaravelZkteco\LaravelZkteco;

class ZktecoService
{
    /**
     * Factory callback or instance for testing.
     *
     * @var (callable(string, int): LaravelZkteco)|null
     */
    protected static $clientResolver = null;

    /**
     * Set a custom resolver for creating LaravelZkteco instances (useful in tests).
     */
    public static function setClientResolver(?callable $resolver): void
    {
        self::$clientResolver = $resolver;
    }

    /**
     * Get a LaravelZkteco client instance.
     */
    public function getClient(string $ip, int $port = 4370, int $timeoutSeconds = 2): LaravelZkteco
    {
        if (self::$clientResolver) {
            return (self::$clientResolver)($ip, $port);
        }

        $client = new LaravelZkteco($ip, $port);

        if (isset($client->_zkclient) && (is_resource($client->_zkclient) || is_object($client->_zkclient))) {
            $timeout = ['sec' => $timeoutSeconds, 'usec' => 0];
            @socket_set_option($client->_zkclient, SOL_SOCKET, SO_RCVTIMEO, $timeout);
            @socket_set_option($client->_zkclient, SOL_SOCKET, SO_SNDTIMEO, $timeout);
        }

        return $client;
    }

    /**
     * Test connection to a ZKTeco device.
     */
    public function testConnection(string $ip, int $port = 4370): bool
    {
        try {
            $zk = $this->getClient($ip, $port);
            if ($zk->connect()) {
                $zk->disconnect();
                return true;
            }
            return false;
        } catch (Exception $e) {
            Log::warning("ZKTeco connection test failed for {$ip}:{$port} - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieve device information (serial number, name, firmware, platform, os).
     */
    public function getDeviceInfo(string $ip, int $port = 4370): array
    {
        $info = [
            'serial_number' => null,
            'name' => null,
            'firmware_version' => null,
            'platform' => null,
            'os_version' => null,
            'device_time' => null,
        ];

        try {
            $zk = $this->getClient($ip, $port);
            if (! $zk->connect()) {
                return $info;
            }

            $info['serial_number'] = $zk->serialNumber() ?: null;
            $info['name'] = $zk->deviceName() ?: null;
            $info['firmware_version'] = $zk->fmVersion() ?: null;
            $info['platform'] = $zk->platform() ?: null;
            $info['os_version'] = $zk->osVersion() ?: null;
            $info['device_time'] = $zk->getTime() ?: null;

            $zk->disconnect();
        } catch (Exception $e) {
            Log::error("Failed to fetch ZKTeco device info for {$ip}:{$port} - " . $e->getMessage());
        }

        return $info;
    }

    /**
     * Sync/Update device details from physical device into Device model.
     */
    public function syncDeviceState(Device $device): bool
    {
        if (! $device->ip_address) {
            return false;
        }

        $port = $device->port ?: 4370;
        $info = $this->getDeviceInfo($device->ip_address, $port);

        if ($info['serial_number'] || $info['name']) {
            $device->update([
                'serial_number' => $device->serial_number ?: $info['serial_number'],
                'name' => $device->name ?: $info['name'],
                'firmware_version' => $info['firmware_version'] ?: $device->firmware_version,
                'model' => $info['platform'] ?: $device->model,
                'status' => 'online',
                'last_activity_at' => now(),
                'last_sync_at' => now(),
            ]);
            return true;
        }

        $device->update(['status' => 'offline']);
        return false;
    }

    /**
     * Reboot the device.
     */
    public function rebootDevice(string $ip, int $port = 4370): bool
    {
        try {
            $zk = $this->getClient($ip, $port);
            if (! $zk->connect()) {
                return false;
            }
            $res = $zk->restart();
            $zk->disconnect();
            return $res !== false;
        } catch (Exception $e) {
            Log::error("Failed to reboot ZKTeco device {$ip}:{$port} - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Shutdown / Power off the device.
     */
    public function shutdownDevice(string $ip, int $port = 4370): bool
    {
        try {
            $zk = $this->getClient($ip, $port);
            if (! $zk->connect()) {
                return false;
            }
            $res = $zk->shutdown();
            $zk->disconnect();
            return $res !== false;
        } catch (Exception $e) {
            Log::error("Failed to shutdown ZKTeco device {$ip}:{$port} - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Unlock door / trigger access control relay.
     */
    public function unlockDoor(string $ip, int $port = 4370): bool
    {
        try {
            $zk = $this->getClient($ip, $port);
            if (! $zk->connect()) {
                return false;
            }
            $result = $zk->_command(31, '') !== false;
            $zk->disconnect();
            return $result;
        } catch (Exception $e) {
            Log::error("Failed to unlock door on ZKTeco device {$ip}:{$port} - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Trigger voice test on the device.
     */
    public function testVoice(string $ip, int $port = 4370): bool
    {
        try {
            $zk = $this->getClient($ip, $port);
            if (! $zk->connect()) {
                return false;
            }
            $res = $zk->testVoice();
            $zk->disconnect();
            return $res !== false;
        } catch (Exception $e) {
            Log::error("Failed to test voice on ZKTeco device {$ip}:{$port} - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Synchronize time on the device.
     */
    public function setDeviceTime(string $ip, int $port = 4370, ?string $datetime = null): bool
    {
        $datetime = $datetime ?: now()->format('Y-m-d H:i:s');
        try {
            $zk = $this->getClient($ip, $port);
            if (! $zk->connect()) {
                return false;
            }
            $res = $zk->setTime($datetime);
            $zk->disconnect();
            return $res !== false;
        } catch (Exception $e) {
            Log::error("Failed to set time on ZKTeco device {$ip}:{$port} - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Clear attendance logs on the device.
     */
    public function clearAttendanceLogs(string $ip, int $port = 4370): bool
    {
        try {
            $zk = $this->getClient($ip, $port);
            if (! $zk->connect()) {
                return false;
            }
            $res = $zk->clearAttendance();
            $zk->disconnect();
            return $res !== false;
        } catch (Exception $e) {
            Log::error("Failed to clear attendance on ZKTeco device {$ip}:{$port} - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetch attendance logs from device.
     */
    public function getAttendanceLogs(string $ip, int $port = 4370): array
    {
        try {
            $zk = $this->getClient($ip, $port);
            if (! $zk->connect()) {
                return [];
            }
            $logs = $zk->getAttendance();
            $zk->disconnect();
            return is_array($logs) ? $logs : [];
        } catch (Exception $e) {
            Log::error("Failed to fetch attendance logs from ZKTeco device {$ip}:{$port} - " . $e->getMessage());
            return [];
        }
    }

    /**
     * Fetch users enrolled on the device.
     */
    public function getUsers(string $ip, int $port = 4370): array
    {
        try {
            $zk = $this->getClient($ip, $port);
            if (! $zk->connect()) {
                return [];
            }
            $users = $zk->getUser();
            $zk->disconnect();
            return is_array($users) ? $users : [];
        } catch (Exception $e) {
            Log::error("Failed to fetch users from ZKTeco device {$ip}:{$port} - " . $e->getMessage());
            return [];
        }
    }

    /**
     * Push or update a user on the device.
     */
    public function setUser(string $ip, int $port, int $uid, string|int $userid, string $name, string $password = '', int $role = 0, int $cardno = 0): bool
    {
        try {
            $zk = $this->getClient($ip, $port);
            if (! $zk->connect()) {
                return false;
            }
            $result = (bool) $zk->setUser($uid, $userid, $name, $password, $role, $cardno);
            $zk->disconnect();
            return $result;
        } catch (Exception $e) {
            Log::error("Failed to set user on ZKTeco device {$ip}:{$port} - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Remove a user from the device.
     */
    public function removeUser(string $ip, int $port, int $uid): bool
    {
        try {
            $zk = $this->getClient($ip, $port);
            if (! $zk->connect()) {
                return false;
            }
            $result = (bool) $zk->removeUser($uid);
            $zk->disconnect();
            return $result;
        } catch (Exception $e) {
            Log::error("Failed to remove user from ZKTeco device {$ip}:{$port} - " . $e->getMessage());
            return false;
        }
    }
}
