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
    $data = $this->jsonData;
    $settings = $data['settings'] ?? ['defaultEventImage' => 'https://primefaces.org/cdn/primeng/images/demo/product/bamboo-watch.jpg'];
    $tagsCollection = collect($data['tags'] ?? []);
    $eventTags = collect($data['event_tags'] ?? []);
    $categoriesCollection = collect($data['category'] ?? [])->keyBy('id');

    // Pre-load task-related data for efficiency
    $allTasks = collect($data['tasks'] ?? []);
    $allEmployees = collect($data['employees'] ?? []);
    $allCommittees = collect($data['committees'] ?? []);
    $taskEmployeeMap = collect($data['task_employee'] ?? [])->groupBy('task_id');
    $allMemorandums = collect($data['memorandums'] ?? [])->keyBy('event_id');

    $events = collect($data['events'] ?? [])
        ->where('archived', '!=', true)
        ->sortByDesc(function ($event) {
            return strtotime($event['startDate'] ?? '1970-01-01');
        })
        ->map(function ($event) use ($tagsCollection, $eventTags, $categoriesCollection, $allTasks, $allEmployees, $allCommittees, $taskEmployeeMap, $allMemorandums) {
            // Get tag IDs for this event from event_tags pseudo-table
            $tagIds = $eventTags->where('event_id', $event['id'])->pluck('tag_id')->toArray();
            // Resolve tag objects
            $event['tags'] = collect($tagIds)->map(fn($tagId) => $tagsCollection->firstWhere('id', $tagId))->filter()->values()->toArray();

            // Resolve tasks for this event
            $event['tasks'] = $allTasks->where('event_id', $event['id'])->map(function ($task) use ($allEmployees, $allCommittees, $taskEmployeeMap) {
                $committee = $allCommittees->firstWhere('id', $task['committee_id']);
                $employeeIds = $taskEmployeeMap->get($task['id'], collect())->pluck('employee_id');
                $employees = $allEmployees->whereIn('id', $employeeIds)->values()->toArray();

                return [
                    'id' => $task['id'],
                    'task' => $task['description'], // Frontend expects 'task' key
                    'committee' => $committee,
                    'employees' => $employees,
                ];
            })->values()->toArray();

            // Resolve memorandum for this event
            $event['memorandum'] = $allMemorandums->get($event['id']);

            // Resolve category for this event
            $event['category'] = $categoriesCollection->get($event['category_id']);

            return $event;
        })->values()->toArray();

    if (request()->wantsJson()) {
        // For API calls, just return the events array.
        return response()->json($events);
    }

    return Inertia::render('List/EventList', [
        'events_prop' => $events,
        'tags_prop' => $data['tags'] ?? [],
        'committees_prop' => $data['committees'] ?? [], // Assuming committees are still in db.json
        'employees_prop' => $data['employees'] ?? [],
        'settings_prop' => $settings,
        'categories_prop' => $data['category'] ?? [],
        'memorandums_prop' => $data['memorandums'] ?? [],
    ]);
}

    public function show($id)
    {
        $json = File::get(base_path('db.json'));
        $data = json_decode($json, true);

        $tagsCollection = collect($data['tags'] ?? []);
        $eventTags = collect($data['event_tags'] ?? []);

        $event = collect($data['events'])->firstWhere('id', $id);
        $event['venue'] = $event['venue'] ?? null;

        // Get tag IDs for this event from event_tags pseudo-table
        $tagIds = $eventTags->where('event_id', $event['id'])->pluck('tag_id')->toArray();
        $event['tags'] = collect($tagIds)->map(fn($tagId) => $tagsCollection->firstWhere('id', $tagId))->filter()->values()->toArray();

        // Get current event's tag IDs
        $currentTagIds = collect($event['tags'])->pluck('id')->toArray();

        // Filter related events to only those sharing at least one tag AND not archived
        $relatedEvents = collect($data['events'])
            ->where('id', '!=', $id)
            ->where('archived', false)
            ->filter(function ($relatedEvent) use ($currentTagIds, $eventTags) {
                $relatedTagIds = $eventTags->where('event_id', $relatedEvent['id'])->pluck('tag_id')->toArray();
                return count(array_intersect($currentTagIds, $relatedTagIds)) > 0;
            })
            ->take(5)
            ->map(function ($relatedEvent) use ($tagsCollection, $eventTags) {
                $relatedTagIds = $eventTags->where('event_id', $relatedEvent['id'])->pluck('tag_id')->toArray();
                $relatedEvent['tags'] = collect($relatedTagIds)->map(fn($tagId) => $tagsCollection->firstWhere('id', $tagId))->filter()->values()->toArray();
                return $relatedEvent;
            })
            ->values()
            ->toArray();

        // Preload normalized activities, tasks, and announcements for this event
        $activities = collect($data['activities'] ?? [])->where('event_id', $id)->values()->toArray();
        $tasksRaw = collect($data['tasks'] ?? [])->where('event_id', $id)->values();
        $taskEmployeeMap = collect($data['task_employee'] ?? [])->groupBy('task_id');
        $allEmployees = collect($data['employees'] ?? []);
        $allCommittees = collect($data['committees'] ?? []);

        $tasks = $tasksRaw->map(function ($task) use ($allEmployees, $allCommittees, $taskEmployeeMap) {
            $committee = $allCommittees->firstWhere('id', $task['committee_id']);
            $employeeIds = $taskEmployeeMap->get($task['id'], collect())->pluck('employee_id');
            $employees = $allEmployees->whereIn('id', $employeeIds)->values()->toArray();
            return [
                'id' => $task['id'],
                'task' => $task['description'],
                'committee' => $committee,
                'employees' => $employees,
            ];
        })->values()->toArray();

        $announcementsRaw = collect($data['announcements'] ?? [])
            ->where('event_id', $id)
            ->sortByDesc(fn ($a) => strtotime($a['timestamp'] ?? '1970-01-01T00:00:00Z'))
            ->values();
        $userIds = $announcementsRaw->pluck('userId')->unique()->filter();
        $users = User::whereIn('id', $userIds)->get()->keyBy('id');
        $announcements = $announcementsRaw->map(function ($announcement) use ($users) {
            $user = $users->get($announcement['userId']);
            $announcement['employee'] = $user ? ['name' => $user->name] : ['name' => 'Admin'];
            return $announcement;
        })->toArray();

        // Find the memorandum for this event
        $memorandum = collect($data['memorandums'] ?? [])->firstWhere('event_id', (string) $id);

        // If the request wants JSON, return only the event data.
        if (request()->wantsJson()) {
            // Attach related data for API consumers
            $event['memorandum'] = $memorandum;
            return response()->json($event);
        }

        return Inertia::render('Events/EventDetails', [
            'event' => $event,
            'tags' => $tagsCollection->values()->toArray(),
            'committees' => $allCommittees->values()->toArray(),
            'employees' => $data['employees'] ?? [],
            'categories' => $data['category'] ?? [],
            'relatedEvents' => $relatedEvents,
            'preloadedActivities' => $activities,
            'preloadedTasks' => $tasks,
            'preloadedAnnouncements' => $announcements,
            'preloadedMemorandum' => $memorandum,
        ]);
    }

    public function dashboard()
    {
        $data = $this->jsonData;
        $events = collect($data['events'] ?? [])
            ->where('archived', '!=', true)
            ->sortByDesc(function ($event) {
                return strtotime($event['startDate'] ?? '1970-01-01');
            })
            ->values()
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
        $validCategoryIds = array_column($this->jsonData['category'] ?? [], 'id');
        $validTagIds = collect($this->jsonData['tags'] ?? [])->pluck('id')->toArray();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255', function ($attribute, $value, $fail) {
                $existingEvents = collect($this->jsonData['events']);
                if ($existingEvents->where('title', $value)->isNotEmpty()) {
                    $fail('The event title already exists.');
                }
            }],
            'description' => 'nullable|string',
            'image' => 'nullable|string',
            'category_id' => ['nullable', Rule::in($validCategoryIds)],
            'venue' => 'nullable|string|max:255', // Corrected position
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
            'tags' => ['nullable', 'array', function ($attribute, $value, $fail) use ($request, $validTagIds) {
                if (empty($value)) {
                    return;
                }
                $categoryId = $request->input('category_id');
                if (!$categoryId) {
                    $fail('A category must be selected to assign tags.');
                    return;
                }

                $tagsForCategory = collect($this->jsonData['tags'])->where('category_id', $categoryId)->pluck('id')->all();

                foreach ($value as $tagId) {
                    if (!in_array($tagId, $validTagIds)) {
                        $fail("The selected tag with id {$tagId} is invalid.");
                        return;
                    }
                    if (!in_array($tagId, $tagsForCategory)) {
                        $tag = collect($this->jsonData['tags'])->firstWhere('id', $tagId);
                        $tagName = $tag['name'] ?? $tagId;
                        $fail("The tag \"{$tagName}\" is not valid for the chosen category.");
                        return;
                    }
                }
            }],
            'archived' => 'boolean',
            'memorandum' => 'nullable|array',
        ]);

        $data = $this->jsonData;
        $newEvent = $validated;
        $newEvent['id'] = substr(md5(uniqid()), 0, 4);
        $newEvent['createdAt'] = now()->toISOString();
        $newEvent['type'] = 'event';

        // Handle image upload
        $newEvent['image'] = $this->saveImage($validated['image']); // No old image on create

        // Remove tags and memorandum from event object as they are handled separately
        unset($newEvent['tags']);
        $memorandumData = $validated['memorandum'] ?? null;
        unset($newEvent['memorandum']);

        array_unshift($data['events'], $newEvent);

        // Update event_tags pseudo-table
        $data['event_tags'] = collect($data['event_tags'] ?? [])
            ->filter(fn($et) => $et['event_id'] !== $newEvent['id'])
            ->values()
            ->toArray();

        foreach ($validated['tags'] ?? [] as $tagId) {
            $data['event_tags'][] = [
                'event_id' => $newEvent['id'],
                'tag_id' => $tagId
            ];
        }

        // Handle memorandum
        if ($memorandumData) {
            $data['memorandums'] = $data['memorandums'] ?? [];
            $newMemo = $memorandumData;
            $newMemo['id'] = (string) \Illuminate\Support\Str::uuid();
            $newMemo['event_id'] = $newEvent['id'];
            $data['memorandums'][] = $newMemo;
        }


        File::put(base_path('db.json'), json_encode($data, JSON_PRETTY_PRINT));

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'event' => $newEvent]);
        }
        return redirect()->route('events.index')->with([
            'success' => 'Event created successfully.',
            'event_id' => $newEvent['id'] // Pass the new event ID back
        ]);
    }

    public function update(Request $request, $id)
    {
        // This method is used by EventDetails.vue, we'll create a new one for EventList.vue
        // to avoid breaking existing functionality.
        return $this->updateFromDetails($request, $id);
    }

    public function updateFromDetails(Request $request, $id)
    {
        // Get valid IDs from JSON
        $validCategoryIds = array_column($this->jsonData['category'] ?? [], 'id');
        $validTagIds = array_column($this->jsonData['tags'] ?? [], 'id');

        // Custom validation rules
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255', function ($attribute, $value, $fail) use ($id) {
                $existingEvents = collect($this->jsonData['events']);
                if ($existingEvents->where('id', '!=', $id)->where('title', $value)->isNotEmpty()) {
                    $fail('The event title already exists.');
                }
            }],
            'description' => 'nullable|string',
            'image' => 'nullable|string',
            'category_id' => ['nullable', Rule::in($validCategoryIds)],
            'venue' => 'nullable|string|max:255', // Corrected position
            'startDate' => ['required', 'date_format:M-d-Y', function ($attribute, $value, $fail) use ($id) {
                $event = collect($this->jsonData['events'])->firstWhere('id', $id);
                if (!$event) return; // Should not happen if validation runs after finding the event

                $originalStartDate = \Carbon\Carbon::createFromFormat('M-d-Y', $event['startDate'])->startOfDay();
                $newStartDate = \Carbon\Carbon::createFromFormat('M-d-Y', $value)->startOfDay();

                if ($newStartDate->isPast() && !$originalStartDate->isPast()) {
                    $fail('The start date for a future event cannot be moved to the past.');
                }
            }],
            'endDate' => 'required|date_format:M-d-Y|after_or_equal:startDate',
            'isAllDay' => 'nullable|boolean',
            'startTime' => 'nullable|date_format:H:i',
            'endTime' => ['nullable', 'date_format:H:i', function ($attribute, $value, $fail) use ($request) {
                $isAllDay = $request->boolean('isAllDay', false); // Assume false if not present
                $startTime = $request->input('startTime');
                $startDate = $request->input('startDate');
                $endDate = $request->input('endDate');

                if (!$isAllDay && $startTime && $value && $startDate === $endDate && strtotime($value) <= strtotime($startTime)) {
                    $fail('The end time must be after the start time for single-day events.');
                }
            }],
            'tags' => ['nullable', 'array', function ($attribute, $value, $fail) use ($request, $validTagIds) {
                if (empty($value)) {
                    return;
                }
                $categoryId = $request->input('category_id');
                if (!$categoryId) {
                    $fail('A category must be selected to assign tags.');
                    return;
                }

                $tagsForCategory = collect($this->jsonData['tags'])->where('category_id', $categoryId)->pluck('id')->all();

                foreach ($value as $tagId) {
                    if (!in_array($tagId, $validTagIds)) {
                        $fail("The selected tag with id {$tagId} is invalid.");
                        return;
                    }
                    if (!in_array($tagId, $tagsForCategory)) {
                        $tag = collect($this->jsonData['tags'])->firstWhere('id', $tagId);
                        $tagName = $tag['name'] ?? $tagId;
                        $fail("The tag \"{$tagName}\" is not valid for the chosen category.");
                        return;
                    }
                }
            }],
            'memorandum' => 'nullable|array', // Allow memorandum to be part of the update
        ]);

        // Separate tags and memorandum from the event data to prevent it from being merged into the event object.
        $tagIdsToUpdate = $validated['tags'] ?? null;
        unset($validated['tags']);
        $memorandumData = $validated['memorandum'] ?? null;
        unset($validated['memorandum']);

        // Update the event
        foreach ($this->jsonData['events'] as &$event) {
            if ($event['id'] == $id) {
                // Handle image upload if a new image is provided
                $validated['image'] = $this->saveImage($validated['image'], $event['image']);

                // Explicitly merge validated data to be more intentional about what is being updated.
                // The 'tags' and 'memorandum' keys are now unset from $validated, so they won't be merged here.
                $event = array_merge($event, $validated);
                break;
            }
        }

        // Update event_tags pseudo-table
        $this->jsonData['event_tags'] = collect($this->jsonData['event_tags'] ?? [])
            ->filter(fn($et) => $et['event_id'] !== $id)
            ->values()
            ->toArray();

        foreach ($tagIdsToUpdate ?? [] as $tagId) {
            $this->jsonData['event_tags'][] = [
                'event_id' => $id,
                'tag_id' => $tagId
            ];
        }

        // Handle memorandum update/creation/deletion.
        // This logic is now self-contained and doesn't rely on a separate client-side DELETE call.
        // $memorandumData is already set from the validation step above.
        $this->jsonData['memorandums'] = $this->jsonData['memorandums'] ?? [];
        $existingMemoIndex = collect($this->jsonData['memorandums'])->search(fn($memo) => $memo['event_id'] === $id);

        if ($memorandumData && !empty($memorandumData['content'])) { // If new/updated data is provided
            $newMemo = $memorandumData;
            $newMemo['id'] = ($existingMemoIndex !== false) ? $this->jsonData['memorandums'][$existingMemoIndex]['id'] : (string) \Illuminate\Support\Str::uuid();
            $newMemo['event_id'] = $id;
            if ($existingMemoIndex !== false) {
                $this->jsonData['memorandums'][$existingMemoIndex] = $newMemo;
            } else {
                $this->jsonData['memorandums'][] = $newMemo;
            }
        } elseif ($existingMemoIndex !== false) { // If no data is provided (null) and one exists, delete it
            array_splice($this->jsonData['memorandums'], $existingMemoIndex, 1);
        }

        try {
            $this->writeJson($this->jsonData);

            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'message' => 'Event updated successfully.']);
            }

            return back()->with('success', 'Event updated successfully.');
        } catch (\Exception $e) {
            $message = 'Failed to save event: '.$e->getMessage();
            return $request->wantsJson() ? response()->json(['success' => false, 'error' => $message], 500) : back()->with('error', $message);
        }
    }

    public function updateFromList(Request $request, $id)
    {
        $validCategoryIds = array_column($this->jsonData['category'] ?? [], 'id');
        $validTagIds = array_column($this->jsonData['tags'] ?? [], 'id');

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255', function ($attribute, $value, $fail) use ($id) {
                if (collect($this->jsonData['events'])->where('id', '!=', $id)->where('title', $value)->isNotEmpty()) {
                    $fail('The event title already exists.');
                }
            }],
            'description' => 'nullable|string',
            'image' => 'nullable|string',
            'category_id' => ['nullable', Rule::in($validCategoryIds)],
            'venue' => 'nullable|string|max:255',
            'startDate' => ['sometimes', 'nullable', 'date_format:M-d-Y', function ($attribute, $value, $fail) use ($id) {
                if (!$value) return;
                $event = collect($this->jsonData['events'])->firstWhere('id', $id);
                if (!$event) return;

                $originalStartDate = \Carbon\Carbon::createFromFormat('M-d-Y', $event['startDate'])->startOfDay();
                $newStartDate = \Carbon\Carbon::createFromFormat('M-d-Y', $value)->startOfDay();

                if ($newStartDate->isPast() && !$originalStartDate->isPast()) {
                    $fail('The start date for a future event cannot be moved to the past.');
                }
            }],
            'endDate' => 'sometimes|nullable|date_format:M-d-Y|after_or_equal:startDate',
            'isAllDay' => 'sometimes|boolean',
            'startTime' => ['sometimes', 'required_if:isAllDay,false', 'nullable', 'date_format:H:i'],
            'endTime' => ['sometimes', 'required_if:isAllDay,false', 'nullable', 'date_format:H:i', function ($attribute, $value, $fail) use ($request) {
                // Use request's validated data if available, otherwise fallback to input
                $isAllDay = $request->has('isAllDay') ? $request->boolean('isAllDay') : $request->input('isAllDay', false);
                $startTime = $request->input('startTime');
                $startDate = $request->input('startDate');
                $endDate = $request->input('endDate');

                if (!$isAllDay && $startTime && $value && $startDate === $endDate && strtotime($value) <= strtotime($startTime)) {
                    $fail('The end time must be after the start time for single-day events.');
                }
            }],
            'tags' => 'nullable|array',
            'tags.*' => ['sometimes', Rule::in($validTagIds)],
            'archived' => 'sometimes|boolean',
            'memorandum' => 'nullable|array',
        ]);

        $data = $this->jsonData;
        $eventFound = false;

        foreach ($data['events'] as &$event) {
            if ($event['id'] == $id) {
                // Check if the event currently has tags in the event_tags table
                $hasExistingTags = collect($this->jsonData['event_tags'] ?? [])->where('event_id', $id)->isNotEmpty();

                // // Safety feature: do not overwrite existing tags with an empty array from the list view.
                // // This prevents accidental removal of all tags.
                // if (isset($validated['tags']) && empty($validated['tags']) && $hasExistingTags) {
                //     unset($validated['tags']);
                // }

                // Separate tags and memorandum from the event data to prevent it from being merged into the event object.
                $tagIdsToUpdate = $validated['tags'] ?? null;
                unset($validated['tags']);
                $memorandumData = $validated['memorandum'] ?? null;
                unset($validated['memorandum']);

                // Handle image upload if a new image is provided
                $validated['image'] = $this->saveImage($validated['image'], $event['image']);

                // Explicitly merge validated data to be more intentional about what is being updated.
                // The 'tags' key is now unset from $validated, so it won't be merged here.
                $event = array_merge($event, $validated);
                $eventFound = true;
                break;
            }
        }

        if (!$eventFound) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'error' => 'Event not found.'], 404);
            }
            return back()->with('error', 'Event not found.');
        }

        // Only update tags if the 'tags' key was passed in the validated data.
        // The safety feature inside the loop would have unset 'tags' if it was an unsafe empty update.
        if ($tagIdsToUpdate !== null) {
            // Update event_tags pseudo-table
            $data['event_tags'] = collect($data['event_tags'] ?? [])
                ->filter(fn($et) => $et['event_id'] !== $id)
                ->values()
                ->toArray();

            foreach ($tagIdsToUpdate as $tagId) {
                $data['event_tags'][] = [
                    'event_id' => $id,
                    'tag_id' => $tagId
                ];
            }
        }

        // Handle memorandum update/creation/deletion
        if (array_key_exists('memorandum', $request->all())) {
            // $memorandumData is already set from the loop above
            $data['memorandums'] = $data['memorandums'] ?? [];
            $existingMemoIndex = collect($data['memorandums'])->search(fn($memo) => $memo['event_id'] === $id);

            if ($memorandumData) {
                if ($existingMemoIndex !== false) {
                    // Update existing
                    $data['memorandums'][$existingMemoIndex] = array_merge($data['memorandums'][$existingMemoIndex], $memorandumData);
                } else {
                    // Create new
                    $newMemo = $memorandumData;
                    $newMemo['id'] = (string) \Illuminate\Support\Str::uuid();
                    $newMemo['event_id'] = $id;
                    $data['memorandums'][] = $newMemo;
                }
            } elseif ($existingMemoIndex !== false) {
                array_splice($data['memorandums'], $existingMemoIndex, 1);
            }
        }

        $this->writeJson($data);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }
        return redirect()->route('events.index')->with('success', 'Event updated successfully.');
    }

    public function archive($id)
    {
        $this->jsonData['events'] = collect($this->jsonData['events'])->map(function ($event) use ($id) {
            if ($event['id'] == $id) {
                $event['archived'] = true;
            }
            return $event;
        })->toArray();

        $this->writeJson($this->jsonData);

        if (request()->wantsJson()) {
            return response()->json(['success' => true]);
        }
        return redirect()->route('events.index')->with('success', 'Event archived successfully.');
    }

    public function getArchivedEvents($type = 'events')
    {
        $json = File::get(base_path('db.json'));
        $data = json_decode($json, true);
        $categories = $data['category'] ?? [];
        $archivedItems = [];

        if ($type === 'events') {
            $archivedItems = collect($data['events'] ?? [])
                ->where('archived', true)
                ->sortByDesc(function ($event) {
                    return strtotime($event['startDate'] ?? '1970-01-01');
                })
                ->values()
                ->toArray();
        } elseif ($type === 'tags') {
            $categoryMap = collect($categories)->keyBy('id');
            $archivedItems = collect($data['tags'] ?? [])
                ->where('archived', true)
                ->map(function ($tag) use ($categoryMap) {
                    $tag['category'] = $categoryMap->get($tag['category_id']);
                    return $tag;
                })
                ->sortBy('name')
                ->values()
                ->toArray();
        }

        return Inertia::render('List/Archive', [
            'type' => $type,
            'archivedItems' => $archivedItems,
            'categories' => $categories,
            // Pass other necessary props if any, e.g., tags for event filtering
            'tags' => $data['tags'] ?? [],
        ]);
    }

    public function restore($id)
    {
        try {
            $json = File::get(base_path('db.json'));
            $data = json_decode($json, true);

            foreach ($data['events'] as &$event) {
                if ($event['id'] == $id) {
                    $event['archived'] = false;
                    break;
                }
            }

            $this->writeJson($data);
            return back()->with('success', 'Event restored successfully');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to restore event');
        }
    }

    public function permanentDelete($id)
    {
        try {
            $json = File::get(base_path('db.json'));
            $data = json_decode($json, true);

            // Find the event to get the image path before deleting the record
            $eventToDelete = collect($data['events'] ?? [])->firstWhere('id', $id);

            // --- Start of cascading delete logic ---

            // 1. Get IDs of related items before deleting them
            $bracketIdsToDelete = collect($data['brackets'] ?? [])->where('event_id', $id)->pluck('id')->all();
            $matchIdsToDelete = collect($data['matches'] ?? [])->whereIn('bracket_id', $bracketIdsToDelete)->pluck('id')->all();
            $taskIdsToDelete = collect($data['tasks'] ?? [])->where('event_id', $id)->pluck('id')->all();

            // 2. Filter out all related data
            $collectionsToDeleteFrom = [
                'events' => 'id',
                'brackets' => 'event_id',
                'activities' => 'event_id',
                'announcements' => 'event_id',
                'memorandums' => 'event_id',
                'event_tags' => 'event_id',
                'tasks' => 'event_id',
            ];

            foreach ($collectionsToDeleteFrom as $collection => $key) {
                if (isset($data[$collection])) {
                    $data[$collection] = collect($data[$collection])
                        ->filter(fn($item) => ($item[$key] ?? null) != $id)
                        ->values()
                        ->toArray();
                }
            }

            // 3. Delete matches and match players related to the event's brackets
            if (!empty($bracketIdsToDelete)) {
                if (isset($data['matches'])) {
                    $data['matches'] = collect($data['matches'])
                        ->filter(fn($match) => !in_array($match['bracket_id'], $bracketIdsToDelete))
                        ->values()->toArray();
                }
                if (isset($data['matchPlayers']) && !empty($matchIdsToDelete)) {
                    $data['matchPlayers'] = collect($data['matchPlayers'])
                        ->filter(fn($player) => !in_array($player['match_id'], $matchIdsToDelete))
                        ->values()->toArray();
                }
            }

            // 4. Delete task-employee assignments
            if (!empty($taskIdsToDelete) && isset($data['task_employee'])) {
                $data['task_employee'] = collect($data['task_employee'])
                    ->filter(fn($assignment) => !in_array($assignment['task_id'], $taskIdsToDelete))
                    ->values()->toArray();
            }

            // 5. Delete the associated image file from storage
            if ($eventToDelete && isset($eventToDelete['image'])) {
                $this->deleteImage($eventToDelete['image']);
            }

            $this->writeJson($data);
            return back()->with('success', 'Event and all its related data were permanently deleted.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete event');
        }
    }

    public function updateDefaultImage(Request $request)
    {
        $request->validate([
            'image' => 'required|string',
        ]);

        $data = $this->jsonData; // Use a local variable for clarity

        if (!isset($data['settings'])) {
            $data['settings'] = [];
        }

        $oldDefaultImage = $data['settings']['defaultEventImage'] ?? null;
        $newDefaultImage = $this->saveImage($request->input('image'), $oldDefaultImage);

        $data['settings']['defaultEventImage'] = $newDefaultImage;

        $this->writeJson($data);

        return back()->with([
            'success' => 'Default event image updated successfully.',
            'settings_prop' => $data['settings'] // Pass back the modified local variable
        ]);
    }
}
