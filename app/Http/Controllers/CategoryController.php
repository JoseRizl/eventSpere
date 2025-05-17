<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;

class CategoryController extends Controller
{
    protected $jsonData;

    public function __construct()
    {
        $this->jsonData = json_decode(File::get(base_path('db.json')), true);
    }

    public function index()
    {
        $categories = $this->jsonData['category'] ?? [];
        $tags = $this->jsonData['tags'] ?? [];
        $events = $this->jsonData['events'] ?? [];

        return Inertia::render('List/CategoryList', [
            'categories' => $categories,
            'tags' => $tags,
            'events' => $events
        ]);
    }

    public function store(Request $request)
    {
        $isTag = $request->has('name');
        $data = $request->validate([
            $isTag ? 'name' : 'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'color' => $isTag ? 'required|string|regex:/^#[a-fA-F0-9]{6}$/' : 'nullable'
        ]);

        $collection = $isTag ? 'tags' : 'category';
        $newItem = [
            'id' => uniqid(),
            ...$data
        ];

        $this->jsonData[$collection][] = $newItem;
        File::put(base_path('db.json'), json_encode($this->jsonData, JSON_PRETTY_PRINT));

        return redirect()->back()->with('success', $isTag ? 'Tag created successfully!' : 'Category created successfully!');
    }

    public function update(Request $request, $id)
    {
        $isTag = $request->has('name');
        $data = $request->validate([
            $isTag ? 'name' : 'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'color' => $isTag ? 'required|string|regex:/^#[a-fA-F0-9]{6}$/' : 'nullable'
        ]);

        $collection = $isTag ? 'tags' : 'category';
        $updated = false;

        foreach ($this->jsonData[$collection] as &$item) {
            if ($item['id'] === $id) {
                $item = array_merge($item, $data);
                $updated = true;
                break;
            }
        }

        if ($updated) {
            File::put(base_path('db.json'), json_encode($this->jsonData, JSON_PRETTY_PRINT));
            return redirect()->back()->with('success', $isTag ? 'Tag updated successfully!' : 'Category updated successfully!');
        }

        return redirect()->back()->with('error', 'Item not found');
    }

    public function destroy($id, Request $request)
    {
        \Log::info('Delete request received', ['id' => $id, 'request' => $request->all()]);

        $isTag = $request->input('type') === 'tag';
        $collection = $isTag ? 'tags' : 'category';
        $dbPath = base_path('db.json');

        \Log::info('Processing delete', ['isTag' => $isTag, 'collection' => $collection, 'request_data' => $request->all()]);

        try {
            // Get file lock
            $lockFile = fopen($dbPath, 'r+');
            if (!$lockFile) {
                throw new \Exception('Could not open file for writing');
            }

            // Get exclusive lock
            if (!flock($lockFile, LOCK_EX)) {
                fclose($lockFile);
                throw new \Exception('Could not acquire file lock');
            }

            // Read current data
            $fileContent = fread($lockFile, filesize($dbPath));
            $this->jsonData = json_decode($fileContent, true);

            if (!isset($this->jsonData[$collection])) {
                $this->jsonData[$collection] = [];
            }

            // Check if item is in use
            $isInUse = false;
            if ($isTag) {
                $isInUse = collect($this->jsonData['events'] ?? [])->contains(function ($event) use ($id) {
                    return collect($event['tags'] ?? [])->contains(function ($tag) use ($id) {
                        return is_array($tag) ? $tag['id'] === $id : $tag === $id;
                    });
                });
            } else {
                $isInUse = collect($this->jsonData['events'] ?? [])->contains(function ($event) use ($id) {
                    return $event['category_id'] === $id && !($event['archived'] ?? false);
                });
            }

            if ($isInUse) {
                flock($lockFile, LOCK_UN);
                fclose($lockFile);
                return redirect()->back()->with('error', 'Item is in use and cannot be deleted');
            }

            // Find and remove the item
            $itemToDelete = null;
            foreach ($this->jsonData[$collection] as $key => $item) {
                \Log::info('Checking item', ['key' => $key, 'item_id' => $item['id'], 'search_id' => $id]);
                if ((string)$item['id'] === (string)$id) {
                    $itemToDelete = $key;
                    break;
                }
            }

            if ($itemToDelete !== null) {
                // Remove the item
                unset($this->jsonData[$collection][$itemToDelete]);
                // Reindex the array
                $this->jsonData[$collection] = array_values($this->jsonData[$collection]);

                // Write back to file
                fseek($lockFile, 0);
                ftruncate($lockFile, 0);
                $jsonString = json_encode($this->jsonData, JSON_PRETTY_PRINT);
                fwrite($lockFile, $jsonString);
                fflush($lockFile);

                // Release lock
                flock($lockFile, LOCK_UN);
                fclose($lockFile);

                \Log::info('Item deleted successfully', [
                    'id' => $id,
                    'collection' => $collection,
                    'remaining_items' => count($this->jsonData[$collection])
                ]);

                // Redirect back to the list page
                return redirect()->route('category.list')->with('success', $isTag ? 'Tag deleted successfully!' : 'Category deleted successfully!');
            }

            // Release lock if item not found
            flock($lockFile, LOCK_UN);
            fclose($lockFile);

            return redirect()->back()->with('error', 'Item not found');

        } catch (\Exception $e) {
            \Log::error('Error during deletion', [
                'error' => $e->getMessage(),
                'id' => $id,
                'collection' => $collection
            ]);

            if (isset($lockFile) && is_resource($lockFile)) {
                flock($lockFile, LOCK_UN);
                fclose($lockFile);
            }

            return redirect()->back()->with('error', 'Failed to delete the item: ' . $e->getMessage());
        }
    }
}
