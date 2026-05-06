<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'AmoPoint') }} — тестовое задание PHP-разработчика</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#FDFDFC] dark:bg-[#0a0a0a] text-[#1b1b18] dark:text-[#EDEDEC] min-h-screen flex flex-col antialiased">
    <header class="w-full max-w-5xl mx-auto px-6 py-6 flex items-center justify-between">
        <div class="flex items-baseline gap-2">
            <span class="font-semibold text-lg">AmoPoint</span>
            <span class="text-xs text-[#706f6c] dark:text-[#A1A09A]">тестовое задание</span>
        </div>

        @if (Route::has('login'))
            <nav class="flex items-center gap-2 text-sm">
                @auth
                    <a href="{{ url('/stats') }}"
                       class="inline-block px-4 py-1.5 border border-[#19140035] dark:border-[#3E3E3A] hover:border-[#1915014a] dark:hover:border-[#62605b] rounded-sm leading-normal">
                        Stats
                    </a>
                @else
                    <a href="{{ route('login') }}"
                       class="inline-block px-4 py-1.5 border border-transparent hover:border-[#19140035] dark:hover:border-[#3E3E3A] rounded-sm leading-normal">
                        Log in
                    </a>

                    @if (Route::has('register'))
                        <a href="{{ route('register') }}"
                           class="inline-block px-4 py-1.5 border border-[#19140035] dark:border-[#3E3E3A] hover:border-[#1915014a] dark:hover:border-[#62605b] rounded-sm leading-normal">
                            Register
                        </a>
                    @endif
                @endauth
            </nav>
        @endif
    </header>

    <main class="w-full max-w-5xl mx-auto px-6 flex-1 pb-12">
        <div class="mb-10">
            <h1 class="text-3xl lg:text-4xl font-semibold mb-3">Тестовое задание PHP-разработчик</h1>
            <p class="text-[#706f6c] dark:text-[#A1A09A] max-w-2xl">
                Три задания из ТЗ — два обязательных и одно бонусное. Подробности реализации, алгоритмы
                и разбор отвергнутых альтернатив — в <code class="text-sm">README.md</code> и каталоге
                <code class="text-sm">docs/test-task/</code>.
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            {{-- Задание 1 --}}
            <article class="rounded-lg border border-[#e3e3e0] dark:border-[#3E3E3A] bg-white dark:bg-[#161615] p-6 flex flex-col">
                <div class="text-xs uppercase tracking-wider text-[#706f6c] dark:text-[#A1A09A] mb-2">Задание 1 · обязательное</div>
                <h2 class="font-semibold text-lg mb-3">Console + JSON API</h2>
                <p class="text-sm text-[#706f6c] dark:text-[#A1A09A] mb-4 flex-1">
                    Команда <code>jokes:fetch</code> по расписанию каждые 5 минут забирает шутку с
                    official-joke-api и сохраняет в БД через <code>updateOrCreate</code> (идемпотентно).
                    HTTP endpoint отдаёт пагинированный JSON.
                </p>
                <a href="/api/jokes" target="_blank"
                   class="inline-block text-center px-4 py-2 bg-[#1b1b18] dark:bg-[#EDEDEC] text-white dark:text-[#1C1C1A] rounded text-sm font-medium hover:opacity-90 transition">
                    GET /api/jokes →
                </a>
            </article>

            {{-- Задание 2 --}}
            <article class="rounded-lg border border-[#e3e3e0] dark:border-[#3E3E3A] bg-white dark:bg-[#161615] p-6 flex flex-col">
                <div class="text-xs uppercase tracking-wider text-[#706f6c] dark:text-[#A1A09A] mb-2">Задание 2 · обязательное</div>
                <h2 class="font-semibold text-lg mb-3">JS: видимость полей по «Тип»</h2>
                <p class="text-sm text-[#706f6c] dark:text-[#A1A09A] mb-4 flex-1">
                    Drop-in IIFE без зависимостей. Слушает <code>change</code> на
                    <code>&lt;select name="type_val"&gt;</code> и оставляет видимыми только те поля, в
                    <code>name</code> которых содержится выбранное значение.
                </p>
                <div class="flex flex-col gap-2">
                    <a href="/testzz/testlist.html" target="_blank"
                       class="inline-block text-center px-4 py-2 bg-[#1b1b18] dark:bg-[#EDEDEC] text-white dark:text-[#1C1C1A] rounded text-sm font-medium hover:opacity-90 transition">
                        Локальное зеркало →
                    </a>
                    <a href="https://test.amopoint-dev.ru/testzz/testlist.html" target="_blank"
                       class="inline-block text-center px-4 py-2 border border-[#e3e3e0] dark:border-[#3E3E3A] rounded text-sm hover:border-[#1b1b18] dark:hover:border-[#EDEDEC] transition">
                        Боевая страница ↗
                    </a>
                </div>
            </article>

            {{-- Задание 3 --}}
            <article class="rounded-lg border border-[#e3e3e0] dark:border-[#3E3E3A] bg-white dark:bg-[#161615] p-6 flex flex-col">
                <div class="text-xs uppercase tracking-wider text-[#706f6c] dark:text-[#A1A09A] mb-2">Задание 3 · бонус</div>
                <h2 class="font-semibold text-lg mb-3">Счётчик посещений</h2>
                <p class="text-sm text-[#706f6c] dark:text-[#A1A09A] mb-4 flex-1">
                    JS-коллектор <code>track.js</code> (sendBeacon, без зависимостей) → ingest endpoint
                    <code>POST /api/visits</code> (UA-парсинг, throttle, CORS-safe) → дашборд статистики
                    с графиками Chart.js под Breeze auth.
                </p>
                <div class="flex flex-col gap-2">
                    @auth
                        <a href="{{ url('/stats') }}"
                           class="inline-block text-center px-4 py-2 bg-[#1b1b18] dark:bg-[#EDEDEC] text-white dark:text-[#1C1C1A] rounded text-sm font-medium hover:opacity-90 transition">
                            Открыть /stats →
                        </a>
                    @else
                        <a href="{{ route('login') }}"
                           class="inline-block text-center px-4 py-2 bg-[#1b1b18] dark:bg-[#EDEDEC] text-white dark:text-[#1C1C1A] rounded text-sm font-medium hover:opacity-90 transition">
                            /stats — нужен login →
                        </a>
                    @endauth
                    <a href="/track-demo.html" target="_blank"
                       class="inline-block text-center px-4 py-2 border border-[#e3e3e0] dark:border-[#3E3E3A] rounded text-sm hover:border-[#1b1b18] dark:hover:border-[#EDEDEC] transition">
                        track.js demo →
                    </a>
                </div>
            </article>
        </div>

        @guest
            <div class="mt-8 text-sm text-[#706f6c] dark:text-[#A1A09A]">
                <strong class="text-[#1b1b18] dark:text-[#EDEDEC]">Совет проверяющему:</strong>
                чтобы увидеть pie-chart по городам в задании 3 —
                <a href="{{ route('register') }}" class="underline">зарегистрируйтесь</a>
                и засейте демо-данные:
                <code class="text-xs px-1 py-0.5 bg-[#f3f3f0] dark:bg-[#1f1f1d] rounded">
                    php artisan db:seed --class=DemoVisitsSeeder
                </code>.
            </div>
        @endguest
    </main>

    <footer class="w-full max-w-5xl mx-auto px-6 py-6 text-xs text-[#706f6c] dark:text-[#A1A09A] flex items-center justify-between">
        <span>Laravel 13 · Breeze (Blade) · MySQL · Tailwind v4</span>
        <a href="https://github.com/mazurok86/amo-point" target="_blank" class="hover:text-[#1b1b18] dark:hover:text-[#EDEDEC] transition">
            github.com/mazurok86/amo-point ↗
        </a>
    </footer>
</body>
</html>
