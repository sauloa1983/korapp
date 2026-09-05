<?php

namespace Database\Seeders;

use App\Enums\LeadStage;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Seeder;

class CrmDemoSeeder extends Seeder
{
    public function run(): void
    {
        $seller = User::query()->where('email', 'vendedor@korapp.test')->first()
            ?? User::query()->whereHas('roles', fn ($q) => $q->where('name', 'super_admin'))->first();

        $samples = [
            ['name' => 'Ana Ruiz', 'company' => 'Café Andino', 'stage' => LeadStage::New, 'value' => 2500000],
            ['name' => 'Luis Mora', 'company' => 'Distribuciones LM', 'stage' => LeadStage::Contacted, 'value' => 4800000],
            ['name' => 'Carmen Vélez', 'company' => 'Hotel Pacífico', 'stage' => LeadStage::Proposal, 'value' => 9200000],
            ['name' => 'Diego Soto', 'company' => 'Soto Retail', 'stage' => LeadStage::Negotiation, 'value' => 6100000],
            ['name' => 'Paula Ríos', 'company' => 'Ríos Market', 'stage' => LeadStage::Won, 'value' => 3500000],
            ['name' => 'Jorge Peña', 'company' => 'Peña Foods', 'stage' => LeadStage::Lost, 'value' => 1800000],
        ];

        foreach ($samples as $i => $sample) {
            Lead::query()->updateOrCreate(
                [
                    'name' => $sample['name'],
                    'company' => $sample['company'],
                ],
                [
                    'email' => strtolower(str_replace(' ', '.', $sample['name'])).'@demo.test',
                    'phone' => '300'.str_pad((string) (1000000 + $i), 7, '0', STR_PAD_LEFT),
                    'stage' => $sample['stage'],
                    'value' => $sample['value'],
                    'sort_order' => $i + 1,
                    'user_id' => $seller?->id,
                    'notes' => 'Lead demo para el pipeline de ventas.',
                ],
            );
        }
    }
}
