<?php

namespace Tests\Unit;

use App\Models\DeviceBinding;
use App\Services\DeviceGateway\DeviceSourceVerifier;
use PHPUnit\Framework\TestCase;

class DeviceSourceVerifierTest extends TestCase
{
    private DeviceSourceVerifier $verifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->verifier = new DeviceSourceVerifier;
    }

    public function test_matches_exact_ip_without_mask(): void
    {
        $binding = new DeviceBinding(['source_cidr' => '192.168.1.50']);

        $this->assertTrue($this->verifier->matches($binding, '192.168.1.50'));
        $this->assertFalse($this->verifier->matches($binding, '192.168.1.51'));
    }

    public function test_matches_cidr_subnet(): void
    {
        $binding = new DeviceBinding(['source_cidr' => '192.168.1.0/24']);

        $this->assertTrue($this->verifier->matches($binding, '192.168.1.1'));
        $this->assertTrue($this->verifier->matches($binding, '192.168.1.254'));
        $this->assertFalse($this->verifier->matches($binding, '192.168.2.1'));
    }

    public function test_handles_whitespace_gracefully(): void
    {
        $binding = new DeviceBinding(['source_cidr' => '  10.0.0.0/8  ']);

        $this->assertTrue($this->verifier->matches($binding, ' 10.12.34.56 '));
        $this->assertFalse($this->verifier->matches($binding, '11.0.0.1'));
    }

    public function test_rejects_invalid_inputs_and_out_of_bounds_prefix(): void
    {
        $binding = new DeviceBinding(['source_cidr' => '192.168.1.0/999']);
        $this->assertFalse($this->verifier->matches($binding, '192.168.1.1'));

        $bindingEmpty = new DeviceBinding(['source_cidr' => '']);
        $this->assertFalse($this->verifier->matches($bindingEmpty, '192.168.1.1'));

        $bindingValid = new DeviceBinding(['source_cidr' => '192.168.1.0/24']);
        $this->assertFalse($this->verifier->matches($bindingValid, 'not-an-ip'));
    }
}
