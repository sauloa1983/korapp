<?php

namespace App\Http\Controllers;

use App\Models\ProductionOrder;
use App\Support\ProductionOrderCodes;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\View\View;

class LabelController extends Controller
{
    /** Hoja imprimible: código de la orden + etiquetas QR por etapa. */
    public function show(ProductionOrder $order): View
    {
        $order->load(['logs.process', 'item']);

        $options = new QROptions([
            'outputType' => QROutputInterface::MARKUP_SVG,
            'imageBase64' => false,
            'scale' => 5,
        ]);

        $qrcode = new QRCode($options);

        $labels = $order->logs->map(fn ($log): array => [
            'log' => $log,
            'svg' => $qrcode->render(route('scan.show', $log->qr_token)),
        ]);

        return view('labels.order', [
            'order' => $order,
            'labels' => $labels,
            'orderQrSvg' => ProductionOrderCodes::qrSvg($order->code, 6),
            'orderBarcodeSvg' => ProductionOrderCodes::barcodeSvg($order->code),
        ]);
    }
}
