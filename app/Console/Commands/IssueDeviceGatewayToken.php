<?php

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;

class IssueDeviceGatewayToken extends Command
{
    protected $signature = 'device-gateway:issue-token {email : Master administrator email}';

    protected $description = 'Issue a scoped token for the Go ADMS gateway to deliver receipts';

    public function handle(): int
    {
        $admin = Admin::query()
            ->where('email', $this->argument('email'))
            ->where('role', 'master')
            ->firstOrFail();

        $token = $admin->createToken('adms-gateway', ['device-gateway:write']);
        $this->warn('Store this once as LARAVEL_GATEWAY_TOKEN. It will not be shown again.');
        $this->line($token->plainTextToken);

        return self::SUCCESS;
    }
}
