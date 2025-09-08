<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\Event;
use Inertia\Inertia;
use Illuminate\Support\Facades\File;
use App\Models\Category;

class EventController extends Controller
{
    public function __construct()
    {
        $this->jsonData = json_decode(File::get(base_path('db.json')), true);
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
            'employees' => $data['employees'] ?? [],
            'relatedEvents' => $relatedEvents,
        ]);
    }

    public function update(Request $request, $id)
    {
        // Get valid IDs from JSON
        $validCategoryIds = array_column($this->jsonData['categories'] ?? [], 'id');
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
            'scheduleLists.*.schedules' => 'required|array',
            'scheduleLists.*.schedules.*.time' => 'required|date_format:H:i',
            'scheduleLists.*.schedules.*.activity' => 'required|string|max:255',
            'tasks' => 'nullable|array',
            'tasks.*.committee.id' => ['required', Rule::in($validCommitteeIds)],
            'tasks.*.employees' => 'required|array',
            'tasks.*.employees.*.id' => ['required', Rule::in($validEmployeeIds)],
            'tasks.*.task' => 'required|string|max:255'
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

        $categories = $data['categories'] ?? [];

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
}
