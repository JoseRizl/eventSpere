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
    $events = collect($data['events'] ?? [])
        ->where('archived', '!=', true)
        ->sortByDesc(function ($event) {
            return strtotime($event['startDate'] ?? '1970-01-01');
        })
        ->values()
        ->toArray();

    if (request()->wantsJson()) {
        return response()->json([
            'events_prop' => $events,
            'tags_prop' => $data['tags'] ?? [],
            'committees_prop' => $data['committees'] ?? [], // Assuming committees are still in db.json
            'employees_prop' => User::all(['id', 'name']),
            'categories_prop' => $data['category'] ?? [],
        ]);
    }

    return Inertia::render('List/EventList', [
        'events_prop' => $events,
        'tags_prop' => $data['tags'] ?? [],
        'committees_prop' => $data['committees'] ?? [], // Assuming committees are still in db.json
        'employees_prop' => User::all(['id', 'name']),
        'categories_prop' => $data['category'] ?? [],
    ]);
}

    public function show($id)
    {
        $json = File::get(base_path('db.json'));
        $data = json_decode($json, true);

        $event = collect($data['events'])->firstWhere('id', $id);
        $event['venue'] = $event['venue'] ?? null;

        // Normalize tags to ensure they are all objects
        $event['tags'] = collect($event['tags'] ?? [])->map(function($tag) use ($data) {
            if (is_array($tag) && isset($tag['id'])) {
                return $tag;
            }
            // If it's just an ID, find the corresponding tag object
            $tagObj = collect($data['tags'])->firstWhere('id', $tag);
            return $tagObj ?? ['id' => $tag, 'name' => 'Unknown Tag', 'color' => '#cccccc'];
        })->values()->toArray();

        // Get current event's tag IDs
        $currentTagIds = collect($event['tags'])->pluck('id')->toArray();

        // Filter related events to only those sharing at least one tag AND not archived
        $relatedEvents = collect($data['events'])
            ->where('id', '!=', $id)
            ->where('archived', false)
            ->filter(function ($relatedEvent) use ($currentTagIds) {
                $relatedTagIds = collect($relatedEvent['tags'])->map(function($tag) {
                    return is_array($tag) ? $tag['id'] : $tag;
                })->toArray();
                return count(array_intersect($currentTagIds, $relatedTagIds)) > 0;
            })
            ->take(5)
            ->values()
            ->toArray();

        // Ensure tasks array exists and has proper structure
        if (!isset($event['tasks']) || !is_array($event['tasks'])) {
            $event['tasks'] = [];
        } else {
            // Ensure each task has the required structure with multiple employees
            $event['tasks'] = array_map(function($task) use ($data) {
                // Handle committee
                $committee = null;
                if (isset($task['committee'])) {
                    if (is_array($task['committee']) && isset($task['committee']['id'])) {
                        $committeeObj = collect($data['committees'])->firstWhere('id', $task['committee']['id']);
                        $committee = $committeeObj ?? ['id' => $task['committee']['id'], 'name' => 'Unknown Committee'];
                    } else {
                        $committeeObj = collect($data['committees'])->firstWhere('id', $task['committee']);
                        $committee = $committeeObj ?? ['id' => $task['committee'], 'name' => 'Unknown Committee'];
                    }
                }

                // Handle employees array
                $employees = [];
                if (isset($task['employees']) && is_array($task['employees'])) {
                    $employees = collect($task['employees'])->map(function($emp) use ($data) {
                        if (is_array($emp) && isset($emp['id'])) {
                            $empObj = collect($data['employees'])->firstWhere('id', $emp['id']);
                            return $empObj ?? ['id' => $emp['id'], 'name' => 'Unknown Employee'];
                        }
                        $empObj = collect($data['employees'])->firstWhere('id', $emp);
                        return $empObj ?? ['id' => $emp, 'name' => 'Unknown Employee'];
                    })->values()->toArray();
                } elseif (isset($task['employee'])) {
                    // Handle legacy single employee format
                    if (is_array($task['employee']) && isset($task['employee']['id'])) {
                        $empObj = collect($data['employees'])->firstWhere('id', $task['employee']['id']);
                        $employees = [$empObj ?? ['id' => $task['employee']['id'], 'name' => 'Unknown Employee']];
                    } else {
                        $empObj = collect($data['employees'])->firstWhere('id', $task['employee']);
                        $employees = [$empObj ?? ['id' => $task['employee'], 'name' => 'Unknown Employee']];
                    }
                }

                return [
                    'committee' => $committee,
                    'employees' => $employees,
                    'task' => $task['task'] ?? ''
                ];
            }, $event['tasks']);
        }

        return Inertia::render('Events/EventDetails', [
            'event' => $event,
            'tags' => $data['tags'] ?? [],
            'committees' => $data['committees'] ?? [],
            'employees' => User::all(['id', 'name']),
            'categories' => $data['category'] ?? [],
            'relatedEvents' => $relatedEvents,
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
                    $fail('The '.$attribute.' must be in MMM-DD-YYYY format.');
                }
            }],
            'endDate' => ['nullable', 'string', function ($attribute, $value, $fail) use ($request) {
                if ($value && !preg_match('/^[A-Za-z]{3}-\d{2}-\d{4}$/', $value)) {
                    $fail('The '.$attribute.' must be in MMM-DD-YYYY format.');
                }
                if ($request->input('startDate') && $value) {
                    $startDate = \DateTime::createFromFormat('M-d-Y', $request->input('startDate'));
                    $endDate = \DateTime::createFromFormat('M-d-Y', $value);
                    if ($endDate < $startDate) {
                        $fail('The end date must be on or after the start date.');
                    }
                }
            }],
            'startTime' => 'required|date_format:H:i',
            'endTime' => 'required|date_format:H:i',
            'tags' => 'nullable|array',
            'tags.*' => ['sometimes', Rule::in($validTagIds)],
            'isAllDay' => 'boolean',
            'archived' => 'boolean',
        ]);

        $data = $this->jsonData;
        $newEvent = $validated;
        $newEvent['id'] = substr(md5(uniqid()), 0, 4);
        $newEvent['createdAt'] = now()->toISOString();
        $newEvent['tasks'] = [];
        $newEvent['scheduleLists'] = [];
        $newEvent['type'] = 'event';

        // Normalize tags to be objects if they are just IDs
        $newEvent['tags'] = collect($validated['tags'] ?? [])->map(function($tagId) use ($data) {
            return collect($data['tags'])->firstWhere('id', $tagId);
        })->filter()->values()->toArray();

        array_unshift($data['events'], $newEvent);

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
        $validCommitteeIds = array_column($this->jsonData['committees'] ?? [], 'id');
        $validEmployeeIds = array_column($this->jsonData['employees'] ?? [], 'id');

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
            'startTime' => 'required|date_format:H:i',
            'endTime' => ['required', 'date_format:H:i', function ($attribute, $value, $fail) use ($request) {
                $startTime = $request->input('startTime');
                if (strtotime($value) <= strtotime($startTime)) {
                    $fail('The end time must be after the start time');
                }
            }],
            'tags' => 'nullable|array',
            'tags.*' => ['required', Rule::in($validTagIds)],
            'scheduleLists' => 'nullable|array',
            'scheduleLists.*.day' => 'required|integer|min:1',
            'scheduleLists.*.date' => ['required', 'string', function ($attribute, $value, $fail) {
                if (!preg_match('/^[A-Za-z]{3}-\d{2}-\d{4}$/', $value)) {
                    $fail('The '.$attribute.' must be in MMM-DD-YYYY format (e.g. May-28-2025)');
                }
            }],
            'scheduleLists.*.schedules' => 'nullable|array',
            'scheduleLists.*.schedules.*.time' => 'nullable|date_format:H:i',
            'scheduleLists.*.schedules.*.activity' => 'nullable|string|max:255',
            'tasks' => ['nullable', 'array', function ($attribute, $value, $fail) use ($request, $id) { $this->validateTaskAssignments($attribute, $value, $fail, $request, $id); }],
            'tasks.*.committee.id' => ['nullable', Rule::in($validCommitteeIds)],
            'tasks.*.employees' => 'nullable|array',
            'tasks.*.employees.*.id' => ['nullable', Rule::in($validEmployeeIds)],
            'tasks.*.task' => 'nullable|string|max:255'
        ]);

        // Update the event
        foreach ($this->jsonData['events'] as &$event) {
            if ($event['id'] == $id) {
                // Normalize tasks to ensure consistent structure
                $normalizedTasks = collect($validated['tasks'] ?? [])->map(function($task) {
                    return [
                        'committee' => isset($task['committee']['id']) ? ['id' => $task['committee']['id']] : null,
                        'employees' => collect($task['employees'])->map(function($emp) {
                            return ['id' => $emp['id']];
                        })->toArray(),
                        'task' => $task['task'] ?? ''
                    ];
                })->toArray();

                $event = [
                    ...$event,
                    'title' => $validated['title'],
                    'description' => $validated['description'],
                    'image' => $validated['image'] ?? $event['image'],
                    'category_id' => $validated['category_id'] ?? ($event['category_id'] ?? null),
                    'venue' => $validated['venue'],
                    'startDate' => $validated['startDate'],
                    'endDate' => $validated['endDate'],
                    'startTime' => $validated['startTime'],
                    'endTime' => $validated['endTime'],
                    'tags' => $validated['tags'] ?? [],
                    'scheduleLists' => $validated['scheduleLists'] ?? [],
                    'tasks' => $normalizedTasks
                ];
                break;
            }
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
        $validCommitteeIds = array_column($this->jsonData['committees'] ?? [], 'id');
        $validEmployeeIds = array_column($this->jsonData['employees'] ?? [], 'id');

        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|string',
            'category_id' => ['nullable', Rule::in($validCategoryIds)],
            'venue' => 'nullable|string|max:255',
            'startDate' => ['nullable', 'string'],
            'endDate' => ['nullable', 'string'],
            'startTime' => 'sometimes|required|date_format:H:i',
            'endTime' => 'sometimes|required|date_format:H:i',
            'tags' => 'nullable|array',
            'tags.*' => ['sometimes', Rule::in($validTagIds)],
            'isAllDay' => 'sometimes|boolean',
            'archived' => 'sometimes|boolean',
            'tasks' => ['sometimes', 'nullable', 'array', function ($attribute, $value, $fail) use ($request, $id) { $this->validateTaskAssignments($attribute, $value, $fail, $request, $id); }],
            'tasks.*.committee.id' => ['nullable', Rule::in($validCommitteeIds)],
            'tasks.*.employees' => 'nullable|array',
            'tasks.*.employees.*.id' => ['nullable', Rule::in($validEmployeeIds)],
            'tasks.*.task' => 'nullable|string|max:255'
        ]);

        $data = $this->jsonData;
        $eventFound = false;

        foreach ($data['events'] as &$event) {
            if ($event['id'] == $id) {
                // Explicitly merge validated data to be more intentional about what is being updated.
                $event = array_merge($event, $validated);

                // Normalize tags to be objects
                if (isset($validated['tags'])) {
                    $event['tags'] = collect($validated['tags'])->map(function($tagId) use ($data) {
                        return collect($data['tags'])->firstWhere('id', $tagId);
                    })->filter()->values()->toArray();
                }

                // Normalize tasks to ensure consistent structure, which was missing.
                if (isset($validated['tasks'])) {
                    $event['tasks'] = collect($validated['tasks'])->map(function($task) {
                        return [
                            'committee' => isset($task['committee']['id']) ? ['id' => $task['committee']['id']] : null,
                            'employees' => collect($task['employees'])->map(fn($emp) => ['id' => $emp['id']])->toArray(),
                            'task' => $task['task'] ?? ''
                        ];
                    })->toArray();
                }
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

            $data['events'] = collect($data['events'])
                ->filter(function($event) use ($id) {
                    return $event['id'] != $id;
                })
                ->values()
                ->toArray();

            File::put(base_path('db.json'), json_encode($data, JSON_PRETTY_PRINT));
            return back()->with('success', 'Event permanently deleted');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete event');
        }
    }

    private function validateTaskAssignments(string $attribute, mixed $value, \Closure $fail, Request $request, ?string $eventId = null): void
    {
        $allEvents = collect($this->jsonData['events'] ?? []);
        $currentEvent = $eventId ? $allEvents->firstWhere('id', $eventId) : null;

        // Determine start and end dates for the event being validated
        $eventStartDateStr = $request->input('startDate');
        $eventEndDateStr = $request->input('endDate');

        if ($currentEvent) {
            $eventStartDateStr = $eventStartDateStr ?? $currentEvent['startDate'];
            $eventEndDateStr = $eventEndDateStr ?? $currentEvent['endDate'];
        }

        // --- Check for duplicates within the same event ---
        $employeeCounts = collect($value)->flatMap(function ($task) {
            return collect($task['employees'] ?? [])->pluck('id');
        })->filter()->countBy();

        foreach ($employeeCounts as $employeeId => $count) {
            if ($count > 1) {
                $employee = collect($this->jsonData['employees'])->firstWhere('id', $employeeId);
                $employeeName = $employee['name'] ?? 'Unknown Employee';
                $fail("Employee \"{$employeeName}\" cannot be assigned to multiple tasks within the same event.");
                return;
            }
        }

        // --- Check for conflicts with other events ---
        if (!$eventStartDateStr || !$eventEndDateStr) {
            return; // Cannot proceed without dates for cross-event check
        }

        $eventStartDate = \DateTime::createFromFormat('M-d-Y', $eventStartDateStr);
        $eventEndDate = \DateTime::createFromFormat('M-d-Y', $eventEndDateStr);

        if (!$eventStartDate || !$eventEndDate) {
            return; // Invalid date format, other validators will catch this.
        }

        $currentEmployeeIds = $employeeCounts->keys();

        if ($currentEmployeeIds->isEmpty()) {
            return; // No employees to check.
        }

        $otherEvents = $eventId
            ? $allEvents->where('id', '!=', $eventId)->where('archived', '!=', true)
            : $allEvents->where('archived', '!=', true);

        foreach ($currentEmployeeIds as $employeeId) {
            $employee = collect($this->jsonData['employees'])->firstWhere('id', $employeeId);
            $employeeName = $employee['name'] ?? 'Unknown Employee';

            foreach ($otherEvents as $otherEvent) {
                $isAssignedToOtherEvent = collect($otherEvent['tasks'] ?? [])->flatMap(function ($task) {
                    return collect($task['employees'] ?? [])->map(function ($emp) {
                        return is_array($emp) ? ($emp['id'] ?? null) : $emp;
                    });
                })->whereNotNull()->contains($employeeId);

                if ($isAssignedToOtherEvent && !empty($otherEvent['startDate']) && !empty($otherEvent['endDate'])) {
                    $otherEventStartDate = \DateTime::createFromFormat('M-d-Y', $otherEvent['startDate']);
                    $otherEventEndDate = \DateTime::createFromFormat('M-d-Y', $otherEvent['endDate']);

                    if ($otherEventStartDate && $otherEventEndDate && $eventStartDate <= $otherEventEndDate && $eventEndDate >= $otherEventStartDate) {
                        $fail("Employee \"{$employeeName}\" is already assigned to another event (\"{$otherEvent['title']}\") during this time period.");
                        return;
                    }
                }
            }
        }
    }
}
