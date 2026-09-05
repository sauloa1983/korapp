<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Comprobante · {{ $sale->code }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 1.5rem; font-family: -apple-system, 'Segoe UI', Roboto, Arial, sans-serif; color: #111; }
        .toolbar { margin-bottom: 1.25rem; }
        .toolbar button {
            padding: .6rem 1.2rem; font-size: 1rem; font-weight: 700; border: 0;
            border-radius: .5rem; background: #f59e0b; color: #111; cursor: pointer;
        }
        .sheet { max-width: 720px; margin: 0 auto; }
        header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #111; padding-bottom: .75rem; }
        header h1 { font-size: 1.4rem; margin: 0; }
        header .brand { color: #666; font-size: .85rem; }
        header .logo { max-height: 64px; max-width: 220px; object-fit: contain; display: block; margin-bottom: .35rem; }
        .doc { text-align: right; }
        .doc .code { font-size: 1.1rem; font-weight: 800; }
        .doc .status { display: inline-block; margin-top: .25rem; padding: .1rem .5rem; border-radius: .4rem; font-size: .7rem; font-weight: 700; text-transform: uppercase; }
        .status.confirmada { background: #dcfce7; color: #166534; }
        .status.borrador { background: #f3f4f6; color: #374151; }
        .status.anulada { background: #fee2e2; color: #991b1b; }
        .meta { display: flex; justify-content: space-between; margin: 1rem 0; font-size: .9rem; }
        .meta .box h3 { margin: 0 0 .25rem; font-size: .7rem; text-transform: uppercase; color: #666; letter-spacing: .05em; }
        table { width: 100%; border-collapse: collapse; margin-top: .5rem; }
        th, td { padding: .5rem .4rem; text-align: left; font-size: .9rem; }
        thead th { border-bottom: 2px solid #111; text-transform: uppercase; font-size: .7rem; letter-spacing: .05em; }
        tbody td { border-bottom: 1px solid #eee; }
        td.num, th.num { text-align: right; }
        tfoot td { font-weight: 800; font-size: 1.05rem; padding-top: .75rem; }
        .notes { margin-top: 1.25rem; font-size: .85rem; color: #444; }
        @media print {
            .toolbar { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
@php
    $company = \App\Models\CompanySetting::current();
    $logoUrl = $company->logoUrl();
@endphp
    <div class="toolbar">
        <button onclick="window.print()">Imprimir / Guardar PDF</button>
    </div>

    <div class="sheet">
        <header>
            <div>
                @if ($logoUrl)
                    <img class="logo" src="{{ $logoUrl }}" alt="{{ $company->name ?: 'Logo' }}">
                @else
                    <h1>Korapp Acrílicos</h1>
                @endif
                <div class="brand">Comprobante de venta</div>
            </div>
            <div class="doc">
                @if ($sale->invoice_number)
                    <div class="code">Factura {{ $sale->invoice_number }}</div>
                    <div class="brand">Ref. interna {{ $sale->code }}</div>
                @else
                    <div class="code">{{ $sale->code }}</div>
                @endif
                <span class="status {{ $sale->status->value }}">{{ $sale->status->getLabel() }}</span>
            </div>
        </header>

        <div class="meta">
            <div class="box">
                <h3>Cliente</h3>
                <div>{{ $sale->customer?->name ?? 'Público' }}</div>
                @if ($sale->customer?->tax_id)
                    <div>{{ $sale->customer->tax_id }}</div>
                @endif
            </div>
            <div class="box" style="text-align:right;">
                <h3>Detalle</h3>
                <div>Fecha: {{ optional($sale->sold_at)->format('d/m/Y') }}</div>
                <div>Vendedor: {{ $sale->user?->name ?? '—' }}</div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Descripción</th>
                    <th class="num">Cant.</th>
                    <th class="num">P. unit.</th>
                    <th class="num">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sale->items as $line)
                    <tr>
                        <td style="max-width:140px;word-break:break-word;">{{ $line->displaySku() }}</td>
                        <td style="word-break:break-word;">{{ $line->displayDescription() }}</td>
                        <td class="num">{{ rtrim(rtrim(number_format((float) $line->quantity, 2), '0'), '.') }}</td>
                        <td class="num">{{ money($line->unit_price) }}</td>
                        <td class="num">{{ money($line->subtotal) }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" class="num" style="font-weight:600;">Subtotal</td>
                    <td class="num" style="font-weight:600;">{{ money($sale->subtotal ?? $sale->total) }}</td>
                </tr>
                @if ((float) ($sale->iva_amount ?? 0) > 0)
                    <tr>
                        <td colspan="4" class="num" style="font-weight:600;">IVA ({{ number_format((float) $sale->iva_rate, 2, ',', '.') }}%)</td>
                        <td class="num" style="font-weight:600;">{{ money($sale->iva_amount) }}</td>
                    </tr>
                @endif
                <tr>
                    <td colspan="4" class="num">Total</td>
                    <td class="num">{{ money($sale->total) }}</td>
                </tr>
            </tfoot>
        </table>

        @if ($sale->notes)
            <div class="notes"><strong>Notas:</strong> {{ $sale->notes }}</div>
        @endif

        @if ($sale->einvoice_uuid)
            <div class="notes" style="margin-top:.75rem; font-size:.75rem; color:#666;">
                CUFE: {{ $sale->einvoice_uuid }}
            </div>
        @endif

        @php($resolution = config('invoicing.resolution'))
        @if ($sale->invoice_number && ($resolution['number'] ?? null))
            <div class="notes" style="margin-top:.75rem; font-size:.75rem; color:#666;">
                Resolución {{ $resolution['number'] }}
                @if (($resolution['from'] ?? null) && ($resolution['to'] ?? null))
                    · Rango autorizado {{ $resolution['from'] }} — {{ $resolution['to'] }}
                @endif
                @if ($resolution['valid_until'] ?? null)
                    · Vigente hasta {{ $resolution['valid_until'] }}
                @endif
            </div>
        @endif
    </div>
</body>
</html>
