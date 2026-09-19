<?php

namespace Tests\Feature;

use App\Jobs\SyncFrappeCheckinJob;
use App\Models\AttendanceLog;
use App\Models\Device;
use App\Models\Organisation;
use App\Services\FrappeHrService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncFrappeCheckinJobTest extends TestCase
{
    protected function tearDown(): void
    {
        tenancy()->end();
        foreach (Organisation::all() as $org) {
            $org->delete();
        }
        parent::tearDown();
    }

    public function test_job_successfully_syncs_checkin_to_frappe_hr()
    {
        Config::set('services.frappe.url', 'https://hrm.example.com');
        Config::set('services.frappe.api_key', 'dummy_key');
        Config::set('services.frappe.api_secret', 'dummy_secret');

        $organisation = Organisation::create([
            'name' => 'Frappe Test Org',
            'db_name' => 'test_org_frappe_1',
        ]);

        tenancy()->initialize($organisation);

        $device = Device::create([
            'name' => 'K30 LOCAL',
            'serial_number' => 'DEV_ZK_001',
            'ip_address' => '192.168.1.30',
            'punch_behavior' => 'device_state',
            'status' => 'online',
        ]);

        $log = AttendanceLog::create([
            'device_id' => $device->id,
            'pin' => '1001',
            'punched_at' => Carbon::parse('2026-09-18 09:30:00'),
            'status' => 0,
            'verify_type' => 1,
        ]);

        Http::fake([
            'https://hrm.example.com/api/method/hrms.hr.doctype.employee_checkin.employee_checkin.add_log_based_on_employee_field' => Http::response([
                'message' => [
                    'name' => 'CHECKIN-2026-00099',
                    'employee' => 'HR-EMP-00001',
                ],
            ], 200),
        ]);

        $job = new SyncFrappeCheckinJob($log, $organisation);
        $job->handle(app(FrappeHrService::class));

        $log->refresh();

        $this->assertNotNull($log->frappe_synced_at);
        $this->assertEquals('CHECKIN-2026-00099', $log->frappe_log_id);
        $this->assertNull($log->frappe_error);
    }

    public function test_job_records_error_when_frappe_hr_fails()
    {
        Config::set('services.frappe.url', 'https://hrm.example.com');
        Config::set('services.frappe.api_key', 'dummy_key');
        Config::set('services.frappe.api_secret', 'dummy_secret');

        $organisation = Organisation::create([
            'name' => 'Frappe Test Org 2',
            'db_name' => 'test_org_frappe_2',
        ]);

        tenancy()->initialize($organisation);

        $device = Device::create([
            'name' => 'K30 LOCAL',
            'serial_number' => 'DEV_ZK_002',
            'ip_address' => '192.168.1.30',
            'punch_behavior' => 'device_state',
            'status' => 'online',
        ]);

        $log = AttendanceLog::create([
            'device_id' => $device->id,
            'pin' => '9999',
            'punched_at' => Carbon::parse('2026-09-18 10:00:00'),
            'status' => 0,
            'verify_type' => 1,
        ]);

        Http::fake([
            'https://hrm.example.com/api/method/hrms.hr.doctype.employee_checkin.employee_checkin.add_log_based_on_employee_field' => Http::response([
                'exception' => 'frappe.exceptions.ValidationError: No Employee found for the given employee field value: 9999',
            ], 417),
        ]);

        $job = new SyncFrappeCheckinJob($log, $organisation);
        $job->handle(app(FrappeHrService::class));

        $log->refresh();

        $this->assertNull($log->frappe_synced_at);
        $this->assertStringContainsString('No Employee found', $log->frappe_error);
    }

    public function test_job_fallback_to_name_when_attendance_device_id_fails()
    {
        Config::set('services.frappe.url', 'https://hrm.example.com');
        Config::set('services.frappe.api_key', 'dummy_key');
        Config::set('services.frappe.api_secret', 'dummy_secret');

        $organisation = Organisation::create([
            'name' => 'Frappe Test Org 3',
            'db_name' => 'test_org_frappe_3',
        ]);

        tenancy()->initialize($organisation);

        $device = Device::create([
            'name' => 'K30 LOCAL',
            'serial_number' => 'DEV_ZK_003',
            'ip_address' => '192.168.1.30',
            'punch_behavior' => 'device_state',
            'status' => 'online',
        ]);

        $log = AttendanceLog::create([
            'device_id' => $device->id,
            'pin' => 'HR-EMP-00001',
            'punched_at' => Carbon::parse('2026-09-18 10:30:00'),
            'status' => 0,
            'verify_type' => 1,
        ]);

        Http::fake([
            'https://hrm.example.com/api/method/hrms.hr.doctype.employee_checkin.employee_checkin.add_log_based_on_employee_field' => function (\Illuminate\Http\Client\Request $request) {
                $data = $request->data();
                if ($data['employee_fieldname'] === 'attendance_device_id') {
                    return Http::response(['exception' => 'No Employee found for the given employee field value: attendance_device_id'], 417);
                }
                if ($data['employee_field_value'] === 'HR-EMP-00001' && $data['employee_fieldname'] === 'name') {
                    return Http::response([
                        'message' => [
                            'name' => 'CHECKIN-2026-00100',
                            'employee' => 'HR-EMP-00001',
                        ],
                    ], 200);
                }
                return Http::response(['exception' => 'Not matched'], 400);
            },
        ]);

        $job = new SyncFrappeCheckinJob($log, $organisation);
        $job->handle(app(FrappeHrService::class));

        $log->refresh();

        $this->assertNotNull($log->frappe_synced_at);
        $this->assertEquals('CHECKIN-2026-00100', $log->frappe_log_id);
    }
}
