<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Casts\EncryptedArrayCast;
use App\Casts\EncryptedStringCast;
use Carbon\Carbon;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->has_login;
    }

    protected static function boot()
    {
        parent::boot();

        static::updating(function ($user) {
            if ($user->isDirty('password')) {
                $user->requires_password_change = false;
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_enabled' => 'boolean',
            'device_password' => EncryptedStringCast::class,
            'fingerprints' => EncryptedArrayCast::class,
            'face_templates' => EncryptedArrayCast::class,
            'face_v2_templates' => EncryptedArrayCast::class,
            'requires_password_change' => 'boolean',
            'blocked_devices' => 'array',
        ];
    }

    public function deviceUsers()
    {
        return $this->hasMany(DeviceUser::class, 'pin', 'pin');
    }

    public function hasFingerprints(): bool
    {
        return ! empty($this->fingerprints);
    }

    public function hasFace(): bool
    {
        return ! empty($this->face_templates);
    }

    public function hasFaceV2(): bool
    {
        return ! empty($this->face_v2_templates);
    }

    public function hasCard(): bool
    {
        return filled($this->card_number);
    }

    public function hasDevicePassword(): bool
    {
        return filled($this->device_password);
    }

    /** @return array<string> */
    public function enrolledCredentialTypes(): array
    {
        $types = ['pin'];
        if ($this->hasCard()) {
            $types[] = 'card';
        }
        if ($this->hasFingerprints()) {
            $types[] = 'fingerprint';
        }
        if ($this->hasFace() || $this->hasFaceV2()) {
            $types[] = 'face';
        }

        return $types;
    }

    public function attendanceLogs()
    {
        return $this->hasMany(AttendanceLog::class, 'pin', 'pin');
    }

    public function organisation()
    {
        return $this->belongsTo(Organisation::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function taskGroups()
    {
        return $this->belongsToMany(TaskGroup::class);
    }

    public function getPrivilegeLabelAttribute(): string
    {
        return match ((int) $this->privilege) {
            0 => 'User',
            14 => 'Admin',
            default => 'Unknown',
        };
    }

    public function getHasLoginAttribute(): bool
    {
        return (int) $this->privilege === 14
            && filled($this->email)
            && filled($this->password);
    }

    public function getActiveSchedule(?Carbon $date = null)
    {
        $date = $date ?? now();

        $groupSchedule = Schedule::where('status', true)
            ->where('target_type', TaskGroup::class)
            ->whereIn('target_id', $this->taskGroups()->pluck('task_groups.id'))
            ->where(function ($query) use ($date) {
                $query->whereNull('valid_from')->orWhere('valid_from', '<=', $date);
            })
            ->where(function ($query) use ($date) {
                $query->whereNull('valid_to')->orWhere('valid_to', '>=', $date);
            })
            ->first();

        if ($groupSchedule) {
            return $groupSchedule;
        }

        if ($this->department_id) {
            $deptSchedule = Schedule::where('status', true)
                ->where('target_type', Department::class)
                ->where('target_id', $this->department_id)
                ->where(function ($query) use ($date) {
                    $query->whereNull('valid_from')->orWhere('valid_from', '<=', $date);
                })
                ->where(function ($query) use ($date) {
                    $query->whereNull('valid_to')->orWhere('valid_to', '>=', $date);
                })
                ->first();

            if ($deptSchedule) {
                return $deptSchedule;
            }
        }

        if ($this->branch_id) {
            $branchSchedule = Schedule::where('status', true)
                ->where('target_type', Branch::class)
                ->where('target_id', $this->branch_id)
                ->where(function ($query) use ($date) {
                    $query->whereNull('valid_from')->orWhere('valid_from', '<=', $date);
                })
                ->where(function ($query) use ($date) {
                    $query->whereNull('valid_to')->orWhere('valid_to', '>=', $date);
                })
                ->first();

            if ($branchSchedule) {
                return $branchSchedule;
            }
        }

        return Schedule::where('status', true)
            ->whereNull('target_type')
            ->where(function ($query) use ($date) {
                $query->whereNull('valid_from')->orWhere('valid_from', '<=', $date);
            })
            ->where(function ($query) use ($date) {
                $query->whereNull('valid_to')->orWhere('valid_to', '>=', $date);
            })
            ->first();
    }
}
