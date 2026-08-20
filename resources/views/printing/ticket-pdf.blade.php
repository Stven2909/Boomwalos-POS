<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $titulo ?? 'Ticket de Impresion' }}</title>
    <style>
        @page {
            margin: 4mm 4mm 4mm 4mm;
            size: 80mm {{ $altoMm ?? 200 }}mm;
        }
        body {
            font-family: Courier, monospace;
            font-size: 11.5px;
            line-height: 1.3;
            color: #000;
            margin: 0;
            padding: 0;
            width: 100%;
            background-color: #fff;
        }
        .ticket-wrapper {
            width: 100%;
            margin: 0 auto;
        }
        .text-center {
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        .text-bold {
            font-weight: bold;
        }
        .divider {
            border-top: 1px dashed #000;
            margin: 5px 0;
        }
        .line {
            white-space: pre-wrap;
            word-wrap: break-word;
            margin: 1px 0;
        }
        .line-bold {
            font-weight: bold;
        }
        .line-center {
            text-align: center;
        }
        .line-header {
            font-size: 13px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 2px;
        }
    </style>
</head>
<body>
    <div class="ticket-wrapper">
        @foreach($lineas as $index => $linea)
            @php
                $trim = trim($linea);
                $upper = mb_strtoupper($trim);
                $isDivider = preg_match('/^[\-=_]{3,}$/', $trim) === 1;
                $isHeader = ($index <= 2 && $upper === $trim && mb_strlen($trim) > 3 && !$isDivider);
                $isCenter = str_contains($upper, 'MESA ') 
                    || $upper === 'PARA LLEVAR · MOSTRADOR' 
                    || $upper === 'PARA LLEVAR'
                    || str_starts_with($upper, 'FECHA')
                    || $upper === 'COMANDA'
                    || $upper === 'TICKET DE CLIENTE';
                $isBold = str_starts_with($upper, 'TOTAL')
                    || str_starts_with($upper, 'PAGO')
                    || str_starts_with($upper, 'RECIBIDO')
                    || str_starts_with($upper, 'CAMBIO')
                    || str_starts_with($upper, 'PEDIDO:')
                    || str_starts_with($upper, 'COMANDA')
                    || str_starts_with($upper, 'TICKET')
                    || str_starts_with($upper, 'TANDA')
                    || str_contains($upper, 'ATENDIDO POR')
                    || str_starts_with($upper, 'DOCUMENTO FISCAL');
            @endphp

            @if($isDivider)
                <div class="divider"></div>
            @elseif($trim === '')
                <div style="height: 6px;"></div>
            @elseif($isHeader)
                <div class="line-header">{{ $linea }}</div>
            @elseif($isCenter)
                <div class="line line-center {{ $isBold ? 'line-bold' : '' }}">{{ $linea }}</div>
            @elseif($isBold)
                <div class="line line-bold">{{ $linea }}</div>
            @else
                <div class="line">{{ $linea }}</div>
            @endif
        @endforeach
    </div>
</body>
</html>
