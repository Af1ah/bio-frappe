<?php

namespace Tests\Unit;

use App\Models\AttendanceLog;
use App\Models\Device;
use App\Models\Organisation;
use App\Services\FrappeHrService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FrappeHrServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.frappe.url', 'https://hrm.example.com');
        Config::set('services.frappe.api_key', 'test_key');
        Config::set('services.frappe.api_secret', 'test_secret');
        Config::set('services.frappe.employee_fieldname', 'attendance_device_id');
    }

    public function test_connection_successful(): void
    {
        Http::fake([
            'https://hrm.example.com/api/method/frappe.auth.get_logged_user' => Http::response([
                'message' => 'apiuser@example.com',
            ], 200),
        ]);

        $service = new FrappeHrService();
        $result = $service->testConnection();

        $this->assertTrue($result['success']);
        $this->assertEquals('apiuser@example.com', $result['user']);
    }

    public function test_connection_failure(): void
    {
        Http::fake([
            'https://hrm.example.com/api/method/frappe.auth.get_logged_user' => Http::response([
                'message' => 'Invalid credentials',
            ], 401),
        ]);

        $service = new FrappeHrService();
        $result = $service->testConnection();

        $this->assertFalse($result['success']);
    }

    public function test_send_employee_checkin_success(): void
    {
        Http::fake([
            'https://hrm.example.com/api/method/hrms.hr.doctype.employee_checkin.employee_checkin.add_log_based_on_employee_field' => Http::response([
                'message' => [
                    'name' => 'CHECKIN-2026-00001',
                    'employee' => 'HR-EMP-00001',
                    'log_type' => 'IN',
                ],
            ], 200),
        ]);

        $service = new FrappeHrService();
        $result = $service->sendEmployeeCheckin([
            'employee_field_value' => '1001',
            'timestamp' => '2026-09-18 09:00:00',
            'device_id' => 'K30 LOCAL',
            'log_type' => 'IN',
        ]);

        $this->assertTrue($result['success']);
        $this->assertEquals('CHECKIN-2026-00001', $result['data']['name']);
    }

    public function test_send_employee_checkin_validation_error(): void
    {
        Http::fake([
            'https://hrm.example.com/api/method/hrms.hr.doctype.employee_checkin.employee_checkin.add_log_based_on_employee_field' => Http::response([
                'exception' => 'frappe.exceptions.ValidationError: No Employee found for the given employee field value: 9999',
            ], 417),
        ]);

        $service = new FrappeHrService();
        $result = $service->sendEmployeeCheckin([
            'employee_field_value' => '9999',
            'timestamp' => '2026-09-18 09:00:00',
            'device_id' => 'K30 LOCAL',
            'log_type' => 'IN',
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('No Employee found', $result['error']);
    }

    public function test_get_employees(): void
    {
        Http::fake([
            'https://hrm.example.com/api/resource/Employee*' => Http::response([
                'data' => [
                    [
                        'name' => 'HR-EMP-00001',
                        'employee_name' => 'John Doe',
                        'attendance_device_id' => '1001',
                        'status' => 'Active',
                    ],
                ],
            ], 200),
        ]);

        $service = new FrappeHrService();
        $employees = $service->getEmployees();

        $this->assertCount(1, $employees);
        $this->assertEquals('1001', $employees[0]['attendance_device_id']);
    }

    public function test_sync_attendance_batch_success(): void
    {
        Http::fake([
            'https://hrm.example.com/api/method/hrms.hr.doctype.employee_checkin.employee_checkin.add_log_based_on_employee_field' => Http::response([
                'message' => [
                    'name' => 'CHECKIN-BATCH-001',
                    'employee' => 'HR-EMP-00001',
                ],
            ], 200),
        ]);

        $log1 = new AttendanceLog([
            'pin' => '1001',
            'punched_at' => Carbon::parse('2026-09-18 09:00:00'),
            'status' => 0,
        ]);
        $log1->id = 101;

        $log2 = new AttendanceLog([
            'pin' => '1002',
            'punched_at' => Carbon::parse('2026-09-18 09:05:00'),
            'status' => 0,
        ]);
        $log2->id = 102;

        $service = new FrappeHrService();
        $result = $service->syncAttendanceBatch([$log1, $log2]);

        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['synced']);
        $this->assertEquals(0, $result['failed']);
    }

    public function test_trigger_auto_attendance(): void
    {
        Http::fake([
            'https://hrm.example.com/api/resource/Shift%20Type/General%20Shift' => Http::response([
                'data' => ['name' => 'General Shift'],
            ], 200),
            'https://hrm.example.com/api/method/run_doc_method' => Http::response([
                'docs' => [['name' => 'General Shift']],
            ], 200),
        ]);

        $service = new FrappeHrService();
        $result = $service->triggerAutoAttendance('General Shift');

        $this->assertTrue($result['success']);
        $this->assertEquals('General Shift', $result['shift']);
    }
}
