<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\Event;
use App\Models\User;
use Inertia\Inertia;
use Illuminate\Support\Facades\File;
use App\Models\Category;

class EventController extends Controller
{
    public function __construct()
    {
        $this->jsonData = json_decode(File::get(base_path('db.json')), true);
    }

    public function index()
{
    $data = $this->jsonData;
    $settings = $data['settings'] ?? ['defaultEventImage' => 'https://primefaces.org/cdn/primeng/images/demo/product/bamboo-watch.jpg'];
    $tagsCollection = collect($data['tags'] ?? []);
    $eventTags = collect($data['event_tags'] ?? []);

    // Pre-load task-related data for efficiency
    $allTasks = collect($data['tasks'] ?? []);
    $allEmployees = collect($data['employees'] ?? []);
    $allCommittees = collect($data['committees'] ?? []);
    $taskEmployeeMap = collect($data['task_employee'] ?? [])->groupBy('task_id');

    $events = collect($data['events'] ?? [])
        ->where('archived', '!=', true)
        ->sortByDesc(function ($event) {
            return strtotime($event['startDate'] ?? '1970-01-01');
        })
        ->map(function ($event) use ($tagsCollection, $eventTags, $allTasks, $allEmployees, $allCommittees, $taskEmployeeMap) {
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

        $announcements = collect($data['announcements'] ?? [])->where('event_id', $id)->sortByDesc(function ($a) {
            return strtotime($a['timestamp'] ?? '1970-01-01T00:00:00Z');
        })->values()->toArray();

        // If the request wants JSON, return only the event data.
        if (request()->wantsJson()) {
            return response()->json($event);
        }

        return Inertia::render('Events/EventDetails', [
            'event' => $event,
            'tags' => $data['tags'] ?? [],
            'committees' => $data['committees'] ?? [],
            'employees' => $data['employees'] ?? [],
            'categories' => $data['category'] ?? [],
            'relatedEvents' => $relatedEvents,
            'preloadedActivities' => $activities,
            'preloadedTasks' => $tasks,
            'preloadedAnnouncements' => $announcements,
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

    public function store(Request $request)
    {
        $validCategoryIds = array_column($this->jsonData['category'] ?? [], 'id');
        $validTagIds = collect($this->jsonData['tags'] ?? [])->pluck('id')->toArray();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|string',
            'category_id' => ['nullable', Rule::in($validCategoryIds)],
            'venue' => 'nullable|string|max:255',
            'startDate' => ['nullable', 'string', function ($attribute, $value, $fail) {
                if ($value && !preg_match('/^[A-Za-z]{3}-\d{2}-\d{4}$/', $value)) {
                    $fail('The start date must be in MMM-DD-YYYY format.');
                }
            }],
            'endDate' => ['nullable', 'string', function ($attribute, $value, $fail) use ($request) {
                if ($value && !preg_match('/^[A-Za-z]{3}-\d{2}-\d{4}$/', $value)) {
                    $fail('The end date must be in MMM-DD-YYYY format.');
                }
                if ($request->input('startDate') && $value) {
                    $startDate = \DateTime::createFromFormat('M-d-Y', $request->input('startDate'));
                    $endDate = \DateTime::createFromFormat('M-d-Y', $value);
                    if ($endDate < $startDate) {
                        $fail('The end date must be on or after the start date.');
                    }
                }
            }],
            'startTime' => 'nullable|date_format:H:i',
            'endTime' => 'nullable|date_format:H:i',
            'isAllDay' => 'boolean',
            'isAllDay' => ['required', 'boolean'],
            'startTime' => ['required_if:isAllDay,false', 'nullable', 'date_format:H:i'],
            'endTime' => ['required_if:isAllDay,false', 'nullable', 'date_format:H:i', function ($attribute, $value, $fail) use ($request) {
                if ($request->input('startDate') == $request->input('endDate') && strtotime($value) <= strtotime($request->input('startTime'))) {
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
            'memorandum.type' => 'required_with:memorandum|string|in:image,file',
            'memorandum.content' => 'required_with:memorandum|string',
            'memorandum.filename' => 'nullable|string',
        ]);

        $data = $this->jsonData;
        $newEvent = $validated;
        $newEvent['id'] = substr(md5(uniqid()), 0, 4);
        $newEvent['createdAt'] = now()->toISOString();
        $newEvent['tasks'] = [];
        $newEvent['activities'] = [];
        $newEvent['type'] = 'event';

        // Remove tags from event object
        unset($newEvent['tags']);
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

        File::put(base_path('db.json'), json_encode($data, JSON_PRETTY_PRINT));

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'event' => $newEvent]);
        }
        return redirect()->route('events.index')->with('success', 'Event created successfully.');
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
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|string',
            'category_id' => ['nullable', Rule::in($validCategoryIds)],
            'venue' => 'nullable|string|max:255',
            'startDate' => ['required', 'string', function ($attribute, $value, $fail) {
                if (!preg_match('/^[A-Za-z]{3}-\d{2}-\d{4}$/', $value)) {
                    $fail('The '.$attribute.' must be in MMM-DD-YYYY format (e.g. May-28-2025)');
                }
            }],
            'endDate' => ['required', 'string', function ($attribute, $value, $fail) use ($request) {
                if (!preg_match('/^[A-Za-z]{3}-\d{2}-\d{4}$/', $value)) {
                    $fail('The '.$attribute.' must be in MMM-DD-YYYY format (e.g. May-28-2025)');
                }

                // Convert to DateTime for comparison
                $startDate = \DateTime::createFromFormat('M-d-Y', $request->input('startDate'));
                $endDate = \DateTime::createFromFormat('M-d-Y', $value);

                if ($endDate < $startDate) {
                    $fail('The end date must be after or equal to the start date');
                }
            }],
            'startTime' => 'nullable|date_format:H:i',
            'endTime' => ['nullable', 'date_format:H:i', function ($attribute, $value, $fail) use ($request) {
                $startTime = $request->input('startTime');
                if ($request->input('startDate') == $request->input('endDate') && strtotime($value) <= strtotime($startTime)) {
                    $fail('The end time must be after the start time');
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
                        'memorandum' => 'nullable|array',
            'memorandum.type' => 'required_with:memorandum|string|in:image,file',
            'memorandum.content' => 'required_with:memorandum|string',
            'memorandum.filename' => 'nullable|string',
        ]);

        // Update the event
        foreach ($this->jsonData['events'] as &$event) {
            if ($event['id'] == $id) {
                $event = array_merge($event, $validated);
                unset($event['tags']); // Tags are managed in event_tags table
                break;
            }
        }

        // Update event_tags pseudo-table
        $this->jsonData['event_tags'] = collect($this->jsonData['event_tags'] ?? [])
            ->filter(fn($et) => $et['event_id'] !== $id)
            ->values()
            ->toArray();

        foreach ($validated['tags'] ?? [] as $tagId) {
            $this->jsonData['event_tags'][] = [
                'event_id' => $id,
                'tag_id' => $tagId
            ];
        }

        try {
            File::put(base_path('db.json'), json_encode($this->jsonData, JSON_PRETTY_PRINT));
            return back()->with('success', 'Event updated successfully.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to save event: '.$e->getMessage());
        }
    }

    public function updateFromList(Request $request, $id)
    {
        $validCategoryIds = array_column($this->jsonData['category'] ?? [], 'id');
        $validTagIds = array_column($this->jsonData['tags'] ?? [], 'id');

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|string',
            'category_id' => ['nullable', Rule::in($validCategoryIds)],
            'venue' => 'nullable|string|max:255',
            'startDate' => 'nullable|date_format:M-d-Y',
            'endDate' => 'nullable|date_format:M-d-Y|after_or_equal:startDate',
            'isAllDay' => 'sometimes|boolean',
            'startTime' => ['sometimes', 'required_if:isAllDay,false', 'nullable', 'date_format:H:i'],
            'endTime' => ['sometimes', 'required_if:isAllDay,false', 'nullable', 'date_format:H:i', function ($attribute, $value, $fail) use ($request) {
                if ($request->input('startDate') && $request->input('endDate') && $request->input('startDate') == $request->input('endDate') && $request->input('startTime') && $value) {
                    if (strtotime($value) <= strtotime($request->input('startTime'))) {
                        $fail('The end time must be after the start time for single-day events.');
                    }
                }
            }],
            'tags' => 'nullable|array',
            'tags.*' => ['sometimes', Rule::in($validTagIds)],
            'archived' => 'sometimes|boolean',
            // Memorandum validation for list view update
            'memorandum' => 'nullable|array',
            'memorandum.type' => 'required_with:memorandum|string|in:image,file',
            'memorandum.content' => 'required_with:memorandum|string',
            'memorandum.filename' => 'nullable|string',
        ]);

        $data = $this->jsonData;
        $eventFound = false;

        foreach ($data['events'] as &$event) {
            if ($event['id'] == $id) {
                // Check if the event currently has tags in the event_tags table
                $hasExistingTags = collect($this->jsonData['event_tags'] ?? [])->where('event_id', $id)->isNotEmpty();

                // Safety feature: do not overwrite existing tags with an empty array from the list view.
                // This prevents accidental removal of all tags.
                if (isset($validated['tags']) && empty($validated['tags']) && $hasExistingTags) {
                    unset($validated['tags']);
                }

                // Safety feature for memorandum: do not overwrite with null if not provided from list view
                // This prevents accidental removal.
                if (array_key_exists('memorandum', $validated) && is_null($validated['memorandum'])) {
                    unset($validated['memorandum']);
                }

                // Explicitly merge validated data to be more intentional about what is being updated.
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
        if (array_key_exists('tags', $validated)) {
            // Update event_tags pseudo-table
            $data['event_tags'] = collect($data['event_tags'] ?? [])
                ->filter(fn($et) => $et['event_id'] !== $id)
                ->values()
                ->toArray();

            foreach (($validated['tags'] ?? []) as $tagId) {
                $data['event_tags'][] = [
                    'event_id' => $id,
                    'tag_id' => $tagId
                ];
            }
        }

        File::put(base_path('db.json'), json_encode($data, JSON_PRETTY_PRINT));

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

        File::put(base_path('db.json'), json_encode($this->jsonData, JSON_PRETTY_PRINT));

        if (request()->wantsJson()) {
            return response()->json(['success' => true]);
        }
        return redirect()->route('events.index')->with('success', 'Event archived successfully.');
    }

    public function getArchivedEvents()
    {
        $json = File::get(base_path('db.json'));
        $data = json_decode($json, true);

        $archivedEvents = collect($data['events'] ?? [])
            ->where('archived', true)
            ->sortByDesc(function ($event) {
                return strtotime($event['startDate'] ?? '1970-01-01');
            })
            ->values()
            ->toArray();

        $categories = $data['category'] ?? [];

        return Inertia::render('List/Archive', [
            'archivedEvents' => $archivedEvents,
            'categories' => $categories
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

            File::put(base_path('db.json'), json_encode($data, JSON_PRETTY_PRINT));
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

            // Filter out the event to be deleted
            $data['events'] = collect($data['events'])
                ->filter(function($event) use ($id) {
                    return $event['id'] != $id;
                })
                ->values()
                ->toArray();

            // Also filter out any brackets associated with the deleted event
            if (isset($data['brackets'])) {
                $data['brackets'] = collect($data['brackets'])
                    ->filter(function($bracket) use ($id) {
                        return ($bracket['event_id'] ?? null) != $id;
                    })
                    ->values()
                    ->toArray();
            }

            File::put(base_path('db.json'), json_encode($data, JSON_PRETTY_PRINT));
            return back()->with('success', 'Event and its associated brackets were permanently deleted.');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete event');
        }
    }

    public function updateDefaultImage(Request $request)
    {
        $request->validate([
            'image' => 'required|string',
        ]);

        $newDefaultImage = $request->input('image');
        $data = $this->jsonData; // Use a local variable for clarity

        if (!isset($data['settings'])) {
            $data['settings'] = [];
        }

        $data['settings']['defaultEventImage'] = $newDefaultImage;

        File::put(base_path('db.json'), json_encode($data, JSON_PRETTY_PRINT));

        return back()->with([
            'success' => 'Default event image updated successfully.',
            'settings_prop' => $data['settings'] // Pass back the modified local variable
        ]);
    }
}
