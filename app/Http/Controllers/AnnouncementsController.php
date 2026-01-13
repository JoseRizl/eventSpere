<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAnnouncementRequest;
use App\Http\Requests\UpdateAnnouncementRequest;
use App\Http\Resources\AnnouncementResource;
use Illuminate\Http\Request;
use App\Models\Announcement;
use App\Models\Event;
use App\Models\Notification;
use App\Models\User;
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

    public function index(Request $request, Event $event = null)
    {
        $query = Announcement::with(['user:id,name', 'event:id,title'])
            ->orderByDesc('timestamp');

        if ($event) {
            $query->where('event_id', $event->id);
        }

        $announcements = $query->get();

        return AnnouncementResource::collection($announcements);
    }

    public function store(StoreAnnouncementRequest $request, Event $event = null)
    {
        $validated = $request->validated();

        $imagePath = $this->saveImage($validated['image'] ?? null);

        $announcement = Announcement::create([
            'event_id' => $event?->id ?? ($validated['event_id'] ?? null),
            'user_id' => auth()->id(),
            'message' => $validated['message'],
            'image' => $imagePath,
            'timestamp' => now(),
        ]);

        $announcement->load(['user:id,name', 'event:id,title']);

        // Create a notification for all users
        $users = User::all();
        foreach ($users as $user) {
            Notification::create([
                'user_id' => $user->id,
                'message' => 'New announcement: ' . $announcement->message,
            ]);
        }

        return new AnnouncementResource($announcement);
    }

    public function update(UpdateAnnouncementRequest $request, Event $event = null, Announcement $announcement)
    {
        // If an event is part of the route, ensure the announcement belongs to it.
        // This is a manual check that complements scoped bindings.
        if ($event && $announcement->event_id !== $event->id) {
            return response()->json(['message' => 'Announcement not found for this event.'], 404);
        }

        $validated = $request->validated();

        $announcement->message = $validated['message'];
        $announcement->image = $this->saveImage($validated['image'] ?? null, $announcement->image);
        $announcement->save();

        $announcement->load(['user:id,name', 'event:id,title'])->refresh();

        return new AnnouncementResource($announcement);
    }

    public function destroy(Request $request, Event $event = null, Announcement $announcement)
    {
        // If an event is part of the route, ensure the announcement belongs to it.
        if ($event && $announcement->event_id !== $event->id) {
            return response()->json(['message' => 'Announcement not found for this event.'], 404);
        }

        if (!empty($announcement->image)) {
            $this->deleteImage($announcement->image);
        }
        $announcement->delete();

        return response()->json(['deleted' => true], 200);
    }
}
