<?php

namespace App\Http\Controllers;

use App\Filament\Imports\CustomerImporter;
use App\Models\Customer;
use Filament\Actions\Imports\ImportColumn;
use Illuminate\Support\Facades\Gate;
use League\Csv\Bom;
use League\Csv\Writer;
use SplTempFileObject;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerImportTemplateController extends Controller
{
    /** Plantilla CSV lista para Excel (UTF-8 con BOM). */
    public function __invoke(): StreamedResponse
    {
        Gate::authorize('create', Customer::class);

        $columns = CustomerImporter::getColumns();

        $csv = Writer::createFromFileObject(new SplTempFileObject);
        $csv->setDelimiter(',');

        $csv->insertOne(array_map(
            fn (ImportColumn $column): string => $column->getExampleHeader(),
            $columns,
        ));

        $columnExamples = array_map(
            fn (ImportColumn $column): array => $column->getExamples(),
            $columns,
        );

        $exampleRowsCount = array_reduce(
            $columnExamples,
            fn (int $count, array $exampleData): int => max($count, count($exampleData)),
            initial: 0,
        );

        $exampleRows = [];

        foreach ($columnExamples as $exampleData) {
            for ($i = 0; $i < $exampleRowsCount; $i++) {
                $exampleRows[$i][] = $exampleData[$i] ?? '';
            }
        }

        $csv->insertAll($exampleRows);

        return response()->streamDownload(function () use ($csv): void {
            $csv->setOutputBOM(Bom::Utf8);
            echo $csv->toString();
        }, 'formato-importacion-clientes.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
