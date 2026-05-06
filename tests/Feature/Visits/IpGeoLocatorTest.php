<?php

namespace Tests\Feature\Visits;

use App\Services\IpGeoLocator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IpGeoLocatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_it_returns_country_and_city_on_success(): void
    {
        Http::fake([
            'ip-api.com/*' => Http::response([
                'status' => 'success',
                'countryCode' => 'us',
                'city' => 'New York',
            ]),
        ]);

        $geo = app(IpGeoLocator::class)->resolve('8.8.8.8');

        $this->assertSame('US', $geo['country']);
        $this->assertSame('New York', $geo['city']);
    }

    public function test_it_returns_nulls_when_api_responds_with_failure_status(): void
    {
        Http::fake([
            'ip-api.com/*' => Http::response([
                'status' => 'fail',
                'message' => 'reserved range',
            ]),
        ]);

        $geo = app(IpGeoLocator::class)->resolve('8.8.8.8');

        $this->assertNull($geo['country']);
        $this->assertNull($geo['city']);
    }

    public function test_it_returns_nulls_on_http_error(): void
    {
        Http::fake([
            'ip-api.com/*' => Http::response('boom', 500),
        ]);

        $geo = app(IpGeoLocator::class)->resolve('8.8.8.8');

        $this->assertNull($geo['country']);
        $this->assertNull($geo['city']);
    }

    public function test_it_short_circuits_on_private_ip_without_http_call(): void
    {
        Http::fake();

        $geo = app(IpGeoLocator::class)->resolve('127.0.0.1');

        $this->assertNull($geo['country']);
        $this->assertNull($geo['city']);
        Http::assertNothingSent();
    }

    public function test_it_caches_the_lookup_per_ip(): void
    {
        Http::fake([
            'ip-api.com/*' => Http::response([
                'status' => 'success',
                'countryCode' => 'DE',
                'city' => 'Berlin',
            ]),
        ]);

        $locator = app(IpGeoLocator::class);
        $locator->resolve('1.2.3.4');
        $locator->resolve('1.2.3.4');

        Http::assertSentCount(1);
    }
}
