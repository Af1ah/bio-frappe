<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceBinding extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'capability_profile' => 'array',
        'is_active' => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    public function getConnectionName()
    {
        return config('tenancy.database.central_connection') ?? config('database.default');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'tenant_id');
    }
}
