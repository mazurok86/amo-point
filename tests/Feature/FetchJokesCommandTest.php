<?php

namespace Tests\Feature;

use App\Models\Joke;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FetchJokesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_saves_a_joke_returned_by_the_api(): void
    {
        Http::fake([
            'official-joke-api.appspot.com/*' => Http::response([
                'id' => 42,
                'type' => 'programming',
                'setup' => 'Why do programmers prefer dark mode?',
                'punchline' => 'Because light attracts bugs.',
            ]),
        ]);

        $this->artisan('jokes:fetch')->assertSuccessful();

        $this->assertDatabaseHas('jokes', [
            'external_id' => 42,
            'type' => 'programming',
            'setup' => 'Why do programmers prefer dark mode?',
            'punchline' => 'Because light attracts bugs.',
        ]);
    }

    public function test_it_is_idempotent_for_the_same_external_id(): void
    {
        Http::fake([
            'official-joke-api.appspot.com/*' => Http::response([
                'id' => 7,
                'type' => 'general',
                'setup' => 'A',
                'punchline' => 'B',
            ]),
        ]);

        $this->artisan('jokes:fetch')->assertSuccessful();
        $this->artisan('jokes:fetch')->assertSuccessful();

        $this->assertSame(1, Joke::query()->where('external_id', 7)->count());
    }

    public function test_it_returns_failure_on_http_error(): void
    {
        Http::fake([
            'official-joke-api.appspot.com/*' => Http::response('boom', 500),
        ]);

        $this->artisan('jokes:fetch')->assertFailed();

        $this->assertSame(0, Joke::query()->count());
    }

    public function test_it_returns_failure_on_unexpected_payload(): void
    {
        Http::fake([
            'official-joke-api.appspot.com/*' => Http::response(['unexpected' => true]),
        ]);

        $this->artisan('jokes:fetch')->assertFailed();

        $this->assertSame(0, Joke::query()->count());
    }
}
