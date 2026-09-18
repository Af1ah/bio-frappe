<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $device_id
 * @property string $command_type
 * @property string $command_content
 * @property string $status
 * @property Carbon|null $sent_at
 * @property Carbon|null $acknowledged_at
 * @property string|null $response
 * @property int $retry_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class DeviceCommand extends Model
{
    protected $guarded = [];

    protected $casts = [
        'sent_at' => 'datetime',
        'acknowledged_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return 'device_commands';
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
            'delivery_status' => 'sent',
        ]);
    }

    public function markAsAcknowledged(?string $response = null): void
    {
        $this->update([
            'status' => 'acknowledged',
            'acknowledged_at' => now(),
            'response' => $response,
            'delivery_status' => 'acknowledged',
        ]);
    }

    public function markAsFailed(?string $response = null): void
    {
        $this->update([
            'status' => 'failed',
            'response' => $response,
            'delivery_status' => 'failed',
        ]);
    }

    public function retry(): void
    {
        $this->update([
            'status' => 'pending',
            'delivery_status' => 'queued',
            'external_id' => null,
            'protocol_command_id' => null,
            'retry_count' => $this->retry_count + 1,
        ]);
    }
}
