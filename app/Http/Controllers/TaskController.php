<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
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
        $validator = Validator::make($request->all(), [
            'tasks' => ['nullable', 'array', function ($attribute, $value, $fail) use ($request, $eventId) {
                $this->validateTaskAssignments($attribute, $value, $fail, $request, $eventId);
            }],
            'tasks.*.committee_id' => ['nullable', 'integer', Rule::in(array_column($this->jsonData['committees'] ?? [], 'id'))],
            'tasks.*.employees' => 'required|array|min:1',
            'tasks.*.employees.*' => ['required', 'integer', Rule::in(array_column($this->jsonData['employees'] ?? [], 'id'))],
            'tasks.*.description' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $validator->errors()
            ], 422);
        }

        $validated = $validator->validated();

        $validCommitteeIds = array_column($this->jsonData['committees'] ?? [], 'id');
        $validEmployeeIds = array_column($this->jsonData['employees'] ?? [], 'id');

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

        $newlyCreatedTasks = [];
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

        // After saving, re-fetch and normalize the new tasks to send back to the frontend
        $allEmployees = collect($this->jsonData['employees'] ?? []);
        $allCommittees = collect($this->jsonData['committees'] ?? []);
        $taskEmployeeMap = collect($this->jsonData['task_employee'] ?? [])->groupBy('task_id');

        $newlyCreatedTasks = collect($this->jsonData['tasks'] ?? [])->where('event_id', $eventId)->map(function ($task) use ($allEmployees, $allCommittees, $taskEmployeeMap) {
            $committee = $allCommittees->firstWhere('id', $task['committee_id']);
            $employeeIds = $taskEmployeeMap->get($task['id'], collect())->pluck('employee_id');
            $employees = $allEmployees->whereIn('id', $employeeIds)->values()->toArray();
            return [
                'id' => $task['id'],
                'task' => $task['description'],
                'committee' => $committee,
                'employees' => $employees,
            ];
        });

        $this->writeJson($this->jsonData);

        // Return a JSON response instead of redirecting
        return response()->json([
            'success' => true,
            'tasks' => $newlyCreatedTasks->values()->all(),
        ]);
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
            return collect($task['employees'] ?? []);
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

        $currentEmployeeIds = $employeeCounts->keys();
        if ($currentEmployeeIds->isEmpty()) return;

        // Normalize tasks for all other events to ensure consistent structure for validation
        $allTasks = collect($this->jsonData['tasks'] ?? []);
        $allEmployees = collect($this->jsonData['employees'] ?? []);
        $allCommittees = collect($this->jsonData['committees'] ?? []);
        $taskEmployeeMap = collect($this->jsonData['task_employee'] ?? [])->groupBy('task_id');

        $otherEvents = $allEvents->where('id', '!=', $eventId)->where('archived', '!=', true)
            ->map(function ($event) use ($allTasks, $allEmployees, $allCommittees, $taskEmployeeMap) {
                $event['tasks'] = $allTasks->where('event_id', $event['id'])->map(function ($task) use ($allEmployees, $taskEmployeeMap) {
                    $employeeIds = $taskEmployeeMap->get($task['id'], collect())->pluck('employee_id');
                    return [
                        'employees' => $allEmployees->whereIn('id', $employeeIds)->values()->toArray(),
                    ];
                })->values()->toArray();
                return $event;
            });

        foreach ($currentEmployeeIds as $employeeId) {
            $employee = collect($this->jsonData['employees'])->firstWhere('id', $employeeId);
            $employeeName = $employee['name'] ?? 'Unknown Employee';

            foreach ($otherEvents as $otherEvent) {
                // Now that tasks are normalized, this check will work correctly.
                $isAssignedToOtherEvent = collect($otherEvent['tasks'] ?? [])->flatMap(fn ($t) => collect($t['employees'] ?? [])->pluck('id'))->contains($employeeId);

                if ($isAssignedToOtherEvent && !empty($otherEvent['startDate']) && !empty($otherEvent['endDate'])) {
                    $currentIsAllDay = $currentEvent['isAllDay'] ?? false;
                    $otherIsAllDay = $otherEvent['isAllDay'] ?? false;

                    $format = ($currentIsAllDay || $otherIsAllDay) ? 'M-d-Y' : 'M-d-Y H:i';

                    $currentStartTime = $currentIsAllDay ? '' : ' ' . ($currentEvent['startTime'] ?? '00:00');
                    $currentEndTime = $currentIsAllDay ? '' : ' ' . ($currentEvent['endTime'] ?? '23:59');
                    $otherStartTime = $otherIsAllDay ? '' : ' ' . ($otherEvent['startTime'] ?? '00:00');
                    $otherEndTime = $otherIsAllDay ? '' : ' ' . ($otherEvent['endTime'] ?? '23:59');

                    $eventStartDateTime = \DateTime::createFromFormat($format, $currentEvent['startDate'] . $currentStartTime);
                    $eventEndDateTime = \DateTime::createFromFormat($format, $currentEvent['endDate'] . $currentEndTime);
                    $otherEventStartDateTime = \DateTime::createFromFormat($format, $otherEvent['startDate'] . $otherStartTime);
                    $otherEventEndDateTime = \DateTime::createFromFormat($format, $otherEvent['endDate'] . $otherEndTime);

                    if ($eventStartDateTime && $eventEndDateTime && $otherEventStartDateTime && $otherEventEndDateTime && $eventStartDateTime < $otherEventEndDateTime && $eventEndDateTime > $otherEventStartDateTime) {
                        $fail("Employee \"{$employeeName}\" is already assigned to \"{$otherEvent['title']}\" during this time period.");
                        return;
                    }
                }
            }
        }
    }
}
