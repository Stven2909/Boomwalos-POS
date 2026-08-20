<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $titulo ?? 'Ticket de Impresión' }}</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            background-color: #0f172a;
            font-family: 'Courier New', Courier, monospace;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 24px 12px;
            min-height: 100vh;
        }
        .actions-bar {
            margin-bottom: 20px;
            display: flex;
            gap: 12px;
        }
        .btn {
            background: #2563eb;
            color: white;
            border: none;
            padding: 10px 22px;
            border-radius: 8px;
            font-family: sans-serif;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
            transition: all 0.2s;
        }
        .btn:hover {
            background: #1d4ed8;
            transform: translateY(-1px);
        }
        .btn-secondary {
            background: #475569;
            box-shadow: 0 4px 12px rgba(71, 85, 105, 0.3);
        }
        .btn-secondary:hover {
            background: #334155;
        }
        .ticket-box {
            background: #fff;
            width: 320px;
            padding: 24px 18px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.35);
            border-radius: 6px;
            border-top: 4px solid #f59e0b;
        }
        pre {
            font-family: 'Courier New', Courier, monospace;
            font-size: 12.5px;
            line-height: 1.35;
            white-space: pre-wrap;
            word-break: break-word;
            color: #0f172a;
        }
        .qr-section {
            text-align: center;
            margin: 14px 0 6px 0;
            padding: 8px 0;
        }
        .qr-section img {
            width: 140px;
            height: 140px;
            display: block;
            margin: 0 auto;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 4px;
            background: #fff;
        }
        .qr-section .qr-hint {
            font-family: sans-serif;
            font-size: 11px;
            font-weight: bold;
            color: #334155;
            margin-top: 6px;
            letter-spacing: 0.5px;
        }
        @media print {
            body {
                background: none;
                padding: 0;
            }
            .actions-bar {
                display: none !important;
            }
            .ticket-box {
                box-shadow: none;
                border: none;
                width: 100%;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="actions-bar">
        <button class="btn" onclick="window.print()">🖨️ Imprimir Ticket</button>
        <button class="btn btn-secondary" onclick="window.close()">Cerrar</button>
    </div>

    @php
        $rawContenido = $contenido ?? '';
        $lineas = explode("\n", $rawContenido);
        $qrUrl = null;
        $filteredLines = [];

        foreach ($lineas as $l) {
            $trimmed = trim($l);
            if (str_starts_with($trimmed, 'QR_URL:')) {
                $qrUrl = trim(substr($trimmed, 7));
                continue; // No mostrar texto crudo QR_URL
            }
            if (str_starts_with($trimmed, 'https://boomwalos.vercel.app/?tracking=')) {
                $qrUrl = $trimmed;
                continue;
            }
            $filteredLines[] = $l;
        }

        // Si no vino la línea QR_URL, extraer tracking para generar la URL
        if (! $qrUrl) {
            foreach ($lineas as $l) {
                if (preg_match('/Tracking:\s*([A-Za-z0-9\-_]+)/i', $l, $matches)) {
                    $qrUrl = 'https://boomwalos.vercel.app/?tracking=' . urlencode(trim($matches[1]));
                    break;
                }
            }
        }

        $qrImageBase64 = null;
        if ($qrUrl) {
            try {
                $qrOptions = new \chillerlan\QRCode\QROptions([
                    'outputType' => \chillerlan\QRCode\QRCode::OUTPUT_IMAGE_PNG,
                    'imageBase64' => true,
                    'scale' => 5,
                ]);
                $qrImageBase64 = (new \chillerlan\QRCode\QRCode($qrOptions))->render($qrUrl);
            } catch (\Throwable $e) {
                $qrImageBase64 = null;
            }
        }

        $textoFinal = implode("\n", $filteredLines);
    @endphp

    <div class="ticket-box">
        <pre>{{ $textoFinal }}</pre>

        @if ($qrImageBase64)
            <div class="qr-section">
                <img src="{{ $qrImageBase64 }}" alt="Código QR de Facturación">
                <div class="qr-hint">ESCANEA PARA SOLICITAR DTE</div>
            </div>
        @elseif ($qrUrl)
            <div class="qr-section">
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=140x140&data={{ urlencode($qrUrl) }}" alt="QR">
                <div class="qr-hint">ESCANEA PARA SOLICITAR DTE</div>
            </div>
        @endif
    </div>
</body>
</html>
