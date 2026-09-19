<?php

namespace Tests\Feature;

use App\Jobs\SyncFrappeCheckinBatchJob;
use App\Models\AttendanceLog;
use App\Models\Device;
use App\Models\Organisation;
use App\Services\FrappeHrService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncFrappeCheckinBatchJobTest extends TestCase
{
    protected function tearDown(): void
    {
        tenancy()->end();
        foreach (Organisation::all() as $org) {
            $org->delete();
        }
        parent::tearDown();
    }

    public function test_batch_job_syncs_multiple_punches_via_pool()
    {
        Config::set('services.frappe.url', 'https://hrm.example.com');
        Config::set('services.frappe.api_key', 'dummy_key');
        Config::set('services.frappe.api_secret', 'dummy_secret');

        $organisation = Organisation::create([
            'name' => 'Batch Test Org',
            'db_name' => 'test_org_batch_1',
        ]);

        tenancy()->initialize($organisation);

        $device = Device::create([
            'name' => 'K30 LOCAL',
            'serial_number' => 'DEV_ZK_BATCH_1',
            'ip_address' => '192.168.1.30',
            'punch_behavior' => 'device_state',
            'status' => 'online',
        ]);

        $log1 = AttendanceLog::create([
            'device_id' => $device->id,
            'pin' => '1001',
            'punched_at' => Carbon::parse('2026-09-18 09:30:00'),
            'status' => 0,
            'verify_type' => 1,
        ]);

        $log2 = AttendanceLog::create([
            'device_id' => $device->id,
            'pin' => '1002',
            'punched_at' => Carbon::parse('2026-09-18 09:35:00'),
            'status' => 0,
            'verify_type' => 1,
        ]);

        Http::fake([
            'https://hrm.example.com/api/method/hrms.hr.doctype.employee_checkin.employee_checkin.add_log_based_on_employee_field' => Http::response([
                'message' => [
                    'name' => 'EMP-CKIN-BATCH-100',
                    'employee' => 'HR-EMP-00001',
                ],
            ], 200),
        ]);

        $job = new SyncFrappeCheckinBatchJob([$log1->id, $log2->id], $organisation, 5);
        $job->handle(app(FrappeHrService::class));

        $log1->refresh();
        $log2->refresh();

        $this->assertNotNull($log1->frappe_synced_at);
        $this->assertNotNull($log2->frappe_synced_at);
        $this->assertEquals('EMP-CKIN-BATCH-100', $log1->frappe_log_id);
        $this->assertEquals('EMP-CKIN-BATCH-100', $log2->frappe_log_id);
    }
}
