<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Enums\CustomerDocumentType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Documentos legales';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->label('Tipo de documento')
                    ->options(CustomerDocumentType::class)
                    ->required()
                    ->native(false),
                TextInput::make('title')
                    ->label('Título / descripción')
                    ->maxLength(255)
                    ->placeholder('Ej. RUT actualizado 2026'),
                FileUpload::make('path')
                    ->label('Archivo')
                    ->required()
                    ->disk('public')
                    ->directory('customers/documents')
                    ->visibility('public')
                    ->acceptedFileTypes([
                        'application/pdf',
                        'image/jpeg',
                        'image/png',
                        'image/webp',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    ])
                    ->maxSize(10240)
                    ->downloadable()
                    ->openable()
                    ->helperText('PDF, JPG, PNG o Word. Máximo 10 MB.')
                    ->storeFileNamesIn('original_name')
                    ->columnSpanFull(),
                DatePicker::make('expires_at')
                    ->label('Vence el')
                    ->helperText('Opcional. Útil para certificados con vigencia.'),
                Textarea::make('notes')
                    ->label('Notas')
                    ->rows(2)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->sortable(),
                TextColumn::make('title')
                    ->label('Título')
                    ->placeholder(fn ($record): string => $record->original_name ?: '—')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('original_name')
                    ->label('Archivo')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('expires_at')
                    ->label('Vence')
                    ->date('d/m/Y')
                    ->placeholder('Sin vencimiento')
                    ->color(fn ($record): ?string => $record->expires_at?->isPast() ? 'danger' : null),
                TextColumn::make('created_at')
                    ->label('Cargado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Subir documento')
                    ->modalHeading('Subir documento legal'),
            ])
            ->recordActions([
                EditAction::make()->label('Editar'),
                DeleteAction::make()
                    ->label('Eliminar')
                    ->after(function ($record): void {
                        if (filled($record->path) && Storage::disk('public')->exists($record->path)) {
                            Storage::disk('public')->delete($record->path);
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Sin documentos legales')
            ->emptyStateDescription('Sube cédula, RUT, cámara de comercio u otros soportes del cliente.')
            ->emptyStateIcon('heroicon-o-document-text');
    }
}
