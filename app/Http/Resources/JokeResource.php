<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JokeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'external_id' => $this->external_id,
            'type' => $this->type,
            'setup' => $this->setup,
            'punchline' => $this->punchline,
            'fetched_at' => $this->fetched_at?->toIso8601String(),
        ];
    }
}
