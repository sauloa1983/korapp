<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Etiquetas · {{ $order->code }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 1rem; font-family: -apple-system, 'Segoe UI', Roboto, Arial, sans-serif; color: #111; }
        .toolbar { margin-bottom: 1rem; }
        .toolbar button {
            padding: .6rem 1.2rem; font-size: 1rem; font-weight: 700; border: 0;
            border-radius: .5rem; background: #f59e0b; color: #111; cursor: pointer;
        }
        h1 { font-size: 1.2rem; margin: 0 0 .25rem; }
        h2 { font-size: 1rem; margin: 1.25rem 0 .5rem; }
        .sub { color: #666; margin-bottom: 1rem; }
        .order-card {
            border: 2px solid #111; border-radius: .75rem; padding: 1rem 1.25rem;
            display: grid; grid-template-columns: 180px 1fr; gap: 1.25rem; align-items: center;
            page-break-inside: avoid; margin-bottom: 1.25rem;
        }
        .order-card .codes { display: flex; flex-direction: column; align-items: center; gap: .5rem; }
        .order-card .codes svg { max-width: 160px; height: auto; }
        .order-card .barcode svg { max-width: 100%; height: auto; }
        .order-card .meta .code { font-size: 1.5rem; font-weight: 800; letter-spacing: .02em; }
        .order-card .meta .item { font-size: 1rem; margin-top: .25rem; }
        .order-card .meta .qty { color: #555; margin-top: .15rem; }
        .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: .75rem; }
        .label {
            border: 1px dashed #999; border-radius: .5rem; padding: .75rem;
            text-align: center; page-break-inside: avoid;
        }
        .label .seq { font-size: .7rem; color: #666; text-transform: uppercase; letter-spacing: .05em; }
        .label .name { font-size: 1rem; font-weight: 800; margin: .15rem 0 .4rem; }
        .label svg { width: 150px; height: 150px; }
        .label .code { font-size: .65rem; color: #444; margin-top: .35rem; }
        @media print {
            .toolbar { display: none; }
            body { padding: 0; }
            .grid { gap: .4rem; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button onclick="window.print()">Imprimir / Guardar PDF</button>
    </div>

    <h1>Orden de producción · {{ $order->code }}</h1>
    <div class="sub">{{ $order->item->name }} — {{ (float) $order->quantity }} und.</div>

    <div class="order-card">
        <div class="codes">
            {!! $orderQrSvg !!}
            <div class="barcode">{!! $orderBarcodeSvg !!}</div>
        </div>
        <div class="meta">
            <div class="code">{{ $order->code }}</div>
            <div class="item">{{ $order->item->name }}</div>
            <div class="qty">Cantidad: {{ (float) $order->quantity }} · Estado: {{ $order->status->getLabel() }}</div>
        </div>
    </div>

    @if ($labels->isNotEmpty())
        <h2>Etiquetas de etapas (trazabilidad)</h2>
        <div class="grid">
            @foreach ($labels as $label)
                <div class="label">
                    <div class="seq">Etapa #{{ $label['log']->sequence }}</div>
                    <div class="name">{{ $label['log']->process->name }}</div>
                    {!! $label['svg'] !!}
                    <div class="code">{{ $order->code }}</div>
                </div>
            @endforeach
        </div>
    @else
        <p>Esta orden aún no tiene flujo de etapas. El código QR y de barras de la orden sí están listos para imprimir.</p>
    @endif
</body>
</html>
