<?php

namespace Tests\Feature\Visits;

use App\Models\User;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/stats')->assertRedirect('/login');
    }

    public function test_authenticated_user_can_open_stats_page(): void
    {
        $response = $this->actingAs(User::factory()->create())->get('/stats');

        $response->assertOk();
        $response->assertSeeText('Visit statistics');
    }

    public function test_hour_chart_data_reflects_seeded_visits(): void
    {
        $today = now()->startOfDay();

        Visit::factory()->create([
            'host' => 'example.com',
            'visitor_uid' => 'uid-1',
            'created_at' => $today->copy()->setTime(10, 0),
        ]);
        Visit::factory()->create([
            'host' => 'example.com',
            'visitor_uid' => 'uid-2',
            'created_at' => $today->copy()->setTime(10, 30),
        ]);
        Visit::factory()->create([
            'host' => 'example.com',
            'visitor_uid' => 'uid-1', // same visitor again, same hour — counts once
            'created_at' => $today->copy()->setTime(10, 45),
        ]);
        Visit::factory()->create([
            'host' => 'example.com',
            'visitor_uid' => 'uid-3',
            'created_at' => $today->copy()->setTime(14, 0),
        ]);
        // Different host — must be excluded
        Visit::factory()->create([
            'host' => 'other.test',
            'visitor_uid' => 'uid-x',
            'created_at' => $today->copy()->setTime(10, 0),
        ]);

        $response = $this->actingAs(User::factory()->create())->get(
            '/stats?host=example.com&date='.$today->format('Y-m-d')
        );

        $response->assertOk();

        $hourValues = $this->extractCanvasArray($response->getContent(), 'hours-chart', 'values');

        $this->assertSame(2, $hourValues[10], 'hour 10 should have 2 unique visitors');
        $this->assertSame(1, $hourValues[14], 'hour 14 should have 1 unique visitor');
        $this->assertSame(0, $hourValues[9], 'hour 9 should be empty');
    }

    public function test_city_chart_groups_by_city_with_unique_visitors(): void
    {
        $today = now()->startOfDay();

        Visit::factory()->create(['host' => 'example.com', 'visitor_uid' => 'a', 'city' => 'Moscow', 'created_at' => $today->copy()->setTime(10, 0)]);
        Visit::factory()->create(['host' => 'example.com', 'visitor_uid' => 'b', 'city' => 'Moscow', 'created_at' => $today->copy()->setTime(11, 0)]);
        Visit::factory()->create(['host' => 'example.com', 'visitor_uid' => 'c', 'city' => 'Berlin', 'created_at' => $today->copy()->setTime(12, 0)]);
        Visit::factory()->create(['host' => 'example.com', 'visitor_uid' => 'a', 'city' => 'Moscow', 'created_at' => $today->copy()->setTime(13, 0)]); // same uid

        $response = $this->actingAs(User::factory()->create())->get(
            '/stats?host=example.com&date='.$today->format('Y-m-d')
        );

        $response->assertOk();
        $labels = $this->extractCanvasArray($response->getContent(), 'cities-chart', 'labels');
        $values = $this->extractCanvasArray($response->getContent(), 'cities-chart', 'values');

        $cities = array_combine($labels, $values);
        $this->assertSame(2, $cities['Moscow']);
        $this->assertSame(1, $cities['Berlin']);
    }

    public function test_empty_city_state_when_no_city_data(): void
    {
        $today = now()->startOfDay();

        Visit::factory()->create([
            'host' => 'example.com',
            'visitor_uid' => 'uid-1',
            'city' => null,
            'created_at' => $today->copy()->setTime(10, 0),
        ]);

        $response = $this->actingAs(User::factory()->create())->get(
            '/stats?host=example.com&date='.$today->format('Y-m-d')
        );

        $response->assertOk();
        $response->assertSee('No city data for this day');
    }

    /**
     * Pull a JSON array out of a canvas data-* attribute.
     *
     * @return array<int|string, mixed>
     */
    private function extractCanvasArray(string $html, string $canvasId, string $attr): array
    {
        $pattern = '/<canvas[^>]*id="'.preg_quote($canvasId, '/').'"[^>]*data-'.$attr.'=\'([^\']*)\'/s';
        $this->assertMatchesRegularExpression($pattern, $html, "data-{$attr} not found on #{$canvasId}");
        preg_match($pattern, $html, $matches);

        return json_decode(html_entity_decode($matches[1]), true) ?? [];
    }
}
