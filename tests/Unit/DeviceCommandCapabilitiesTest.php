<?php

namespace Tests\Unit;

use App\Models\Device;
use App\Services\DeviceCommandCapabilities;
use PHPUnit\Framework\TestCase;

class DeviceCommandCapabilitiesTest extends TestCase
{
    private DeviceCommandCapabilities $capabilities;

    protected function setUp(): void
    {
        parent::setUp();
        $this->capabilities = new DeviceCommandCapabilities;
    }

    public function test_adms_capabilities(): void
    {
        $device = new Device(['options' => [
            'connection_mode' => 'adms',
            'enrollment_methods' => ['fingerprint', 'rfid'],
        ]]);

        $this->assertTrue($this->capabilities->supports($device, 'device_info'));
        $this->assertTrue($this->capabilities->supports($device, 'check'));
        $this->assertTrue($this->capabilities->supports($device, 'reboot'));
        $this->assertTrue($this->capabilities->supports($device, 'clear_logs'));
        $this->assertTrue($this->capabilities->supports($device, 'reset_transaction_stamp'));
        $this->assertTrue($this->capabilities->supports($device, 'reset_op_stamp'));
        $this->assertTrue($this->capabilities->supports($device, 'fetch_users'));
        $this->assertTrue($this->capabilities->supports($device, 'delete_user'));
        $this->assertTrue($this->capabilities->supports($device, 'enroll_fingerprint'));
        $this->assertFalse($this->capabilities->supports($device, 'enroll_face'));
        $this->assertFalse($this->capabilities->supports($device, 'unlock_door'));
        $this->assertFalse($this->capabilities->supports($device, 'sync_time'));

        $faceDevice = new Device(['options' => [
            'connection_mode' => 'adms',
            'enrollment_methods' => ['face_v2'],
        ]]);
        $this->assertTrue($this->capabilities->supports($faceDevice, 'enroll_face'));
        $this->assertFalse($this->capabilities->supports($faceDevice, 'enroll_fingerprint'));

        $doorDevice = new Device(['options' => ['connection_mode' => 'adms', 'type' => 'Door']]);
        $this->assertTrue($this->capabilities->supports($doorDevice, 'unlock_door'));
        $this->assertTrue($this->capabilities->supports($doorDevice, 'lock_door'));
        $this->assertTrue($this->capabilities->supports($doorDevice, 'normal_door'));
    }

    public function test_ebio_capabilities(): void
    {
        $device = new Device(['options' => ['connection_mode' => 'ebio']]);

        $this->assertTrue($this->capabilities->supports($device, 'reboot'));
        $this->assertTrue($this->capabilities->supports($device, 'clear_logs'));
        $this->assertTrue($this->capabilities->supports($device, 'reset_transaction_stamp'));
        $this->assertTrue($this->capabilities->supports($device, 'reset_op_stamp'));
        $this->assertTrue($this->capabilities->supports($device, 'unlock_door'));
        $this->assertTrue($this->capabilities->supports($device, 'lock_door'));
        $this->assertTrue($this->capabilities->supports($device, 'normal_door'));
        $this->assertFalse($this->capabilities->supports($device, 'device_info'));

        $attendanceDevice = new Device(['options' => ['connection_mode' => 'ebio', 'type' => 'Attendance']]);
        $this->assertFalse($this->capabilities->supports($attendanceDevice, 'unlock_door'));
        $this->assertFalse($this->capabilities->supports($attendanceDevice, 'lock_door'));
        $this->assertFalse($this->capabilities->supports($attendanceDevice, 'normal_door'));
    }

    public function test_direct_capabilities(): void
    {
        $device = new Device(['options' => ['connection_mode' => 'direct']]);

        $this->assertTrue($this->capabilities->supports($device, 'sync_time'));
        $this->assertTrue($this->capabilities->supports($device, 'reboot'));
        $this->assertTrue($this->capabilities->supports($device, 'clear_logs'));
        $this->assertTrue($this->capabilities->supports($device, 'reset_transaction_stamp'));
        $this->assertTrue($this->capabilities->supports($device, 'reset_op_stamp'));
        $this->assertFalse($this->capabilities->supports($device, 'device_info'));
        $this->assertFalse($this->capabilities->supports($device, 'unlock_door'));
    }
}
