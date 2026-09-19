<?php

namespace Tests\Unit;

use App\Models\Device;
use App\Services\ZktecoService;
use MehediJaman\LaravelZkteco\LaravelZkteco;
use Mockery;
use Tests\TestCase;

class ZktecoServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        ZktecoService::setClientResolver(null);
        Mockery::close();
        parent::tearDown();
    }

    public function test_test_connection_successful(): void
    {
        $mock = Mockery::mock(LaravelZkteco::class);
        $mock->shouldReceive('connect')->once()->andReturn(true);
        $mock->shouldReceive('disconnect')->once()->andReturn(true);

        ZktecoService::setClientResolver(fn () => $mock);

        $service = new ZktecoService();
        $this->assertTrue($service->testConnection('192.168.1.100', 4370));
    }

    public function test_test_connection_failed(): void
    {
        $mock = Mockery::mock(LaravelZkteco::class);
        $mock->shouldReceive('connect')->once()->andReturn(false);

        ZktecoService::setClientResolver(fn () => $mock);

        $service = new ZktecoService();
        $this->assertFalse($service->testConnection('192.168.1.100', 4370));
    }

    public function test_get_device_info(): void
    {
        $mock = Mockery::mock(LaravelZkteco::class);
        $mock->shouldReceive('connect')->once()->andReturn(true);
        $mock->shouldReceive('serialNumber')->once()->andReturn('ZKTEST123456');
        $mock->shouldReceive('deviceName')->once()->andReturn('ZKTeco K40');
        $mock->shouldReceive('fmVersion')->once()->andReturn('Ver 6.60');
        $mock->shouldReceive('platform')->once()->andReturn('ZEM560');
        $mock->shouldReceive('osVersion')->once()->andReturn('Linux 3.0');
        $mock->shouldReceive('getTime')->once()->andReturn('2026-09-18 12:00:00');
        $mock->shouldReceive('disconnect')->once()->andReturn(true);

        ZktecoService::setClientResolver(fn () => $mock);

        $service = new ZktecoService();
        $info = $service->getDeviceInfo('192.168.1.100', 4370);

        $this->assertEquals('ZKTEST123456', $info['serial_number']);
        $this->assertEquals('ZKTeco K40', $info['name']);
        $this->assertEquals('Ver 6.60', $info['firmware_version']);
        $this->assertEquals('ZEM560', $info['platform']);
        $this->assertEquals('Linux 3.0', $info['os_version']);
        $this->assertEquals('2026-09-18 12:00:00', $info['device_time']);
    }

    public function test_reboot_device(): void
    {
        $mock = Mockery::mock(LaravelZkteco::class);
        $mock->shouldReceive('connect')->once()->andReturn(true);
        $mock->shouldReceive('restart')->once()->andReturn(true);
        $mock->shouldReceive('disconnect')->once()->andReturn(true);

        ZktecoService::setClientResolver(fn () => $mock);

        $service = new ZktecoService();
        $this->assertTrue($service->rebootDevice('192.168.1.100', 4370));
    }

    public function test_get_attendance_logs(): void
    {
        $mockLogs = [
            ['uid' => 1, 'id' => '1001', 'state' => 1, 'timestamp' => '2026-09-18 09:00:00'],
            ['uid' => 2, 'id' => '1002', 'state' => 1, 'timestamp' => '2026-09-18 09:05:00'],
        ];

        $mock = Mockery::mock(LaravelZkteco::class);
        $mock->shouldReceive('connect')->once()->andReturn(true);
        $mock->shouldReceive('getAttendance')->once()->andReturn($mockLogs);
        $mock->shouldReceive('disconnect')->once()->andReturn(true);

        ZktecoService::setClientResolver(fn () => $mock);

        $service = new ZktecoService();
        $logs = $service->getAttendanceLogs('192.168.1.100', 4370);

        $this->assertCount(2, $logs);
        $this->assertEquals('1001', $logs[0]['id']);
    }

    public function test_user_operations(): void
    {
        $mock = Mockery::mock(LaravelZkteco::class);
        $mock->shouldReceive('connect')->twice()->andReturn(true);
        $mock->shouldReceive('setUser')->with(1, '1001', 'John Doe', '', 0, 0)->once()->andReturn(true);
        $mock->shouldReceive('removeUser')->with(1)->once()->andReturn(true);
        $mock->shouldReceive('disconnect')->twice()->andReturn(true);

        ZktecoService::setClientResolver(fn () => $mock);

        $service = new ZktecoService();
        $this->assertTrue($service->setUser('192.168.1.100', 4370, 1, '1001', 'John Doe'));
        $this->assertTrue($service->removeUser('192.168.1.100', 4370, 1));
    }
}
