<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Orden de producción · {{ $order->code }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 1rem; font-family: Arial, Helvetica, sans-serif; color: #111; font-size: 11px; }
        .toolbar { margin-bottom: 1rem; }
        .toolbar button {
            padding: .55rem 1.1rem; font-size: .95rem; font-weight: 700; border: 0;
            border-radius: .45rem; background: #2563eb; color: #fff; cursor: pointer;
        }
        .sheet { max-width: 900px; margin: 0 auto; border: 1px solid #222; padding: .75rem; }
        .header { display: flex; justify-content: space-between; gap: 1rem; border-bottom: 2px solid #111; padding-bottom: .6rem; margin-bottom: .6rem; }
        .brand h1 { margin: 0; font-size: 1.15rem; }
        .brand .meta { color: #444; font-size: .75rem; line-height: 1.35; margin-top: .25rem; }
        .brand--logo-only { display: flex; align-items: flex-start; }
        .doc { text-align: right; }
        .doc .title { font-size: 1rem; font-weight: 800; letter-spacing: .04em; text-transform: uppercase; }
        .doc .code { font-size: 1.25rem; font-weight: 800; color: #b91c1c; margin-top: .15rem; }
        .doc .date { margin-top: .35rem; font-size: .8rem; }
        h2.section {
            margin: .7rem 0 .35rem; font-size: .72rem; text-transform: uppercase; letter-spacing: .06em;
            background: #111; color: #fff; padding: .25rem .45rem;
        }
        .grid { display: grid; grid-template-columns: 1.4fr .8fr .8fr; gap: .35rem .6rem; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: .35rem .6rem; }
        .field { border-bottom: 1px solid #ccc; min-height: 1.35rem; padding: .1rem 0; }
        .field label { display: block; font-size: .65rem; text-transform: uppercase; color: #666; }
        .field .val { font-weight: 600; }
        .check { display: inline-flex; align-items: center; gap: .25rem; }
        .box {
            display: inline-block; width: .85rem; height: .85rem; border: 1px solid #222;
            text-align: center; line-height: .85rem; font-size: .7rem; font-weight: 800;
        }
        .box.on { background: #111; color: #fff; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: .25rem; }
        table.lines th, table.lines td { border: 1px solid #333; padding: .3rem .35rem; }
        table.lines th { background: #f3f4f6; font-size: .65rem; text-transform: uppercase; }
        table.lines td.num { text-align: right; white-space: nowrap; }
        .totals { width: 240px; margin-left: auto; margin-top: .4rem; }
        .totals tr td { padding: .15rem .25rem; }
        .totals tr td:last-child { text-align: right; font-weight: 700; }
        .obs { border: 1px solid #333; padding: .4rem; min-height: 2.5rem; }
        .depts { display: grid; grid-template-columns: 1fr 1fr; gap: .45rem; margin-top: .5rem; }
        .dept { border: 1px solid #333; padding: .35rem; page-break-inside: avoid; }
        .dept h3 {
            margin: 0 0 .3rem; font-size: .7rem; text-transform: uppercase; letter-spacing: .04em;
            border-bottom: 1px solid #333; padding-bottom: .15rem;
        }
        .dept .dates { display: flex; gap: .75rem; font-size: .7rem; margin-bottom: .25rem; }
        .dept .tasks { display: flex; flex-wrap: wrap; gap: .2rem .55rem; margin: .2rem 0; }
        .dept .line { border-bottom: 1px dotted #999; min-height: 1rem; margin-top: .25rem; font-size: .7rem; }
        .sign { margin-top: .75rem; display: flex; justify-content: space-between; gap: 1rem; }
        .sign .slot { flex: 1; border-top: 1px solid #333; margin-top: 2rem; padding-top: .25rem; text-align: center; font-size: .7rem; }
        .disclaimer { margin-top: .5rem; font-size: .65rem; color: #555; }
        .logo { max-height: 72px; max-width: 220px; margin: 0; object-fit: contain; }
        @media print {
            .toolbar { display: none; }
            body { padding: 0; }
            .sheet { border: 0; max-width: none; }
        }
    </style>
</head>
<body>
@php
    $unit = $unitPrice ?? null;
    $lineTotal = $lineTotal ?? null;
    $qty = (float) $order->quantity;
    $docType = $customer?->document_type?->getLabel() ?? (filled($customer?->tax_id) ? 'NIT/CC' : null);
    $description = $quoteItem?->description ?: ($order->item?->name ?? '—');
@endphp

<div class="toolbar">
    <button onclick="window.print()">Imprimir / Guardar PDF</button>
</div>

<div class="sheet">
    <div class="header">
        <div class="brand {{ $company->logoUrl() ? 'brand--logo-only' : '' }}">
            @if ($company->logoUrl())
                <img class="logo" src="{{ $company->logoUrl() }}" alt="{{ $company->name ?: 'Logo' }}">
            @else
                <h1>{{ $company->name ?: 'Korapp' }}</h1>
                <div class="meta">
                    @if ($company->email)<div>{{ $company->email }}</div>@endif
                    @if ($company->address)<div>{{ $company->address }}</div>@endif
                    @if ($company->phone)<div>{{ $company->phone }}</div>@endif
                    @if ($company->tax_id)<div>NIT {{ $company->tax_id }}</div>@endif
                </div>
            @endif
        </div>
        <div class="doc">
            <div class="title">Orden de producción</div>
            <div class="code">No. {{ $order->code }}</div>
            <div class="date">Fecha: {{ optional($order->requested_at)->format('d/m/Y') ?? optional($order->created_at)->format('d/m/Y') }}</div>
        </div>
    </div>

    <h2 class="section">Datos del cliente</h2>
    <div class="grid">
        <div class="field">
            <label>Empresa</label>
            <div class="val">{{ $companyName ?? '—' }}</div>
        </div>
        <div class="field">
            <label>{{ $docType ?: 'NIT / CC' }}</label>
            <div class="val">{{ $customer?->tax_id ?: '—' }}</div>
        </div>
        <div class="field">
            <label>Teléfono</label>
            <div class="val">{{ $customer?->phone ?? $lead?->phone ?? '—' }}</div>
        </div>
        <div class="field">
            <label>Dirección</label>
            <div class="val">{{ $customer?->address ?: '—' }}</div>
        </div>
        <div class="field">
            <label>Ciudad</label>
            <div class="val">{{ $customer?->city ?: '—' }}</div>
        </div>
        <div class="field">
            <label>E-mail</label>
            <div class="val">{{ $customer?->email ?? $lead?->email ?? '—' }}</div>
        </div>
        <div class="field">
            <label>Contacto</label>
            <div class="val">{{ $contactName ?? '—' }}</div>
        </div>
        <div class="field">
            <label>Cotizó</label>
            <div class="val">{{ $quotedBy ?? '—' }}</div>
        </div>
        <div class="field">
            <label>Fecha de entrega</label>
            <div class="val">{{ optional($order->due_at)->format('d/m/Y') ?: '—' }}</div>
        </div>
    </div>

    <h2 class="section">Detalles del trabajo</h2>
    <table class="lines">
        <thead>
            <tr>
                <th>Descripción</th>
                <th style="width:70px;">Cantidad</th>
                <th style="width:100px;">Valor unitario</th>
                <th style="width:100px;">Valor total</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    {{ $description }}
                    @if ($order->item?->sku)
                        <div style="color:#666;font-size:.7rem;">SKU {{ $order->item->sku }}</div>
                    @endif
                    @if ($quote)
                        <div style="color:#666;font-size:.7rem;">Cotización {{ $quote->code }}</div>
                    @endif
                    @if ($sale)
                        <div style="color:#666;font-size:.7rem;">Venta {{ $sale->code }}</div>
                    @endif
                </td>
                <td class="num">{{ rtrim(rtrim(number_format($qty, 3), '0'), '.') }}</td>
                <td class="num">{{ filled($unit) ? money($unit) : '—' }}</td>
                <td class="num">{{ filled($lineTotal) ? money($lineTotal) : '—' }}</td>
            </tr>
        </tbody>
    </table>

    @if (filled($docTotal))
        <table class="totals">
            <tr><td>Valor neto</td><td>{{ money($docSubtotal) }}</td></tr>
            <tr><td>IVA{{ filled($docIvaRate) ? ' ('.$docIvaRate.'%)' : '' }}</td><td>{{ money($docIva) }}</td></tr>
            <tr><td>Valor total</td><td>{{ money($docTotal) }}</td></tr>
        </table>
    @endif

    <h2 class="section">Observaciones</h2>
    <div class="obs">
        <div><strong>Ruta del archivo:</strong> {{ $order->file_path ?: '—' }}</div>
        <div style="margin-top:.25rem;">
            <span class="check">
                <span class="box {{ $order->has_plans ? 'on' : '' }}">{{ $order->has_plans ? '✓' : '' }}</span>
                Planos adjuntos
            </span>
            @if ($order->plans_attachment)
                <span style="margin-left:.5rem;color:#444;">(archivo cargado)</span>
            @endif
        </div>
        <div style="margin-top:.35rem;"><strong>Notas:</strong> {{ $order->notes ?: '—' }}</div>
    </div>

    <h2 class="section">Procesos de producción</h2>
    <div class="depts">
        @foreach ($departmentBlocks as $block)
            @php
                $logsInDept = $block['processes']->pluck('log')->filter();
                $fi = $logsInDept->min('started_at');
                $fs = $logsInDept->max('ended_at');
                $responsible = $logsInDept->pluck('user.name')->filter()->unique()->implode(', ');
                $deptNotes = $logsInDept->pluck('notes')->filter()->unique()->implode(' · ');
            @endphp
            <div class="dept">
                <h3>{{ $block['department']->getLabel() }}</h3>
                <div class="dates">
                    <span>Fi: {{ $fi ? \Illuminate\Support\Carbon::parse($fi)->format('d/m/Y H:i') : '________' }}</span>
                    <span>Fs: {{ $fs ? \Illuminate\Support\Carbon::parse($fs)->format('d/m/Y H:i') : '________' }}</span>
                </div>
                <div class="tasks">
                    @foreach ($block['processes'] as $row)
                        <span class="check">
                            <span class="box {{ $row['selected'] ? 'on' : '' }}">{{ $row['selected'] ? '✓' : '' }}</span>
                            {{ $row['process']->name }}{{ $row['done'] ? ' ✓' : '' }}
                        </span>
                    @endforeach
                </div>
                <div class="line"><strong>Obs:</strong> {{ $deptNotes ?: '' }}</div>
                <div class="line"><strong>Responsable:</strong> {{ $responsible ?: '' }}</div>
            </div>
        @endforeach

        @if ($ungroupedLogs->isNotEmpty())
            <div class="dept">
                <h3>Otros procesos</h3>
                <div class="tasks">
                    @foreach ($ungroupedLogs as $log)
                        <span class="check">
                            <span class="box on">✓</span>
                            {{ $log->process?->name ?? '—' }}
                            @if ($log->started_at) · Fi {{ $log->started_at->format('d/m H:i') }}@endif
                            @if ($log->ended_at) · Fs {{ $log->ended_at->format('d/m H:i') }}@endif
                        </span>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <div class="disclaimer">
        El trabajo no reclamado después de 30 días será donado o destruido sin derecho a reclamo.
    </div>

    <div class="sign">
        <div class="slot">Firma aprobado (cliente)</div>
        <div class="slot">Vo.Bo. producción</div>
    </div>
</div>
</body>
</html>
