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
                'matches' => [],
                'matchPlayers' => [],
            ];
        }

        $data = json_decode(File::get($this->dbPath), true) ?? [];

        // Ensure all top-level keys for normalized data exist
        $data['brackets'] = $data['brackets'] ?? [];
        $data['matches'] = $data['matches'] ?? [];
        $data['matchPlayers'] = $data['matchPlayers'] ?? [];

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
        if (!is_array($matchesNode)) return $out;

        // Are keys numeric (0..n-1)? -> could be array-of-rounds or flat array-of-matches.
        $keys = array_keys($matchesNode);
        $isNumericIndexed = ($keys === range(0, count($matchesNode) - 1));

        if ($isNumericIndexed) {
            if (isset($matchesNode[0]) && is_array($matchesNode[0])) {
                $first = $matchesNode[0];
                // FIX: This logic was flawed for grand_finals which can be [[{match}]].
                // The check for $firstElemIsRound was too aggressive and caused incorrect flattening.
                // A "match" is an associative array, while a "round" is a numeric array of matches.
                // We check if the first element of the first element is an associative array (a match).
                // This correctly identifies array-of-rounds like [[{match}]] or [[{m1},{m2}],[{m3}]].
                $firstElemOfFirstIsMatch = isset($first[0]) && is_array($first[0]) && Arr::isAssoc($first[0]);

                // This check remains correct for flat arrays of matches like [{m1}, {m2}].
                $firstElemLooksLikeMatch = !isset($first[0]) && (isset($first['id']) || isset($first['players']));

                if ($firstElemLooksLikeMatch) {
                    return $matchesNode; // It's already a flat array of matches.
                } else {
                    // array-of-rounds -> flatten one level
                    foreach ($matchesNode as $roundMatches) {
                        if (!is_array($roundMatches)) continue;
                        foreach ($roundMatches as $m) {
                            if (is_array($m)) $out[] = $m;
                        }
                    }
                }
            } else if (isset($matchesNode[0])) {
                // This handles a flat array of matches, e.g. [ {match1}, {match2} ]
                return $matchesNode;
            }
            return $out; // Return the flattened rounds
        }

        // --- NEW: handle associative object with named sections like { winners: [...], losers: [...], grand_finals: [...] } ---
        foreach ($matchesNode as $sectionKey => $sectionVal) {
            if (!is_array($sectionVal)) continue;

            // sectionVal could be:
            // - flat array of matches
            // - array-of-rounds (array of arrays)
            // either way, recursively flatten the section
            $sectionMatches = $this->flattenMatchesNode($sectionVal);

            foreach ($sectionMatches as $m) {
                if (is_array($m)) {
                    // annotate origin section so later code can group/recognize it
                    $m['bracket_type'] = $m['bracket_type'] ?? $sectionKey;
                    $out[] = $m;
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
    // 2. Flatten matches from input (handles both array-of-rounds and double-elim object)
    $incomingMatchesNode = $validatedData['matches'] ?? [];
    $flatMatches = $this->flattenMatchesNode($incomingMatchesNode);

    // 3. Remove old matches and matchPlayers for this bracket before adding new ones
    $existingMatchIds = collect($jsonData['matches'])->where('bracket_id', $bracketId)->pluck('id');
    $jsonData['matches'] = collect($jsonData['matches'])->reject(fn ($m) => ($m['bracket_id'] ?? null) == $bracketId)->values()->all();
    $jsonData['matchPlayers'] = collect($jsonData['matchPlayers'])->reject(fn ($mp) => $existingMatchIds->contains($mp['match_id'] ?? null))->values()->all();

    // 4. Add new matches and matchPlayers (build matches list and matchPlayers rows)
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
            'matches' => $matches,
            'matchPlayers' => $matchPlayers,
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
            'allow_draws' => 'sometimes|boolean',
            'tiebreaker_data' => 'sometimes|array',
        ]);

        $jsonData = $this->readJson();
        $bracketId = substr(md5(uniqid()), 0, 8);

        $newBracket = [
            'id' => $bracketId,
            'name' => $validated['name'],
            'type' => $validated['type'],
            'event_id' => $validated['event_id'],
            'allow_draws' => $validated['allow_draws'] ?? null,
            'tiebreaker_data' => $validated['tiebreaker_data'] ?? null,
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
            'allow_draws' => 'sometimes|boolean',
            'tiebreaker_data' => 'sometimes|array',
        ]);

        // Update bracket details
        $jsonData['brackets'][$bracketIndex] = array_merge($jsonData['brackets'][$bracketIndex], Arr::only($validated, ['name','type','event_id','allow_draws','tiebreaker_data']));
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

    /**
     * Update player names in a bracket
     */
    public function updatePlayerNames(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'updates' => 'required|array',
            'updates.*.oldName' => 'required|string',
            'updates.*.newName' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $jsonData = $this->readJson();
        $updates = $request->input('updates');

        // Find the bracket
        $bracketIndex = collect($jsonData['brackets'])->search(fn($b) => ($b['id'] ?? null) == $id);
        
        if ($bracketIndex === false) {
            return response()->json(['error' => 'Bracket not found'], 404);
        }

        $bracket = $jsonData['brackets'][$bracketIndex];

        // Update player names in the bracket's matches structure
        $this->updatePlayerNamesInMatches($bracket['matches'], $updates);

        // Save back
        $jsonData['brackets'][$bracketIndex] = $bracket;
        $this->writeJson($jsonData);

        return response()->json(['message' => 'Player names updated successfully', 'bracket' => $bracket]);
    }

    /**
     * Recursively update player names in matches
     */
    private function updatePlayerNamesInMatches(&$matches, $updates)
    {
        if (is_array($matches)) {
            foreach ($matches as &$item) {
                if (isset($item['players']) && is_array($item['players'])) {
                    // This is a match with players
                    foreach ($item['players'] as &$player) {
                        if (isset($player['name'])) {
                            foreach ($updates as $update) {
                                if ($player['name'] === $update['oldName']) {
                                    $player['name'] = $update['newName'];
                                }
                            }
                        }
                    }
                } else {
                    // Recurse into nested structure (rounds, etc.)
                    $this->updatePlayerNamesInMatches($item, $updates);
                }
            }
        }
    }
}
