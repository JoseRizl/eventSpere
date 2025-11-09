<?php

namespace App\Http\Controllers;

use App\Models\Memorandum;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MemorandumController extends Controller
{
    private function saveFile($base64File, $filename, $type, $oldFilePath = null)
    {
        if (!$base64File || !str_contains($base64File, 'base64')) {
            // Not a new upload; keep existing path or the provided string as-is
            return $base64File;
        }

        // A new file is being uploaded, so delete the old one if it exists.
        if ($oldFilePath) {
            $this->deleteFile($oldFilePath);
        }

        @list(, $data) = explode(',', $base64File);
        $decodedData = base64_decode($data);

        $path = ($type === 'image') ? 'memorandums/images/' . date('Y/m') : 'memorandums/files/' . date('Y/m');
        $safeFilename = preg_replace('/[^A-Za-z0-9\._-]/', '', (string) $filename);
        $uniqueFilename = uniqid() . '_' . $safeFilename;

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

        $existingMemo = Memorandum::where('event_id', $eventId)->first();

        if ($existingMemo) {
            $oldContentPath = ($existingMemo->type !== 'text') ? ($existingMemo->content ?? null) : null;
            $newContentPath = $this->saveFile($validated['content'], $validated['filename'] ?? '', $validated['type'], $oldContentPath);

            $existingMemo->update([
                'type' => $validated['type'],
                'content' => $newContentPath,
                'filename' => $validated['filename'] ?? $existingMemo->filename,
            ]);

            $message = 'Memorandum updated successfully.';
        } else {
            $newContentPath = $this->saveFile($validated['content'], $validated['filename'] ?? '', $validated['type']);

            Memorandum::create([
                'event_id' => $eventId,
                'type' => $validated['type'],
                'content' => $newContentPath,
                'filename' => $validated['filename'] ?? null,
            ]);

            $message = 'Memorandum saved successfully.';
        }

        return response()->json(['success' => true, 'message' => $message]);
    }

    public function destroy($eventId)
    {
        $memo = Memorandum::where('event_id', $eventId)->first();
        if (!$memo) {
            return response()->json(['success' => false, 'message' => 'Memorandum not found.'], 404);
        }

        if (!empty($memo->content) && ($memo->type !== 'text')) {
            $this->deleteFile($memo->content);
        }

        $memo->delete();

        return response()->json(['success' => true, 'message' => 'Memorandum removed successfully.']);
    }
}
