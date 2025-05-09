<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\Event;
use Inertia\Inertia;
use Illuminate\Support\Facades\File;

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

    // Get current event's tag IDs
    $currentTagIds = collect($event['tags'])->pluck('id')->toArray();

    // Filter related events to only those sharing at least one tag AND not archived
    $relatedEvents = collect($data['events'])
        ->where('id', '!=', $id)
        ->where('archived', false) // Add this line to exclude archived events
        ->filter(function ($relatedEvent) use ($currentTagIds) {
            $relatedTagIds = collect($relatedEvent['tags'])->pluck('id')->toArray();
            return count(array_intersect($currentTagIds, $relatedTagIds)) > 0;
        })
        ->take(5)
        ->values()
        ->toArray();

    // Ensure tasks array exists and has proper structure
    if (!isset($event['tasks']) || !is_array($event['tasks'])) {
        $event['tasks'] = [];
    } else {
        // Ensure each task has the required structure
        $event['tasks'] = array_map(function($task) {
            return [
                'committee' => $task['committee'] ?? null,
                'employee' => $task['employee'] ?? null,
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
    $validTagIds = array_column($this->jsonData['tags'] ?? [], 'id');
    $validCommitteeIds = array_column($this->jsonData['committees'] ?? [], 'id');
    $validEmployeeIds = array_column($this->jsonData['employees'] ?? [], 'id');

    // Custom validation rules
    $validated = $request->validate([
        'title' => 'required|string|max:255',
        'description' => 'nullable|string',
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
        'tags.*.id' => ['required', Rule::in($validTagIds)],
        'schedules' => 'nullable|array',
        'schedules.*.time' => 'required|date_format:H:i',
        'schedules.*.activity' => 'required|string|max:255',
        'tasks' => 'nullable|array',
        'tasks.*.committee.id' => ['required', Rule::in($validCommitteeIds)],
        'tasks.*.employee.id' => ['required', Rule::in($validEmployeeIds)],
        'tasks.*.task' => 'required|string|max:255'
    ]);

    // Update the event
    foreach ($this->jsonData['events'] as &$event) {
        if ($event['id'] == $id) {
            $event = [
                ...$event,
                'title' => $validated['title'],
                'description' => $validated['description'],
                'venue' => $validated['venue'],
                'startDate' => $validated['startDate'], // Keeps original MMM-DD-YYYY format
                'endDate' => $validated['endDate'],     // Keeps original MMM-DD-YYYY format
                'startTime' => $validated['startTime'],
                'endTime' => $validated['endTime'],
                'tags' => $validated['tags'] ?? [],
                'schedules' => $validated['schedules'] ?? [],
                'tasks' => $validated['tasks'] ?? []
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

}
