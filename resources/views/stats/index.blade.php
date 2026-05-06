<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Visit statistics') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <form method="get" class="flex flex-wrap gap-4 items-end">
                    <div>
                        <x-input-label for="date" :value="__('Date')" />
                        <x-text-input id="date" name="date" type="date"
                                      :value="$date->format('Y-m-d')" class="mt-1 block" />
                    </div>

                    <div>
                        <x-input-label for="host" :value="__('Host')" />
                        <select id="host" name="host"
                                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                            @forelse ($hosts as $h)
                                <option value="{{ $h }}" @selected($h === $host)>{{ $h }}</option>
                            @empty
                                <option value="">{{ __('— no data —') }}</option>
                            @endforelse
                        </select>
                    </div>

                    <x-primary-button>{{ __('Apply') }}</x-primary-button>
                </form>

                <p class="mt-4 text-sm text-gray-600">
                    {{ __('Total visits') }}: <strong>{{ $totalVisits }}</strong> ·
                    {{ __('Unique visitors') }}: <strong>{{ $uniqueVisitors }}</strong>
                </p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="font-semibold text-gray-700 mb-3">{{ __('Unique visits per hour') }}</h3>
                    <canvas id="hours-chart"
                            data-labels='@json($hourLabels)'
                            data-values='@json($hourValues)'
                            height="180"></canvas>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                    <h3 class="font-semibold text-gray-700 mb-3">{{ __('Top cities') }}</h3>
                    @if (count($cityLabels) === 0)
                        <p class="text-sm text-gray-500">
                            {{ __('No city data for this day. Run') }}
                            <code>php artisan db:seed --class=DemoVisitsSeeder</code>
                            {{ __('for a populated demo.') }}
                        </p>
                    @else
                        <canvas id="cities-chart"
                                data-labels='@json($cityLabels)'
                                data-values='@json($cityValues)'
                                height="180"></canvas>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        (function () {
            function readCanvas(id) {
                const el = document.getElementById(id);
                if (!el) return null;
                return {
                    el,
                    labels: JSON.parse(el.dataset.labels || '[]'),
                    values: JSON.parse(el.dataset.values || '[]'),
                };
            }

            const hours = readCanvas('hours-chart');
            if (hours) {
                new Chart(hours.el, {
                    type: 'bar',
                    data: {
                        labels: hours.labels,
                        datasets: [{
                            label: 'Unique visitors',
                            data: hours.values,
                            backgroundColor: 'rgba(99, 102, 241, 0.6)',
                            borderColor: 'rgb(99, 102, 241)',
                            borderWidth: 1,
                        }],
                    },
                    options: {
                        responsive: true,
                        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
                        plugins: { legend: { display: false } },
                    },
                });
            }

            const cities = readCanvas('cities-chart');
            if (cities && cities.labels.length) {
                new Chart(cities.el, {
                    type: 'pie',
                    data: {
                        labels: cities.labels,
                        datasets: [{ data: cities.values }],
                    },
                    options: { responsive: true },
                });
            }
        })();
    </script>
</x-app-layout>
