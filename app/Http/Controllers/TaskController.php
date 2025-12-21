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

use App\Models\Bracket;
use App\Models\User;

class TaskController extends Controller
{
    public function indexForEvent($eventId)
    {
        $tasks = Task::where('event_id', $eventId)
            ->with(['committee', 'employees', 'managers.managedBrackets' => function ($query) use ($eventId) {
                $query->where('event_id', $eventId);
            }])
            ->get();

        $formattedTasks = $tasks->map(function ($task) {
            return [
                'id' => $task->id,
                'task' => $task->description, // Match frontend key 'task'
                'committee' => $task->committee,
                'employees' => $task->employees,
                'managers' => $task->managers,
            ];
        });

        return response()->json($formattedTasks);
    }

    /**
     * Update all tasks for a specific event.
     */
    public function updateForEvent(Request $request, $eventId)
    {
        Log::info("Updating tasks for event ID: {$eventId}", ['payload' => $request->all()]);

        $validator = Validator::make($request->all(), [
            'tasks' => ['nullable', 'array'],
            'tasks.*.description' => ['required', 'string', 'max:255'],
            'tasks.*.committee_id' => ['nullable', 'integer', 'exists:pgsql.committees,id'],
            'tasks.*.employee_ids' => ['present', 'array'],
            'tasks.*.employee_ids.*' => ['integer', 'exists:pgsql.emport.employees,id'],
            'tasks.*.manager_assignments' => ['present', 'array'],
            'tasks.*.manager_assignments.*.user_id' => ['required', 'integer', 'exists:users,id'],
            'tasks.*.manager_assignments.*.bracket_ids' => ['required', 'array', 'min:1'],
            'tasks.*.manager_assignments.*.bracket_ids.*' => ['required', 'string', 'exists:brackets,id'],
        ], [
            'tasks.*.manager_assignments.*.bracket_ids.min' => 'Each manager must be assigned to at least one bracket.',
            'tasks.*.description.required' => 'Please provide a description for all tasks.',
        ]);
        
        $validator->after(function ($validator) use ($request) {
            foreach ($request->input('tasks', []) as $index => $task) {
                if (empty($task['employee_ids']) && empty($task['manager_assignments'])) {
                    $validator->errors()->add("tasks.{$index}.employees", 'Please assign at least one employee or manager to each task.');
                }
            }
        });

        if ($validator->fails()) {
            Log::warning("Task update validation failed for event ID: {$eventId}", ['errors' => $validator->errors()->toArray()]);
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();

        DB::transaction(function () use ($validated, $eventId) {
            $eventBrackets = Bracket::where('event_id', $eventId)->pluck('id');
            DB::table('bracket_manager')->whereIn('bracket_id', $eventBrackets)->delete();
            Task::where('event_id', $eventId)->delete();

            foreach ($validated['tasks'] ?? [] as $taskData) {
                $task = Task::create([
                    'description' => $taskData['description'],
                    'event_id' => $eventId,
                    'committee_id' => $taskData['committee_id'] ?? null,
                ]);

                if (!empty($taskData['employee_ids'])) {
                    $task->employees()->sync($taskData['employee_ids']);
                }

                if (!empty($taskData['manager_assignments'])) {
                    $managerIds = array_column($taskData['manager_assignments'], 'user_id');
                    $task->managers()->sync($managerIds);

                    foreach ($taskData['manager_assignments'] as $assignment) {
                        $user = User::find($assignment['user_id']);
                        if ($user) {
                            $validBrackets = array_intersect($assignment['bracket_ids'], $eventBrackets->toArray());
                            $user->managedBrackets()->syncWithoutDetaching($validBrackets);
                        }
                    }
                }
            }
        });

        $newlyCreatedTasks = Task::where('event_id', $eventId)
            ->with(['committee', 'employees', 'managers.managedBrackets' => function ($query) use ($eventId) {
                $query->where('event_id', $eventId);
            }])
            ->get()
            ->map(function ($task) {
                return [
                    'id' => $task->id,
                    'task' => $task->description,
                    'committee' => $task->committee,
                    'employees' => $task->employees,
                    'managers' => $task->managers,
                ];
            });

        return back()->with([
            'success' => 'Tasks updated successfully.',
            'tasks' => $newlyCreatedTasks->values()->all()
        ]);
    }

    /**
     * A dedicated route to save tasks for an event.
     * This handles PUT requests to /events/{eventId}/tasks
     */
    public function saveTasksForEvent(Request $request, $eventId)
    {
        Log::info("Saving tasks for event ID: {$eventId}", ['payload' => $request->all()]);

        $validator = Validator::make($request->all(), [
            'tasks' => ['nullable', 'array'],
            'tasks.*.description' => ['required', 'string', 'max:255'],
            'tasks.*.committee_id' => ['nullable', 'integer', 'exists:pgsql.committees,id'],
            'tasks.*.employee_ids' => ['present', 'array'],
            'tasks.*.employee_ids.*' => ['integer', 'exists:pgsql.emport.employees,id'],
            'tasks.*.manager_assignments' => ['present', 'array'],
            'tasks.*.manager_assignments.*.user_id' => ['required', 'integer', 'exists:users,id'],
            'tasks.*.manager_assignments.*.bracket_ids' => ['required', 'array', 'min:1'],
            'tasks.*.manager_assignments.*.bracket_ids.*' => ['required', 'string', 'exists:brackets,id'],
        ], [
            'tasks.*.manager_assignments.*.bracket_ids.min' => 'Each manager must be assigned to at least one bracket.',
            'tasks.*.description.required' => 'Please provide a description for all tasks.',
        ]);

        $validator->after(function ($validator) use ($request) {
            foreach ($request->input('tasks', []) as $index => $task) {
                if (empty($task['employee_ids']) && empty($task['manager_assignments'])) {
                    $validator->errors()->add("tasks.{$index}.employees", 'Please assign at least one employee or manager to each task.');
                }
            }
        });

        if ($validator->fails()) {
            Log::warning("Task save validation failed for event ID: {$eventId}", ['errors' => $validator->errors()->toArray()]);
            return redirect()->back()->withErrors($validator)->withInput();
        }

        $validated = $validator->validated();

        DB::transaction(function () use ($validated, $eventId) {
            $eventBrackets = Bracket::where('event_id', $eventId)->pluck('id');
            DB::table('bracket_manager')->whereIn('bracket_id', $eventBrackets)->delete();
            Task::where('event_id', $eventId)->delete();

            foreach ($validated['tasks'] ?? [] as $taskData) {
                $task = Task::create([
                    'description' => $taskData['description'],
                    'event_id' => $eventId,
                    'committee_id' => $taskData['committee_id'] ?? null,
                ]);

                if (!empty($taskData['employee_ids'])) {
                    $task->employees()->sync($taskData['employee_ids']);
                }

                if (!empty($taskData['manager_assignments'])) {
                    $managerIds = array_column($taskData['manager_assignments'], 'user_id');
                    $task->managers()->sync($managerIds);

                    foreach ($taskData['manager_assignments'] as $assignment) {
                        $user = User::find($assignment['user_id']);
                        if ($user) {
                            $validBrackets = array_intersect($assignment['bracket_ids'], $eventBrackets->toArray());
                            $user->managedBrackets()->syncWithoutDetaching($validBrackets);
                        }
                    }
                }
            }
        });

        $newlyCreatedTasks = Task::with(['committee', 'employees', 'managers.managedBrackets' => function ($query) use ($eventId) {
                $query->where('event_id', $eventId);
            }])->where('event_id', $eventId)->get()->map(function ($task) {
            return [
                'id' => $task->id, 
                'task' => $task->description, 
                'committee' => $task->committee, 
                'employees' => $task->employees,
                'managers' => $task->managers,
            ];
        });

        return back()->with(['success' => 'Tasks updated successfully.', 'tasks' => $newlyCreatedTasks->values()->all()]);
    }
}

