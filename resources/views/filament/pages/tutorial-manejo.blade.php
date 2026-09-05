<x-filament-panels::page>
    @php
        $lessons = $this->getLessons();
        $keys = $this->getLessonKeys();
        $links = $this->getLinks();
        $index = $this->getLessonIndex();
        $total = count($keys);
        $currentKeys = $this->showAll ? $keys : [$this->lesson];
        $current = $lessons[$this->lesson] ?? $lessons['bienvenida'];
        $hasPrev = $index > 0;
        $hasNext = $index < $total - 1;
    @endphp

    <div class="saas-guide {{ $this->showAll ? 'saas-guide--all' : '' }}" id="saas-guide-root">
        <header class="saas-guide-top">
            <div class="saas-guide-top-main">
                <p class="saas-guide-kicker">Tutorial para usuarios</p>
                <h2>Aprende Korapp paso a paso</h2>
                <p>Sigue las lecciones en orden. Cada una te dice qué hacer y dónde encontrarlo en el menú.</p>
            </div>

            <div class="saas-guide-top-actions">
                <button type="button" class="saas-guide-btn saas-guide-btn--ghost" wire:click="showAllLessons">
                    Ver todo
                </button>
                <a href="{{ $this->getPrintUrl() }}" target="_blank" rel="noopener" class="saas-guide-btn">
                    Imprimir / PDF
                </a>
            </div>
        </header>

        @unless ($this->showAll)
            <div class="saas-guide-progress">
                <div class="saas-guide-progress-meta">
                    <span>Lección {{ $index + 1 }} de {{ $total }}</span>
                    <strong>{{ $current['title'] }}</strong>
                    <em>{{ $current['minutes'] }}</em>
                </div>
                <div class="saas-guide-progress-bar" aria-hidden="true">
                    <span style="width: {{ $this->getProgressPercent() }}%"></span>
                </div>
                <div class="saas-guide-chips" role="tablist" aria-label="Lecciones">
                    @foreach ($keys as $i => $key)
                        <button
                            type="button"
                            role="tab"
                            wire:click="showLesson(@js($key))"
                            class="saas-guide-chip {{ $this->lesson === $key ? 'is-active' : '' }} {{ $i < $index ? 'is-done' : '' }}"
                            title="{{ $lessons[$key]['title'] }}"
                        >
                            {{ $i + 1 }}
                        </button>
                    @endforeach
                </div>
            </div>
        @endunless

        <div class="saas-guide-lessons">
            @foreach ($currentKeys as $lessonKey)
                @php
                    $lesson = $lessons[$lessonKey];
                    $lessonNumber = array_search($lessonKey, $keys, true);
                    $lessonNumber = $lessonNumber === false ? 1 : $lessonNumber + 1;
                @endphp

                <article class="saas-guide-lesson" wire:key="lesson-{{ $lessonKey }}">
                    <div class="saas-guide-lesson-head">
                        <span class="saas-guide-lesson-num">Lección {{ $lessonNumber }}</span>
                        <h3>{{ $lesson['title'] }}</h3>
                        <p>{{ $lesson['goal'] }} · {{ $lesson['minutes'] }}</p>
                    </div>

                    @include('tutorial.partials.lesson-body', [
                        'lessonKey' => $lessonKey,
                        'links' => $links,
                        'showLinks' => true,
                    ])
                </article>
            @endforeach
        </div>

        @unless ($this->showAll)
            <footer class="saas-guide-nav">
                <button
                    type="button"
                    class="saas-guide-btn saas-guide-btn--ghost"
                    wire:click="previousLesson"
                    @disabled(! $hasPrev)
                >
                    Anterior
                </button>

                <div class="saas-guide-nav-center">
                    <span>{{ $current['title'] }}</span>
                    <small>{{ $index + 1 }} / {{ $total }}</small>
                </div>

                @if ($hasNext)
                    <button type="button" class="saas-guide-btn" wire:click="nextLesson">
                        Siguiente
                    </button>
                @else
                    <button type="button" class="saas-guide-btn" wire:click="showLesson('bienvenida')">
                        Volver al inicio
                    </button>
                @endif
            </footer>
        @else
            <footer class="saas-guide-nav">
                <button type="button" class="saas-guide-btn saas-guide-btn--ghost" wire:click="showLesson('bienvenida')">
                    Volver al modo lección
                </button>
                <a href="{{ $this->getPrintUrl() }}" target="_blank" rel="noopener" class="saas-guide-btn">
                    Imprimir / PDF
                </a>
            </footer>
        @endunless
    </div>
</x-filament-panels::page>
