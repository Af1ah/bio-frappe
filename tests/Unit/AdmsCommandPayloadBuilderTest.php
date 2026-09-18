<?php

namespace Tests\Unit;

use App\Models\DeviceCommand;
use App\Services\DeviceGateway\AdmsCommandPayloadBuilder;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdmsCommandPayloadBuilderTest extends TestCase
{
    private AdmsCommandPayloadBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new AdmsCommandPayloadBuilder;
    }

    public function test_builds_supported_commands(): void
    {
        $info = new DeviceCommand(['command_type' => 'device_info']);
        $this->assertSame('INFO', $this->builder->build($info));

        $check = new DeviceCommand(['command_type' => 'check']);
        $this->assertSame('CHECK', $this->builder->build($check));

        $reboot = new DeviceCommand(['command_type' => 'reboot']);
        $this->assertSame('REBOOT', $this->builder->build($reboot));

        $clearLogs = new DeviceCommand(['command_type' => 'clear_logs']);
        $this->assertSame('CLEAR LOG', $this->builder->build($clearLogs));

        $fetchUsers = new DeviceCommand(['command_type' => 'fetch_users']);
        $this->assertSame('DATA QUERY USERINFO', $this->builder->build($fetchUsers));

        $queryUsers = new DeviceCommand(['command_type' => 'query_users']);
        $this->assertSame('DATA QUERY USERINFO', $this->builder->build($queryUsers));

        $resetTx = new DeviceCommand(['command_type' => 'reset_transaction_stamp']);
        $this->assertSame('SET OPTION ATTLOGStamp=0', $this->builder->build($resetTx));

        $resetOp = new DeviceCommand(['command_type' => 'reset_op_stamp']);
        $this->assertSame('SET OPTION OPERLOGStamp=0', $this->builder->build($resetOp));

        $unlock = new DeviceCommand(['command_type' => 'unlock_door']);
        $this->assertSame('AC_UN', $this->builder->build($unlock));

        $lock = new DeviceCommand(['command_type' => 'lock_door']);
        $this->assertSame('AC_LOCK', $this->builder->build($lock));

        $normal = new DeviceCommand(['command_type' => 'normal_door']);
        $this->assertSame('AC_NOR', $this->builder->build($normal));
    }

    public function test_builds_user_deletion_commands(): void
    {
        $delUser = new DeviceCommand([
            'command_type' => 'delete_user',
            'command_content' => json_encode(['pin' => '1001']),
        ]);
        $this->assertSame('DATA DELETE USERINFO PIN=1001', $this->builder->build($delUser));

        $delFp = new DeviceCommand([
            'command_type' => 'delete_fingerprint',
            'command_content' => json_encode(['pin' => '1001', 'fid' => 2]),
        ]);
        $this->assertSame("DATA DELETE FINGERTMP PIN=1001\tFID=2", $this->builder->build($delFp));

        $delFace = new DeviceCommand([
            'command_type' => 'delete_face',
            'command_content' => json_encode(['pin' => '1001']),
        ]);
        $this->assertSame('DATA DELETE USERFACE PIN=1001', $this->builder->build($delFace));
    }

    public function test_builds_user_upload_commands(): void
    {
        $uploadUser = new DeviceCommand([
            'command_type' => 'upload_user',
            'command_content' => json_encode([
                'pin' => '1001',
                'name' => 'Alice Doe',
                'privilege' => 0,
                'card' => 'CARD123',
                'password' => '9999',
            ]),
        ]);
        $this->assertSame("DATA UPDATE USERINFO PIN=1001\tName=Alice Doe\tPrivilege=0\tCard=CARD123\tPassword=9999", $this->builder->build($uploadUser));

        $uploadFp = new DeviceCommand([
            'command_type' => 'upload_fingerprint',
            'command_content' => json_encode([
                'pin' => '1001',
                'fid' => 0,
                'size' => 128,
                'template' => 'base64templateXYZ',
            ]),
        ]);
        $this->assertSame("DATA UPDATE FINGERTMP PIN=1001\tFID=0\tSize=128\tValid=1\tTMP=base64templateXYZ", $this->builder->build($uploadFp));

        $uploadFace = new DeviceCommand([
            'command_type' => 'upload_face',
            'command_content' => json_encode([
                'pin' => '1001',
                'template' => 'base64faceABC',
            ]),
        ]);
        $this->assertSame("DATA UPDATE USERFACE PIN=1001\tFID=0\tSize=13\tValid=1\tTMP=base64faceABC", $this->builder->build($uploadFace));

        $uploadFaceV2 = new DeviceCommand([
            'command_type' => 'upload_face_v2',
            'command_content' => json_encode([
                'pin' => '1001',
                'template' => 'base64biodataDEF',
                'major_version' => '12',
                'minor_version' => '0',
            ]),
        ]);
        $this->assertSame("DATA UPDATE BIODATA Pin=1001\tNo=0\tIndex=0\tValid=1\tDuress=0\tType=9\tMajorVer=12\tMinorVer=0\tFormat=0\tTmp=base64biodataDEF", $this->builder->build($uploadFaceV2));

        $uploadBiophoto = new DeviceCommand([
            'command_type' => 'upload_face_v2',
            'command_content' => json_encode([
                'pin' => '1001',
                'photo' => 'base64jpegcontent',
                'file_name' => '1001.jpg',
            ]),
        ]);
        $this->assertSame("DATA UPDATE BIOPHOTO PIN=1001\tFileName=1001.jpg\tSize=17\tContent=base64jpegcontent", $this->builder->build($uploadBiophoto));
    }

    public function test_builds_device_enrollment_commands(): void
    {
        $enrollFp = new DeviceCommand([
            'command_type' => 'enroll_fingerprint',
            'command_content' => json_encode(['pin' => '1001', 'fid' => 3]),
        ]);
        $this->assertSame("ENROLL_FP PIN=1001\tFID=3\tRETRY=3\tOVERWRITE=1", $this->builder->build($enrollFp));

        $enrollFace = new DeviceCommand([
            'command_type' => 'enroll_face',
            'command_content' => json_encode(['pin' => '1001']),
        ]);
        $this->assertSame('ENROLL_FACE PIN=1001', $this->builder->build($enrollFace));
    }

    public function test_rejects_unsupported_commands(): void
    {
        $unsupported = new DeviceCommand(['command_type' => 'sync_time']);

        $this->expectException(ValidationException::class);
        $this->builder->build($unsupported);
    }
}
