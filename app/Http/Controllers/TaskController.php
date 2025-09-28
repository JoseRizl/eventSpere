<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    private $dbPath;
    private $jsonData;

    public function __construct()
    {
        $this->dbPath = base_path('db.json');
        $this->jsonData = json_decode(File::get($this->dbPath), true);
    }

    private function writeJson(array $data)
    {
        File::put($this->dbPath, json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * Update all tasks for a specific event.
     * This replaces the task-handling logic from EventController@updateFromList.
     */
    public function updateForEvent(Request $request, $eventId)
    {
        $validCommitteeIds = array_column($this->jsonData['committees'] ?? [], 'id');
        $validEmployeeIds = array_column($this->jsonData['employees'] ?? [], 'id');

        $validated = $request->validate([
            'tasks' => ['nullable', 'array', function ($attribute, $value, $fail) use ($request, $eventId) {
                $this->validateTaskAssignments($attribute, $value, $fail, $request, $eventId);
            }],
            'tasks.*.committee.id' => ['nullable', Rule::in($validCommitteeIds)],
            'tasks.*.employees' => 'nullable|array',
            'tasks.*.employees.*.id' => ['nullable', Rule::in($validEmployeeIds)],
            'tasks.*.task' => 'nullable|string|max:255',
        ]);

        $eventFound = false;
        foreach ($this->jsonData['events'] as &$event) {
            if ($event['id'] == $eventId) {
                // Normalize tasks to ensure consistent structure before saving
                $event['tasks'] = collect($validated['tasks'])->map(function ($task) {
                    return [
                        'committee' => isset($task['committee']['id']) ? ['id' => $task['committee']['id']] : null,
                        'employees' => collect($task['employees'] ?? [])->map(fn ($emp) => ['id' => $emp['id']])->values()->toArray(),
                        'task' => $task['task'] ?? ''
                    ];
                })->toArray();
                $eventFound = true;
                break;
            }
        }

        if (!$eventFound) {
            return response()->json(['success' => false, 'error' => 'Event not found.'], 404);
        }

        $this->writeJson($this->jsonData);

        return redirect()->back()->with('success', 'Tasks updated successfully.');
    }

    /**
     * Validates employee assignments for tasks to prevent duplicates and scheduling conflicts.
     * Moved from EventController.
     */
    private function validateTaskAssignments(string $attribute, mixed $value, \Closure $fail, Request $request, ?string $eventId = null): void
    {
        $allEvents = collect($this->jsonData['events'] ?? []);
        $currentEvent = $eventId ? $allEvents->firstWhere('id', $eventId) : null;

        if (!$currentEvent) {
            // This validation is for an existing event, so it must be found.
            $fail("The event with ID {$eventId} was not found.");
            return;
        }

        $eventStartDateStr = $currentEvent['startDate'];
        $eventEndDateStr = $currentEvent['endDate'];

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
        if (!$eventStartDateStr || !$eventEndDateStr) return;

        $eventStartDate = \DateTime::createFromFormat('M-d-Y', $eventStartDateStr);
        $eventEndDate = \DateTime::createFromFormat('M-d-Y', $eventEndDateStr);

        if (!$eventStartDate || !$eventEndDate) return;

        $currentEmployeeIds = $employeeCounts->keys();
        if ($currentEmployeeIds->isEmpty()) return;

        $otherEvents = $allEvents->where('id', '!=', $eventId)->where('archived', '!=', true);

        foreach ($currentEmployeeIds as $employeeId) {
            $employee = collect($this->jsonData['employees'])->firstWhere('id', $employeeId);
            $employeeName = $employee['name'] ?? 'Unknown Employee';

            foreach ($otherEvents as $otherEvent) {
                $isAssignedToOtherEvent = collect($otherEvent['tasks'] ?? [])->flatMap(fn ($t) => collect($t['employees'] ?? [])->pluck('id'))->contains($employeeId);

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
