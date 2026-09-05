<?php

namespace App\Jobs;

use App\Enums\EInvoiceStatus;
use App\Models\Sale;
use App\Services\EInvoice\EInvoiceProvider;
use App\Services\EInvoice\EInvoiceResult;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SubmitElectronicInvoice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public Sale $sale)
    {
    }

    public function handle(EInvoiceProvider $provider): void
    {
        // Marca como enviada antes de intentar la emisión.
        $this->sale->forceFill(['einvoice_status' => EInvoiceStatus::Enviada])->save();

        try {
            $result = $provider->submit($this->sale);
        } catch (Throwable $e) {
            $result = EInvoiceResult::rejected($e->getMessage());
        }

        $this->sale->forceFill([
            'einvoice_status' => $result->status,
            'einvoice_uuid' => $result->uuid,
            'einvoice_response' => array_merge($result->raw, ['message' => $result->message]),
            'einvoiced_at' => $result->status === EInvoiceStatus::Aceptada ? now() : $this->sale->einvoiced_at,
        ])->save();
    }
}
