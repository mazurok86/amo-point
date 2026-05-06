<?php

namespace App\Http\Controllers;

use App\Models\Visit;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class StatsController extends Controller
{
    public function index(Request $request): View
    {
        $hosts = Visit::query()->distinct()->orderBy('host')->pluck('host');

        $host = (string) $request->input('host', $hosts->first() ?? '');

        $date = $request->filled('date')
            ? CarbonImmutable::parse((string) $request->input('date'))
            : CarbonImmutable::today();

        $visits = $host !== ''
            ? Visit::query()
                ->where('host', $host)
                ->whereDate('created_at', $date)
                ->get(['visitor_uid', 'city', 'created_at'])
            : collect();

        // Bar chart: unique visitors per hour, full 24h timeline (zeros included).
        $hourly = collect(range(0, 23))->mapWithKeys(fn (int $h) => [
            sprintf('%02d', $h) => $visits
                ->filter(fn ($v) => $v->created_at->hour === $h)
                ->pluck('visitor_uid')
                ->unique()
                ->count(),
        ]);

        // Pie chart: top 10 cities (visitors with city resolved).
        $cityCounts = $visits
            ->filter(fn ($v) => filled($v->city))
            ->groupBy('city')
            ->map(fn ($group) => $group->pluck('visitor_uid')->unique()->count())
            ->sortDesc()
            ->take(10);

        return view('stats.index', [
            'hosts' => $hosts,
            'host' => $host,
            'date' => $date,
            'hourLabels' => $hourly->keys()->values()->all(),
            'hourValues' => $hourly->values()->all(),
            'cityLabels' => $cityCounts->keys()->values()->all(),
            'cityValues' => $cityCounts->values()->all(),
            'totalVisits' => $visits->count(),
            'uniqueVisitors' => $visits->pluck('visitor_uid')->unique()->count(),
        ]);
    }
}
