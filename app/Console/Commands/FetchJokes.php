<?php

namespace App\Console\Commands;

use App\Models\Joke;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class FetchJokes extends Command
{
    protected $signature = 'jokes:fetch';

    protected $description = 'Fetch a random joke from official-joke-api and store it';

    private const ENDPOINT = 'https://official-joke-api.appspot.com/random_joke';

    public function handle(): int
    {
        try {
            $response = Http::timeout(5)->retry(3, 200)->get(self::ENDPOINT);
        } catch (Throwable $e) {
            Log::error('jokes:fetch http error', ['error' => $e->getMessage()]);
            $this->error('Failed to fetch joke: '.$e->getMessage());

            return self::FAILURE;
        }

        if (! $response->successful()) {
            Log::warning('jokes:fetch non-2xx', ['status' => $response->status()]);
            $this->error('Non-2xx response: '.$response->status());

            return self::FAILURE;
        }

        $data = $response->json();

        if (! is_array($data) || ! isset($data['id'], $data['type'], $data['setup'], $data['punchline'])) {
            Log::warning('jokes:fetch unexpected payload', ['payload' => $data]);
            $this->error('Unexpected payload from API');

            return self::FAILURE;
        }

        Joke::updateOrCreate(
            ['external_id' => $data['id']],
            [
                'type' => $data['type'],
                'setup' => $data['setup'],
                'punchline' => $data['punchline'],
                'fetched_at' => now(),
            ]
        );

        Log::info('jokes:fetch ok', ['external_id' => $data['id']]);
        $this->info("Saved joke #{$data['id']}");

        return self::SUCCESS;
    }
}
