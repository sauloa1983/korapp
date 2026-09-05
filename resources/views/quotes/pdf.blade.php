<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Cotización · {{ $quote->code }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 1rem;
            color: #111;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
        }
        .toolbar { margin-bottom: 1rem; }
        .toolbar button {
            padding: .55rem 1.1rem; font-size: .95rem; font-weight: 700; border: 0;
            border-radius: .4rem; background: #c62828; color: #fff; cursor: pointer;
        }
        .sheet { max-width: 1050px; margin: 0 auto; border: 2px solid #111; }
        table.grid { width: 100%; border-collapse: collapse; }
        table.grid th, table.grid td {
            border: 1px solid #111;
            padding: .35rem .4rem;
            vertical-align: top;
        }
        .title-cell {
            font-size: 1.35rem;
            font-weight: 800;
            letter-spacing: .02em;
            text-align: center;
            background: #f3f4f6;
        }
        .logo-cell { text-align: right; width: 28%; background: #fff; }
        .logo-mark {
            display: inline-block;
            text-align: center;
            line-height: 1.1;
        }
        .logo-mark img {
            display: block;
            max-height: 72px;
            max-width: 220px;
            margin: 0 auto;
            object-fit: contain;
        }
        .logo-mark .name { font-size: .95rem; font-weight: 800; }
        .label {
            font-size: .65rem;
            font-weight: 800;
            text-transform: uppercase;
            color: #222;
            white-space: nowrap;
            background: #f3f4f6;
            width: 1%;
        }
        .value { font-weight: 600; }
        .quote-no { color: #c62828; font-weight: 900; font-size: 1.05rem; }
        .project-row {
            font-weight: 800;
            text-transform: uppercase;
            background: #fafafa;
            letter-spacing: .02em;
        }
        .items thead th {
            background: #e5e7eb;
            font-size: .65rem;
            text-transform: uppercase;
            text-align: center;
            font-weight: 800;
        }
        .items td { font-size: .78rem; }
        .num { text-align: right; white-space: nowrap; }
        .center { text-align: center; }
        .acabados { min-width: 220px; line-height: 1.25; }
        .footer-wrap td { border: 1px solid #111; vertical-align: top; }
        .obs-title {
            font-size: .7rem;
            font-weight: 800;
            text-transform: uppercase;
            margin: 0 0 .35rem;
        }
        .obs-body { white-space: pre-line; line-height: 1.35; }
        .obs-body .highlight { color: #c62828; font-weight: 700; }
        .totals {
            width: 100%;
            border-collapse: collapse;
            background: #fde8e8;
        }
        .totals td {
            border: 1px solid #111;
            padding: .4rem .5rem;
            font-weight: 700;
        }
        .totals .total-final {
            background: #f8c7c7;
            font-size: 1rem;
            font-weight: 900;
        }
        .company-foot {
            text-align: right;
            font-size: .7rem;
            color: #444;
            line-height: 1.35;
        }
        @media print {
            .toolbar { display: none; }
            body { padding: 0; }
            .sheet { border-width: 1.5px; }
        }
    </style>
</head>
<body>
@php
    $company = \App\Models\CompanySetting::current();
    $empresa = $quote->customer?->company_name
        ?: $quote->customer?->name
        ?: $quote->lead?->company
        ?: $quote->lead?->name
        ?: '—';
    $contacto = $quote->contactDisplay();
    $nit = $quote->customer?->tax_id ?: '—';
    $mail = $quote->customer?->email ?? $quote->lead?->email ?? '—';
    $telefono = $quote->customer?->phone ?? $quote->lead?->phone ?? '—';
    $clientCode = $quote->clientCodeDisplay();
    $totalPayable = $quote->totalPayable();
    $hasWithholding = (float) ($quote->withholding_amount ?? 0) > 0
        || (float) ($quote->withholding_rate ?? 0) > 0;
    $fmt = static function ($n, int $decimals = 0): string {
        if ($n === null || $n === '') {
            return '—';
        }

        return number_format((float) $n, $decimals, ',', '.');
    };
@endphp

<div class="toolbar">
    <button onclick="window.print()">Imprimir / Guardar PDF</button>
</div>

<div class="sheet">
    <table class="grid">
        <tr>
            <td class="title-cell" colspan="6">Cotización Proyectos Especiales</td>
            <td class="logo-cell" colspan="3">
                <div class="logo-mark">
                    @if ($company->logoUrl())
                        <img src="{{ $company->logoUrl() }}" alt="{{ $company->name ?: 'Logo' }}">
                    @else
                        <div class="name">{{ $company->name ?: 'Acrílicos Serna' }}</div>
                    @endif
                </div>
            </td>
        </tr>
        <tr>
            <td class="label">Cod. Cliente</td>
            <td class="value" colspan="2">{{ $clientCode !== '' ? $clientCode : '' }}</td>
            <td class="label">N° Cotización</td>
            <td class="value quote-no" colspan="2">{{ $quote->code }}</td>
            <td class="label">F. Cotización</td>
            <td class="value" colspan="2">{{ optional($quote->created_at)->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td class="label">Empresa</td>
            <td class="value" colspan="5">{{ $empresa }}</td>
            <td class="label">F. de Entrega</td>
            <td class="value" colspan="2">{{ $quote->deliveryDisplay() }}</td>
        </tr>
        <tr>
            <td class="label">NIT</td>
            <td class="value" colspan="5">{{ $nit }}</td>
            <td class="label">Vigencia</td>
            <td class="value" colspan="2">{{ $quote->validityDisplay() }}</td>
        </tr>
        <tr>
            <td class="label">Contacto</td>
            <td class="value" colspan="5">{{ $contacto }}</td>
            <td class="label">Forma de Pago</td>
            <td class="value quote-no" colspan="2">{{ $quote->paymentFormDisplay() }}</td>
        </tr>
        <tr>
            <td class="label">Mail</td>
            <td class="value" colspan="2">{{ $mail }}</td>
            <td class="label">Teléfono</td>
            <td class="value" colspan="2">{{ $telefono }}</td>
            <td class="value" colspan="3"></td>
        </tr>
        <tr>
            <td class="project-row" colspan="9">
                {{ filled($quote->project_name) ? $quote->project_name : 'PROYECTO' }}
            </td>
        </tr>
    </table>

    <table class="grid items">
        <thead>
            <tr>
                <th style="width:10%;">Pieza</th>
                <th style="width:11%;">Material</th>
                <th style="width:28%;">Acabados</th>
                <th colspan="2">Medida cm</th>
                <th style="width:8%;">CM2</th>
                <th style="width:11%;">V. Unitario</th>
                <th style="width:6%;">Cant.</th>
                <th style="width:11%;">V. Total</th>
            </tr>
            <tr>
                <th></th>
                <th></th>
                <th></th>
                <th style="width:7%;">X</th>
                <th style="width:7%;">Y</th>
                <th></th>
                <th></th>
                <th></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @php
                $pieces = $quote->relationLoaded('pieces') && $quote->pieces->isNotEmpty()
                    ? $quote->pieces
                    : collect([
                        (object) [
                            'name' => $quote->project_name ?: 'PIEZA',
                            'items' => $quote->items,
                        ],
                    ]);
                $hasMultiplePieces = $quote->relationLoaded('pieces') && $quote->pieces->count() > 1;
            @endphp

            @forelse ($pieces as $piece)
                @if ($hasMultiplePieces)
                    <tr>
                        <td class="project-row" colspan="9">{{ $piece->name }}</td>
                    </tr>
                @endif

                @foreach ($piece->items as $line)
                    @php
                        $calc = $line->meta['calculation'] ?? [];
                        $pieza = $piece->name
                            ?? ($calc['piece_name'] ?? null)
                            ?? ($calc['pieza'] ?? null)
                            ?? ($line->description ?: ($line->item?->name ?? '—'));
                        $material = $calc['material'] ?? '—';
                        $acabados = $calc['acabados'] ?? '—';
                        $x = $calc['width_cm'] ?? null;
                        $y = $calc['height_cm'] ?? null;
                        $cm2 = $calc['area_cm2'] ?? null;
                    @endphp
                    <tr>
                        <td>{{ $pieza }}</td>
                        <td>{{ $material }}</td>
                        <td class="acabados">{{ $acabados }}</td>
                        <td class="num">{{ $x !== null ? $fmt($x, 2) : '—' }}</td>
                        <td class="num">{{ $y !== null ? $fmt($y, 2) : '—' }}</td>
                        <td class="num">{{ $cm2 !== null ? $fmt($cm2, 0) : '—' }}</td>
                        <td class="num">{{ money($line->unit_price) }}</td>
                        <td class="center">{{ $fmt($line->quantity, 0) }}</td>
                        <td class="num">{{ money($line->line_total) }}</td>
                    </tr>
                @endforeach
            @empty
                <tr>
                    <td colspan="9" class="center">Sin líneas</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="grid footer-wrap">
        <tr>
            <td style="width:62%;">
                <p class="obs-title">Observaciones</p>
                <div class="obs-body">
                    @if ($quote->notes)
                        {{ $quote->notes }}
                    @endif

                    @if ($quote->terms)

<span class="highlight">{{ $quote->terms }}</span>
                    @else

<span class="highlight">FORMA DE PAGO: {{ number_format((float) ($quote->advance_percent ?? 50), 0) }}% DE ANTICIPO Y SALDO CONTRA ENTREGA.</span>
                    @endif

                    @if (filled($company->bank_name) || filled($company->bank_account_number))

CUENTA {{ mb_strtoupper((string) ($company->bank_account_type ?: 'CORRIENTE'), 'UTF-8') }} {{ mb_strtoupper((string) $company->bank_name, 'UTF-8') }}{{ filled($company->bank_account_number) ? ' '.$company->bank_account_number : '' }}{{ filled($company->bank_account_holder) ? ' A NOMBRE DE '.mb_strtoupper((string) $company->bank_account_holder, 'UTF-8') : '' }}
                    @endif
                </div>
            </td>
            <td style="width:38%; padding:0;">
                <table class="totals">
                    <tr>
                        <td>SUBTOTAL</td>
                        <td class="num">{{ money($quote->subtotal ?? $quote->total) }}</td>
                    </tr>
                    <tr>
                        <td>IVA{{ (float) ($quote->iva_rate ?? 0) > 0 ? ' ('.number_format((float) $quote->iva_rate, 0).'%)' : '' }}</td>
                        <td class="num">{{ money($quote->iva_amount ?? 0) }}</td>
                    </tr>
                    <tr>
                        <td>RETEFUENTE{{ $hasWithholding ? ' ('.number_format((float) $quote->withholding_rate, 2, ',', '.').'%)' : '' }}</td>
                        <td class="num">{{ $hasWithholding ? '- '.money($quote->withholding_amount) : '-' }}</td>
                    </tr>
                    <tr>
                        <td class="total-final">TOTAL A PAGAR</td>
                        <td class="num total-final">{{ money($totalPayable) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <div class="company-foot">
                    @if ($company->phone) {{ $company->phone }} · @endif
                    @if ($company->address) {{ $company->address }} · @endif
                    @if ($company->website) {{ $company->website }} @endif
                </div>
            </td>
        </tr>
    </table>
</div>
</body>
</html>
