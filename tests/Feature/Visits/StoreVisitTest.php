<?php

namespace Tests\Feature\Visits;

use App\Jobs\ResolveVisitGeo;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class StoreVisitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
    }

    public function test_it_stores_a_visit_with_parsed_ua_and_host(): void
    {
        $response = $this->post('/api/visits', [
            'visitor_uid' => '11111111-1111-1111-1111-111111111111',
            'page_url' => 'https://example.com/landing?ref=ad',
            'referrer' => 'https://google.com',
            'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
        ]);

        $response->assertNoContent();

        $this->assertSame(1, Visit::query()->count());
        $visit = Visit::query()->first();
        $this->assertSame('11111111-1111-1111-1111-111111111111', $visit->visitor_uid);
        $this->assertSame('example.com', $visit->host);
        $this->assertSame('mobile', $visit->device);
        $this->assertSame('Safari', $visit->browser);
        $this->assertSame('iOS', $visit->os);
        $this->assertSame('https://example.com/landing?ref=ad', $visit->page_url);
        $this->assertSame('https://google.com', $visit->referrer);

        Bus::assertDispatched(
            ResolveVisitGeo::class,
            fn (ResolveVisitGeo $job) => $job->visitId === $visit->id,
        );
    }

    public function test_it_falls_back_to_md5_when_visitor_uid_is_absent(): void
    {
        $response = $this->post('/api/visits', [
            'page_url' => 'https://example.com/',
            'user_agent' => 'Mozilla/5.0',
        ]);

        $response->assertNoContent();

        $visit = Visit::query()->first();
        $this->assertNotNull($visit->visitor_uid);
        $this->assertSame(32, strlen($visit->visitor_uid));
    }

    public function test_invalid_url_returns_422(): void
    {
        $response = $this->post('/api/visits', [
            'page_url' => 'not-a-url',
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, Visit::query()->count());
    }

    public function test_throttle_kicks_in_after_60_requests_per_minute(): void
    {
        $payload = ['page_url' => 'https://example.com/'];

        for ($i = 0; $i < 60; $i++) {
            $this->post('/api/visits', $payload)->assertNoContent();
        }

        $this->post('/api/visits', $payload)->assertStatus(429);
    }
}
