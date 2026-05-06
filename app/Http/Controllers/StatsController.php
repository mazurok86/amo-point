<?php

namespace App\Http\Controllers;

use App\Models\Visit;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    public function index(Request $request): View
    {
        $hosts = Visit::query()->distinct()->orderBy('host')->pluck('host');

        $host = (string) $request->input('host', $hosts->first() ?? '');

        $date = $request->filled('date')
            ? CarbonImmutable::parse((string) $request->input('date'))
            : CarbonImmutable::today();

        $start = $date->startOfDay();
        $end = $date->copy()->addDay()->startOfDay();

        // No host yet → empty dataset (avoids querying with `host = ''`).
        if ($host === '') {
            return view('stats.index', [
                'hosts' => $hosts,
                'host' => $host,
                'date' => $date,
                'hourLabels' => $this->hourLabels(),
                'hourValues' => array_fill(0, 24, 0),
                'cityLabels' => [],
                'cityValues' => [],
                'totalVisits' => 0,
                'uniqueVisitors' => 0,
            ]);
        }

        $base = fn () => Visit::query()
            ->where('host', $host)
            ->whereBetween('created_at', [$start, $end]);

        $totals = $base()
            ->selectRaw('COUNT(*) AS total, COUNT(DISTINCT visitor_uid) AS unique_visitors')
            ->first();

        // Driver-aware HOUR() — MySQL is the prod target; the sqlite branch
        // exists only so the test suite (sqlite :memory:) keeps working.
        $hourExpr = DB::connection()->getDriverName() === 'sqlite'
            ? "CAST(strftime('%H', created_at) AS INTEGER)"
            : 'HOUR(created_at)';

        $hourCounts = $base()
            ->selectRaw("$hourExpr AS h, COUNT(DISTINCT visitor_uid) AS c")
            ->groupBy('h')
            ->pluck('c', 'h');

        $hourValues = [];
        for ($h = 0; $h < 24; $h++) {
            $hourValues[] = (int) ($hourCounts[$h] ?? 0);
        }

        $cityCounts = $base()
            ->whereNotNull('city')
            ->selectRaw('city, COUNT(DISTINCT visitor_uid) AS c')
            ->groupBy('city')
            ->orderByDesc('c')
            ->limit(10)
            ->pluck('c', 'city');

        return view('stats.index', [
            'hosts' => $hosts,
            'host' => $host,
            'date' => $date,
            'hourLabels' => $this->hourLabels(),
            'hourValues' => $hourValues,
            'cityLabels' => $cityCounts->keys()->all(),
            'cityValues' => $cityCounts->values()->map(fn ($c) => (int) $c)->all(),
            'totalVisits' => (int) ($totals->total ?? 0),
            'uniqueVisitors' => (int) ($totals->unique_visitors ?? 0),
        ]);
    }

    /**
     * @return list<string>
     */
    private function hourLabels(): array
    {
        return array_map(fn (int $h) => sprintf('%02d', $h), range(0, 23));
    }
}
