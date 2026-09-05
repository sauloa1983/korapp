<?php

namespace App\Http\Requests\Serna;

use App\Enums\SernaItemType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CalculateSernaQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'lead_id' => ['nullable', 'integer', 'exists:leads,id'],
            'project_name' => ['required', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'payment_form' => ['nullable', 'string', 'max:100'],
            'validity_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'valid_until' => ['nullable', 'date'],
            'delivery_date' => ['nullable', 'date'],
            'delivery_note' => ['nullable', 'string', 'max:100'],
            'advance_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'withholding_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'pieces' => ['required', 'array', 'min:1'],
            'pieces.*.name' => ['required', 'string', 'max:255'],
            'pieces.*.items' => ['required', 'array', 'min:1'],
            'pieces.*.items.*.item_type' => ['required', Rule::enum(SernaItemType::class)],
            'pieces.*.items.*.material' => ['nullable', 'string', 'max:255'],
            'pieces.*.items.*.acabados' => ['nullable', 'string', 'max:5000'],
            'pieces.*.items.*.width_cm' => ['nullable', 'numeric', 'min:0'],
            'pieces.*.items.*.height_cm' => ['nullable', 'numeric', 'min:0'],
            'pieces.*.items.*.thickness_mm' => ['nullable', 'numeric', 'min:0'],
            'pieces.*.items.*.quantity' => ['required', 'integer', 'min:1'],
            'pieces.*.items.*.process_rate_id' => ['nullable', 'integer', 'exists:serna_process_rates,id'],
            'pieces.*.items.*.sheet_price_id' => ['nullable', 'integer', 'exists:serna_sheet_prices,id'],
            'pieces.*.items.*.catalog_product_id' => ['nullable', 'integer', 'exists:serna_catalog_products,id'],
            'pieces.*.items.*.lighting_option_id' => [
                'nullable',
                'integer',
                Rule::exists('acrylic_lighting_options', 'id')->where('is_active', true),
            ],
            'pieces.*.items.*.unit_price' => ['nullable'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'customer_id' => 'cliente',
            'lead_id' => 'prospecto',
            'project_name' => 'proyecto',
            'pieces' => 'piezas',
            'pieces.*.name' => 'nombre de pieza',
            'pieces.*.items' => 'ítems de la pieza',
            'pieces.*.items.*.item_type' => 'tipo de ítem',
            'pieces.*.items.*.quantity' => 'cantidad',
            'pieces.*.items.*.width_cm' => 'medida X',
            'pieces.*.items.*.height_cm' => 'medida Y',
            'pieces.*.items.*.thickness_mm' => 'calibre',
            'pieces.*.items.*.lighting_option_id' => 'opción de iluminación',
            'withholding_rate' => 'retención',
        ];
    }
}
