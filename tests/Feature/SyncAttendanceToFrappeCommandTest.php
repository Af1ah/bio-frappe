<?php

namespace Tests\Feature;

use App\Models\AttendanceLog;
use App\Models\Device;
use App\Models\Organisation;
use App\Services\FrappeHrService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncAttendanceToFrappeCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        tenancy()->end();
        foreach (Organisation::all() as $org) {
            $org->delete();
        }
        parent::tearDown();
    }

    public function test_command_syncs_unsynced_punches_and_triggers_attendance()
    {
        Config::set('services.frappe.url', 'https://hrm.example.com');
        Config::set('services.frappe.api_key', 'test_key');
        Config::set('services.frappe.api_secret', 'test_secret');

        $organisation = Organisation::create([
            'name' => 'Cmd Test Org',
            'db_name' => 'test_org_cmd_1',
        ]);

        tenancy()->initialize($organisation);

        $device = Device::create([
            'name' => 'K30 LOCAL',
            'serial_number' => 'DEV_ZK_CMD_1',
            'ip_address' => '192.168.1.30',
            'punch_behavior' => 'device_state',
            'status' => 'online',
        ]);

        $log = AttendanceLog::create([
            'device_id' => $device->id,
            'pin' => '1002',
            'punched_at' => Carbon::parse('2026-09-18 09:30:00'),
            'status' => 0,
            'verify_type' => 1,
        ]);

        Http::fake([
            'https://hrm.example.com/api/method/hrms.hr.doctype.employee_checkin.employee_checkin.add_log_based_on_employee_field' => Http::response([
                'message' => [
                    'name' => 'EMP-CKIN-CMD-001',
                    'employee' => 'HR-EMP-00002',
                ],
            ], 200),
            'https://hrm.example.com/api/resource/Shift%20Type*' => Http::response([
                'data' => [
                    ['name' => 'General Shift', 'enable_auto_attendance' => 1],
                ],
            ], 200),
            'https://hrm.example.com/api/method/run_doc_method' => Http::response([
                'docs' => [['name' => 'General Shift']],
            ], 200),
        ]);

        $exitCode = Artisan::call('attendance:auto-sync', [
            '--trigger-attendance' => true,
        ]);

        $this->assertEquals(0, $exitCode);

        tenancy()->initialize($organisation);
        $log->refresh();

        $this->assertNotNull($log->frappe_synced_at);
        $this->assertEquals('EMP-CKIN-CMD-001', $log->frappe_log_id);
    }
}
