<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Committee;
use Illuminate\Validation\Rule;

class CommitteeController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:committees,name',
        ]);

        $newCommittee = Committee::create($validated);

        return response()->json(['success' => true, 'committee' => $newCommittee]);
    }

    public function destroy($id)
    {
        $committee = Committee::with(['tasks', 'employees'])->find($id);

        if (!$committee) {
            return response()->json(['message' => 'Committee not found.'], 404);
        }

        $tasksUsingCommittee = $committee->tasks;
        $employeesUsingCommittee = $committee->employees;

        $isUsedInTasks = $tasksUsingCommittee->isNotEmpty();
        $isUsedByEmployees = $employeesUsingCommittee->isNotEmpty();

        if ($isUsedInTasks || $isUsedByEmployees) {
            return response()->json([
                'message' => 'This committee cannot be deleted because it is currently in use.',
                'in_use' => true,
                'details' => [
                    'tasks' => $tasksUsingCommittee->pluck('description')->all(),
                    'employees' => $employeesUsingCommittee->pluck('name')->all(),
                ]
            ], 422);
        }

        $committee->delete();

        return response()->json(['success' => true, 'message' => 'Committee deleted successfully.']);
    }
}
