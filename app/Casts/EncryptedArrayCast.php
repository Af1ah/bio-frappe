<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class EncryptedArrayCast implements CastsAttributes
{
    /**
     * Cast the given value from database storage.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<mixed>|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        // If already an array (e.g. from memory or pre-hydrated)
        if (is_array($value)) {
            return $value;
        }

        // First, check if stored as JSON envelope: {"encrypted":true,"payload":"..."}
        $decoded = json_decode((string) $value, true);
        if (is_array($decoded) && ($decoded['encrypted'] ?? false) === true && filled($decoded['payload'] ?? null)) {
            try {
                $decrypted = Crypt::decryptString($decoded['payload']);
                $unpacked = json_decode($decrypted, true);

                return is_array($unpacked) ? $unpacked : [];
            } catch (DecryptException) {
                return [];
            }
        }

        // Second, try direct string decryption in case it was stored as raw Crypt::encryptString
        try {
            $decrypted = Crypt::decryptString((string) $value);
            $unpacked = json_decode($decrypted, true);
            if (is_array($unpacked)) {
                return $unpacked;
            }
        } catch (Throwable) {
            // Not a direct encrypted string
        }

        // Fallback: Legacy unencrypted JSON in DB
        if (is_array($decoded)) {
            return $decoded;
        }

        return [];
    }

    /**
     * Prepare the given value for database storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $arrayValue = is_array($value) ? $value : (json_decode((string) $value, true) ?: [$value]);

        $ciphertext = Crypt::encryptString(json_encode($arrayValue, JSON_THROW_ON_ERROR));

        return json_encode([
            'encrypted' => true,
            'payload' => $ciphertext,
        ], JSON_THROW_ON_ERROR);
    }
}
