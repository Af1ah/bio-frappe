<?php

namespace Tests\Unit;

use App\Casts\EncryptedArrayCast;
use App\Casts\EncryptedStringCast;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class EncryptedCastsTest extends TestCase
{
    public function test_encrypted_array_cast_encrypts_on_set_and_decrypts_on_get(): void
    {
        $cast = new EncryptedArrayCast;
        $user = new User;

        $original = [
            ['fid' => 0, 'size' => 128, 'template' => 'base64templateA'],
            ['fid' => 1, 'size' => 128, 'template' => 'base64templateB'],
        ];

        // Store into DB
        $stored = $cast->set($user, 'fingerprints', $original, []);
        $this->assertIsString($stored);

        // Stored value must be a valid JSON envelope containing ciphertext
        $decodedJson = json_decode($stored, true);
        $this->assertTrue($decodedJson['encrypted']);
        $this->assertNotEmpty($decodedJson['payload']);
        $this->assertNotSame(json_encode($original), $stored);

        // Ciphertext should decrypt back with Crypt
        $decryptedPayload = Crypt::decryptString($decodedJson['payload']);
        $this->assertSame(json_encode($original), $decryptedPayload);

        // Cast on retrieve
        $retrieved = $cast->get($user, 'fingerprints', $stored, []);
        $this->assertSame($original, $retrieved);
    }

    public function test_encrypted_array_cast_gracefully_handles_legacy_unencrypted_json(): void
    {
        $cast = new EncryptedArrayCast;
        $user = new User;

        $legacyPlainJson = json_encode([
            ['fid' => 0, 'template' => 'legacyA'],
            ['fid' => 1, 'template' => 'legacyB'],
        ]);

        // Reading legacy unencrypted JSON must not throw DecryptException and must return array
        $retrieved = $cast->get($user, 'fingerprints', $legacyPlainJson, []);
        $this->assertCount(2, $retrieved);
        $this->assertSame('legacyA', $retrieved[0]['template']);
    }

    public function test_encrypted_array_cast_handles_null_and_empty(): void
    {
        $cast = new EncryptedArrayCast;
        $user = new User;

        $this->assertNull($cast->get($user, 'fingerprints', null, []));
        $this->assertNull($cast->get($user, 'fingerprints', '', []));
        $this->assertNull($cast->set($user, 'fingerprints', null, []));
    }

    public function test_encrypted_string_cast_encrypts_and_decrypts(): void
    {
        $cast = new EncryptedStringCast;
        $user = new User;

        $stored = $cast->set($user, 'device_password', '9876', []);
        $this->assertNotSame('9876', $stored);

        $retrieved = $cast->get($user, 'device_password', $stored, []);
        $this->assertSame('9876', $retrieved);

        // Legacy unencrypted string fallback
        $this->assertSame('legacy_pass', $cast->get($user, 'device_password', 'legacy_pass', []));
    }
}
