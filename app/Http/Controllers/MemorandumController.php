<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MemorandumController extends JsonController
{
    private function saveFile($base64File, $filename, $type, $oldFilePath = null)
    {
        if (!$base64File || !str_contains($base64File, 'base64')) {
            return $base64File; // Not a new upload, return path as is.
        }

        // A new file is being uploaded, so delete the old one if it exists.
        if ($oldFilePath) {
            $this->deleteFile($oldFilePath);
        }

        @list(, $data) = explode(',', $base64File);
        $decodedData = base64_decode($data);

        $path = ($type === 'image') ? 'memorandums/images/' . date('Y/m') : 'memorandums/files/' . date('Y/m');
        $uniqueFilename = uniqid() . '_' . preg_replace('/[^A-Za-z0-9\._-]/', '', $filename);

        Storage::disk('public')->put($path . '/' . $uniqueFilename, $decodedData);

        return Storage::url($path . '/' . $uniqueFilename);
    }

    public function deleteFile($path)
    {
        if ($path && str_starts_with($path, '/storage/')) {
            $filePath = str_replace('/storage/', '', $path);
            if (Storage::disk('public')->exists($filePath)) {
                Storage::disk('public')->delete($filePath);
            }
        }
    }

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
            $oldMemo = $this->jsonData['memorandums'][$existingMemoIndex];
            $oldContentPath = ($oldMemo['type'] !== 'text') ? ($oldMemo['content'] ?? null) : null;

            // Save the new file and get its path, passing the old path for deletion.
            $validated['content'] = $this->saveFile($validated['content'], $validated['filename'], $validated['type'], $oldContentPath);

            // Update existing memorandum
            $this->jsonData['memorandums'][$existingMemoIndex] = array_merge(
                $this->jsonData['memorandums'][$existingMemoIndex],
                $validated
            );
            $message = 'Memorandum updated successfully.';
        } else {
            $newMemo = $validated;
            // Save the new file and get its path. No old path on create.
            $newMemo['content'] = $this->saveFile($newMemo['content'], $newMemo['filename'], $newMemo['type']);
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
            // Before removing the record, delete the associated file from storage.
            $memoToDelete = $this->jsonData['memorandums'][$memoIndex];
            if (isset($memoToDelete['content']) && $memoToDelete['type'] !== 'text') {
                $this->deleteFile($memoToDelete['content']);
            }

            array_splice($this->jsonData['memorandums'], $memoIndex, 1);
            $this->writeJson($this->jsonData);
            return response()->json(['success' => true, 'message' => 'Memorandum removed successfully.']);
        }

        return response()->json(['success' => false, 'message' => 'Memorandum not found.'], 404);
    }
}
