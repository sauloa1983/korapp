<?php

namespace App\Http\Controllers;

use App\Enums\ProcessDepartment;
use App\Enums\ProductionLogStatus;
use App\Models\CompanySetting;
use App\Models\Process;
use App\Models\ProductionOrder;
use Illuminate\View\View;

class ProductionOrderPdfController extends Controller
{
    /** Orden de producción imprimible (formato hoja de taller). */
    public function show(ProductionOrder $order): View
    {
        $order->load([
            'item',
            'user',
            'quotedBy',
            'quote.customer',
            'quote.lead',
            'quote.user',
            'quoteItem',
            'sale.customer',
            'sale.user',
            'saleItem',
            'logs.process',
            'logs.user',
        ]);

        $company = CompanySetting::current();
        $quote = $order->quote ?? $order->sale?->quote;
        $sale = $order->sale;
        $customer = $quote?->customer ?? $sale?->customer;
        $lead = $quote?->lead;
        $quoteItem = $order->quoteItem;

        $logsByProcessId = $order->logs->keyBy('process_id');

        $departmentBlocks = collect(ProcessDepartment::ordered())->map(function (ProcessDepartment $department) use ($logsByProcessId) {
            $processes = Process::query()
                ->where('department', $department->value)
                ->ordered()
                ->get();

            return [
                'department' => $department,
                'processes' => $processes->map(function ($process) use ($logsByProcessId) {
                    $log = $logsByProcessId->get($process->id);

                    return [
                        'process' => $process,
                        'log' => $log,
                        'selected' => $log !== null,
                        'done' => $log?->status === ProductionLogStatus::Terminado,
                    ];
                }),
            ];
        })->filter(fn (array $block): bool => $block['processes']->isNotEmpty());

        $ungroupedLogs = $order->logs->filter(fn ($log) => blank($log->process?->department));

        $unit = $order->saleItem?->unit_price ?? $quoteItem?->unit_price;
        $lineTotal = $order->saleItem?->line_total ?? $quoteItem?->line_total;
        $docSubtotal = $sale?->subtotal ?? $quote?->subtotal;
        $docIva = $sale?->iva_amount ?? $quote?->iva_amount;
        $docIvaRate = $sale?->iva_rate ?? $quote?->iva_rate;
        $docTotal = $sale?->total ?? $quote?->total;

        $fromQuote = $quote?->contactDisplay();
        $contactName = filled($order->contact_name)
            ? (string) $order->contact_name
            : ((filled($fromQuote) && $fromQuote !== '—')
                ? $fromQuote
                : ($lead?->name ?: $customer?->personContactName() ?: '—'));

        return view('production-orders.pdf', [
            'order' => $order,
            'company' => $company,
            'customer' => $customer,
            'sale' => $sale,
            'quote' => $quote,
            'lead' => $lead,
            'quoteItem' => $quoteItem,
            'unitPrice' => $unit,
            'lineTotal' => $lineTotal,
            'docSubtotal' => $docSubtotal,
            'docIva' => $docIva,
            'docIvaRate' => $docIvaRate,
            'docTotal' => $docTotal,
            'departmentBlocks' => $departmentBlocks,
            'ungroupedLogs' => $ungroupedLogs,
            'contactName' => $contactName,
            'companyName' => $customer?->company_name
                ?: $customer?->name
                ?: $lead?->company
                ?: $lead?->name,
            'quotedBy' => $order->quotedBy?->name
                ?: $quote?->user?->name
                ?: $sale?->user?->name
                ?: $order->user?->name,
        ]);
    }
}
