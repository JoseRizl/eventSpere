<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Arr;

class BracketController extends Controller
{
    private $dbPath;

    public function __construct()
    {
        $this->dbPath = base_path('db.json');
    }

    private function readJson()
    {
        if (!File::exists($this->dbPath)) {
            // initialize normalized shape if file missing
            return [
                'brackets' => [],
                'players' => [],
                'matches' => [],
                'matchPlayers' => [],
                'matchLinks' => [],
                'standings' => []
            ];
        }

        $data = json_decode(File::get($this->dbPath), true) ?? [];

        // Ensure all top-level keys for normalized data exist
        $data['brackets'] = $data['brackets'] ?? [];
        $data['players'] = $data['players'] ?? [];
        $data['matches'] = $data['matches'] ?? [];
        $data['matchPlayers'] = $data['matchPlayers'] ?? [];
        $data['matchLinks'] = $data['matchLinks'] ?? [];
        $data['standings'] = $data['standings'] ?? [];

        return $data;
    }

    private function writeJson(array $data)
    {
        File::put($this->dbPath, json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * Flatten different match node shapes into a single flat array of match objects.
     *
     * Accepts:
     * - numeric-array-of-rounds: [ [m,m], [m] ]
     * - associative object for double-elim: { winners: [ [m], [m] ], losers: [ [m] ], grand_finals: [m] }
     * - flat array-of-matches
     *
     * Returns array of match objects (unchanged).
     */
    private function flattenMatchesNode($matchesNode)
    {
        $out = [];

        if ($matchesNode === null) return $out;

        // If numeric indexed array
        if (is_array($matchesNode) && array_values(array_keys($matchesNode)) === range(0, count($matchesNode) - 1)) {
            // Could be either array of rounds (each item is an array) or flat array of matches
            // If first element is array -> treat as rounds
            if (isset($matchesNode[0]) && is_array($matchesNode[0])) {
                foreach ($matchesNode as $roundMatches) {
                    if (!is_array($roundMatches)) continue;
                    foreach ($roundMatches as $m) {
                        if (is_array($m)) $out[] = $m;
                    }
                }
            } else {
                // flat array of matches
                foreach ($matchesNode as $m) {
                    if (is_array($m)) $out[] = $m;
                }
            }
            return $out;
        }

        // If associative/object style: iterate keys (winners, losers, grand_finals, etc)
        if (is_array($matchesNode)) {
            foreach ($matchesNode as $sectionKey => $roundsOrMatches) {
                if (is_array($roundsOrMatches) && isset($roundsOrMatches[0]) && is_array($roundsOrMatches[0])) {
                    // outer array is rounds
                    foreach ($roundsOrMatches as $roundMatches) {
                        if (!is_array($roundMatches)) continue;
                        foreach ($roundMatches as $m) {
                            if (is_array($m)) $out[] = $m;
                        }
                    }
                } else {
                    // either flat array of match objects for this section, or single match
                    if (is_array($roundsOrMatches)) {
                        foreach ($roundsOrMatches as $m) {
                            if (is_array($m)) $out[] = $m;
                        }
                    }
                }
            }
        }

        return $out;
    }

    /**
     * Normalize and process incoming bracket data, separating it into tables.
     */
    private function processAndNormalizeBracketData(array &$jsonData, array $validatedData, $bracketId)
    {
        // 1. Add/update players (merge & dedupe by id)
        $newPlayers = $validatedData['players'] ?? [];
        if (!empty($newPlayers)) {
            $merged = array_merge($jsonData['players'] ?? [], $newPlayers);
            $unique = [];
            foreach ($merged as $p) {
                if (isset($p['id'])) $unique[$p['id']] = $p;
            }
            $jsonData['players'] = array_values($unique);
        }

        // 2. Flatten matches from input (handles both array-of-rounds and double-elim object)
        $incomingMatchesNode = $validatedData['matches'] ?? [];
        $flatMatches = $this->flattenMatchesNode($incomingMatchesNode);

        // 3. Remove old matches and matchPlayers for this bracket before adding new ones
        $existingMatchIds = collect($jsonData['matches'])->where('bracket_id', $bracketId)->pluck('id');
        $jsonData['matches'] = collect($jsonData['matches'])->reject(fn ($m) => ($m['bracket_id'] ?? null) == $bracketId)->values()->all();
        $jsonData['matchPlayers'] = collect($jsonData['matchPlayers'])->reject(fn ($mp) => $existingMatchIds->contains($mp['match_id'] ?? null))->values()->all();

        // 4. Add new matches and matchPlayers
        foreach ($flatMatches as $match) {
            // copy match fields except players
            $matchData = collect($match)->except('players')->all();
            $matchData['bracket_id'] = $bracketId;
            // ensure match id exists
            if (!isset($matchData['id'])) {
                $matchData['id'] = substr(md5(uniqid()), 0, 8);
            }
            // Normalize date format
            if (isset($matchData['date'])) {
                try {
                    $matchData['date'] = (new \DateTime($matchData['date']))->format('Y-m-d');
                } catch (\Exception $e) {
                    // Keep original if parsing fails
                }
            }
            $jsonData['matches'][] = $matchData;

            // players array: can be missing or be an array of player objects
            if (!empty($match['players']) && is_array($match['players'])) {
                foreach ($match['players'] as $slotIndex => $player) {
                    $jsonData['matchPlayers'][] = [
                        'match_id' => $matchData['id'],
                        'player_id' => $player['id'] ?? null,
                        'name' => $player['name'] ?? ($player['id'] ? null : 'TBD'),
                        'slot' => $slotIndex + 1,
                        'score' => isset($player['score']) ? (int)$player['score'] : 0,
                        'completed' => !empty($player['completed']),
                    ];
                }
            }
        }
    }

    /**
     * Display a listing of the resource (entire normalized database).
     */
    public function index()
    {
        // Return the normalized "tables" directly to the client
        return response()->json($this->readJson());
    }

    /**
     * Display a listing of the resource for a specific event (normalized subset).
     */
    public function indexForEvent($eventId)
    {
        $jsonData = $this->readJson();
        $brackets = $jsonData['brackets'] ?? [];

        // filter brackets by event_id
        $filteredBrackets = array_values(array_filter($brackets, function ($b) use ($eventId) {
            return ($b['event_id'] ?? null) == $eventId;
        }));

        $bracketIds = array_map(fn($b) => $b['id'], $filteredBrackets);

        // filter matches and matchPlayers
        $matches = array_values(array_filter($jsonData['matches'] ?? [], fn($m) => in_array($m['bracket_id'], $bracketIds)));
        $matchIds = array_map(fn($m) => $m['id'], $matches);

        $matchPlayers = array_values(array_filter($jsonData['matchPlayers'] ?? [], fn($mp) => in_array($mp['match_id'], $matchIds)));

        // gather players referenced by matchPlayers
        $playerIds = array_values(array_unique(array_values(array_filter(array_map(fn($mp) => $mp['player_id'] ?? null, $matchPlayers)))));
        $players = [];
        if (!empty($jsonData['players'])) {
            foreach ($playerIds as $pid) {
                $found = array_values(array_filter($jsonData['players'], fn($p) => ($p['id'] ?? null) == $pid));
                if (!empty($found)) $players[] = $found[0];
                else $players[] = ['id' => $pid, 'name' => null];
            }
        } else {
            foreach ($playerIds as $pid) {
                $players[] = ['id' => $pid, 'name' => null];
            }
        }

        return response()->json([
            'brackets' => $filteredBrackets,
            'players' => $players,
            'matches' => $matches,
            'matchPlayers' => $matchPlayers,
            'matchLinks' => $jsonData['matchLinks'] ?? [],
            'standings' => $jsonData['standings'] ?? [],
        ]);
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
            'matches' => 'required',
            'players' => 'sometimes|array',
        ]);

        $jsonData = $this->readJson();
        $bracketId = substr(md5(uniqid()), 0, 8);

        $newBracket = [
            'id' => $bracketId,
            'name' => $validated['name'],
            'type' => $validated['type'],
            'event_id' => $validated['event_id'],
            'created_at' => now()->toISOString(),
            'updated_at' => now()->toISOString(),
        ];

        $this->processAndNormalizeBracketData($jsonData, $validated, $bracketId);

        array_unshift($jsonData['brackets'], $newBracket);
        $this->writeJson($jsonData);

        return response()->json($newBracket, 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $jsonData = $this->readJson();
        $bracketIndex = collect($jsonData['brackets'])->search(fn($b) => ($b['id'] ?? null) == $id);

        if ($bracketIndex === false || $bracketIndex === null) {
            return response()->json(['message' => 'Bracket not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string',
            'type' => 'sometimes|string',
            'event_id' => 'sometimes',
            'matches' => 'sometimes',
            'players' => 'sometimes|array',
        ]);

        // Update bracket details
        $jsonData['brackets'][$bracketIndex] = array_merge($jsonData['brackets'][$bracketIndex], Arr::only($validated, ['name','type','event_id']));
        $jsonData['brackets'][$bracketIndex]['updated_at'] = now()->toISOString();

        // The `matches` node is optional on update. If it's present, re-process.
        // This logic replaces all matches for the bracket, which is simpler than diffing.
        $this->processAndNormalizeBracketData($jsonData, $validated, $id);

        $this->writeJson($jsonData);

        // return normalized bracket (without event object)
        return response()->json(collect($jsonData['brackets'][$bracketIndex])->except('event')->all());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $jsonData = $this->readJson();
        $matchIds = collect($jsonData['matches'])->where('bracket_id', $id)->pluck('id');

        $jsonData['brackets'] = collect($jsonData['brackets'])->reject(fn ($b) => ($b['id'] ?? null) == $id)->values()->all();
        $jsonData['matches'] = collect($jsonData['matches'])->reject(fn ($m) => ($m['bracket_id'] ?? null) == $id)->values()->all();
        $jsonData['matchPlayers'] = collect($jsonData['matchPlayers'])->reject(fn ($mp) => $matchIds->contains($mp['match_id'] ?? null))->values()->all();
        // Note: Players are not deleted as they might be in other brackets.

        $this->writeJson($jsonData);

        return response()->json(null, 204);
    }
}
