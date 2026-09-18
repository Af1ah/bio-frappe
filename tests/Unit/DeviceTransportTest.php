<?php

namespace Tests\Unit;

use App\Enums\DeviceTransport;
use App\Models\Device;
use PHPUnit\Framework\TestCase;

class DeviceTransportTest extends TestCase
{
    public function test_resolves_configured_mode(): void
    {
        $admsDevice = new Device(['options' => ['connection_mode' => 'adms']]);
        $this->assertSame(DeviceTransport::Adms, DeviceTransport::forDevice($admsDevice));

        $directDevice = new Device(['options' => ['connection_mode' => 'direct']]);
        $this->assertSame(DeviceTransport::Direct, DeviceTransport::forDevice($directDevice));

        $ebioDevice = new Device(['options' => ['connection_mode' => 'ebio']]);
        $this->assertSame(DeviceTransport::Ebio, DeviceTransport::forDevice($ebioDevice));
    }

    public function test_falls_back_to_adms_enabled_legacy_flag(): void
    {
        $legacyDirect = new Device(['options' => ['adms_enabled' => true]]);
        $this->assertSame(DeviceTransport::Direct, DeviceTransport::forDevice($legacyDirect));

        $defaultEbio = new Device(['options' => []]);
        $this->assertSame(DeviceTransport::Ebio, DeviceTransport::forDevice($defaultEbio));
    }
}
