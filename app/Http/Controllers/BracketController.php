<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;

class BracketController extends Controller
{
    private $dbPath;

    public function __construct()
    {
        $this->dbPath = base_path('db.json');
    }

    private function readJson()
    {
        return json_decode(File::get($this->dbPath), true);
    }

    private function writeJson(array $data)
    {
        File::put($this->dbPath, json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $jsonData = $this->readJson();
        $brackets = $jsonData['brackets'] ?? [];
        $events = collect($jsonData['events'] ?? [])->keyBy('id');

        $activeBrackets = collect($brackets)->filter(function ($bracket) use ($events) {
            // Check if the event exists and is not archived.
            // If event_id is not set or event not found, we can decide to show or hide it.
            // Here, we'll only show brackets with a valid, non-archived event.
            return isset($bracket['event_id']) && $events->has($bracket['event_id']) && !$events->get($bracket['event_id'])['archived'];
        })->values()->all();

        return response()->json($activeBrackets);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'type' => 'required|string',
            'event_id' => 'required',
            'matches' => 'required|array',
        ]);

        $jsonData = $this->readJson();

        // Construct the new bracket with 'id' as the first key.
        $newBracket = [
            'id' => substr(md5(uniqid()), 0, 8), // Generate a unique ID
        ] + $validated;

        $newBracket['created_at'] = now()->toISOString();
        $newBracket['updated_at'] = now()->toISOString();

        if (!isset($jsonData['brackets'])) {
            $jsonData['brackets'] = [];
        }

        // The $newBracket only contains event_id, not the full event object.
        array_unshift($jsonData['brackets'], $newBracket);
        $this->writeJson($jsonData);

        // The frontend will add the event object client-side for immediate display.
        return response()->json($newBracket, 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $jsonData = $this->readJson();
        $bracketIndex = collect($jsonData['brackets'])->search(fn ($b) => $b['id'] == $id);

        if ($bracketIndex === false) {
            return response()->json(['message' => 'Bracket not found'], 404);
        }

        $jsonData['brackets'][$bracketIndex] = array_merge($jsonData['brackets'][$bracketIndex], $request->all());
        $jsonData['brackets'][$bracketIndex]['updated_at'] = now()->toISOString();
        $this->writeJson($jsonData);

        return response()->json($jsonData['brackets'][$bracketIndex]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $jsonData = $this->readJson();
        $jsonData['brackets'] = collect($jsonData['brackets'])->reject(fn ($b) => $b['id'] == $id)->values()->all();
        $this->writeJson($jsonData);

        return response()->json(null, 204);
    }
}
