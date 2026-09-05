<?php

namespace App\Filament\Resources\ProductionOrders\Support;

use App\Models\ProductionOrder;
use App\Support\ProductionOrderCodes;
use Illuminate\Support\HtmlString;

class OrderCodeModal
{
    public static function content(ProductionOrder $order): HtmlString
    {
        $qr = ProductionOrderCodes::qrSvg($order->code, 6);
        $barcode = ProductionOrderCodes::barcodeSvg($order->code);

        return new HtmlString(
            '<div style="display:flex;flex-direction:column;align-items:center;gap:1rem;padding:1rem 0.5rem;">'
            . '<div style="font-size:1.25rem;font-weight:700;letter-spacing:.02em;">'.e($order->code).'</div>'
            . '<div style="width:220px;">'.$qr.'</div>'
            . '<div style="width:100%;max-width:320px;overflow:auto;text-align:center;">'.$barcode.'</div>'
            . '<p style="margin:0;color:#64748b;font-size:.8125rem;text-align:center;">Escanea el QR o el código de barras para identificar esta orden.</p>'
            . '</div>'
        );
    }
}
