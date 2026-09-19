<?php

namespace App\Services;

use App\Models\AttendanceLog;
use App\Models\Organisation;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FrappeHrService
{
    /**
     * Resolve configuration for Frappe HR API.
     */
    protected function getConfig(?Organisation $organisation = null): array
    {
        $url = $organisation?->frappe_url ?: config('services.frappe.url', 'https://hrm.secumaxtech.com');
        $apiKey = $organisation?->frappe_api_key ?: config('services.frappe.api_key');
        $apiSecret = $organisation?->frappe_api_secret ?: config('services.frappe.api_secret');
        $fieldName = $organisation?->frappe_employee_fieldname ?: config('services.frappe.employee_fieldname', 'attendance_device_id');

        return [
            'url' => rtrim($url, '/'),
            'api_key' => $apiKey,
            'api_secret' => $apiSecret,
            'employee_fieldname' => $fieldName ?: 'attendance_device_id',
        ];
    }

    /**
     * Verify credentials against Frappe HR.
     */
    public function testConnection(?Organisation $organisation = null): array
    {
        $cfg = $this->getConfig($organisation);

        if (empty($cfg['api_key']) || empty($cfg['api_secret'])) {
            return [
                'success' => false,
                'error' => 'API Key or API Secret is missing.',
            ];
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => "token {$cfg['api_key']}:{$cfg['api_secret']}",
                    'Accept' => 'application/json',
                ])
                ->get("{$cfg['url']}/api/method/frappe.auth.get_logged_user");

            if ($response->successful()) {
                $user = $response->json('message');
                return [
                    'success' => true,
                    'user' => $user,
                ];
            }

            return [
                'success' => false,
                'status' => $response->status(),
                'error' => $response->json('message') ?: $response->body(),
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Send an individual checkin record to Frappe HR.
     */
    public function sendEmployeeCheckin(array $payload, ?Organisation $organisation = null): array
    {
        $cfg = $this->getConfig($organisation);

        if (empty($cfg['api_key']) || empty($cfg['api_secret'])) {
            return [
                'success' => false,
                'error' => 'Frappe HR API credentials are not configured.',
            ];
        }

        $endpoint = "{$cfg['url']}/api/method/hrms.hr.doctype.employee_checkin.employee_checkin.add_log_based_on_employee_field";
        $employeeFieldName = $payload['employee_fieldname'] ?? $cfg['employee_fieldname'];

        $data = [
            'employee_field_value' => (string) $payload['employee_field_value'],
            'timestamp' => (string) $payload['timestamp'],
            'device_id' => $payload['device_id'] ?? null,
            'log_type' => $payload['log_type'] ?? null,
            'employee_fieldname' => $employeeFieldName,
        ];

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'Authorization' => "token {$cfg['api_key']}:{$cfg['api_secret']}",
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ])
                ->post($endpoint, $data);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'data' => $response->json('message') ?: $response->json(),
                ];
            }

            $body = $response->json();
            $errorMessage = $body['exception'] ?? ($body['_server_messages'] ?? $response->body());

            // If the log already exists in Frappe HR with the same timestamp, treat as success
            if (str_contains($errorMessage, 'already has a log with the same timestamp')) {
                preg_match('/Employee Checkin\/([^\"]+)/', $errorMessage, $matches);
                $docName = $matches[1] ?? null;

                return [
                    'success' => true,
                    'duplicate' => true,
                    'data' => [
                        'name' => $docName,
                    ],
                ];
            }

            // Check if it's "No Employee found"
            if (str_contains($errorMessage, 'No Employee found')) {
                Log::warning("Frappe HR: No Employee found matching {$employeeFieldName} = {$data['employee_field_value']}");
            } else {
                Log::error("Frappe HR checkin sync failed: {$errorMessage}");
            }

            return [
                'success' => false,
                'status' => $response->status(),
                'error' => $errorMessage,
            ];
        } catch (Exception $e) {
            Log::error("Frappe HR connection error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Sync an AttendanceLog model to Frappe HR Employee Checkin.
     */
    public function syncAttendanceCheckin(AttendanceLog $log, ?Organisation $organisation = null): array
    {
        $device = $log->device;
        $logType = match ($log->status) {
            0 => 'IN',
            1 => 'OUT',
            default => null,
        };

        $cfg = $this->getConfig($organisation);

        $payload = [
            'employee_field_value' => (string) $log->pin,
            'timestamp' => $log->punched_at->format('Y-m-d H:i:s'),
            'device_id' => $device ? ($device->name ?: $device->serial_number) : null,
            'log_type' => $logType,
            'employee_fieldname' => $cfg['employee_fieldname'] ?: 'attendance_device_id',
        ];

        $result = $this->sendEmployeeCheckin($payload, $organisation);

        // If not found with attendance_device_id, attempt fallback to 'name'
        if (!$result['success'] && str_contains($result['error'] ?? '', 'No Employee found')) {
            $fallbackPayload = $payload;
            $fallbackPayload['employee_fieldname'] = 'name';
            $fallbackResult = $this->sendEmployeeCheckin($fallbackPayload, $organisation);

            if (!empty($fallbackResult['success'])) {
                $result = $fallbackResult;
            }
        }

        try {
            if (!empty($result['success'])) {
                $log->frappe_synced_at = now();
                $log->frappe_error = null;
                $log->frappe_log_id = is_string($result['data'] ?? null) 
                    ? $result['data'] 
                    : (is_array($result['data'] ?? null) ? ($result['data']['name'] ?? null) : null);
                $log->saveQuietly();
            } else {
                $log->frappe_error = substr($result['error'] ?? 'Sync failed', 0, 500);
                $log->saveQuietly();
            }
        } catch (\Throwable $e) {
            Log::warning("Could not update AttendanceLog with Frappe sync status: " . $e->getMessage());
        }

        return $result;
    }

    /**
     * Retrieve employees from Frappe HR.
     */
    public function getEmployees(?Organisation $organisation = null): array
    {
        $cfg = $this->getConfig($organisation);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => "token {$cfg['api_key']}:{$cfg['api_secret']}",
                ])
                ->get("{$cfg['url']}/api/resource/Employee", [
                    'fields' => json_encode(['name', 'employee_name', 'attendance_device_id', 'status']),
                    'limit_page_length' => 500,
                ]);

            if ($response->successful()) {
                return $response->json('data') ?: [];
            }
            return [];
        } catch (Exception $e) {
            Log::error("Failed to fetch Frappe HR employees: " . $e->getMessage());
            return [];
        }
    }
}
