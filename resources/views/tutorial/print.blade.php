@php
    $keys = array_keys($lessons);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tutorial de uso · Korapp</title>
    <style>
        @page {
            size: A4;
            margin: 14mm 16mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            color: #111827;
            background: #fff;
            font-family: Georgia, "Times New Roman", Times, serif;
            font-size: 11pt;
            line-height: 1.45;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .toolbar {
            position: sticky;
            top: 0;
            z-index: 10;
            display: flex;
            flex-wrap: wrap;
            gap: 0.6rem;
            align-items: center;
            justify-content: space-between;
            padding: 0.85rem 1rem;
            background: #f8fafc;
            border-bottom: 1px solid #d1d5db;
        }

        .toolbar p {
            margin: 0;
            font-family: system-ui, sans-serif;
            font-size: 0.9rem;
            color: #475569;
        }

        .toolbar-actions {
            display: flex;
            gap: 0.5rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 2.3rem;
            padding: 0.4rem 0.9rem;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            background: #fff;
            color: #0f172a;
            font-family: system-ui, sans-serif;
            font-size: 0.85rem;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        .btn-primary {
            background: #0f172a;
            border-color: #0f172a;
            color: #fff;
        }

        .doc {
            max-width: 720px;
            margin: 0 auto;
            padding: 1.5rem 1.25rem 2.5rem;
        }

        .cover {
            margin-bottom: 1.4rem;
            padding-bottom: 0.9rem;
            border-bottom: 2px solid #111827;
        }

        .cover .kicker {
            margin: 0 0 0.25rem;
            font-family: system-ui, sans-serif;
            font-size: 0.75rem;
            font-weight: 700;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            color: #4b5563;
        }

        .cover h1 {
            margin: 0 0 0.4rem;
            font-size: 1.8rem;
            line-height: 1.2;
        }

        .cover p {
            margin: 0;
            color: #374151;
            font-size: 1rem;
        }

        .toc {
            margin: 0 0 1.5rem;
            padding: 0.9rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            background: #f9fafb;
        }

        .toc h2 {
            margin: 0 0 0.55rem;
            font-size: 1rem;
            font-family: system-ui, sans-serif;
        }

        .toc ol {
            margin: 0;
            padding-left: 1.2rem;
        }

        .toc li {
            margin: 0.2rem 0;
            color: #374151;
        }

        .lesson {
            margin: 0 0 1.15rem;
            padding: 0 0 0.95rem;
            border-bottom: 1px solid #d1d5db;
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .lesson:last-child {
            border-bottom: 0;
        }

        .lesson-num {
            display: inline-block;
            margin-bottom: 0.2rem;
            font-family: system-ui, sans-serif;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
            color: #1d4ed8;
        }

        .lesson h2 {
            margin: 0 0 0.25rem;
            font-size: 1.25rem;
        }

        .lesson-goal {
            margin: 0 0 0.75rem;
            color: #4b5563;
            font-size: 0.95rem;
        }

        .saas-guide-intro {
            margin: 0 0 0.7rem;
            color: #374151;
        }

        .saas-guide-steps {
            display: grid;
            gap: 0.55rem;
        }

        .saas-guide-step {
            display: grid;
            grid-template-columns: 1.8rem 1fr;
            gap: 0.7rem;
            padding: 0.7rem 0.8rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .saas-guide-step > span {
            width: 1.8rem;
            height: 1.8rem;
            border-radius: 999px;
            background: #111827;
            color: #fff;
            font-family: system-ui, sans-serif;
            font-size: 0.75rem;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .saas-guide-step h4 {
            margin: 0 0 0.2rem;
            font-size: 1rem;
        }

        .saas-guide-step p {
            margin: 0;
            color: #374151;
        }

        .saas-guide-where {
            margin-top: 0.35rem !important;
            font-family: system-ui, sans-serif;
            font-size: 0.82rem !important;
            color: #6b7280 !important;
        }

        .saas-guide-where code {
            font-size: 0.8rem;
            background: #e5e7eb;
            padding: 0.05rem 0.3rem;
            border-radius: 3px;
        }

        .saas-guide-roadmap {
            margin: 0;
            padding: 0;
            list-style: none;
            display: grid;
            gap: 0.45rem;
        }

        .saas-guide-roadmap li {
            display: grid;
            gap: 0.1rem;
            padding: 0.65rem 0.8rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            break-inside: avoid;
        }

        .saas-guide-roadmap strong {
            font-size: 0.95rem;
        }

        .saas-guide-roadmap span {
            color: #6b7280;
            font-size: 0.88rem;
        }

        .saas-guide-faq {
            margin: 0;
            padding: 0;
            list-style: none;
            display: grid;
            gap: 0.45rem;
        }

        .saas-guide-faq li {
            padding: 0.65rem 0.8rem;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            color: #374151;
            break-inside: avoid;
        }

        .saas-guide-faq strong {
            color: #111827;
        }

        .saas-guide-link,
        .saas-guide-actions {
            display: none !important;
        }

        @media print {
            .toolbar {
                display: none !important;
            }

            .doc {
                max-width: none;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <p>Vista lista para PDF. Usa <strong>Guardar como PDF</strong> o Imprimir.</p>
        <div class="toolbar-actions">
            <a class="btn" href="{{ \App\Filament\Pages\TutorialManejo::getUrl() }}">Volver al tutorial</a>
            <button type="button" class="btn btn-primary" onclick="window.print()">Imprimir / PDF</button>
        </div>
    </div>

    <main class="doc">
        <header class="cover">
            <p class="kicker">Korapp</p>
            <h1>Tutorial de uso</h1>
            <p>Guía práctica para el equipo: configuración, clientes, ventas y producción.</p>
        </header>

        <nav class="toc" aria-label="Índice">
            <h2>Índice de lecciones</h2>
            <ol>
                @foreach ($keys as $i => $key)
                    <li>{{ $lessons[$key]['title'] }} — {{ $lessons[$key]['goal'] }}</li>
                @endforeach
            </ol>
        </nav>

        @foreach ($keys as $i => $lessonKey)
            <article class="lesson">
                <span class="lesson-num">Lección {{ $i + 1 }}</span>
                <h2>{{ $lessons[$lessonKey]['title'] }}</h2>
                <p class="lesson-goal">{{ $lessons[$lessonKey]['goal'] }} · {{ $lessons[$lessonKey]['minutes'] }}</p>
                @include('tutorial.partials.lesson-body', [
                    'lessonKey' => $lessonKey,
                    'links' => $links,
                    'showLinks' => false,
                ])
            </article>
        @endforeach
    </main>

    <script>
        window.addEventListener('load', function () {
            setTimeout(function () {
                window.print();
            }, 250);
        });
    </script>
</body>
</html>
