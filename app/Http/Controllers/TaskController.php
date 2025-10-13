<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;
class TaskController extends JsonController
{
    public function indexForEvent($eventId)
    {
        $allTasks = collect($this->jsonData['tasks'] ?? []);
        $allEmployees = collect($this->jsonData['employees'] ?? []);
        $allCommittees = collect($this->jsonData['committees'] ?? []);
        $taskEmployeeMap = collect($this->jsonData['task_employee'] ?? [])->groupBy('task_id');

        $eventTasks = $allTasks->where('event_id', $eventId)->map(function ($task) use ($allEmployees, $allCommittees, $taskEmployeeMap) {
            // Resolve committee
            $committee = $allCommittees->firstWhere('id', $task['committee_id']);

            // Resolve employees
            $employeeIds = $taskEmployeeMap->get($task['id'], collect())->pluck('employee_id');
            $employees = $allEmployees->whereIn('id', $employeeIds)->values()->toArray();

            return [
                'id' => $task['id'],
                'task' => $task['description'], // Frontend expects 'task' key for description
                'committee' => $committee,
                'employees' => $employees,
            ];
        })->values()->toArray();

        return response()->json($eventTasks);
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
            'tasks' => 'nullable|array',
            'tasks.*.committee_id' => ['nullable', Rule::in($validCommitteeIds)],
            'tasks.*.employees' => 'nullable|array',
            'tasks.*.employees.*' => ['nullable', Rule::in($validEmployeeIds)],
            'tasks.*.description' => 'nullable|string|max:255',
        ]);

        $taskIdsForEvent = collect($this->jsonData['tasks'] ?? [])->where('event_id', $eventId)->pluck('id')->all();

        // Remove existing tasks and employee assignments for this event
        $this->jsonData['tasks'] = collect($this->jsonData['tasks'] ?? [])
            ->filter(fn($task) => $task['event_id'] != $eventId)
            ->values()
            ->toArray();

        $this->jsonData['task_employee'] = collect($this->jsonData['task_employee'] ?? [])
            ->filter(fn($assignment) => !in_array($assignment['task_id'], $taskIdsForEvent))
            ->values()
            ->toArray();

        // Add the new\/updated tasks
        collect($validated['tasks'] ?? [])->each(function ($taskData) use ($eventId) {
            $newTaskId = $eventId . '-' . substr(md5(uniqid(rand(), true)), 0, 4);
            $this->jsonData['tasks'][] = [
                'id' => $newTaskId,
                'description' => $taskData['description'] ?? '',
                'event_id' => $eventId,
                'committee_id' => $taskData['committee_id'] ?? null,
            ];

            collect($taskData['employees'] ?? [])->each(function ($employeeId) use ($newTaskId) {
                $this->jsonData['task_employee'][] = ['task_id' => $newTaskId, 'employee_id' => $employeeId];
            });
        });

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
