<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Announcement;
use App\Models\Event;
use Illuminate\Support\Facades\Storage;

class AnnouncementsController extends Controller
{
    private function saveImage($base64Image, $oldImagePath = null)
    {
        if (!$base64Image || !str_contains($base64Image, 'base64')) {
            if (is_null($base64Image) && $oldImagePath) {
                $this->deleteImage($oldImagePath);
            }
            return $base64Image;
        }

        if ($oldImagePath) {
            $this->deleteImage($oldImagePath);
        }

        @list($type, $data) = explode(';', $base64Image);
        @list(, $data)      = explode(',', $data);
        $data = base64_decode($data);

        $path = 'images/announcements/' . date('Y/m');
        $filename = uniqid() . '.webp';

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
        $ann = Announcement::with(['user:id,name', 'event:id,title'])
            ->orderByDesc('timestamp')
            ->get()
            ->map(function ($a) {
                return [
                    'id' => (string) $a->id,
                    'event_id' => $a->event_id,
                    'message' => $a->message,
                    'image' => $a->image,
                    'timestamp' => optional($a->timestamp)->toIso8601String(),
                    'userId' => $a->user_id,
                    'employee' => ['name' => optional($a->user)->name ?? 'Admin'],
                    'event' => $a->event_id ? ['id' => $a->event_id, 'title' => optional($a->event)->title] : null,
                ];
            });

        return response()->json($ann->values());
    }

    public function indexForEvent($eventId)
    {
        $ann = Announcement::with(['user:id,name', 'event:id,title'])
            ->where('event_id', $eventId)
            ->orderByDesc('timestamp')
            ->get()
            ->map(function ($a) {
                return [
                    'id' => (string) $a->id,
                    'event_id' => $a->event_id,
                    'message' => $a->message,
                    'image' => $a->image,
                    'timestamp' => optional($a->timestamp)->toIso8601String(),
                    'userId' => $a->user_id,
                    'employee' => ['name' => optional($a->user)->name ?? 'Admin'],
                    'event' => $a->event_id ? ['id' => $a->event_id, 'title' => optional($a->event)->title] : null,
                ];
            });

        return response()->json($ann->values());
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
                'userId' => 'required|exists:users,id',
                'event_id' => 'nullable|exists:events,id',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['message' => $e->validator->errors()->first(), 'errors' => $e->errors()], 422);
        }

        $imagePath = $this->saveImage($validated['image'] ?? null);

        $a = Announcement::create([
            'event_id' => $validated['event_id'] ?? null,
            'user_id' => $validated['userId'],
            'message' => $validated['message'],
            'image' => $imagePath,
            'timestamp' => isset($validated['timestamp']) ? now()->parse($validated['timestamp']) : now(),
        ]);

        $a->load(['user:id,name', 'event:id,title']);

        $resp = [
            'id' => (string) $a->id,
            'event_id' => $a->event_id,
            'message' => $a->message,
            'image' => $a->image,
            'timestamp' => optional($a->timestamp)->toIso8601String(),
            'userId' => $a->user_id,
            'employee' => ['name' => optional($a->user)->name ?? 'Admin'],
            'event' => $a->event_id ? ['id' => $a->event_id, 'title' => optional($a->event)->title] : null,
        ];

        return response()->json($resp, 201);
    }

    public function storeForEvent(Request $request, $eventId)
    {
        $event = Event::find($eventId);
        if (!$event) {
            return $request->wantsJson()
                ? response()->json(['message' => 'Event not found.'], 404)
                : back()->with('error', 'Event not found.');
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
                'userId' => 'required|exists:users,id',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            $errorMessage = $e->validator->errors()->first('message') ?? 'The given data was invalid.';
            if ($request->wantsJson()) {
                return response()->json(['message' => $errorMessage, 'errors' => $e->errors()], 422);
            }
            return back()->with('error', $errorMessage)->withInput();
        }

        $imagePath = $this->saveImage($validated['image'] ?? null);

        $a = Announcement::create([
            'event_id' => $event->id,
            'user_id' => $validated['userId'],
            'message' => $validated['message'],
            'image' => $imagePath,
            'timestamp' => isset($validated['timestamp']) ? now()->parse($validated['timestamp']) : now(),
        ]);

        $a->load(['user:id,name', 'event:id,title']);

        $resp = [
            'id' => (string) $a->id,
            'event_id' => $a->event_id,
            'message' => $a->message,
            'image' => $a->image,
            'timestamp' => optional($a->timestamp)->toIso8601String(),
            'userId' => $a->user_id,
            'employee' => ['name' => optional($a->user)->name ?? 'Admin'],
            'event' => ['id' => $a->event_id, 'title' => optional($a->event)->title],
        ];

        if ($request->wantsJson()) {
            return response()->json($resp, 201);
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

        $a = Announcement::find($announcementId);
        if (!$a) {
            return response()->json(['message' => 'Announcement not found.'], 404);
        }

        $a->message = $validated['message'];
        $a->image = $this->saveImage($validated['image'] ?? null, $a->image);
        $a->save();
        $a->load(['user:id,name', 'event:id,title']);

        $resp = [
            'id' => (string) $a->id,
            'event_id' => $a->event_id,
            'message' => $a->message,
            'image' => $a->image,
            'timestamp' => optional($a->timestamp)->toIso8601String(),
            'userId' => $a->user_id,
            'employee' => ['name' => optional($a->user)->name ?? 'Admin'],
            'event' => $a->event_id ? ['id' => $a->event_id, 'title' => optional($a->event)->title] : null,
        ];

        return response()->json($resp, 200);
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

        $a = Announcement::where('id', $announcementId)->where('event_id', $eventId)->first();
        if (!$a) {
            return response()->json(['message' => 'Announcement not found.'], 404);
        }

        $a->message = $validated['message'];
        $a->image = $this->saveImage($validated['image'] ?? null, $a->image);
        $a->save();
        $a->load(['user:id,name', 'event:id,title']);

        $resp = [
            'id' => (string) $a->id,
            'event_id' => $a->event_id,
            'message' => $a->message,
            'image' => $a->image,
            'timestamp' => optional($a->timestamp)->toIso8601String(),
            'userId' => $a->user_id,
            'employee' => ['name' => optional($a->user)->name ?? 'Admin'],
            'event' => ['id' => $a->event_id, 'title' => optional($a->event)->title],
        ];

        return response()->json($resp, 200);
    }

    public function destroy(Request $request, $announcementId)
    {
        $a = Announcement::find($announcementId);
        if (!$a) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Announcement not found.'], 404);
            }
            return back()->with('error', 'Announcement not found.');
        }

        if (!empty($a->image)) {
            $this->deleteImage($a->image);
        }
        $a->delete();

        if ($request->wantsJson()) {
            return response()->json(['deleted' => true], 200);
        }
        return back()->with('success', 'Announcement removed.');
    }

    public function destroyForEvent(Request $request, $eventId, $announcementId)
    {
        $a = Announcement::where('id', $announcementId)->where('event_id', $eventId)->first();
        if (!$a) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Announcement not found.'], 404);
            }
            return back()->with('error', 'Announcement not found.');
        }

        if (!empty($a->image)) {
            $this->deleteImage($a->image);
        }
        $a->delete();

        if ($request->wantsJson()) {
            return response()->json(['deleted' => 1]);
        }
        return back()->with('success', 'Announcement removed.');
    }
}
