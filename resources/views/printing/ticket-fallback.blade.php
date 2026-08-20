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
            background-color: #f1f5f9;
            font-family: 'Courier New', Courier, monospace;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
            min-height: 100vh;
        }
        .actions-bar {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }
        .btn {
            background: #2563eb;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-family: sans-serif;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        }
        .btn:hover {
            background: #1d4ed8;
        }
        .btn-secondary {
            background: #64748b;
        }
        .btn-secondary:hover {
            background: #475569;
        }
        .ticket-box {
            background: #fff;
            width: 320px;
            padding: 24px 18px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            border-radius: 4px;
            border-top: 4px solid #f59e0b;
        }
        pre {
            font-family: 'Courier New', Courier, monospace;
            font-size: 13px;
            line-height: 1.4;
            white-space: pre-wrap;
            word-break: break-word;
            color: #0f172a;
        }
        @media print {
            body {
                background: none;
                padding: 0;
            }
            .actions-bar {
                display: none;
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

    <div class="ticket-box">
        <pre>{{ $contenido ?? '' }}</pre>
    </div>
</body>
</html>
