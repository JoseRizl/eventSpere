<?php

namespace App\Http\Controllers;

use App\Models\Committee;
use App\Models\Employee;
use App\Models\Event;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function indexForEvent($eventId)
    {
        $tasks = Task::where('event_id', $eventId)
            ->with(['committee', 'employees'])
            ->get();

        $formattedTasks = $tasks->map(function ($task) {
            return [
                'id' => $task->id,
                'task' => $task->description, // Match frontend key 'task'
                'committee' => $task->committee,
                'employees' => $task->employees,
            ];
        });

        return response()->json($formattedTasks);
    }


    /**
     * Update all tasks for a specific event.
     */
    public function updateForEvent(Request $request, $eventId)
    {
        // Log the incoming request payload for debugging
        Log::info("Updating tasks for event ID: {$eventId}", ['payload' => $request->all()]);

        $validator = Validator::make($request->all(), [
            'tasks' => ['nullable', 'array'],
            'tasks.*.committee_id' => ['nullable', 'integer', 'exists:pgsql.committees,id'],
            'tasks.*.employees' => 'required|array|min:1',
            'tasks.*.employees.*' => ['required', 'integer', 'exists:pgsql.emport.employees,id'],
            'tasks.*.description' => 'required|string|max:255',
        ], [
            'tasks.*.employees.required' => 'Please assign at least one employee to all tasks.',
            'tasks.*.description.required' => 'Please provide a description for all tasks.',
        ]);

        // Add our custom validation rule
        $validator->after(function ($validator) use ($request, $eventId) {
            $this->validateTaskAssignments($request->input('tasks', []), $validator, $eventId);
        });

        if ($validator->fails()) {
            Log::warning("Task update validation failed for event ID: {$eventId}", ['errors' => $validator->errors()->toArray()]);
            return response()->json(['message' => 'The given data was invalid.', 'errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();

        DB::transaction(function () use ($validated, $eventId) {
            // Delete all existing tasks for this event.
            Log::info("Deleting existing tasks for event ID: {$eventId}");
            Task::where('event_id', $eventId)->delete();

            // Re-create tasks and their assignments
            foreach ($validated['tasks'] ?? [] as $taskData) {
                $taskPayload = [
                    'description' => $taskData['description'],
                    'event_id' => $eventId,
                ];
                if (!empty($taskData['committee_id'])) {
                    $taskPayload['committee_id'] = $taskData['committee_id'];
                }
                $task = Task::create($taskPayload);
                Log::info("Created task ID: {$task->id} for event ID: {$eventId}");

                $task->employees()->sync($taskData['employees']);
                Log::info("Synced employees for task ID: {$task->id}", ['employees' => $taskData['employees']]);
            }
        });

        // Log success before returning the response
        Log::info("Successfully updated tasks for event ID: {$eventId}");

        // After saving, re-fetch and normalize the new tasks to send back to the frontend
        $newlyCreatedTasks = Task::where('event_id', $eventId)
            ->with(['committee', 'employees'])
            ->get()
            ->map(function ($task) {
                return [
                    'id' => $task->id,
                    'task' => $task->description,
                    'committee' => $task->committee,
                    'employees' => $task->employees,
                ];
            });

        return back()->with([
            'success' => 'Tasks updated successfully.',
            'tasks' => $newlyCreatedTasks->values()->all()
        ]);
    }

    private function validateTaskAssignments(array $tasks, \Illuminate\Contracts\Validation\Validator $validator, $eventId): void
    {
        $currentEvent = Event::find($eventId);

        if (!$currentEvent) {
            $validator->errors()->add('event', "The event with ID {$eventId} was not found.");
            return;
        }

        // --- Check for duplicates within the same event ---
        $employeeCounts = collect($tasks)->flatMap(function ($task) {
            return collect($task['employees'] ?? []);
        })->filter()->countBy();

        foreach ($employeeCounts as $employeeId => $count) {
            if ($count > 1) {
                $employeeName = Employee::find($employeeId)->name ?? 'Unknown Employee';
                $validator->errors()->add("tasks", "Employee \"{$employeeName}\" cannot be assigned to multiple tasks within the same event.");
                return;
            }
        }

        // --- Check for conflicts with other events ---
        // Get employee IDs, filter out any non-ID keys (like an empty string from countBy),
        // and ensure we have a valid collection of IDs.
        $currentEmployeeIds = $employeeCounts->keys()->filter(function ($id) {
            return is_numeric($id) && $id > 0;
        });

        // If the current event doesn't have a valid start or end date,
        // we cannot check for scheduling conflicts.
        if (!$currentEvent->start || !$currentEvent->end) {
            return;
        }

        if ($currentEmployeeIds->isEmpty()) return; // Now this guard is effective.

        Log::debug("Checking for employee conflicts for event ID: {$eventId}", ['employee_ids' => $currentEmployeeIds->all()]);
        $conflictingAssignments = DB::table('emport.task_employee as te')
            ->join('emport.tasks as t', 'te.task_id', '=', 't.id')
            ->join('events as e', 't.event_id', '=', 'e.id')
            ->join('emport.employees as emp', 'te.employee_id', '=', 'emp.id')
            ->whereIn('te.employee_id', $currentEmployeeIds)
            ->where('e.id', '!=', $eventId)
            ->where('e.archived', false)
            ->where(function ($query) use ($currentEvent) {
                $query->where('e.start', '<', $currentEvent->end)
                      ->where('e.end', '>', $currentEvent->start);
            })
            ->select('emp.name as employee_name', 'e.title as event_title')
            ->get();

        if ($conflictingAssignments->isNotEmpty()) {
            Log::warning("Found conflicting employee assignment for event ID: {$eventId}", ['conflicts' => $conflictingAssignments->all()]);
            $conflict = $conflictingAssignments->first();
            $validator->errors()->add(
                "tasks",
                "Employee \"{$conflict->employee_name}\" is already assigned to \"{$conflict->event_title}\" during this time period."
            );
        }
    }

    /**
     * A dedicated route to save tasks for an event.
     * This handles PUT requests to /events/{eventId}/tasks
     */
    public function saveTasksForEvent(Request $request, $eventId)
    {
        // Log the incoming request payload for debugging
        Log::info("Saving tasks for event ID: {$eventId}", ['payload' => $request->all()]);

        $validator = Validator::make($request->all(), [
            'tasks' => ['nullable', 'array'],
            'tasks.*.committee_id' => ['nullable', 'integer', 'exists:pgsql.committees,id'],
            'tasks.*.employees' => 'required|array|min:1',
            'tasks.*.employees.*' => ['required', 'integer', 'exists:pgsql.emport.employees,id'],
            'tasks.*.description' => 'required|string|max:255',
        ], [
            'tasks.*.employees.required' => 'Please assign at least one employee to all tasks.',
            'tasks.*.description.required' => 'Please provide a description for all tasks.',
        ]);

        $validator->after(function ($validator) use ($request, $eventId) {
            $this->validateTaskAssignments($request->input('tasks', []), $validator, $eventId);
        });

        if ($validator->fails()) {
            Log::warning("Task save validation failed for event ID: {$eventId}", ['errors' => $validator->errors()->toArray()]);
            return response()->json(['message' => 'The given data was invalid.', 'errors' => $validator->errors()], 422);
        }

        $validated = $validator->validated();

        DB::transaction(function () use ($validated, $eventId) {
            Task::where('event_id', $eventId)->delete();

            foreach ($validated['tasks'] ?? [] as $taskData) {
                $task = Task::create([
                    'description' => $taskData['description'],
                    'event_id' => $eventId,
                    'committee_id' => $taskData['committee_id'] ?? null,
                ]);
                $task->employees()->sync($taskData['employees']);
            }
        });

        $newlyCreatedTasks = Task::with(['committee', 'employees'])->where('event_id', $eventId)->get()->map(function ($task) {
            return ['id' => $task->id, 'task' => $task->description, 'committee' => $task->committee, 'employees' => $task->employees];
        });

        return back()->with(['success' => 'Tasks updated successfully.', 'tasks' => $newlyCreatedTasks->values()->all()]);
    }
}
