<?php

namespace Tests\Feature;

use App\Jobs\EbioDeviceCommandJob;
use App\Models\AttendanceLog;
use App\Models\Device;
use App\Models\DeviceCommand;
use App\Models\Organisation;
use App\Services\EbioSoapService;
use App\Services\ZktecoService;
use Mockery;
use Tests\TestCase;

class EbioDeviceCommandJobTest extends TestCase
{
    public function tearDown(): void
    {
        tenancy()->end();
        foreach (Organisation::all() as $org) {
            $org->delete();
        }
        Mockery::close();
        parent::tearDown();
    }

    public function test_command_uses_zkteco_service_when_ip_is_present(): void
    {
        $organisation = Organisation::create([
            'name' => 'Direct ZK Org',
            'db_name' => 'test_org_zk_cmd_1',
        ]);

        tenancy()->initialize($organisation);

        $device = Device::create([
            'serial_number' => 'DEV_ZK_100',
            'name' => 'Main Gate ZK',
            'ip_address' => '192.168.1.50',
            'port' => 4370,
            'vendor' => 'zkteco',
        ]);

        $command = DeviceCommand::create([
            'device_id' => $device->id,
            'command_type' => 'reboot',
            'command_content' => 'Reboot command',
            'status' => 'pending',
        ]);

        tenancy()->end();

        $zkServiceMock = Mockery::mock(ZktecoService::class);
        $zkServiceMock->shouldReceive('rebootDevice')
            ->with('192.168.1.50', 4370)
            ->once()
            ->andReturn(true);

        $soapServiceMock = Mockery::mock(EbioSoapService::class);
        $soapServiceMock->shouldNotReceive('rebootDevice');

        $job = new EbioDeviceCommandJob($organisation, 'DEV_ZK_100', 'reboot', $command->id);
        $job->handle($soapServiceMock, $zkServiceMock);

        tenancy()->initialize($organisation);
        $command->refresh();
        $this->assertEquals('acknowledged', $command->status);
        $this->assertStringContainsString('reboot', strtolower($command->response));
    }

    public function test_door_unlock_command(): void
    {
        $organisation = Organisation::create([
            'name' => 'Door Unlock Org',
            'db_name' => 'test_org_door_cmd',
        ]);

        tenancy()->initialize($organisation);

        $device = Device::create([
            'serial_number' => 'DEV_DOOR_1',
            'name' => 'Front Door Access',
            'ip_address' => '192.168.1.60',
            'port' => 4370,
        ]);

        $command = DeviceCommand::create([
            'device_id' => $device->id,
            'command_type' => 'unlock_door',
            'command_content' => 'Door unlock pulse',
            'status' => 'pending',
        ]);

        tenancy()->end();

        $zkServiceMock = Mockery::mock(ZktecoService::class);
        $zkServiceMock->shouldReceive('unlockDoor')
            ->with('192.168.1.60', 4370)
            ->once()
            ->andReturn(true);

        $soapServiceMock = Mockery::mock(EbioSoapService::class);

        $job = new EbioDeviceCommandJob($organisation, 'DEV_DOOR_1', 'unlock_door', $command->id);
        $job->handle($soapServiceMock, $zkServiceMock);

        tenancy()->initialize($organisation);
        $command->refresh();
        $this->assertEquals('acknowledged', $command->status);
        $this->assertStringContainsString('unlocked', strtolower($command->response));
    }

    public function test_fetch_attendance_command(): void
    {
        $organisation = Organisation::create([
            'name' => 'Fetch Attendance Org',
            'db_name' => 'test_org_fetch_att',
        ]);

        tenancy()->initialize($organisation);

        $device = Device::create([
            'serial_number' => 'DEV_ATT_1',
            'name' => 'Office Biometric',
            'ip_address' => '192.168.1.70',
            'port' => 4370,
            'punch_behavior' => 'device_state',
        ]);

        $command = DeviceCommand::create([
            'device_id' => $device->id,
            'command_type' => 'fetch_attendance',
            'command_content' => 'Fetch attendance logs',
            'status' => 'pending',
        ]);

        tenancy()->end();

        $zkServiceMock = Mockery::mock(ZktecoService::class);
        $zkServiceMock->shouldReceive('getAttendanceLogs')
            ->with('192.168.1.70', 4370)
            ->once()
            ->andReturn([
                ['id' => '1001', 'state' => 0, 'timestamp' => '2026-09-18 09:00:00'],
                ['id' => '1002', 'state' => 1, 'timestamp' => '2026-09-18 09:05:00'],
            ]);

        $soapServiceMock = Mockery::mock(EbioSoapService::class);

        $job = new EbioDeviceCommandJob($organisation, 'DEV_ATT_1', 'fetch_attendance', $command->id);
        $job->handle($soapServiceMock, $zkServiceMock);

        tenancy()->initialize($organisation);
        $command->refresh();
        $this->assertEquals('acknowledged', $command->status);
        $this->assertStringContainsString('Fetched 2 records', $command->response);

        $this->assertDatabaseHas('attendance_logs', [
            'pin' => '1001',
            'status' => 0,
        ]);
        $this->assertDatabaseHas('attendance_logs', [
            'pin' => '1002',
            'status' => 1,
        ]);
    }

    public function test_command_falls_back_to_ebio_soap_when_no_ip(): void
    {
        $organisation = Organisation::create([
            'name' => 'eBio Fallback Org',
            'db_name' => 'test_org_ebio_cmd_2',
            'ebio_url' => 'http://ebio.example.com/iclock',
        ]);

        tenancy()->initialize($organisation);

        $device = Device::create([
            'serial_number' => 'DEV_EBIO_200',
            'name' => 'eBio Gate',
            'ip_address' => null,
            'vendor' => 'zkteco',
        ]);

        $command = DeviceCommand::create([
            'device_id' => $device->id,
            'command_type' => 'reboot',
            'command_content' => 'Reboot command',
            'status' => 'pending',
        ]);

        tenancy()->end();

        $zkServiceMock = Mockery::mock(ZktecoService::class);
        $zkServiceMock->shouldNotReceive('rebootDevice');

        $soapServiceMock = Mockery::mock(EbioSoapService::class);
        $soapServiceMock->shouldReceive('rebootDevice')
            ->with($organisation, 'DEV_EBIO_200')
            ->once()
            ->andReturn(true);

        $job = new EbioDeviceCommandJob($organisation, 'DEV_EBIO_200', 'reboot', $command->id);
        $job->handle($soapServiceMock, $zkServiceMock);

        tenancy()->initialize($organisation);
        $command->refresh();
        $this->assertEquals('acknowledged', $command->status);
        $this->assertStringContainsString('eBioServer SOAP', $command->response);
    }
}
