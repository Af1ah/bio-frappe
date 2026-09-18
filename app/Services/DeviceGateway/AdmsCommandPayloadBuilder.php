<?php

namespace App\Services\DeviceGateway;

use App\Models\DeviceCommand;
use Illuminate\Validation\ValidationException;

class AdmsCommandPayloadBuilder
{
    public function build(DeviceCommand $command): string
    {
        $content = $this->parseContent($command->command_content);

        return match ($command->command_type) {
            'device_info', 'INFO' => 'INFO',
            'check', 'CHECK' => 'CHECK',
            'reboot', 'REBOOT' => 'REBOOT',
            'clear_logs', 'CLEAR' => 'CLEAR LOG',
            'reset_transaction_stamp' => 'SET OPTION ATTLOGStamp=0',
            'reset_op_stamp' => 'SET OPTION OPERLOGStamp=0',
            'unlock_door' => 'AC_UN',
            'lock_door' => 'AC_LOCK',
            'normal_door' => 'AC_NOR',
            'fetch_users', 'query_users', 'query_userinfo' => 'DATA QUERY USERINFO',

            // User deletion
            'delete_user' => $this->buildDeleteUser($content),
            'delete_fingerprint' => $this->buildDeleteFingerprint($content),
            'delete_face' => $this->buildDeleteFace($content),

            // User upload & credentials
            'upload_user', 'user_add' => $this->buildUploadUser($content),
            'upload_fingerprint' => $this->buildUploadFingerprint($content),
            'upload_face' => $this->buildUploadFace($content),
            'upload_face_v2' => $this->buildUploadFaceV2($content),

            // Device-side enrollment triggers
            'enroll_fingerprint', 'enroll_fp' => $this->buildEnrollFingerprint($content),
            'enroll_face' => $this->buildEnrollFace($content),

            default => throw ValidationException::withMessages([
                'command_type' => "{$command->command_type} is not supported by the standalone ADMS transport.",
            ]),
        };
    }

    private function parseContent(mixed $raw): mixed
    {
        if (is_array($raw)) {
            return $raw;
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return $raw;
    }

    /**
     * Sanitize string parameter to ensure no protocol-injected newline characters.
     */
    private function sanitize(mixed $value): string
    {
        return str_replace(["\r", "\n"], '', trim((string) $value));
    }

    private function buildDeleteUser(mixed $content): string
    {
        $pin = is_array($content) ? ($content['pin'] ?? '') : (string) $content;
        $cleanPin = $this->sanitize($pin);

        if ($cleanPin === '') {
            throw ValidationException::withMessages(['command_content' => 'PIN is required to delete a user.']);
        }

        return "DATA DELETE USERINFO PIN={$cleanPin}";
    }

    private function buildDeleteFingerprint(mixed $content): string
    {
        $pin = is_array($content) ? ($content['pin'] ?? '') : (string) $content;
        $cleanPin = $this->sanitize($pin);

        if ($cleanPin === '') {
            throw ValidationException::withMessages(['command_content' => 'PIN is required to delete fingerprint template.']);
        }

        if (is_array($content) && isset($content['fid'])) {
            $fid = (int) $content['fid'];

            return "DATA DELETE FINGERTMP PIN={$cleanPin}\tFID={$fid}";
        }

        return "DATA DELETE FINGERTMP PIN={$cleanPin}";
    }

    private function buildDeleteFace(mixed $content): string
    {
        $pin = is_array($content) ? ($content['pin'] ?? '') : (string) $content;
        $cleanPin = $this->sanitize($pin);

        if ($cleanPin === '') {
            throw ValidationException::withMessages(['command_content' => 'PIN is required to delete face template.']);
        }

        return "DATA DELETE USERFACE PIN={$cleanPin}";
    }

    private function buildUploadUser(mixed $content): string
    {
        if (! is_array($content) || empty($content['pin'])) {
            throw ValidationException::withMessages(['command_content' => 'User upload requires at least a PIN.']);
        }

        $pin = $this->sanitize($content['pin']);
        $name = $this->sanitize($content['name'] ?? "User {$pin}");
        $privilege = (int) ($content['privilege'] ?? 0);

        $cmd = "DATA UPDATE USERINFO PIN={$pin}\tName={$name}\tPrivilege={$privilege}";

        if (filled($content['card'] ?? null)) {
            $card = $this->sanitize($content['card']);
            $cmd .= "\tCard={$card}";
        }

        if (filled($content['password'] ?? null)) {
            $password = $this->sanitize($content['password']);
            $cmd .= "\tPassword={$password}";
        }

        return $cmd;
    }

    private function buildUploadFingerprint(mixed $content): string
    {
        if (! is_array($content) || empty($content['pin']) || empty($content['template'])) {
            throw ValidationException::withMessages(['command_content' => 'Fingerprint upload requires PIN and template.']);
        }

        $pin = $this->sanitize($content['pin']);
        $fid = (int) ($content['fid'] ?? 0);
        $template = $this->sanitize($content['template']);
        $size = (int) ($content['size'] ?? strlen($template));
        $valid = (int) ($content['valid'] ?? 1);

        return "DATA UPDATE FINGERTMP PIN={$pin}\tFID={$fid}\tSize={$size}\tValid={$valid}\tTMP={$template}";
    }

    private function buildUploadFace(mixed $content): string
    {
        if (! is_array($content) || empty($content['pin']) || empty($content['template'])) {
            throw ValidationException::withMessages(['command_content' => 'Face upload requires PIN and template.']);
        }

        $pin = $this->sanitize($content['pin']);
        $fid = (int) ($content['fid'] ?? 0);
        $template = $this->sanitize($content['template']);
        $size = (int) ($content['size'] ?? strlen($template));
        $valid = (int) ($content['valid'] ?? 1);

        return "DATA UPDATE USERFACE PIN={$pin}\tFID={$fid}\tSize={$size}\tValid={$valid}\tTMP={$template}";
    }

    private function buildUploadFaceV2(mixed $content): string
    {
        if (! is_array($content) || empty($content['pin'])) {
            throw ValidationException::withMessages(['command_content' => 'Face v2 upload requires PIN.']);
        }

        $pin = $this->sanitize($content['pin']);

        // Check if biophoto upload
        if (filled($content['photo'] ?? null)) {
            $photo = $this->sanitize($content['photo']);
            $size = strlen($photo);
            $fileName = $this->sanitize($content['file_name'] ?? "{$pin}.jpg");

            return "DATA UPDATE BIOPHOTO PIN={$pin}\tFileName={$fileName}\tSize={$size}\tContent={$photo}";
        }

        $template = $this->sanitize($content['template'] ?? ($content['biodata'] ?? ''));
        if ($template === '') {
            throw ValidationException::withMessages(['command_content' => 'Face v2 upload requires template or photo.']);
        }

        $major = $this->sanitize($content['major_version'] ?? '12');
        $minor = $this->sanitize($content['minor_version'] ?? '0');
        $type = (int) ($content['type'] ?? 9); // Type 9 = Face

        return "DATA UPDATE BIODATA Pin={$pin}\tNo=0\tIndex=0\tValid=1\tDuress=0\tType={$type}\tMajorVer={$major}\tMinorVer={$minor}\tFormat=0\tTmp={$template}";
    }

    private function buildEnrollFingerprint(mixed $content): string
    {
        $pin = is_array($content) ? ($content['pin'] ?? '') : (string) $content;
        $cleanPin = $this->sanitize($pin);

        if ($cleanPin === '') {
            throw ValidationException::withMessages(['command_content' => 'PIN is required to trigger fingerprint enrollment.']);
        }

        $fid = is_array($content) ? (int) ($content['fid'] ?? 0) : 0;
        $retry = is_array($content) ? (int) ($content['retry'] ?? 3) : 3;
        $overwrite = is_array($content) ? (int) ($content['overwrite'] ?? 1) : 1;

        return "ENROLL_FP PIN={$cleanPin}\tFID={$fid}\tRETRY={$retry}\tOVERWRITE={$overwrite}";
    }

    private function buildEnrollFace(mixed $content): string
    {
        $pin = is_array($content) ? ($content['pin'] ?? '') : (string) $content;
        $cleanPin = $this->sanitize($pin);

        if ($cleanPin === '') {
            throw ValidationException::withMessages(['command_content' => 'PIN is required to trigger face enrollment.']);
        }

        return "ENROLL_FACE PIN={$cleanPin}";
    }
}
