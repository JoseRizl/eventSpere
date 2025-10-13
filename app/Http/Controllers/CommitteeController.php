<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CommitteeController extends JsonController
{
    public function store(Request $request)
    {
        // Manually check for uniqueness in the JSON file
        $existingNames = collect($this->jsonData['committees'] ?? [])->pluck('name')->map('strtolower');
        if ($existingNames->contains(strtolower($request->input('name')))) {
            return response()->json(['message' => 'The name has already been taken.'], 422);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $newCommittee = [
            'id' => ($this->jsonData['last_ids']['committees'] ?? 0) + 1,
            'name' => $validated['name'],
        ];

        $this->jsonData['committees'][] = $newCommittee;
        $this->jsonData['last_ids']['committees'] = $newCommittee['id'];

        $this->writeJson($this->jsonData);

        return response()->json(['success' => true, 'committee' => $newCommittee]);
    }

    public function destroy($id)
    {
        $allTasks = collect($this->jsonData['tasks'] ?? []);
        $allEmployees = collect($this->jsonData['employees'] ?? []);

        // Check if the committee is in use by any tasks
        $tasksUsingCommittee = $allTasks->where('committee_id', $id);

        // Check if the committee is in use by any employees
        $employeesUsingCommittee = $allEmployees->where('committeeId', $id);

        $isUsedInTasks = $tasksUsingCommittee->isNotEmpty();
        if ($isUsedByEmployees) {
            $isUsedByEmployees = $employeesUsingCommittee->isNotEmpty();
        }

        if ($isUsedInTasks || $isUsedByEmployees) {
            return response()->json([
                'message' => 'Cannot delete committee. It is in use.',
                'in_use' => true,
                'details' => [
                    'tasks' => $tasksUsingCommittee->pluck('description')->all(),
                    'employees' => $employeesUsingCommittee->pluck('name')->all(),
                ]
            ], 422);
        }

        $initialCount = count($this->jsonData['committees']);
        $this->jsonData['committees'] = collect($this->jsonData['committees'])
            ->where('id', '!=', $id)
            ->values()
            ->toArray();

        if (count($this->jsonData['committees']) === $initialCount) {
            return response()->json(['message' => 'Committee not found.'], 404);
        }

        $this->writeJson($this->jsonData);

        return response()->json(['success' => true, 'message' => 'Committee deleted successfully.']);
    }
}
