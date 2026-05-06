<?php

namespace App\Jobs;

use App\Models\Visit;
use App\Services\IpGeoLocator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ResolveVisitGeo implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 10;

    public function __construct(public int $visitId) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 60, 300];
    }

    public function handle(IpGeoLocator $locator): void
    {
        $visit = Visit::query()->find($this->visitId);

        if ($visit === null) {
            return;
        }

        $geo = $locator->resolve((string) $visit->ip);

        if ($geo['country'] === null && $geo['city'] === null) {
            return;
        }

        $visit->forceFill([
            'country' => $geo['country'],
            'city' => $geo['city'],
        ])->save();
    }
}
