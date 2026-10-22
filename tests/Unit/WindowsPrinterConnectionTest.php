<?php

namespace Tests\Unit;

use App\Enums\TipoConexionImpresora;
use App\Models\Impresora;
use App\Rules\WindowsPrinterPath;
use App\Services\Printing\PrinterConnectorFactory;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;
use Mike42\Escpos\PrintConnectors\MemoryPrintConnector;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class WindowsPrinterConnectionTest extends TestCase
{
    public static function paths(): array
    {
        return [
            ['\\\\CAJA-PC\\RECIBOS', 'smb://CAJA-PC/RECIBOS'],
            ['\\\\192.168.0.200\\Recibos Caja', 'smb://192.168.0.200/Recibos Caja'],
        ];
    }

    #[DataProvider('paths')]
    public function test_valid_paths(string $path, string $expected): void
    {
        $this->assertSame($expected, WindowsPrinterPath::toSmb($path));
        $this->assertTrue(Validator::make(['ruta' => $path], ['ruta' => ['required', new WindowsPrinterPath]])->passes());
    }

    public static function invalidPaths(): array
    {
        return array_map(fn ($path) => [$path], [
            '', 'RECIBOS', 'C:\\ticket.prn', 'smb://CAJA-PC/RECIBOS',
            '\\\\CAJA-PC', '\\\\CAJA-PC\\', '\\\\CAJA-PC\\RECIBOS\\extra',
            '\\\\CAJA-PC\\carpeta/RECIBOS', '\\\\usuario:clave@CAJA-PC\\RECIBOS',
            "\\\\CAJA-PC\\RECIBOS\n", '\\\\CAJA-PC\\'.str_repeat('a', 250),
        ]);
    }

    #[DataProvider('invalidPaths')]
    public function test_invalid_paths(string $path): void
    {
        $this->assertTrue(Validator::make(['ruta' => $path], ['ruta' => ['required', new WindowsPrinterPath]])->fails());
        $this->expectException(InvalidArgumentException::class);
        WindowsPrinterPath::toSmb($path);
    }

    public function test_windows_connector_is_selected_without_sending_data(): void
    {
        $factory = new class extends PrinterConnectorFactory
        {
            protected function isWindows(): bool { return true; }
        };
        $printer = new Impresora(['conexion' => 'WINDOWS', 'ruta_windows' => '\\\\CAJA-PC\\RECIBOS']);
        $connector = $factory->create($printer);
        $this->assertInstanceOf(WindowsPrintConnector::class, $connector);
        $this->assertSame('\\\\CAJA-PC\\RECIBOS', $printer->direccionConexion());
        // Discard the library buffer without finalize(), which would contact SMB.
        (new \ReflectionProperty($connector, 'buffer'))->setValue($connector, null);
    }

    public function test_windows_connection_is_rejected_on_other_platforms(): void
    {
        $factory = new class extends PrinterConnectorFactory
        {
            protected function isWindows(): bool { return false; }
        };
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('requiere ejecutar PHP en Windows');
        $factory->create(new Impresora(['conexion' => 'WINDOWS', 'ruta_windows' => '\\\\CAJA-PC\\RECIBOS']));
    }

    public function test_factory_also_validates_paths_outside_the_form(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new PrinterConnectorFactory)->create(new Impresora(['conexion' => 'WINDOWS', 'ruta_windows' => '\\\\CAJA-PC\\RECIBOS\\extra']));
    }

    public function test_pdf_and_usb_connectors_keep_their_existing_behavior(): void
    {
        $factory = new PrinterConnectorFactory;
        $memory = $factory->create(new Impresora(['conexion' => 'PDF']));
        $this->assertInstanceOf(MemoryPrintConnector::class, $memory);
        $memory->finalize();
        $path = tempnam(sys_get_temp_dir(), 'printer-test-');
        try {
            $connector = $factory->create(new Impresora(['conexion' => 'USB', 'dispositivo_usb' => $path]));
            $this->assertInstanceOf(FilePrintConnector::class, $connector);
            $connector->write('test');
            $connector->finalize();
            $this->assertSame('test', file_get_contents($path));
        } finally {
            unlink($path);
        }
        $this->assertSame('10.0.0.1:9100', (new Impresora(['conexion' => 'RED', 'ip' => '10.0.0.1', 'puerto' => 9100]))->direccionConexion());
    }

    public function test_network_connector_uses_a_loopback_socket_without_hardware(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        $this->assertIsResource($server);
        $port = (int) substr(strrchr(stream_socket_get_name($server, false), ':'), 1);
        try {
            $connector = (new PrinterConnectorFactory)->create(new Impresora(['conexion' => 'RED', 'ip' => '127.0.0.1', 'puerto' => $port]));
            $client = stream_socket_accept($server, 2);
            $this->assertInstanceOf(NetworkPrintConnector::class, $connector);
            $this->assertIsResource($client);
            try {
                $connector->write('network-test');
                $connector->finalize();
                $this->assertSame('network-test', stream_get_contents($client));
            } finally {
                fclose($client);
            }
        } finally {
            fclose($server);
        }
    }
}
