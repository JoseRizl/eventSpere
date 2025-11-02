<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

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

    private function saveImage($base64Image, $oldImagePath = null)
    {
        // If the new image is not a base64 string, it's either an existing path or null.
        if (!$base64Image || !str_contains($base64Image, 'base64')) {
            // If the "new" image is null and there was an old one, delete the old one.
            if (is_null($base64Image) && $oldImagePath) {
                $this->deleteImage($oldImagePath);
            }
            return $base64Image; // Return the (potentially null) path.
        }

        // A new base64 image is being uploaded, so delete the old one.
        if ($oldImagePath) {
            $this->deleteImage($oldImagePath);
        }

        @list($type, $data) = explode(';', $base64Image);
        @list(, $data)      = explode(',', $data);
        $data = base64_decode($data);

        $path = 'images/announcements/' . date('Y/m');
        $filename = uniqid() . '.webp'; // Standardize on webp

        Storage::disk('public')->put($path . '/' . $filename, $data);

        return Storage::url($path . '/' . $filename);
    }

    private function deleteImage($path)
    {
        if ($path && str_starts_with($path, '/storage/')) {
            $filePath = str_replace('/storage/', '', $path);
            if (Storage::disk('public')->exists($filePath)) {
                Storage::disk('public')->delete($filePath);
            }
        }
    }

    public function index()
    {
        $ann = collect($this->jsonData['announcements'] ?? [])
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

        // Handle image upload
        $imagePath = $this->saveImage($validated['image'] ?? null);

        $new = [
            'id' => ($validated['event_id'] ?? 'gen') . '-' . substr(md5(uniqid(rand(), true)), 0, 4),
            'event_id' => $validated['event_id'] ?? null,
            'message' => $validated['message'],
            'image' => $imagePath,
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

        // Handle image upload
        $imagePath = $this->saveImage($validated['image'] ?? null);

        $new = [
            'id' => $eventId.'-'.substr(md5(uniqid(rand(), true)), 0, 4),
            'event_id' => $eventId,
            'message' => $validated['message'],
            'image' => $imagePath,
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

    public function update(Request $request, $announcementId)
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
        $announcementIndex = $announcements->search(fn($ann) => $ann['id'] === $announcementId);

        if ($announcementIndex === false) {
            return response()->json(['message' => 'Announcement not found.'], 404);
        }

        $updatedAnnouncement = $announcements[$announcementIndex];
        $oldImage = $updatedAnnouncement['image'] ?? null;

        $updatedAnnouncement['message'] = $validated['message'];
        $updatedAnnouncement['image'] = $this->saveImage($validated['image'] ?? null, $oldImage);

        $this->jsonData['announcements'][$announcementIndex] = $updatedAnnouncement;
        $this->writeJson($this->jsonData);

        $user = User::find($updatedAnnouncement['userId']);
        $updatedAnnouncement['employee'] = $user ? ['name' => $user->name] : ['name' => 'Admin'];

        return response()->json($updatedAnnouncement, 200);
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
        $oldImage = $updatedAnnouncement['image'] ?? null;
        $updatedAnnouncement['message'] = $validated['message'];
        $updatedAnnouncement['image'] = $this->saveImage($validated['image'] ?? null, $oldImage);

        $this->jsonData['announcements'][$announcementIndex] = $updatedAnnouncement;
        $this->writeJson($this->jsonData);

        $user = User::find($updatedAnnouncement['userId']);
        $updatedAnnouncement['employee'] = $user ? ['name' => $user->name] : ['name' => 'Admin'];

        return response()->json($updatedAnnouncement, 200);
    }

    public function destroy(Request $request, $announcementId)
    {
        $announcements = collect($this->jsonData['announcements'] ?? []);
        $originalCount = $announcements->count();
        $announcementToDelete = $announcements->firstWhere('id', $announcementId);

        // Delete the image file if it exists
        if ($announcementToDelete && !empty($announcementToDelete['image'])) {
            $this->deleteImage($announcementToDelete['image']);
        }

        $filteredAnnouncements = $announcements->filter(fn($ann) => $ann['id'] !== $announcementId);

        if ($filteredAnnouncements->count() === $originalCount) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Announcement not found.'], 404);
            }
            return back()->with('error', 'Announcement not found.');
        }

        $this->jsonData['announcements'] = $filteredAnnouncements->values()->toArray();
        $this->writeJson($this->jsonData);

        if ($request->wantsJson()) {
            return response()->json(['deleted' => true], 200);
        }
        return back()->with('success', 'Announcement removed.');
    }

    public function destroyForEvent(Request $request, $eventId, $announcementId)
    {
        $before = count($this->jsonData['announcements'] ?? []);

        $announcementToDelete = collect($this->jsonData['announcements'] ?? [])
            ->firstWhere('id', $announcementId);

        if ($announcementToDelete && !empty($announcementToDelete['image'])) {
            $this->deleteImage($announcementToDelete['image']);
        }
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
