<?php

namespace App\Http\Controllers;

use App\Models\ProductionLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ScanController extends Controller
{
    /** Muestra el estado de la etapa asociada al QR escaneado. */
    public function show(string $token): View
    {
        $log = ProductionLog::query()
            ->where('qr_token', $token)
            ->with(['process', 'user', 'productionOrder.item'])
            ->firstOrFail();

        return view('scan.show', ['log' => $log]);
    }

    /** Dispara el cambio de estado (iniciar / finalizar) al confirmar el escaneo. */
    public function update(Request $request, string $token): RedirectResponse
    {
        $log = ProductionLog::query()
            ->where('qr_token', $token)
            ->firstOrFail();

        $operator = null;
        if ($pin = $request->string('pin')->trim()->value()) {
            $operator = User::query()->where('pin', $pin)->first();

            if (! $operator) {
                return redirect()
                    ->route('scan.show', $token)
                    ->with('error', 'PIN no reconocido. No se registró el evento.');
            }
        }

        $result = $log->handleScan($operator?->id);

        return redirect()
            ->route('scan.show', $token)
            ->with('result', $result)
            ->with('operator', $operator?->name);
    }
}
