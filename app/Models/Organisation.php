<?php

namespace App\Models;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Organisation extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    protected $table = 'organisations';

    protected $fillable = [
        'name',
        'shortname',
        'db_name',
        'email',
        'phone',
        'logo',
        'brand_color',
        'status',
    ];

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'shortname',
            'db_name',
            'email',
            'phone',
            'logo',
            'brand_color',
            'status',
        ];
    }

    public function branches()
    {
        return $this->hasMany(Branch::class);
    }

    public function departments()
    {
        return $this->hasMany(Department::class);
    }

    public function resolveRouteBinding($value, $field = null)
    {
        return $this->where('shortname', $value)->orWhere('id', $value)->firstOrFail();
    }

    protected static function booted()
    {
        static::created(function ($tenant) {
            // Extract central domain from CENTRAL_DOMAIN env, fallback to APP_URL host
            $centralDomain = env('CENTRAL_DOMAIN');
            if (empty($centralDomain)) {
                $centralDomain = parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST);
            }

            $tenant->domains()->create([
                'domain' => $tenant->shortname.'.'.ltrim($centralDomain, '.'),
            ]);

            // Also create a localhost domain for easy local testing
            if ($centralDomain !== 'localhost') {
                $tenant->domains()->create([
                    'domain' => $tenant->shortname.'.localhost',
                ]);
            }
        });
    }
}
