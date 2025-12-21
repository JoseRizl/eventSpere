<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\Event;
use App\Models\User;
use Inertia\Inertia;
use Illuminate\Support\Facades\File;
use App\Models\Category;
use Illuminate\Support\Facades\Storage;
class EventController extends JsonController
{
    public function index()
    {
        // Eager-load relations required by the frontend
        $events = Event::with([
            'tags',
            'category',
            'memorandum',
            'tasks.committee',
            'tasks.employees',
            'tasks.managers',
        ])
            ->where(function ($q) {
                $q->whereNull('archived')->orWhere('archived', '!=', true);
            })
            ->orderByDesc('startDate')
            ->get()
            ->map(function ($event) {
                // Shape tasks for UI
                $tasks = $event->tasks->map(function ($task) {
                    return [
                        'id' => $task->id,
                        'task' => $task->description,
                        'committee' => $task->committee,
                        'employees' => $task->employees,
                        'managers' => $task->managers,
                    ];
                })->values()->toArray();

                return [
                    'id' => (string)$event->id,
                    'title' => $event->title,
                    'description' => $event->description,
                    'image' => $event->image,
                    'category_id' => $event->category_id,
                    'category' => $event->category,
                    'venue' => $event->venue,
                    'startDate' => $event->startDate,
                    'endDate' => $event->endDate,
                    'isAllDay' => (bool)$event->isAllDay,
                    'startTime' => $event->startTime,
                    'endTime' => $event->endTime,
                    'createdAt' => $event->created_at ? $event->created_at->toISOString() : null,
                    'archived' => (bool)$event->archived,
                    'tags' => $event->tags,
                    'tasks' => $tasks,
                    'memorandum' => $event->memorandum,
                ];
            })->values()->toArray();

        if (request()->wantsJson()) {
            return response()->json($events);
        }

        // Compose props for Inertia view
        $tags = \App\Models\Tag::where('archived', '!=', true)->get();
        $committees = \App\Models\Committee::query()->get();
        $employees = \App\Models\Employee::query()->get();
        $categories = Category::where('archived', '!=', true)->get();

        // Temporary settings fallback; migrate to a Settings model if available
        $settings = ['defaultEventImage' => '/images/NCSlogo.png'];

        return Inertia::render('List/EventList', [
            'events_prop' => $events,
            'tags_prop' => $tags,
            'committees_prop' => $committees,
            'employees_prop' => $employees,
            'settings_prop' => $settings,
            'categories_prop' => $categories,
            'memorandums_prop' => [],
        ]);
    }

    public function show($id)
    {
        $event = Event::with(['tags', 'category', 'memorandum', 'tasks.committee', 'tasks.employees', 'tasks.managers.managedBrackets' => function ($query) use ($id) {
            $query->where('event_id', $id);
        }])
            ->findOrFail($id);

        // Build related events: share at least one tag
        $tagIds = $event->tags->pluck('id');
        $related = Event::with(['tags'])
            ->where('id', '!=', $event->id)
            ->whereHas('tags', function ($q) use ($tagIds) {
                $q->whereIn('tags.id', $tagIds);
            })
            ->where(function ($q) {
                $q->whereNull('archived')->orWhere('archived', false);
            })
            ->limit(5)
            ->get();

        // Activities
        $activities = \App\Models\Activity::where('event_id', $event->id)->get()->values()->toArray();

        // Tasks normalized for details page
        $tasks = $event->tasks->map(function ($task) {
            return [
                'id' => $task->id,
                'task' => $task->description,
                'committee' => $task->committee,
                'employees' => $task->employees,
                'managers' => $task->managers,
            ];
        })->values()->toArray();

        // Announcements with user names
        $announcements = \App\Models\Announcement::where('event_id', $event->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($a) {
                $user = $a->user()->select('id', 'name')->first();
                return [
                    'id' => (string)$a->id,
                    'event_id' => (string)$a->event_id,
                    'message' => $a->message,
                    'image' => $a->image,
                    'timestamp' => optional($a->created_at)->toISOString(),
                    'employee' => ['name' => $user?->name ?? 'Admin'],
                    'userId' => $user?->id,
                ];
            })->values()->toArray();

        $eventPayload = [
            'id' => (string)$event->id,
            'title' => $event->title,
            'description' => $event->description,
            'image' => $event->image,
            'category_id' => $event->category_id,
            'category' => $event->category,
            'venue' => $event->venue,
            'startDate' => $event->startDate,
            'endDate' => $event->endDate,
            'isAllDay' => (bool)$event->isAllDay,
            'startTime' => $event->startTime,
            'endTime' => $event->endTime,
            'createdAt' => $event->created_at ? $event->created_at->toISOString() : null,
            'archived' => (bool)$event->archived,
            'tags' => $event->tags,
            'tasks' => $tasks,
            'memorandum' => $event->memorandum,
        ];

        if (request()->wantsJson()) {
            return response()->json($eventPayload);
        }

        return Inertia::render('Events/EventDetails', [
            'event' => $eventPayload,
            'tags' => \App\Models\Tag::where('archived', '!=', true)->get(),
            'committees' => \App\Models\Committee::all(),
            'employees' => \App\Models\Employee::all(),
            'categories' => Category::where('archived', '!=', true)->get(),
            'relatedEvents' => $related,
            'preloadedActivities' => $activities,
            'preloadedTasks' => $tasks,
            'preloadedAnnouncements' => $announcements,
            'preloadedMemorandum' => $event->memorandum,
        ]);
    }

    public function dashboard()
    {
        $events = Event::where(function ($q) {
            $q->whereNull('archived')->orWhere('archived', '!=', true);
        })
            ->orderByDesc('startDate')
            ->get()
            ->toArray();

        return Inertia::render('Dashboard', [
            'events_prop' => $events,
        ]);
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

    private function normalizeDateInput($value)
    {
        if (!$value) return null;
        try {
            // Already in expected format like 'Nov-06-2025'
            if (preg_match('/^[A-Za-z]{3}-\d{2}-\d{4}$/', $value)) {
                return \Carbon\Carbon::createFromFormat('M-d-Y', $value)->format('M-d-Y');
            }
            // ISO or yyyy-mm-dd
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
                return \Carbon\Carbon::createFromFormat('Y-m-d', substr($value, 0, 10))->format('M-d-Y');
            }
            // Fallback parse
            return \Carbon\Carbon::parse($value)->format('M-d-Y');
        } catch (\Exception $e) {
            // Leave as-is if unparseable; validation will catch
            return $value;
        }
    }

    private function saveImage($base64Image, $oldImagePath = null)
    {
        if (!$base64Image || !str_contains($base64Image, 'base64')) {
            // Not a new base64 upload, so return it as is (might be an existing path or null)
            return $base64Image;
        }

        // A new image is being uploaded, so delete the old one if it exists.
        if ($oldImagePath) {
            $this->deleteImage($oldImagePath);
        }

        // Extract image data and type
        list($type, $data) = explode(';', $base64Image);
        list(, $data)      = explode(',', $data);
        $data = base64_decode($data);

        // Determine file extension
        $type = str_replace('data:image/', '', $type);
        $extension = $type === 'jpeg' ? 'jpg' : $type;

        // Create a unique path and filename
        $path = 'images/events/' . date('Y/m');
        $filename = uniqid() . '.' . $extension;

        Storage::disk('public')->put($path . '/' . $filename, $data);

        return Storage::url($path . '/' . $filename);
    }

    public function store(Request $request)
    {
        // Normalize incoming dates to M-d-Y just in case
        $request->merge([
            'startDate' => $this->normalizeDateInput($request->input('startDate')),
            'endDate' => $this->normalizeDateInput($request->input('endDate')),
        ]);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255', 'unique:events,title'],
            'description' => 'nullable|string',
            'image' => 'nullable|string',
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where(function ($query) {
                $query->where('archived', '!=', true);
            })],
            'venue' => 'nullable|string|max:255',
            'startDate' => 'required|date_format:M-d-Y|after_or_equal:today',
            'endDate' => 'required|date_format:M-d-Y|after_or_equal:startDate',
            'isAllDay' => ['required', 'boolean'],
            'startTime' => ['required_if:isAllDay,false', 'nullable', 'date_format:H:i'],
            'endTime' => ['required_if:isAllDay,false', 'nullable', 'date_format:H:i', function ($attribute, $value, $fail) use ($request) {
                $isAllDay = $request->boolean('isAllDay');
                $startTime = $request->input('startTime');
                $startDate = $request->input('startDate');
                $endDate = $request->input('endDate');
                if (!$isAllDay && $startTime && $value && $startDate === $endDate && strtotime($value) <= strtotime($startTime)) {
                    $fail('The end time must be after the start time for single-day events.');
                }
            }],
            'tags' => ['nullable', 'array'],
            'tags.*' => [Rule::exists('tags', 'id')->where(function ($query) {
                $query->where('archived', '!=', true);
            })],
            'archived' => 'boolean',
            'memorandum' => 'nullable|array',
        ]);

        // Enforce that provided tags belong to chosen category (if provided)
        if (!empty($validated['tags']) && !empty($validated['category_id'])) {
            $count = \App\Models\Tag::whereIn('id', $validated['tags'])->where('category_id', $validated['category_id'])->count();
            if ($count !== count($validated['tags'])) {
                return back()->withErrors(['tags' => 'Some tags are not valid for the selected category.'])->withInput();
            }
        }

        // Handle image upload
        $imagePath = $this->saveImage($validated['image'] ?? null, null);

        // Create event
        $event = new Event();
        $event->title = $validated['title'];
        $event->description = $validated['description'] ?? null;
        $event->image = $imagePath;
        $event->category_id = $validated['category_id'] ?? null;
        $event->venue = $validated['venue'] ?? null;
        $event->startDate = $validated['startDate'];
        $event->endDate = $validated['endDate'];
        $event->isAllDay = $validated['isAllDay'];
        $event->startTime = $validated['startTime'] ?? null;
        $event->endTime = $validated['endTime'] ?? null;
        $event->archived = $validated['archived'] ?? false;
        $event->save();

        // Attach tags
        if (!empty($validated['tags'])) {
            $event->tags()->sync($validated['tags']);
        }

        // Memorandum create if provided
        if (!empty($validated['memorandum'])) {
            $memo = new \App\Models\Memorandum();
            $memo->event_id = $event->id;
            $memo->type = $validated['memorandum']['type'] ?? 'file';
            $memo->content = $validated['memorandum']['content'] ?? null;
            $memo->filename = $validated['memorandum']['filename'] ?? null;
            $memo->save();
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'event' => $event]);
        }
        return redirect()->route('events.index')->with([
            'success' => 'Event created successfully.',
            'event_id' => (string)$event->id,
        ]);
    }

    public function update(Request $request, $id)
    {
        return $this->updateFromDetails($request, $id);
    }

    public function updateFromDetails(Request $request, $id)
    {
        $event = Event::findOrFail($id);

        // Normalize incoming dates to M-d-Y
        $request->merge([
            'startDate' => $this->normalizeDateInput($request->input('startDate')),
            'endDate' => $this->normalizeDateInput($request->input('endDate')),
        ]);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255', Rule::unique('events', 'title')->ignore($event->id)],
            'description' => 'nullable|string',
            'image' => 'nullable|string',
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where(function ($query) {
                $query->where('archived', '!=', true);
            })],
            'venue' => 'nullable|string|max:255',
            'startDate' => ['required', 'date_format:M-d-Y', function ($attribute, $value, $fail) use ($event) {
                if (!$event->startDate) return;
                try {
                    $original = \Carbon\Carbon::createFromFormat('M-d-Y', $event->startDate)->startOfDay();
                    $new = \Carbon\Carbon::createFromFormat('M-d-Y', $value)->startOfDay();
                } catch (\Exception $e) {
                    // If parsing fails, skip this custom rule to let the main validator handle format
                    return;
                }
                if ($new->isPast() && !$original->isPast()) {
                    $fail('The start date for a future event cannot be moved to the past.');
                }
            }],
            'endDate' => 'required|date_format:M-d-Y|after_or_equal:startDate',
            'isAllDay' => 'sometimes|boolean',
            'startTime' => 'nullable|date_format:H:i',
            'endTime' => ['nullable', 'date_format:H:i', function ($attribute, $value, $fail) use ($request) {
                $isAllDay = $request->boolean('isAllDay', false);
                $startTime = $request->input('startTime');
                $startDate = $request->input('startDate');
                $endDate = $request->input('endDate');
                if (!$isAllDay && $startTime && $value && $startDate === $endDate && strtotime($value) <= strtotime($startTime)) {
                    $fail('The end time must be after the start time for single-day events.');
                }
            }],
            'tags' => ['nullable', 'array'],
            'tags.*' => [Rule::exists('tags', 'id')->where(function ($query) {
                $query->where('archived', '!=', true);
            })],
            'memorandum' => 'nullable|array',
        ]);

        // Process image if base64 given
        $validated['image'] = $this->saveImage($validated['image'] ?? null, $event->image);

        // Extract and remove special keys
        $tagIds = $validated['tags'] ?? null;
        unset($validated['tags']);
        $memorandumData = $validated['memorandum'] ?? null;
        unset($validated['memorandum']);

        // Update event
        $event->fill($validated);
        $event->save();

        // Sync tags only if provided
        if ($tagIds !== null) {
            $event->tags()->sync($tagIds);
        }

        // Upsert/delete memorandum
        if ($memorandumData && !empty($memorandumData['content'])) {
            $memo = $event->memorandum ?: new \App\Models\Memorandum(['event_id' => $event->id]);
            $memo->type = $memorandumData['type'] ?? 'file';
            $memo->content = $memorandumData['content'] ?? null;
            $memo->filename = $memorandumData['filename'] ?? null;
            $memo->event_id = $event->id;
            $memo->save();
        } else {
            if ($event->memorandum) {
                $event->memorandum->delete();
            }
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Event updated successfully.']);
        }

        return back()->with('success', 'Event updated successfully.');
    }

    public function updateFromList(Request $request, $id)
    {
        $event = Event::findOrFail($id);

        // Normalize incoming dates to M-d-Y
        $request->merge([
            'startDate' => $this->normalizeDateInput($request->input('startDate')),
            'endDate' => $this->normalizeDateInput($request->input('endDate')),
        ]);

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('events', 'title')->ignore($event->id)],
            'description' => 'nullable|string',
            'image' => 'nullable|string',
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where(function ($query) {
                $query->where('archived', '!=', true);
            })],
            'venue' => 'nullable|string|max:255',
            'startDate' => ['sometimes', 'nullable', 'date_format:M-d-Y', function ($attribute, $value, $fail) use ($event) {
                if (!$value) return;
                if (!$event->startDate) return;
                try {
                    $original = \Carbon\Carbon::createFromFormat('M-d-Y', $event->startDate)->startOfDay();
                    $new = \Carbon\Carbon::createFromFormat('M-d-Y', $value)->startOfDay();
                } catch (\Exception $e) {
                    return; // let base validation handle format errors
                }
                if ($new->isPast() && !$original->isPast()) {
                    $fail('The start date for a future event cannot be moved to the past.');
                }
            }],
            'endDate' => 'sometimes|nullable|date_format:M-d-Y|after_or_equal:startDate',
            'isAllDay' => 'sometimes|boolean',
            'startTime' => ['sometimes', 'required_if:isAllDay,false', 'nullable', 'date_format:H:i'],
            'endTime' => ['sometimes', 'required_if:isAllDay,false', 'nullable', 'date_format:H:i', function ($attribute, $value, $fail) use ($request) {
                $isAllDay = $request->has('isAllDay') ? $request->boolean('isAllDay') : $request->input('isAllDay', false);
                $startTime = $request->input('startTime');
                $startDate = $request->input('startDate');
                $endDate = $request->input('endDate');
                if (!$isAllDay && $startTime && $value && $startDate === $endDate && strtotime($value) <= strtotime($startTime)) {
                    $fail('The end time must be after the start time for single-day events.');
                }
            }],
            'tags' => 'nullable|array',
            'tags.*' => ['sometimes', Rule::exists('tags', 'id')->where(function ($query) {
                $query->where('archived', '!=', true);
            })],
            'archived' => 'sometimes|boolean',
            'memorandum' => 'nullable|array',
        ]);

        // Extract special keys
        $tagIds = $validated['tags'] ?? null;
        unset($validated['tags']);
        $memorandumData = $validated['memorandum'] ?? null;
        unset($validated['memorandum']);

        // Process image
        $validated['image'] = $this->saveImage($validated['image'] ?? null, $event->image);

        // Update model
        $event->fill($validated);
        $event->save();

        if ($tagIds !== null) {
            $event->tags()->sync($tagIds);
        }

        // Handle memorandum upsert/delete only if key present in request
        if (array_key_exists('memorandum', $request->all())) {
            if ($memorandumData && !empty($memorandumData['content'])) {
                $memo = $event->memorandum ?: new \App\Models\Memorandum(['event_id' => $event->id]);
                $memo->type = $memorandumData['type'] ?? 'file';
                $memo->content = $memorandumData['content'] ?? null;
                $memo->filename = $memorandumData['filename'] ?? null;
                $memo->event_id = $event->id;
                $memo->save();
            } else {
                if ($event->memorandum) {
                    $event->memorandum->delete();
                }
            }
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }
        return redirect()->route('events.index')->with('success', 'Event updated successfully.');
    }

    public function archive($id)
    {
        $event = Event::findOrFail($id);
        $event->archived = true;
        $event->save();

        if (request()->wantsJson()) {
            return response()->json(['success' => true]);
        }
        return redirect()->route('events.index')->with('success', 'Event archived successfully.');
    }

    public function getArchivedEvents($type = 'events')
    {
        $archivedItems = [];

        if ($type === 'events') {
            $archivedItems = Event::where('archived', true)
                ->orderByDesc('startDate')
                ->get()
                ->toArray();
        } elseif ($type === 'tags') {
            $archivedItems = \App\Models\Tag::with('category')
                ->where('archived', true)
                ->get()
                ->map(function ($tag) {
                    return [
                        'id' => (string)$tag->id,
                        'name' => $tag->name,
                        'category_id' => $tag->category_id,
                        'category' => $tag->category,
                        'archived' => (bool)$tag->archived,
                    ];
                })->values()->toArray();
        } elseif ($type === 'categories') {
            $archivedItems = \App\Models\Category::where('archived', true)
                ->get()
                ->toArray();
        }

        return Inertia::render('List/Archive', [
            'type' => $type,
            'archivedItems' => $archivedItems,
            'categories' => Category::all(),
            'tags' => \App\Models\Tag::all(),
        ]);
    }

    public function restore($id)
    {
        $event = Event::findOrFail($id);
        $event->archived = false;
        $event->save();
        return back()->with('success', 'Event restored successfully');
    }

    public function permanentDelete($id)
    {
        $event = Event::with(['tasks', 'memorandum'])->findOrFail($id);

        \DB::transaction(function () use ($event) {
            // Delete dependent records
            // Activities
            \App\Models\Activity::where('event_id', $event->id)->delete();
            // Announcements
            \App\Models\Announcement::where('event_id', $event->id)->delete();
            // Brackets, Matches, MatchPlayers
            $brackets = \App\Models\Bracket::where('event_id', $event->id)->get();
            $bracketIds = $brackets->pluck('id');
            if ($bracketIds->isNotEmpty()) {
                \App\Models\Matches::whereIn('bracket_id', $bracketIds)->delete();
                \App\Models\MatchPlayer::whereIn('match_id', function ($q) use ($bracketIds) {
                    $q->select('id')->from('matches')->whereIn('bracket_id', $bracketIds);
                })->delete();
                \App\Models\Bracket::whereIn('id', $bracketIds)->delete();
            }
            // Tasks and pivot
            $taskIds = $event->tasks->pluck('id');
            if ($taskIds->isNotEmpty()) {
                \App\Models\TaskEmployee::whereIn('task_id', $taskIds)->delete();
                \App\Models\Task::whereIn('id', $taskIds)->delete();
            }
            // Pivot event_tags
            $event->tags()->detach();
            // Memorandum
            if ($event->memorandum) {
                $event->memorandum->delete();
            }
            // Delete image file from storage if applicable
            $this->deleteImage($event->image);
            // Finally, delete event
            $event->delete();
        });

        return back()->with('success', 'Event and all its related data were permanently deleted.');
    }

    public function updateDefaultImage(Request $request)
    {
        $request->validate([
            'image' => 'required|string',
        ]);

        // TODO: Replace with Settings model persistence when available
        $oldDefaultImage = null; // No persisted settings yet
        $newDefaultImage = $this->saveImage($request->input('image'), $oldDefaultImage);

        return back()->with([
            'success' => 'Default event image updated successfully.',
            'settings_prop' => ['defaultEventImage' => $newDefaultImage]
        ]);
    }
}
