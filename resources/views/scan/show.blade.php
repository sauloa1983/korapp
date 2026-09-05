@php
    use App\Enums\ProductionLogStatus;

    $statusColors = [
        'en_espera' => '#6b7280',
        'procesando' => '#f59e0b',
        'terminado' => '#16a34a',
    ];
    $color = $statusColors[$log->status->value] ?? '#6b7280';

    $resultMessages = [
        'iniciada' => 'Etapa iniciada. El cronómetro está en marcha.',
        'finalizada' => 'Etapa finalizada. Tiempo registrado.',
        'sin_cambios' => 'La etapa ya estaba finalizada.',
    ];
    $result = session('result');
    $operator = session('operator');
    $error = session('error');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>Escaneo · {{ $log->process->name }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .card {
            background: #1e293b;
            border-radius: 1rem;
            width: 100%;
            max-width: 420px;
            padding: 1.75rem;
            box-shadow: 0 20px 45px rgba(0,0,0,.45);
        }
        .code { font-size: .8rem; letter-spacing: .05em; color: #94a3b8; text-transform: uppercase; }
        h1 { font-size: 1.6rem; margin: .35rem 0 1rem; }
        .badge {
            display: inline-block; padding: .35rem .85rem; border-radius: 999px;
            font-size: .8rem; font-weight: 700; color: #fff; background: {{ $color }};
        }
        .row { display: flex; justify-content: space-between; padding: .6rem 0; border-bottom: 1px solid #334155; }
        .row span:first-child { color: #94a3b8; }
        .row span:last-child { font-weight: 600; text-align: right; }
        .alert { margin: 1rem 0; padding: .85rem 1rem; border-radius: .65rem; background: #064e3b; color: #d1fae5; font-weight: 600; }
        .alert-error { background: #7f1d1d; color: #fee2e2; }
        label { display:block; font-size:.8rem; color:#94a3b8; margin: 1rem 0 .35rem; }
        input[type=text] {
            width: 100%; padding: .85rem 1rem; border-radius: .65rem; border: 1px solid #334155;
            background: #0f172a; color: #e2e8f0; font-size: 1.4rem; letter-spacing: .4em; text-align: center;
        }
        button {
            width: 100%; border: 0; border-radius: .75rem; padding: 1rem; margin-top: 1.25rem;
            font-size: 1.05rem; font-weight: 800; color: #0f172a; cursor: pointer;
        }
        .btn-start { background: #22c55e; }
        .btn-finish { background: #f59e0b; }
        .done { margin-top: 1.25rem; text-align: center; color: #86efac; font-weight: 700; }
        .muted { color: #64748b; font-size: .8rem; text-align: center; margin-top: 1rem; }
    </style>
</head>
<body>
    <div class="card">
        <div class="code">{{ $log->productionOrder->code }}</div>
        <h1>{{ $log->process->name }}</h1>
        <span class="badge">{{ $log->status->getLabel() }}</span>

        @if ($result)
            <div class="alert">
                {{ $resultMessages[$result] ?? 'Escaneo registrado.' }}
                @if ($operator)<br><small>Operario: {{ $operator }}</small>@endif
            </div>
        @endif

        @if ($error)
            <div class="alert alert-error">{{ $error }}</div>
        @endif

        <div style="margin-top:1rem;">
            <div class="row"><span>Producto</span><span>{{ $log->productionOrder->item->name }}</span></div>
            <div class="row"><span>Etapa</span><span>#{{ $log->sequence }}</span></div>
            <div class="row"><span>Inicio</span><span>{{ $log->started_at?->format('d/m/Y H:i') ?? '—' }}</span></div>
            <div class="row"><span>Fin</span><span>{{ $log->ended_at?->format('d/m/Y H:i') ?? '—' }}</span></div>
            <div class="row"><span>Duración</span><span>{{ $log->duration_for_humans ?? '—' }}</span></div>
            <div class="row"><span>Operario</span><span>{{ $log->user?->name ?? '—' }}</span></div>
        </div>

        @if ($log->status !== ProductionLogStatus::Terminado)
            <form method="POST" action="{{ route('scan.update', $log->qr_token) }}">
                @csrf
                <label for="pin">PIN del operario (opcional)</label>
                <input type="text" id="pin" name="pin" inputmode="numeric" autocomplete="off" placeholder="••••">
                @if ($log->status === ProductionLogStatus::EnEspera)
                    <button type="submit" class="btn-start">▶ Iniciar etapa</button>
                @else
                    <button type="submit" class="btn-finish">■ Finalizar etapa</button>
                @endif
            </form>
        @else
            <div class="done">✓ Etapa completada</div>
        @endif

        <p class="muted">Escanea de nuevo el mismo código para registrar el siguiente evento.</p>
    </div>
</body>
</html>
