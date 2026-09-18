<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class GatewayCommand extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected $casts = [
        'delivered_at' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function getConnectionName()
    {
        return config('tenancy.database.central_connection') ?? config('database.default');
    }
}
