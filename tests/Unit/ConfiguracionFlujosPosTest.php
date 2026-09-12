<?php

namespace Tests\Unit;

use App\Enums\FlujoPos;
use App\ValueObjects\ConfiguracionFlujosPos;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ConfiguracionFlujosPosTest extends TestCase
{
    public function test_defaults_keep_both_flows_and_counter_as_default(): void
    {
        $settings = ConfiguracionFlujosPos::defaults();

        $this->assertTrue($settings->mostradorPrepago);
        $this->assertTrue($settings->mesaPostpago);
        $this->assertSame(FlujoPos::MOSTRADOR_PREPAGO, $settings->predeterminado);
    }

    public function test_at_least_one_flow_must_remain_enabled(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ConfiguracionFlujosPos::fromArray([
            'version' => 1,
            'mostrador_prepago' => false,
            'mesa_postpago' => false,
            'predeterminado' => FlujoPos::MOSTRADOR_PREPAGO->value,
        ]);
    }

    public function test_default_flow_must_be_enabled(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ConfiguracionFlujosPos::fromArray([
            'version' => 1,
            'mostrador_prepago' => false,
            'mesa_postpago' => true,
            'predeterminado' => FlujoPos::MOSTRADOR_PREPAGO->value,
        ]);
    }

    public function test_unknown_configuration_fields_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ConfiguracionFlujosPos::fromArray([
            'version' => 1,
            'mostrador_prepago' => true,
            'mesa_postpago' => true,
            'predeterminado' => FlujoPos::MOSTRADOR_PREPAGO->value,
            'empresa_id' => 99,
        ]);
    }
}
