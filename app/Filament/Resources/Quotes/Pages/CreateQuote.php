<?php

namespace App\Filament\Resources\Quotes\Pages;

use App\Filament\Resources\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\Quotes\QuoteResource;
use App\Models\Customer;
use Filament\Resources\Pages\CreateRecord;

class CreateQuote extends CreateRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = QuoteResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if ((! isset($data['withholding_rate']) || (float) $data['withholding_rate'] <= 0)
            && filled($data['customer_id'] ?? null)) {
            $customer = Customer::query()->find($data['customer_id']);
            $data['withholding_rate'] = $customer?->withholdingRate() ?? 0;
        }

        $data['advance_percent'] ??= 50;

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->getRecord()->recalculateTotal();
    }
}
