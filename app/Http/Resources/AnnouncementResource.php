<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnnouncementResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (string) $this->id,
            'event_id' => $this->event_id,
            'message' => $this->message,
            'image' => $this->image,
            'timestamp' => optional($this->timestamp)->toIso8601String(),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
            'userId' => $this->user_id,
            // Use optional() when accessing relationships that might not be loaded or might be null.
            'employee' => ['name' => optional($this->user)->name ?? 'Admin'],
            'event' => $this->whenLoaded('event', fn() => ['id' => $this->event->id, 'title' => $this->event->title]),
        ];
    }
}
