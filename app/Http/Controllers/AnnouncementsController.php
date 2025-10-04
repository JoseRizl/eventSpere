<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class AnnouncementsController extends Controller
{
    private string $dbPath;
    private array $jsonData;

    public function __construct()
    {
        $this->dbPath = base_path('db.json');
        $this->jsonData = json_decode(File::get($this->dbPath), true);
    }

    private function writeJson(array $data): void
    {
        File::put($this->dbPath, json_encode($data, JSON_PRETTY_PRINT));
    }

    public function indexForEvent($eventId)
    {
        $ann = collect($this->jsonData['announcements'] ?? [])
            ->where('event_id', $eventId)
            ->sortByDesc(function ($a) {
                return strtotime($a['timestamp'] ?? '1970-01-01T00:00:00Z');
            })
            ->values()
            ->toArray();
        return response()->json($ann);
    }

    public function storeForEvent(Request $request, $eventId)
    {
        $event = collect($this->jsonData['events'] ?? [])->firstWhere('id', $eventId);
        if (!$event) {
            return back()->with('error', 'Event not found.');
        }

        $validated = $request->validate([
            'message' => 'required|string',
            'image' => 'nullable|string',
            'timestamp' => 'nullable|string',
            'userId' => 'required',
        ]);

        $new = [
            'id' => $eventId.'-'.substr(md5(uniqid(rand(), true)), 0, 4),
            'event_id' => $eventId,
            'message' => $validated['message'],
            'image' => $validated['image'] ?? null,
            'timestamp' => $validated['timestamp'] ?? now()->toISOString(),
            'userId' => $validated['userId'],
        ];

        $this->jsonData['announcements'] = $this->jsonData['announcements'] ?? [];
        array_unshift($this->jsonData['announcements'], $new);
        $this->writeJson($this->jsonData);

        if ($request->wantsJson()) {
            return response()->json($new, 201);
        }
        return back()->with('success', 'Announcement posted.');
    }

    public function destroyForEvent(Request $request, $eventId, $announcementId)
    {
        $before = count($this->jsonData['announcements'] ?? []);
        $this->jsonData['announcements'] = collect($this->jsonData['announcements'] ?? [])
            ->filter(fn($a) => !($a['id'] === $announcementId && ($a['event_id'] ?? null) == $eventId))
            ->values()
            ->toArray();
        $after = count($this->jsonData['announcements']);

        $this->writeJson($this->jsonData);

        if ($request->wantsJson()) {
            return response()->json(['deleted' => $before - $after]);
        }
        return back()->with('success', 'Announcement removed.');
    }
}
