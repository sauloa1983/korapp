<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Lead;
use Illuminate\Console\Command;

class UppercaseCustomersCommand extends Command
{
    protected $signature = 'korapp:uppercase-customers
                            {--leads : También normaliza nombres de prospectos}';

    protected $description = 'Pasa a MAYÚSCULAS los textos de clientes ya guardados (excepto email)';

    public function handle(): int
    {
        $customers = $this->normalizeCustomers();
        $this->info("Clientes: revisados={$customers['scanned']}, actualizados={$customers['updated']}");

        if ($this->option('leads')) {
            $leads = $this->normalizeLeads();
            $this->info("Prospectos: revisados={$leads['scanned']}, actualizados={$leads['updated']}");
        }

        return self::SUCCESS;
    }

    /** @return array{scanned: int, updated: int} */
    protected function normalizeCustomers(): array
    {
        $scanned = 0;
        $updated = 0;

        Customer::query()
            ->withTrashed()
            ->orderBy('id')
            ->chunkById(100, function ($rows) use (&$scanned, &$updated): void {
                foreach ($rows as $customer) {
                    $scanned++;
                    if ($this->uppercaseModel($customer, Customer::UPPERCASE_FIELDS)) {
                        $updated++;
                    }
                }
            });

        return compact('scanned', 'updated');
    }

    /** @return array{scanned: int, updated: int} */
    protected function normalizeLeads(): array
    {
        $scanned = 0;
        $updated = 0;

        Lead::query()
            ->withTrashed()
            ->orderBy('id')
            ->chunkById(100, function ($rows) use (&$scanned, &$updated): void {
                foreach ($rows as $lead) {
                    $scanned++;
                    if ($this->uppercaseModel($lead, Lead::UPPERCASE_FIELDS)) {
                        $updated++;
                    }
                }
            });

        return compact('scanned', 'updated');
    }

    /**
     * @param  list<string>  $fields
     */
    protected function uppercaseModel(Customer|Lead $model, array $fields): bool
    {
        $dirty = false;

        foreach ($fields as $field) {
            $value = $model->getAttributes()[$field] ?? null;

            if (! is_string($value) || $value === '') {
                continue;
            }

            $upper = mb_strtoupper(trim($value), 'UTF-8');

            if ($upper !== $value) {
                $model->setAttribute($field, $upper);
                $dirty = true;
            }
        }

        if ($model instanceof Customer && isset($model->getAttributes()['email']) && is_string($model->email)) {
            $email = trim($model->email);
            if ($email !== $model->email) {
                $model->email = $email;
                $dirty = true;
            }
        }

        if (! $dirty) {
            return false;
        }

        $model->save();

        return true;
    }
}
