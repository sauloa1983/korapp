<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum LeadStage: string implements HasLabel, HasColor
{
    case New = 'new';
    case Contacted = 'contacted';
    case Proposal = 'proposal';
    case Negotiation = 'negotiation';
    case Won = 'won';
    case Lost = 'lost';

    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'Nuevo',
            self::Contacted => 'Contactado',
            self::Proposal => 'Propuesta',
            self::Negotiation => 'Negociación',
            self::Won => 'Ganado',
            self::Lost => 'Perdido',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'info',
            self::Contacted => 'gray',
            self::Proposal => 'warning',
            self::Negotiation => 'primary',
            self::Won => 'success',
            self::Lost => 'danger',
        };
    }

    public function isClosed(): bool
    {
        return $this === self::Won || $this === self::Lost;
    }

    public function isOpen(): bool
    {
        return ! $this->isClosed();
    }

    /** Se puede marcar Ganado desde estas etapas (evita convertir demasiado pronto). */
    public function canMarkWon(): bool
    {
        return in_array($this, [self::Proposal, self::Negotiation], true);
    }

    /** Se puede archivar como perdido en cualquier etapa abierta. */
    public function canMarkLost(): bool
    {
        return $this->isOpen();
    }

    /** @return list<self> */
    public static function openCases(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $stage): bool => $stage->isOpen(),
        ));
    }

    /** @return array<string, string> */
    public static function pipelineColumns(): array
    {
        return collect(self::openCases())
            ->mapWithKeys(fn (self $stage): array => [$stage->value => $stage->getLabel()])
            ->all();
    }
}
