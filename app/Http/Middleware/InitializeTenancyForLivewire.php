<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Organisation;

class InitializeTenancyForLivewire
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->is('livewire/*')) {
            $host = $request->getHost();
            $centralDomain = env('CENTRAL_DOMAIN', 'ariise.cloud');
            
            $tenant = null;

            // 1. Try Domain-based tenancy
            if ($host !== 'localhost' && $host !== $centralDomain && !str_ends_with($host, '.test')) {
                $domain = \Stancl\Tenancy\Database\Models\Domain::where('domain', $host)->first();
                if ($domain) {
                    $tenant = $domain->tenant;
                }
            }

            // 2. Try the Livewire component snapshot. Unlike Referer, this remains
            // available for SPA navigation and for POST /livewire/update requests.
            if (!$tenant) {
                $snapshot = json_decode((string) data_get($request->input('components'), '0.snapshot'), true);
                $tenant = $this->tenantFromPath(data_get($snapshot, 'memo.path'));
            }

            // 3. Fall back to the browser URL for regular Livewire requests.
            if (!$tenant) {
                $referer = $request->header('referer');
                if ($referer) {
                    $tenant = $this->tenantFromPath(parse_url($referer, PHP_URL_PATH));
                }
            }
            
            if ($tenant) {
                tenancy()->initialize($tenant);
                \Illuminate\Support\Facades\URL::defaults(['tenant' => $tenant->shortname ?? $tenant->id]);
                
                // Ensure essential storage directories exist for this tenant
                $directories = [
                    storage_path('framework/cache'),
                    storage_path('framework/views'),
                    storage_path('framework/sessions'),
                    storage_path('app/livewire-tmp'),
                    storage_path('app/public'),
                ];
                foreach ($directories as $dir) {
                    if (!is_dir($dir)) {
                        @mkdir($dir, 0755, true);
                    }
                }
            }
        }

        return $next($request);
    }

    private function tenantFromPath(?string $path): ?Organisation
    {
        if (!$path) {
            return null;
        }

        $shortname = explode('/', trim($path, '/'))[0] ?? null;

        if (!$shortname || in_array($shortname, ['master', 'livewire'])) {
            return null;
        }

        return Organisation::where('shortname', $shortname)->first();
    }
}
