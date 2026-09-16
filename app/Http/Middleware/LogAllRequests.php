<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LogAllRequests
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        \Illuminate\Support\Facades\Log::channel('single')->info('INCOMING HTTP REQUEST:', [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'headers' => $this->safeHeaders($request),
            'payload' => $this->safePayload($request),
            'content_length' => strlen($request->getContent()),
        ]);
        return $next($request);
    }

    /** @return array<string, array<int, string>> */
    private function safeHeaders(Request $request): array
    {
        return collect($request->headers->all())
            ->mapWithKeys(fn (array $values, string $name) => [
                $name => in_array(strtolower($name), ['authorization', 'cookie', 'set-cookie', 'x-api-key'], true)
                    ? ['[REDACTED]']
                    : $values,
            ])
            ->all();
    }

    /** @return array<string, mixed> */
    private function safePayload(Request $request): array
    {
        // Livewire snapshots are serialized state and can contain arbitrary form values.
        // Log only their presence; never write form state or raw request content to disk.
        if ($request->has('components')) {
            return ['livewire_components' => '[REDACTED]'];
        }

        return $this->redact($request->all());
    }

    /** @param array<string, mixed> $values */
    private function redact(array $values): array
    {
        $sensitive = ['password', 'token', 'secret', 'authorization', 'cookie', 'api_key', 'encryption', 'aes', 'signature'];

        foreach ($values as $key => $value) {
            if (Str::contains(strtolower((string) $key), $sensitive)) {
                $values[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $values[$key] = $this->redact($value);
            }
        }

        return $values;
    }
}
