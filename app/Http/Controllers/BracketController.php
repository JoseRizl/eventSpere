<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Arr;
use App\Models\Bracket;
use App\Models\Matches;
use App\Models\MatchPlayer;
use App\Models\Event;
use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class BracketController extends Controller
{
    private function authorizeBracketAction(Bracket $bracket)
    {
        $user = Auth::user();

        if (!$user) {
            return false;
        }

        if (in_array($user->role, ['Admin', 'Principal'])) {
            return true;
        }

        if ($user->role === 'TournamentManager') {
            return $user->managedBrackets()->where('bracket_id', $bracket->id)->exists();
        }

        return false;
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
    private function processAndNormalizeBracketData(array $validatedData, $bracketId)
    {
        // Flatten matches from input (handles both array-of-rounds and double-elim object)
        $incomingMatchesNode = $validatedData['matches'] ?? [];
        $flatMatches = $this->flattenMatchesNode($incomingMatchesNode);

        // Remove old matches and matchPlayers for this bracket before adding new ones
        Matches::where('bracket_id', $bracketId)->delete(); // Deletes associated match_players due to cascade

        // Add new matches and matchPlayers
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
            $newMatch = Matches::create($matchData);

            // players array: can be missing or be an array of player objects
            if (!empty($match['players']) && is_array($match['players'])) {
                foreach ($match['players'] as $slotIndex => $player) {
                    // Defensive normalisation
                    if (is_object($player)) {
                        $player = (array) $player;
                    }

                    $rawId = $player['id'] ?? null;

                    // If id is array/object, log and return a 422 with diagnostic payload
                    if (is_array($rawId) || is_object($rawId)) {
                        \Log::error('Invalid player id type in incoming bracket payload', [
                            'bracket_id' => $bracketId,
                            'match_payload' => $match,
                            'slot_index' => $slotIndex,
                            'raw_player' => $player,
                        ]);
                        throw new \Illuminate\Validation\ValidationException(
                            Validator::make([],[]),
                            response()->json([
                                'message' => 'Invalid player id type in payload',
                                'bracket_id' => $bracketId,
                                'match' => $match,
                                'slot' => $slotIndex,
                                'raw_player' => $player
                            ], 422)
                        );
                    }

                    // Since player_id is a string, we don't need an is_numeric check.
                    // An empty string should be treated as null.
                    $playerIdForInsert = ($rawId === '' || $rawId === null) ? null : $rawId;

                    $playerName = $player['name'] ?? ($playerIdForInsert ? null : 'TBD');

                    try {
                        MatchPlayer::create([
                            'match_id' => $newMatch->id,
                            'player_id' => $playerIdForInsert,
                            'name' => $playerName,
                            'slot' => $slotIndex + 1,
                            'score' => isset($player['score']) ? (int)$player['score'] : 0,
                            'completed' => !empty($player['completed']),
                            'color' => $player['color'] ?? null,
                        ]);
                    } catch (\Exception $e) {
                        \Log::error('Failed inserting MatchPlayer', [
                            'exception' => $e->getMessage(),
                            'bracket_id' => $bracketId,
                            'match_payload' => $match,
                            'player_payload' => $player,
                            'slot' => $slotIndex
                        ]);
                        throw $e; // keep transaction semantics
                    }
                }
            }
        }

    }

    /**
     * Display a listing of the resource (entire normalized database).
     */
    public function index()
    {
        // The `readJson()` method appears to be a remnant of a previous implementation and is not used.
        // It has been removed to avoid confusion.

        // FIX: Load `event` and then eager load `category` on the event model.
        $brackets = Bracket::with(['event.category', 'managers'])->orderBy('created_at', 'desc')->get();
        $matches = Matches::whereIn('bracket_id', $brackets->pluck('id'))->get();
        $matchPlayers = MatchPlayer::whereIn('match_id', $matches->pluck('id'))->get();
        $events = Event::with('category')->get();
        $categories = Category::all();

        return response()->json([
            'brackets' => $brackets,
            'matches' => $matches,
            'matchPlayers' => $matchPlayers,
            'events' => $events,
            'categories' => $categories,
        ]);
    }

    /**
     * Display a listing of the resource for a specific event (normalized subset).
     */
    public function indexForEvent($eventId)
    {
        $brackets = Bracket::where('event_id', $eventId)
            ->with('managers')
            ->orderBy('created_at', 'desc')
            ->get();
        $bracketIds = $brackets->pluck('id');

        $matches = Matches::whereIn('bracket_id', $bracketIds)->get();
        $matchIds = $matches->pluck('id');

        $matchPlayers = MatchPlayer::whereIn('match_id', $matchIds)->get();

        return response()->json([
            'brackets' => $brackets,
            'matches' => $matches,
            'matchPlayers' => $matchPlayers,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        if (!$user || !in_array($user->role, ['Admin', 'Principal'])) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'name' => 'required|string',
            'type' => 'required|string',
            'event_id' => 'required|exists:events,id',
            'matches' => 'required',
            'players' => 'sometimes|array',
            'allow_draws' => 'sometimes|boolean',
            'tiebreaker_data' => 'sometimes|array',
            'id' => 'required|string|unique:brackets,id' // Ensure client-side ID is provided and unique
        ]);

        $newBracket = DB::transaction(function () use ($validated) {
            $bracket = Bracket::create([
                'id' => $validated['id'],
                'name' => $validated['name'],
                'type' => $validated['type'],
                'event_id' => $validated['event_id'],
                'allow_draws' => $validated['allow_draws'] ?? false,
                'tiebreaker_data' => $validated['tiebreaker_data'] ?? null,
            ]);

            $this->processAndNormalizeBracketData($validated, $bracket->id);
            return $bracket;
        });

        return response()->json($newBracket, 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $bracket = Bracket::findOrFail($id);
        if (!$this->authorizeBracketAction($bracket)) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'name' => 'sometimes|string',
            'type' => 'sometimes|string',
            'event_id' => 'sometimes|exists:events,id',
            'matches' => 'sometimes',
            'players' => 'sometimes|array',
            'allow_draws' => 'sometimes|boolean',
            'tiebreaker_data' => 'sometimes|array',
        ]);

        DB::transaction(function () use ($bracket, $validated, $id) {
            // Update bracket details
            $bracket->update(Arr::only($validated, ['name', 'type', 'event_id', 'allow_draws', 'tiebreaker_data']));

            // The `matches` node is optional on update. If it's present, re-process.
            if (isset($validated['matches'])) {
                $this->processAndNormalizeBracketData($validated, $id);
            }
        });

        return response()->json($bracket->fresh());
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $bracket = Bracket::findOrFail($id);
        if (!$this->authorizeBracketAction($bracket)) {
            abort(403, 'Unauthorized action.');
        }
        $bracket->delete(); // This will cascade delete matches and match_players

        return response()->json(null, 204);
    }

    /**
     * Update player names in a bracket
     */
    public function updatePlayerNames(Request $request, $id)
    {
        $bracket = Bracket::findOrFail($id);
        if (!$this->authorizeBracketAction($bracket)) {
            abort(403, 'Unauthorized action.');
        }

        $validator = Validator::make($request->all(), [
            'updates' => 'required|array',
            'updates.*.oldName' => 'required|string',
            'updates.*.newName' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $updates = $request->input('updates');

        // Get all match IDs for the bracket
        $matchIds = Matches::where('bracket_id', $id)->pluck('id');

        // Update names in the matchPlayers table
        DB::transaction(function () use ($matchIds, $updates) {
            foreach ($updates as $update) {
                MatchPlayer::whereIn('match_id', $matchIds)
                    ->where('name', $update['oldName'])
                    ->update(['name' => $update['newName']]);
            }
        });

        return response()->json(['message' => 'Player names updated successfully']);
    }

    public function updatePlayerColors(Request $request, $id)
    {
        $bracket = Bracket::findOrFail($id);
        if (!$this->authorizeBracketAction($bracket)) {
            abort(403, 'Unauthorized action.');
        }

        $validator = Validator::make($request->all(), [
            'colors' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $colors = $request->input('colors');

        // Get all match IDs for the specified bracket
        $matchIds = Matches::where('bracket_id', $id)->pluck('id');

        DB::transaction(function () use ($matchIds, $colors) {
            foreach ($colors as $name => $color) {
                MatchPlayer::whereIn('match_id', $matchIds)
                    ->where('name', $name)
                    ->update(['color' => $color]);
            }
        });

        return response()->json(['message' => 'Player colors updated successfully']);
    }

    /**
     * Recursively update player names in matches
     */
    private function updatePlayerNamesInMatches(&$matches, $updates)
    { // This function was for the old JSON structure and is no longer needed with the new updatePlayerNames method.
    }
}
