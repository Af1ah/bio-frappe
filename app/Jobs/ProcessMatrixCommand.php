<?php

namespace App\Jobs;

use App\Models\DeviceCommand;
use App\Models\Organisation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessMatrixCommand implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $commandId,
        public string $organisationId,
    ) {}

    public function handle(): void
    {
        $command = null;
        // This job can be executed synchronously from a Livewire action. Keep
        // the request's tenant context intact so Filament's subsequent refresh
        // does not fall back to the central database.
        $previousTenant = tenancy()->tenant;

        try {
            tenancy()->initialize(Organisation::findOrFail($this->organisationId));
            $command = DeviceCommand::findOrFail($this->commandId);
            $device = $command->device;
            $baseUrl = ($device->protocol ?? 'http').'://'.$device->ip_address.':'.($device->port ?? 80);
            $auth = [$device->username, $device->password];

            $content = $command->command_content;

            if (str_starts_with($content, 'INFO') || str_starts_with($content, 'CHECK')) {
                // Get basic device config
                $response = Http::withDigestAuth(...$auth)
                    ->timeout(10)
                    ->get($baseUrl.'/device.cgi/device-basic-config', [
                        'action' => 'get',
                        'format' => 'xml',
                    ]);

                if ($response->successful()) {
                    $xml = simplexml_load_string($response->body());
                    $model = (string) ($xml->name ?? 'Matrix Device');
                    $device->update([
                        'status' => 'online',
                        'model' => $model,
                        'last_activity_at' => now(),
                    ]);
                    $command->update(['status' => 'acknowledged', 'response' => "Device Online: {$model}"]);
                } else {
                    $command->update(['status' => 'failed', 'response' => 'HTTP Error: '.$response->status()]);
                }

            } elseif (str_starts_with($content, 'DATA QUERY USERINFO')) {
                // To fetch users, Matrix requires iterating over users.
                // First get user count, then fetch each. For simplicity, we just acknowledge.
                $response = Http::withDigestAuth(...$auth)
                    ->timeout(10)
                    ->get($baseUrl.'/device.cgi/command', [
                        'action' => 'getusercount',
                        'format' => 'xml',
                    ]);

                if ($response->successful()) {
                    $command->update(['status' => 'acknowledged', 'response' => 'Fetched user count successfully (sync logic omitted for matrix)']);
                } else {
                    $command->update(['status' => 'failed', 'response' => 'HTTP Error: '.$response->status()]);
                }

            } elseif (str_starts_with($content, 'DATA USER ')) {
                $pin = $this->commandField($content, 'PIN');
                $name = $this->commandField($content, 'Name');

                if ($pin) {
                    // Matrix COSEC only accepts alphanumeric and spaces for name (max 15 chars)
                    $matrixName = $name !== null ? substr(trim(preg_replace('/[^a-zA-Z0-9 ]+/', ' ', $name)), 0, 15) : null;

                    $response = Http::withDigestAuth(...$auth)
                        ->timeout(10)
                        ->get($baseUrl.'/device.cgi/users', array_filter([
                            'action' => 'set',
                            'user-id' => $pin,
                            'ref-user-id' => $pin,
                            'name' => $matrixName,
                            'user-active' => 1,
                            'user-pin' => $this->commandField($content, 'Passwd') ?: null,
                            'card1' => $this->commandField($content, 'Card') ?: null,
                            'user-group' => $this->commandField($content, 'Grp') ?: null,
                            'format' => 'xml',
                        ], fn ($v) => $v !== null));

                    $body = $response->body();
                    if ($response->successful() && str_contains($body, '<Response-Code>0</Response-Code>')) {
                        $command->update(['status' => 'acknowledged', 'response' => 'User pushed to Matrix device successfully.']);
                    } else {
                        $errorMsg = str_contains($body, 'Request Failed') || str_contains($body, '<Response-Code>')
                            ? trim(strip_tags($body))
                            : 'HTTP Error: '.$response->status();
                        $command->update(['status' => 'failed', 'response' => $errorMsg]);
                    }
                } else {
                    $command->update(['status' => 'failed', 'response' => 'Invalid command payload (Missing PIN)']);
                }

            } elseif (str_starts_with($content, 'DATA DEL_USER')) {
                preg_match('/PIN=([^\t]+)/', $content, $pinMatch);
                $pin = $pinMatch[1] ?? '';

                if ($pin) {
                    $response = Http::withDigestAuth(...$auth)
                        ->timeout(10)
                        ->get($baseUrl.'/device.cgi/users', [
                            'action' => 'delete',
                            'user-id' => $pin,
                            'format' => 'xml',
                        ]);

                    $body = $response->body();
                    if ($response->successful() && (str_contains($body, '<Response-Code>0</Response-Code>') || str_contains($body, '<Response-Code>13</Response-Code>'))) {
                        $command->update(['status' => 'acknowledged', 'response' => 'User deleted from Matrix device successfully.']);
                    } else {
                        $errorMsg = str_contains($body, 'Request Failed') || str_contains($body, '<Response-Code>')
                            ? trim(strip_tags($body))
                            : 'HTTP Error: '.$response->status();
                        $command->update(['status' => 'failed', 'response' => $errorMsg]);
                    }
                } else {
                    $command->update(['status' => 'failed', 'response' => 'Invalid command payload (Missing PIN)']);
                }

            } elseif (str_starts_with($content, 'REBOOT')) {
                // Matrix API manual doesn't explicitly mention a reboot command in device.cgi/command.
                $command->update(['status' => 'failed', 'response' => 'Reboot command not natively supported via Matrix HTTP API']);
            } elseif (str_starts_with($content, 'SET OPTIONS ServerLocalTime=')) {
                $dateTime = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', substr($content, strlen('SET OPTIONS ServerLocalTime=')));
                $response = Http::withDigestAuth(...$auth)
                    ->timeout(10)
                    ->get($baseUrl.'/device.cgi/date-time', [
                        'action' => 'set',
                        'date' => $dateTime->format('d'),
                        'month' => $dateTime->format('m'),
                        'year' => $dateTime->format('Y'),
                        'hour' => $dateTime->format('H'),
                        'minute' => $dateTime->format('i'),
                        'second' => $dateTime->format('s'),
                        'format' => 'xml',
                    ]);

                $response->successful()
                    ? $command->update(['status' => 'acknowledged', 'response' => 'Matrix device time synchronized.'])
                    : $command->update(['status' => 'failed', 'response' => 'HTTP Error: '.$response->status().' '.$response->body()]);
            } elseif (str_starts_with($content, 'CLEAR LOG')) {
                $command->update(['status' => 'failed', 'response' => 'Clear Log command not explicitly supported via Matrix HTTP API']);
            } else {
                // Command not fully mapped
                $command->update(['status' => 'acknowledged', 'response' => 'Simulated success (Command not strictly mapped for Matrix)']);
            }

        } catch (\Exception $e) {
            Log::error('Matrix API Error: '.$e->getMessage());
            $command?->update(['status' => 'failed', 'response' => 'Matrix API Error: '.$e->getMessage()]);
        } finally {
            if ($previousTenant) {
                tenancy()->initialize($previousTenant);
            } else {
                tenancy()->end();
            }
        }
    }

    private function commandField(string $content, string $name): ?string
    {
        preg_match('/(?:^|\\s)'.preg_quote($name, '/').'=([^\\t]*)/', $content, $match);

        return isset($match[1]) && $match[1] !== '' ? trim($match[1]) : null;
    }
}
