<?php

namespace Tests\Unit;

use App\Models\Device;
use App\Models\DeviceCommand;
use App\Models\DeviceUser;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Attendance\UserDeviceSyncService;
use App\Services\DeviceCommandDispatcher;
use Tests\TestCase;

class UserDeviceSyncServiceTest extends TestCase
{
    private Organisation $organisation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organisation = Organisation::create([
            'name' => 'Sync Test Org',
            'db_name' => 'test_org_sync_'.uniqid(),
        ]);

        tenancy()->initialize($this->organisation);
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        foreach (Organisation::all() as $org) {
            $org->delete();
        }
        parent::tearDown();
    }

    public function test_upload_user_to_adms_device_queues_commands_for_selected_credentials(): void
    {
        $mockDispatcher = $this->createMock(DeviceCommandDispatcher::class);
        $mockDispatcher->expects($this->atLeastOnce())
            ->method('dispatch')
            ->willReturnCallback(function ($device, $command) {
                $command->update(['status' => 'sent']);
            });

        $service = new UserDeviceSyncService($mockDispatcher);

        $device = Device::create([
            'serial_number' => 'DEV_ADMS_01',
            'name' => 'Main Gate',
            'status' => 'online',
            'options' => [
                'connection_mode' => 'adms',
                'enrollment_methods' => ['fingerprint', 'rfid', 'face', 'face_v2'],
            ],
        ]);

        $user = User::create([
            'pin' => '2001',
            'name' => 'John Doe',
            'card_number' => 'CARD9988',
            'device_password' => '1234',
            'fingerprints' => [
                ['fid' => 0, 'size' => 120, 'template' => 'base64fp0'],
            ],
            'face_templates' => [
                ['fid' => 0, 'size' => 250, 'template' => 'base64face0'],
            ],
            'privilege' => 0,
        ]);

        $result = $service->uploadUserToDevice($device, $user, ['pin', 'card', 'fingerprint', 'face']);

        $this->assertTrue($result['success']);
        $this->assertGreaterThanOrEqual(3, $result['commands_queued']); // upload_user, upload_fingerprint, upload_face

        $this->assertDatabaseHas('device_users', [
            'device_id' => $device->id,
            'pin' => '2001',
        ]);
    }

    public function test_upload_user_skips_unsupported_credentials(): void
    {
        $mockDispatcher = $this->createMock(DeviceCommandDispatcher::class);
        $mockDispatcher->expects($this->once()) // only upload_user (pin)
            ->method('dispatch');

        $service = new UserDeviceSyncService($mockDispatcher);

        $device = Device::create([
            'serial_number' => 'DEV_ADMS_PIN_ONLY',
            'name' => 'Keypad Terminal',
            'status' => 'online',
            'options' => [
                'connection_mode' => 'adms',
                'enrollment_methods' => ['rfid'], // no fingerprint, no face
            ],
        ]);

        $user = User::create([
            'pin' => '2002',
            'name' => 'Jane Smith',
            'fingerprints' => [
                ['fid' => 0, 'size' => 120, 'template' => 'base64fp0'],
            ],
            'face_templates' => [
                ['fid' => 0, 'size' => 250, 'template' => 'base64face0'],
            ],
        ]);

        $result = $service->uploadUserToDevice($device, $user, ['pin', 'fingerprint', 'face']);

        $this->assertTrue($result['success']);
        $this->assertSame(1, $result['commands_queued']);
        $this->assertCount(2, $result['skipped_warnings']);
    }

    public function test_delete_user_from_adms_device_queues_command(): void
    {
        $mockDispatcher = $this->createMock(DeviceCommandDispatcher::class);
        $mockDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->anything(), $this->callback(function (DeviceCommand $cmd) {
                return $cmd->command_type === 'delete_user' && str_contains($cmd->command_content, '2005');
            }));

        $service = new UserDeviceSyncService($mockDispatcher);

        $device = Device::create([
            'serial_number' => 'DEV_ADMS_DEL',
            'name' => 'Exit Gate',
            'status' => 'online',
            'options' => ['connection_mode' => 'adms'],
        ]);

        DeviceUser::create([
            'device_id' => $device->id,
            'pin' => '2005',
            'name' => 'User to Delete',
        ]);

        $command = $service->deleteUserFromDevice($device, '2005');
        $this->assertNotNull($command);
        $this->assertSame('delete_user', $command->command_type);

        $this->assertDatabaseMissing('device_users', [
            'device_id' => $device->id,
            'pin' => '2005',
        ]);
    }

    public function test_trigger_on_device_enrollment(): void
    {
        $mockDispatcher = $this->createMock(DeviceCommandDispatcher::class);
        $mockDispatcher->expects($this->exactly(2))->method('dispatch');

        $service = new UserDeviceSyncService($mockDispatcher);

        $device = Device::create([
            'serial_number' => 'DEV_ADMS_ENROLL',
            'name' => 'Enroll Terminal',
            'status' => 'online',
            'options' => ['connection_mode' => 'adms'],
        ]);

        $user = User::create([
            'pin' => '2010',
            'name' => 'Enroll User',
        ]);

        $cmd1 = $service->triggerOnDeviceEnrollment($device, $user, 'fingerprint', 2);
        $this->assertNotNull($cmd1);
        $this->assertSame('enroll_fingerprint', $cmd1->command_type);
        $this->assertStringContainsString('"fid":2', $cmd1->command_content);

        // String PIN directly (e.g. from DeviceUser)
        $cmd2 = $service->triggerOnDeviceEnrollment($device, '2010', 'face');
        $this->assertNotNull($cmd2);
        $this->assertSame('enroll_face', $cmd2->command_type);
        $this->assertStringContainsString('2010', $cmd2->command_content);
    }

    public function test_device_user_relationships_and_matching(): void
    {
        $device = Device::create([
            'serial_number' => 'DEV_ADMS_REL',
            'name' => 'Gate Terminal',
            'status' => 'online',
            'options' => ['connection_mode' => 'adms'],
        ]);

        $user = User::create([
            'pin' => '3001',
            'name' => 'Alice Software',
            'card_number' => 'CARD3001',
            'privilege' => 14,
        ]);

        $deviceUser = DeviceUser::create([
            'device_id' => $device->id,
            'pin' => '3001',
            'name' => 'Alice Device',
            'card_number' => 'CARD3001',
            'privilege' => 14,
            'fingerprint_count' => 2,
            'face_count' => 1,
        ]);

        $this->assertSame($user->id, $deviceUser->user->id);
        $this->assertCount(1, $device->deviceUsers);
        $this->assertSame('3001', $device->deviceUsers->first()->pin);
    }
}
