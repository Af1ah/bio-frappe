<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GatewayReceipt extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'received_at' => 'datetime',
        'delivered_at' => 'datetime',
        'query' => 'array',
        'routing_metadata' => 'array',
    ];

    public function getConnectionName()
    {
        return config('tenancy.database.central_connection') ?? config('database.default');
    }

    public function binding(): BelongsTo
    {
        return $this->belongsTo(DeviceBinding::class, 'device_binding_id');
    }
}
