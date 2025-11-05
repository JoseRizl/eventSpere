<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Event;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $categories = Category::query()->get();
        $tags = Tag::query()->get();

        // Build events array with tag IDs for CategoryList.vue usage checks
        $events = Event::with('tags')->get()->map(function ($e) {
            return [
                'id' => (string)$e->id,
                'title' => $e->title,
                'category_id' => $e->category_id,
                'archived' => (bool)$e->archived,
                // CategoryList.vue expects tags to be an array of tag IDs
                'tags' => $e->tags->pluck('id')->values()->toArray(),
            ];
        })->values()->toArray();

        // Build event_tags bridge array for tagUsageCount and details
        $event_tags = [];
        foreach ($events as $ev) {
            foreach ($ev['tags'] as $tid) {
                $event_tags[] = [
                    'event_id' => (string)$ev['id'],
                    'tag_id' => (string)$tid,
                ];
            }
        }

        return Inertia::render('List/CategoryList', [
            'categories' => $categories,
            'tags' => $tags,
            'event_tags' => $event_tags,
            'events' => $events,
        ]);
    }

    public function store(Request $request)
    {
        $isTag = $request->has('name') && $request->has('category_id');

        if ($isTag) {
            $data = $request->validate([
                'name' => ['required', 'string', 'max:255', 'unique:tags,name'],
                'description' => 'nullable|string',
                'category_id' => ['required', 'exists:categories,id'],
            ]);

            $tag = new Tag();
            $tag->name = $data['name'];
            $tag->description = $data['description'] ?? null;
            $tag->category_id = $data['category_id'];
            $tag->archived = false;
            $tag->save();

            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'tag' => $tag]);
            }

            return redirect()->route('category.list')->with('success', 'Tag created successfully!');
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255', 'unique:categories,title'],
            'description' => 'nullable|string',
            'allow_brackets' => 'sometimes|boolean',
        ]);

        $category = new Category();
        $category->title = $data['title'];
        $category->description = $data['description'] ?? null;
        $category->allow_brackets = (bool)($data['allow_brackets'] ?? false);
        $category->save();

        return redirect()->route('category.list')->with('success', 'Category created successfully!');
    }

    public function update(Request $request, $id)
    {
        $isTag = $request->has('name');

        if ($isTag) {
            $tag = Tag::findOrFail($id);
            $data = $request->validate([
                'name' => ['required', 'string', 'max:255', 'unique:tags,name,' . $tag->id],
                'description' => 'nullable|string',
                'category_id' => ['sometimes', 'required', 'exists:categories,id'],
            ]);

            $tag->name = $data['name'];
            if (array_key_exists('description', $data)) {
                $tag->description = $data['description'];
            }
            if (array_key_exists('category_id', $data)) {
                $tag->category_id = $data['category_id'];
            }
            $tag->save();

            return redirect()->route('category.list')->with('success', 'Tag updated successfully!');
        }

        $category = Category::findOrFail($id);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255', 'unique:categories,title,' . $category->id],
            'description' => 'nullable|string',
            'allow_brackets' => 'sometimes|boolean',
        ]);

        $category->title = $data['title'];
        if (array_key_exists('description', $data)) {
            $category->description = $data['description'];
        }
        if (array_key_exists('allow_brackets', $data)) {
            $category->allow_brackets = (bool)$data['allow_brackets'];
        }
        $category->save();

        return redirect()->route('category.list')->with('success', 'Category updated successfully!');
    }

    public function destroy($id, Request $request)
    {
        $isTag = $request->input('type') === 'tag';

        if ($isTag) {
            $tag = Tag::findOrFail($id);
            // Check if any event uses this tag
            $inUse = Event::whereHas('tags', function ($q) use ($id) {
                $q->where('tags.id', $id);
            })->exists();

            if ($inUse) {
                return redirect()->route('category.list')->with('error', 'Item is in use and cannot be deleted');
            }

            // Detach from pivot and delete
            DB::table('event_tags')->where('tag_id', $id)->delete();
            $tag->delete();

            return redirect()->route('category.list')->with('success', 'Tag deleted successfully!');
        }

        $category = Category::findOrFail($id);
        // Check usage: by non-archived events or by tags
        $eventUsingCategory = Event::where('category_id', $id)
            ->where(function ($q) { $q->whereNull('archived')->orWhere('archived', false); })
            ->exists();
        $tagUsingCategory = Tag::where('category_id', $id)->exists();

        if ($eventUsingCategory || $tagUsingCategory) {
            return redirect()->route('category.list')->with('error', 'Item is in use and cannot be deleted');
        }

        $category->delete();
        return redirect()->route('category.list')->with('success', 'Category deleted successfully!');
    }

    public function archiveTag($id)
    {
        $tag = Tag::findOrFail($id);
        $tag->archived = true;
        $tag->save();

        return redirect()->route('category.list')->with('success', 'Tag archived successfully.');
    }

    public function restoreTag($id)
    {
        $tag = Tag::findOrFail($id);
        $tag->archived = false;
        $tag->save();

        return redirect()->route('category.list')->with('success', 'Tag restored successfully.');
    }

    public function permanentDeleteTag($id)
    {
        $tag = Tag::findOrFail($id);
        // Ensure no events reference
        DB::table('event_tags')->where('tag_id', $id)->delete();
        $tag->delete();

        return redirect()->route('category.list')->with('success', 'Tag permanently deleted.');
    }
}
