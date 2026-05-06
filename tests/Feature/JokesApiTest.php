<?php

namespace Tests\Feature;

use App\Models\Joke;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JokesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_jokes_in_json(): void
    {
        Joke::factory()->count(3)->create();

        $response = $this->getJson('/api/jokes');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    ['id', 'external_id', 'type', 'setup', 'punchline', 'fetched_at'],
                ],
                'meta' => ['current_page', 'per_page', 'total'],
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_per_page_query_param_limits_results(): void
    {
        Joke::factory()->count(5)->create();

        $response = $this->getJson('/api/jokes?per_page=2');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_returns_empty_data_when_no_jokes_exist(): void
    {
        $response = $this->getJson('/api/jokes');

        $response->assertOk();
        $this->assertSame([], $response->json('data'));
    }
}
