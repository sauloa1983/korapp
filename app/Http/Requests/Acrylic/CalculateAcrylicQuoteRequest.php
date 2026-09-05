<?php

namespace App\Http\Requests\Acrylic;

use App\Enums\AcrylicSignType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CalculateAcrylicQuoteRequest extends FormRequest
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
        $signType = AcrylicSignType::tryFrom((string) $this->input('sign_type', AcrylicSignType::WithBase->value))
            ?? AcrylicSignType::WithBase;

        $lettersOnly = $signType === AcrylicSignType::LettersOnly;
        $contentMode = (string) $this->input('content_mode', 'letters');

        if (in_array($contentMode, ['letters', 'logo', 'both'], true)) {
            $hasLogo = in_array($contentMode, ['logo', 'both'], true);
            $hasLetters = in_array($contentMode, ['letters', 'both'], true);
        } else {
            $hasLogo = (bool) $this->boolean('has_logo');
            $hasLetters = ! $hasLogo;
        }

        return [
            'sign_type' => ['required', 'string', Rule::enum(AcrylicSignType::class)],
            'content_mode' => ['nullable', 'string', Rule::in(['letters', 'logo', 'both'])],
            'width_cm' => [
                Rule::requiredIf(fn (): bool => ! $lettersOnly),
                'nullable',
                'numeric',
                'gt:0',
                'max:10000',
            ],
            'height_cm' => [
                Rule::requiredIf(fn (): bool => ! $lettersOnly),
                'nullable',
                'numeric',
                'gt:0',
                'max:10000',
            ],
            'letters_width_cm' => [
                Rule::requiredIf(fn (): bool => $lettersOnly && $hasLetters),
                'nullable',
                'numeric',
                'gt:0',
                'max:10000',
            ],
            'letters_height_cm' => [
                Rule::requiredIf(fn (): bool => $lettersOnly && $hasLetters),
                'nullable',
                'numeric',
                'gt:0',
                'max:10000',
            ],
            'logo_width_cm' => [
                Rule::requiredIf(fn (): bool => $lettersOnly && $hasLogo),
                'nullable',
                'numeric',
                'gt:0',
                'max:10000',
            ],
            'logo_height_cm' => [
                Rule::requiredIf(fn (): bool => $lettersOnly && $hasLogo),
                'nullable',
                'numeric',
                'gt:0',
                'max:10000',
            ],
            'quantity' => ['required', 'integer', 'min:1', 'max:9999'],
            'material_id' => [
                Rule::requiredIf(fn (): bool => $signType->requiresBase() || $lettersOnly),
                'nullable',
                'integer',
                Rule::exists('acrylic_materials', 'id')->where('is_active', true),
            ],
            'lettering_option_id' => [
                Rule::requiredIf(fn (): bool => $hasLetters),
                'nullable',
                'integer',
                Rule::exists('acrylic_lettering_options', 'id')->where('is_active', true),
            ],
            'letter_count' => ['nullable', 'integer', 'min:0', 'max:999'],
            'has_logo' => ['nullable', 'boolean'],
            'logo_coverage_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lettering_coverage_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'cut_complexity' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'lighting_option_id' => ['required', 'integer', Rule::exists('acrylic_lighting_options', 'id')->where('is_active', true)],
            'finish_option_ids' => ['nullable', 'array'],
            'finish_option_ids.*' => ['integer', Rule::exists('acrylic_finish_options', 'id')->where('is_active', true)],
            'spacer_count' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'margin_percent' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'lead_id' => ['nullable', 'integer', 'exists:leads,id'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'valid_until' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'sign_type' => 'tipo de aviso',
            'content_mode' => 'contenido del aviso',
            'width_cm' => 'ancho (cm)',
            'height_cm' => 'alto (cm)',
            'letters_width_cm' => 'ancho por letra (cm)',
            'letters_height_cm' => 'alto por letra (cm)',
            'logo_width_cm' => 'ancho logo (cm)',
            'logo_height_cm' => 'alto logo (cm)',
            'quantity' => 'cantidad',
            'material_id' => 'material acrílico',
            'lettering_option_id' => 'tipo de letras / logo',
            'letter_count' => 'cantidad de letras',
            'has_logo' => 'incluye logo',
            'logo_coverage_percent' => '% cobertura del logo',
            'lettering_coverage_percent' => '% de cobertura letras',
            'cut_complexity' => 'complejidad de corte',
            'lighting_option_id' => 'iluminación',
            'finish_option_ids' => 'accesorios',
            'spacer_count' => 'cantidad de distanciadores',
            'margin_percent' => 'margen comercial',
            'customer_id' => 'cliente',
            'lead_id' => 'prospecto',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'width_cm.gt' => 'El ancho debe ser mayor a cero.',
            'height_cm.gt' => 'El alto debe ser mayor a cero.',
            'quantity.min' => 'La cantidad debe ser al menos 1.',
            'margin_percent.min' => 'El margen no puede ser negativo.',
            'lettering_coverage_percent.max' => 'La cobertura no puede superar el 100%.',
            'material_id.required' => 'Selecciona el material acrílico.',
        ];
    }
}
