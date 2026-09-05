<?php

namespace App\Filament\Pages;

use App\Support\TutorialGuide;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class TutorialManejo extends Page
{
    protected string $view = 'filament.pages.tutorial-manejo';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-academic-cap';

    protected static string|UnitEnum|null $navigationGroup = 'General';

    protected static ?string $navigationLabel = 'Ayuda';

    protected static ?string $title = 'Tutorial de uso';

    protected static ?string $slug = 'ayuda';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?int $navigationSort = 100;

    public string $lesson = 'bienvenida';

    public bool $showAll = false;

    public static function canAccess(): bool
    {
        return Auth::check();
    }

    public function getHeading(): string | Htmlable
    {
        return 'Tutorial de uso';
    }

    public function getSubheading(): string | Htmlable | null
    {
        return 'Aprende Korapp paso a paso: desde entrar al sistema hasta vender, producir y comprar.';
    }

    /**
     * @return array<string, array{title: string, goal: string, minutes: string}>
     */
    public function getLessons(): array
    {
        return TutorialGuide::lessons();
    }

    public function getLessonKeys(): array
    {
        return array_keys($this->getLessons());
    }

    public function getLessonIndex(): int
    {
        $keys = $this->getLessonKeys();
        $index = array_search($this->lesson, $keys, true);

        return $index === false ? 0 : (int) $index;
    }

    public function getProgressPercent(): int
    {
        $total = max(count($this->getLessonKeys()) - 1, 1);

        return (int) round(($this->getLessonIndex() / $total) * 100);
    }

    public function showLesson(string $lesson): void
    {
        if (! array_key_exists($lesson, $this->getLessons())) {
            return;
        }

        $this->showAll = false;
        $this->lesson = $lesson;
    }

    public function nextLesson(): void
    {
        $keys = $this->getLessonKeys();
        $next = $this->getLessonIndex() + 1;

        if (isset($keys[$next])) {
            $this->showLesson($keys[$next]);
        }
    }

    public function previousLesson(): void
    {
        $keys = $this->getLessonKeys();
        $prev = $this->getLessonIndex() - 1;

        if (isset($keys[$prev])) {
            $this->showLesson($keys[$prev]);
        }
    }

    public function showAllLessons(): void
    {
        $this->showAll = true;
        $this->lesson = 'bienvenida';
    }

    /**
     * @return array<string, string|null>
     */
    public function getLinks(): array
    {
        return TutorialGuide::links();
    }

    public function getPrintUrl(): string
    {
        return route('tutorial.print');
    }
}
