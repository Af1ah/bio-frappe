<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class MatrixBulkEnrollCommand extends Command
{
    protected $signature = 'matrix:bulk-enroll
        {--tenant= : Tenant / Organisation ID (defaults to first)}
        {--device= : Device ID (defaults to first Matrix device)}
        {--start-id=1001 : Starting numeric User ID (e.g. 1001 or 1000)}
        {--end-id=1110 : Ending numeric User ID (e.g. 1110)}
        {--count= : Number of users to enroll (optional, overrides end-id)}
        {--name-prefix=test- : Name prefix (e.g. test-)}
        {--start-card= : Starting numeric card number (e.g. 10954200)}
        {--update-card : Only update card details for existing users without resetting profile}
        {--save-db : Also create or update users in the tenant database}
        {--delete : Delete these users instead of enrolling them (cleanup mode)}
        {--verify : Verify enrolled users count and list on device after execution}';

    protected $description = 'Perform or test bulk enrollment of users on a Matrix COSEC device via HTTP CGI API';

    public function handle(): int
    {
        $tenantId = $this->option('tenant');
        $organisation = $tenantId ? Organisation::find($tenantId) : Organisation::first();

        if (! $organisation) {
            $this->error('No organisation/tenant found.');
            return 1;
        }

        tenancy()->initialize($organisation);
        $this->info("Initialized tenant: {$organisation->name} ({$organisation->id})");

        $deviceId = $this->option('device');
        $device = $deviceId
            ? Device::where('vendor', 'matrix')->find($deviceId)
            : Device::where('vendor', 'matrix')->first();

        if (! $device) {
            $this->error('No Matrix device found.');
            return 1;
        }

        $protocol = $device->protocol ?? 'http';
        $baseUrl = "{$protocol}://{$device->ip_address}:" . ($device->port ?? 80);
        $auth = [$device->username, $device->password];

        $this->info("Target Matrix Device: {$device->name} (ID: {$device->id}, IP: {$device->ip_address}:{$device->port})");

        // Ping / test connectivity
        try {
            $ping = Http::withDigestAuth(...$auth)
                ->timeout(5)
                ->get("{$baseUrl}/device.cgi/device-basic-config", ['action' => 'get', 'format' => 'xml']);

            if (! $ping->successful()) {
                $this->error("Cannot connect to Matrix device: HTTP {$ping->status()}");
                return 1;
            }
        } catch (\Exception $e) {
            $this->error("Connection failed: {$e->getMessage()}");
            return 1;
        }

        $startId = (int) $this->option('start-id');
        $count = $this->option('count') !== null ? (int) $this->option('count') : null;
        $endId = $count !== null ? ($startId + $count - 1) : (int) $this->option('end-id');

        if ($endId < $startId) {
            $this->error("Invalid ID range: start-id ({$startId}) > end-id ({$endId})");
            return 1;
        }

        $totalUsers = $endId - $startId + 1;
        $namePrefix = $this->option('name-prefix');
        $isDelete = (bool) $this->option('delete');
        $updateCard = (bool) $this->option('update-card');
        $startCard = $this->option('start-card') !== null ? (int) $this->option('start-card') : null;
        $saveDb = (bool) $this->option('save-db');

        $actionWord = $isDelete ? 'Deletion' : ($updateCard ? 'Card Update' : 'Enrollment');
        $this->info("Preparing Bulk {$actionWord} for {$totalUsers} users (IDs {$startId} to {$endId})...");

        $bar = $this->output->createProgressBar($totalUsers);
        $bar->start();

        $successCount = 0;
        $failedCount = 0;
        $errors = [];

        for ($uid = $startId; $uid <= $endId; $uid++) {
            // Compute name index: if starting at 1001, 1001 -> 1, etc.
            // If starting at 1000, 1000 -> 0 (or 1000)
            $index = ($startId === 1001) ? ($uid - 1000) : ($uid === 1000 ? 0 : ($uid - 1000));
            $userName = "{$namePrefix}{$index}";

            // Matrix CGI rejects hyphens and symbols in name parameter. Only alphanumeric and space.
            $matrixName = substr(trim(preg_replace('/[^a-zA-Z0-9 ]+/', ' ', $userName)), 0, 15);

            $cardValue = $startCard !== null ? (string) ($startCard + ($uid - $startId)) : null;

            if ($isDelete) {
                try {
                    $response = Http::withDigestAuth(...$auth)
                        ->timeout(5)
                        ->get("{$baseUrl}/device.cgi/users", [
                            'action' => 'delete',
                            'user-id' => (string) $uid,
                            'format' => 'xml',
                        ]);

                    $body = $response->body();
                    // Response code 0 = deleted, 13 = user not found (already deleted)
                    if ($response->successful() && (str_contains($body, '<Response-Code>0</Response-Code>') || str_contains($body, '<Response-Code>13</Response-Code>'))) {
                        $successCount++;
                        if ($saveDb) {
                            User::where('pin', (string) $uid)->delete();
                        }
                    } else {
                        $failedCount++;
                        $errors[] = "User {$uid}: " . trim(strip_tags($body));
                    }
                } catch (\Exception $e) {
                    $failedCount++;
                    $errors[] = "User {$uid}: " . $e->getMessage();
                }
            } elseif ($updateCard) {
                // Update card only
                try {
                    $params = [
                        'action' => 'set',
                        'user-id' => (string) $uid,
                        'card1' => $cardValue ?? '0',
                        'format' => 'xml',
                    ];

                    $response = Http::withDigestAuth(...$auth)
                        ->timeout(5)
                        ->get("{$baseUrl}/device.cgi/users", $params);

                    $body = $response->body();
                    if ($response->successful() && str_contains($body, '<Response-Code>0</Response-Code>')) {
                        $successCount++;

                        if ($saveDb && $cardValue) {
                            User::where('pin', (string) $uid)->update(['card_number' => $cardValue]);
                        }
                    } else {
                        $failedCount++;
                        $errors[] = "User {$uid}: " . trim(strip_tags($body));
                    }
                } catch (\Exception $e) {
                    $failedCount++;
                    $errors[] = "User {$uid}: " . $e->getMessage();
                }
            } else {
                // Enrollment (action=set)
                try {
                    $params = [
                        'action' => 'set',
                        'user-id' => (string) $uid,
                        'ref-user-id' => (string) $uid,
                        'name' => $matrixName,
                        'user-active' => 1,
                        'format' => 'xml',
                    ];

                    if ($cardValue !== null) {
                        $params['card1'] = $cardValue;
                    }

                    $response = Http::withDigestAuth(...$auth)
                        ->timeout(5)
                        ->get("{$baseUrl}/device.cgi/users", $params);

                    $body = $response->body();
                    if ($response->successful() && str_contains($body, '<Response-Code>0</Response-Code>')) {
                        $successCount++;

                        if ($saveDb) {
                            $userData = [
                                'name' => $userName,
                                'is_enabled' => true,
                                'privilege' => 0,
                            ];
                            if ($cardValue !== null) {
                                $userData['card_number'] = $cardValue;
                            }

                            User::updateOrCreate(
                                ['pin' => (string) $uid],
                                $userData
                            );
                        }
                    } else {
                        $failedCount++;
                        $errors[] = "User {$uid}: " . trim(strip_tags($body));
                    }
                } catch (\Exception $e) {
                    $failedCount++;
                    $errors[] = "User {$uid}: " . $e->getMessage();
                }
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Completed {$actionWord}:");
        $this->info("- Successful: {$successCount}");
        if ($failedCount > 0) {
            $this->warn("- Failed: {$failedCount}");
            foreach (array_slice($errors, 0, 10) as $err) {
                $this->error("  * {$err}");
            }
            if (count($errors) > 10) {
                $this->error("  ... and " . (count($errors) - 10) . " more errors.");
            }
        }

        // Verify total enrolled users on the device
        try {
            $countRes = Http::withDigestAuth(...$auth)
                ->timeout(5)
                ->get("{$baseUrl}/device.cgi/command", ['action' => 'getusercount', 'format' => 'xml']);

            if ($countRes->successful()) {
                if (preg_match('/<Enrolled-User-Count>(\d+)<\/Enrolled-User-Count>/', $countRes->body(), $m)) {
                    $this->info("Device Enrolled-User-Count: {$m[1]}");
                }
            }
        } catch (\Exception $e) {
            $this->warn("Could not retrieve user count: {$e->getMessage()}");
        }

        if ($this->option('verify') && ! $isDelete) {
            $this->info("\nSample verification (first 3 and last 3 users):");
            $sampleIds = array_unique([
                $startId,
                $startId + 1,
                $startId + 2,
                $endId - 2,
                $endId - 1,
                $endId,
            ]);

            foreach ($sampleIds as $sId) {
                if ($sId < $startId || $sId > $endId) continue;
                $verifyRes = Http::withDigestAuth(...$auth)
                    ->timeout(5)
                    ->get("{$baseUrl}/device.cgi/users", [
                        'action' => 'get',
                        'user-id' => (string) $sId,
                        'format' => 'xml',
                    ]);

                if (str_contains($verifyRes->body(), '<user-id>')) {
                    preg_match('/<name>(.*?)<\/name>/', $verifyRes->body(), $nameMatch);
                    preg_match('/<card1>(.*?)<\/card1>/', $verifyRes->body(), $cardMatch);
                    $devName = $nameMatch[1] ?? 'unknown';
                    $devCard = $cardMatch[1] ?? '0';
                    $this->line("  - ID {$sId}: Enrolled (Name: '{$devName}', Card: '{$devCard}')");
                } else {
                    $this->warn("  - ID {$sId}: Not found on device");
                }
            }
        }

        return $failedCount === 0 ? 0 : 1;
    }
}
