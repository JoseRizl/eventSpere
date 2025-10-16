<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
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
            })->values();

        $userIds = $ann->pluck('userId')->unique()->filter();
        $users = User::whereIn('id', $userIds)->get()->keyBy('id');

        $ann = $ann->map(function ($announcement) use ($users) {
            $user = $users->get($announcement['userId']);
            $announcement['employee'] = $user ? ['name' => $user->name] : ['name' => 'Admin'];
            return $announcement;
        })->toArray();

        return response()->json($ann);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'message' => ['required', 'string', function ($attribute, $value, $fail) {
                    if (trim($value) === '') {
                        $fail('The announcement message cannot be empty.');
                    }
                }],
                'image' => 'nullable|string',
                'timestamp' => 'nullable|string',
                'userId' => 'required',
                'event_id' => 'nullable|string', // Event ID is now optional
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => $e->validator->errors()->first(), 'errors' => $e->errors()], 422);
        }

        $new = [
            'id' => ($validated['event_id'] ?? 'gen') . '-' . substr(md5(uniqid(rand(), true)), 0, 4),
            'event_id' => $validated['event_id'] ?? null,
            'message' => $validated['message'],
            'image' => $validated['image'] ?? null,
            'timestamp' => $validated['timestamp'] ?? now()->toISOString(),
            'userId' => $validated['userId'],
        ];

        $this->jsonData['announcements'] = $this->jsonData['announcements'] ?? [];
        array_unshift($this->jsonData['announcements'], $new);
        $this->writeJson($this->jsonData);

        $user = User::find($new['userId']);
        $new['employee'] = $user ? ['name' => $user->name] : ['name' => 'Admin'];

        return response()->json($new, 201);
    }

    public function storeForEvent(Request $request, $eventId)
    {
        $event = collect($this->jsonData['events'] ?? [])->firstWhere('id', $eventId);
        if (!$event) {
            return back()->with('error', 'Event not found.');
        }

        try {
            $validated = $request->validate([
                'message' => ['required', 'string', function ($attribute, $value, $fail) {
                    if (trim($value) === '') {
                        $fail('The announcement message cannot be empty.');
                    }
                }],
                'image' => 'nullable|string',
                'timestamp' => 'nullable|string',
                'userId' => 'required',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $errorMessage = $e->validator->errors()->first('message') ?? 'The given data was invalid.';
            if ($request->wantsJson()) {
                return response()->json(['message' => $errorMessage, 'errors' => $e->errors()], 422);
            }
            return back()->with('error', $errorMessage)->withInput();
        }

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

        // Attach employee name for the response
        $user = User::find($new['userId']);
        $new['employee'] = $user ? ['name' => $user->name] : ['name' => 'Admin'];

        if ($request->wantsJson()) {
            return response()->json($new, 201);
        }
        return back()->with('success', 'Announcement posted.');
    }

    public function updateForEvent(Request $request, $eventId, $announcementId)
    {
        try {
            $validated = $request->validate([
                'message' => ['required', 'string', function ($attribute, $value, $fail) {
                    if (trim($value) === '') {
                        $fail('The announcement message cannot be empty.');
                    }
                }],
                'image' => 'nullable|string',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $errorMessage = $e->validator->errors()->first('message') ?? 'The given data was invalid.';
            if ($request->wantsJson()) {
                return response()->json(['message' => $errorMessage, 'errors' => $e->errors()], 422);
            }
            return back()->with('error', $errorMessage)->withInput();
        }

        $announcements = collect($this->jsonData['announcements'] ?? []);
        $announcementIndex = $announcements->search(fn($ann) => $ann['id'] === $announcementId && ($ann['event_id'] ?? null) == $eventId);

        if ($announcementIndex === false) {
            return response()->json(['message' => 'Announcement not found.'], 404);
        }

        $updatedAnnouncement = $announcements[$announcementIndex];
        $updatedAnnouncement['message'] = $validated['message'];
        if ($request->has('image')) {
            $updatedAnnouncement['image'] = $validated['image'];
        }
        // Optionally, you could add an 'updated_at' field instead of changing the timestamp
        // $updatedAnnouncement['updated_at'] = now()->toISOString();

        $this->jsonData['announcements'][$announcementIndex] = $updatedAnnouncement;
        $this->writeJson($this->jsonData);

        $user = User::find($updatedAnnouncement['userId']);
        $updatedAnnouncement['employee'] = $user ? ['name' => $user->name] : ['name' => 'Admin'];

        return response()->json($updatedAnnouncement, 200);
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
