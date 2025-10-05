<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MemorandumController extends JsonController
{
    public function storeOrUpdate(Request $request, $eventId)
    {
        $validated = $request->validate([
            'type' => 'required|string|in:image,file',
            'content' => 'required|string',
            'filename' => 'nullable|string',
        ]);

        // Check if a memorandum for this event already exists
        $existingMemoIndex = -1;
        foreach ($this->jsonData['memorandums'] ?? [] as $index => $memo) {
            if ($memo['event_id'] === $eventId) {
                $existingMemoIndex = $index;
                break;
            }
        }

        if ($existingMemoIndex !== -1) {
            // Update existing memorandum
            $this->jsonData['memorandums'][$existingMemoIndex] = array_merge(
                $this->jsonData['memorandums'][$existingMemoIndex],
                $validated
            );
            $message = 'Memorandum updated successfully.';
        } else {
            // Create new memorandum
            $newMemo = $validated;
            $newMemo['id'] = (string) Str::uuid();
            $newMemo['created_at'] = now()->toISOString();
            $newMemo['event_id'] = $eventId;
            $this->jsonData['memorandums'][] = $newMemo;
            $message = 'Memorandum saved successfully.';
        }

        $this->writeJson($this->jsonData);

        return response()->json(['success' => true, 'message' => $message]);
    }

    public function destroy($eventId)
    {
        $memoIndex = -1;
        foreach ($this->jsonData['memorandums'] ?? [] as $index => $memo) {
            if ($memo['event_id'] === $eventId) {
                $memoIndex = $index;
                break;
            }
        }

        if ($memoIndex !== -1) {
            array_splice($this->jsonData['memorandums'], $memoIndex, 1);
            $this->writeJson($this->jsonData);
            return response()->json(['success' => true, 'message' => 'Memorandum removed successfully.']);
        }

        return response()->json(['success' => false, 'message' => 'Memorandum not found.'], 404);
    }
}
