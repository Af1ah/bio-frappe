<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $serial_number
 * @property string|null $name
 * @property string|null $ip_address
 * @property string|null $model
 * @property string|null $firmware_version
 * @property string|null $push_version
 * @property string|null $device_type
 * @property string $status
 * @property Carbon|null $last_activity_at
 * @property Carbon|null $last_sync_at
 * @property int $att_stamp
 * @property int $op_stamp
 * @property array|null $options
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Device extends Model
{
    protected $guarded = [];

    protected $casts = [
        'last_activity_at' => 'datetime',
        'last_sync_at' => 'datetime',
        'options' => 'array',
    ];

    public function getTable(): string
    {
        return 'devices';
    }

    public function attendanceLogs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class);
    }

    public function commands(): HasMany
    {
        return $this->hasMany(DeviceCommand::class);
    }

    public function pendingCommands(): HasMany
    {
        return $this->commands()->where('status', 'pending');
    }

    public function isOnline(): bool
    {
        if (! $this->last_activity_at) {
            return false;
        }

        $threshold = 10;

        return $this->last_activity_at->diffInMinutes(now()) < $threshold;
    }

    public function markAsOnline(): void
    {
        $this->update([
            'status' => 'online',
            'last_activity_at' => now(),
        ]);
    }

    public function markAsOffline(): void
    {
        $this->update([
            'status' => 'offline',
        ]);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    protected static function booted(): void
    {
        static::saving(function (Device $device) {
            $options = $device->options ?? [];
            $type = data_get($options, 'type', $device->device_type);
            if ($type) {
                $isDoor = in_array(strtolower((string) $type), ['door', 'door based', 'attendance_door'], true);
                $options['type'] = $type;
                $options['is_door_based'] = $isDoor;
                $device->options = $options;
                $device->device_type = (string) $type;
            }
        });
    }

    public function isDoorBased(): bool
    {
        $type = data_get($this->options, 'type', $this->device_type);
        if (in_array(strtolower((string) $type), ['door', 'door based', 'attendance_door'], true)) {
            return true;
        }

        return (bool) data_get($this->options, 'is_door_based', false);
    }

    public function deviceUsers(): HasMany
    {
        return $this->hasMany(DeviceUser::class);
    }

    /** @return array<string> */
    public function supportedEnrollmentMethods(): array
    {
        $methods = data_get($this->options, 'enrollment_methods');
        if (is_array($methods) && ! empty($methods)) {
            return array_values($methods);
        }

        return ['fingerprint', 'rfid'];
    }

    public function supportsEnrollment(string $method): bool
    {
        return in_array($method, $this->supportedEnrollmentMethods(), true);
    }
}
