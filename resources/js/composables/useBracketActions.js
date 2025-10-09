import { nextTick, watch } from 'vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import { format, parseISO, isValid, parse } from 'date-fns';

const robustParseDate = (dateString) => {
    if (!dateString) return new Date(NaN); // Return invalid date if no string

    // ISO format e.g., 2023-10-27T10:00:00.000Z or 2023-10-27
    let date = parseISO(dateString);
    if (isValid(date)) return date;

    // yyyy-MM-dd format
    date = parse(dateString, 'yyyy-MM-dd', new Date());
    if (isValid(date)) return date;

    // MMM-dd-yyyy format e.g., Oct-27-2023
    date = parse(dateString, 'MMM-dd-yyyy', new Date());
    if (isValid(date)) return date;

    // Fallback for other potential formats that Date.parse might handle
    date = new Date(dateString);
    if (isValid(date)) return date;

    return new Date(NaN); // Return invalid date if all parsing fails
};

// Helpers for validating match date/time within event window
const getFullDateTime = (dateInput, timeStr) => {
    if (!dateInput) return null;
    let d = dateInput instanceof Date ? dateInput : robustParseDate(dateInput);
    if (!isValid(d)) return null;
    if (typeof timeStr === 'string' && timeStr.length >= 4) {
        const parsedTime = parse(timeStr.padStart(5, '0'), 'HH:mm', new Date());
        if (isValid(parsedTime)) {
            d = new Date(d.getFullYear(), d.getMonth(), d.getDate(), parsedTime.getHours(), parsedTime.getMinutes(), 0, 0);
        }
    } else {
        d = new Date(d.getFullYear(), d.getMonth(), d.getDate(), 0, 0, 0, 0);
    }
    return d;
};

const dateOnlyStr = (d) => (d && isValid(d)) ? format(d, 'yyyy-MM-dd') : null;

const validateMatchDateTime = (event, dateInput, timeStr) => {
    if (!event) return null;
    const evStart = robustParseDate(event.startDate);
    const evEnd = robustParseDate(event.endDate || event.startDate);
    const matchDate = dateInput instanceof Date ? dateInput : robustParseDate(dateInput);

    if (!isValid(matchDate)) return 'Invalid match date.';
    if (!isValid(evStart) || !isValid(evEnd)) return null; // No strict bounds if event dates invalid

    const m = dateOnlyStr(matchDate);
    const s = dateOnlyStr(evStart);
    const e = dateOnlyStr(evEnd);
    if ((s && m < s) || (e && m > e)) {
        return `Match date (${format(matchDate, 'MMM-dd-yyyy')}) must be within the event range (${format(evStart, 'MMM-dd-yyyy')} - ${format(evEnd, 'MMM-dd-yyyy')}).`;
    }

    const evStartDT = getFullDateTime(event.startDate, event.startTime);
    const evEndDT = getFullDateTime(event.endDate || event.startDate, event.endTime || event.startTime);
    if (timeStr) {
        const matchDT = getFullDateTime(matchDate, timeStr);
        if (matchDT && evStartDT && matchDT < evStartDT) {
            return `Match time must be on/after event start (${format(evStart, 'MMM-dd-yyyy')} ${event.startTime || '00:00'}).`;
        }
        if (matchDT && evEndDT && matchDT > evEndDT) {
            return `Match time must be on/before event end (${format(evEnd, 'MMM-dd-yyyy')} ${event.endTime || '23:59'}).`;
        }
    }
    return null;
};

const getBracketTypeClass = (type) => {
    switch (type) {
        case 'Single Elimination': return 'type-single';
        case 'Double Elimination': return 'type-double';
        case 'Round Robin': return 'type-round-robin';
        default: return '';
    }
};

export function useBracketActions(state) {
  const {
    bracketName,
    numberOfPlayers,
    matchType,
    selectedEvent,
    events,
    brackets,
    showDialog,
    currentMatchIndex,
    expandedBrackets,
    activeBracketIdx,
    showWinnerDialog,
    winnerMessage,
    showConfirmDialog,
    pendingBracketIdx,
    showMissingFieldsDialog,
    showDeleteConfirmDialog,
    deleteBracketIdx,
    showSuccessDialog,
    successMessage,
    currentWinnersMatchIndex,
    currentLosersMatchIndex,
    currentGrandFinalsIndex,
    activeBracketSection,
    showMatchEditorDialog,
    selectedMatch,
    selectedMatchData,
    showMatchUpdateConfirmDialog,
    showGenericErrorDialog,
    genericErrorMessage,
    roundRobinScoring,
    standingsRevision,
    showScoringConfigDialog,
    tempScoringConfig,

    bracketTypeOptions,
    bracketViewModes,
    bracketMatchFilters,
  } = state;


  const getSeedingOrder = (n) => {
    if (n <= 1) return [1];
    if (n === 2) return [1, 2];
    const prevOrder = getSeedingOrder(n / 2);
    const newOrder = [];
    for (const seed of prevOrder) {
        newOrder.push(seed);
        newOrder.push(n + 1 - seed);
    }
    return newOrder;
  };

  // Action log for precise undo of slot assignments per bracket
  const bracketActionLog = new Map();

  const deepClone = (obj) => JSON.parse(JSON.stringify(obj));

  const resolveMatch = (bracket, part, roundIdx, matchIdx) => {
    if (part === 'winners') return bracket.matches.winners[roundIdx]?.[matchIdx];
    if (part === 'losers') return bracket.matches.losers[roundIdx]?.[matchIdx];
    if (part === 'grand_finals') return bracket.matches.grand_finals[matchIdx];
    if (part === 'single') return bracket.matches[roundIdx]?.[matchIdx];
    return undefined;
  };

  const setPlayerWithLog = (bracket, part, roundIdx, matchIdx, playerPos, newPlayer, performedActions) => {
    const match = resolveMatch(bracket, part, roundIdx, matchIdx);
    if (!match) return;
    const prevPlayer = deepClone(match.players[playerPos]);
    match.players[playerPos] = deepClone(newPlayer);
    performedActions.push({
      type: 'slot',
      target: { part, roundIdx, matchIdx, playerPos },
      prevPlayer,
    });
  };

  // --- REPLACE the existing getAllMatches function with this robust version ---
  const flattenMaybeRounds = (maybeRounds) => {
    // Accepts:
    // - null/undefined -> []
    // - flat array of matches -> return that array
    // - array of rounds -> e.g. [ [m,m], [m] ] -> flatten to [m,m,m]
    if (!maybeRounds) return [];
    if (!Array.isArray(maybeRounds)) return [];
    // If first element is an array -> treat as rounds
    if (maybeRounds.length > 0 && Array.isArray(maybeRounds[0])) {
      return maybeRounds.flat();
    }
    // Otherwise treat as flat array of matches
    return maybeRounds;
  };

  const getAllMatches = (bracket, filter = 'all') => {
    let allMatches = [];
    if (!bracket || !bracket.matches) return allMatches;

    try {
      if (bracket.type === 'Single Elimination' || bracket.type === 'Round Robin') {
        // bracket.matches might be array-of-rounds or flat array
        allMatches = flattenMaybeRounds(bracket.matches);
      } else if (bracket.type === 'Double Elimination') {
        const winners = flattenMaybeRounds(bracket.matches.winners);
        const losers = flattenMaybeRounds(bracket.matches.losers);
        // grand_finals may be flat array or array-of-rounds or missing
        const grand = flattenMaybeRounds(bracket.matches.grand_finals || []);
        allMatches = [...winners, ...losers, ...grand];
      } else {
        // unknown bracket type: try to flatten everything defensively
        if (Array.isArray(bracket.matches)) {
          allMatches = flattenMaybeRounds(bracket.matches);
        } else if (typeof bracket.matches === 'object' && bracket.matches !== null) {
          // collect any arrays inside bracket.matches and flatten them
          Object.values(bracket.matches).forEach((val) => {
            if (Array.isArray(val)) allMatches = [...allMatches, ...flattenMaybeRounds(val)];
          });
        }
      }
    } catch (err) {
      // Fallback to an empty list on unexpected shape
      console.warn('getAllMatches: unexpected bracket.matches shape', err, bracket.matches);
      allMatches = [];
    }

    // Apply filter if requested
    let filteredMatches = allMatches;
    if (filter && filter !== 'all') {
      filteredMatches = allMatches.filter(m => (m && m.status) ? m.status === filter : false);
    }

    // sort by round, then match_number if present (defensive)
    filteredMatches.sort((a, b) => {
      const ra = Number(a?.round ?? 0);
      const rb = Number(b?.round ?? 0);
      if (ra !== rb) return ra - rb;
      const ma = Number(a?.match_number ?? a?.matchNumber ?? 0);
      const mb = Number(b?.match_number ?? b?.matchNumber ?? 0);
      return ma - mb;
    });

    return filteredMatches;
  };

  const setBracketViewMode = (bracketIdx, mode) => {
    bracketViewModes.value[bracketIdx] = mode;
  };

  const setBracketMatchFilter = (bracketIdx, filter) => {
    bracketMatchFilters.value[bracketIdx] = filter;
  };

  const handleByeRounds = (bracketIdx) => {
    const bracket = brackets.value[bracketIdx];

    // Round Robin handles BYE matches during generation, so skip here
    if (bracket.type === 'Round Robin') {
      return;
    }

    // Auto-progression for BYE rounds in Single and Double Elimination is disabled.
    // BYE matches will be handled in the MatchEditorDialog to prevent unexpected state changes.
  };

  const toggleBracket = (bracketIdx) => {
    if (expandedBrackets.value[bracketIdx]) {
      expandedBrackets.value[bracketIdx] = false;
    } else {
      expandedBrackets.value = expandedBrackets.value.map((_, idx) => idx === bracketIdx);
      handleByeRounds(bracketIdx);
      nextTick(() => updateLines(bracketIdx));

      const bracket = brackets.value[bracketIdx];
      if (bracket.type === 'Single Elimination') {
        const lastRound = bracket.matches[bracket.matches.length - 1];
        const finalMatch = lastRound[0];
        if (finalMatch.players[0].completed && finalMatch.players[1].completed) {
          const winner = finalMatch.players[0].score >= finalMatch.players[1].score ? finalMatch.players[0] : finalMatch.players[1];
          winnerMessage.value = `Winner: ${winner.name}`;
          showWinnerDialog.value = true;
        }
      } else if (bracket.type === 'Double Elimination') {
        const grandFinals = bracket.matches.grand_finals;
        const lastMatch = grandFinals[grandFinals.length - 1];
        if (lastMatch.players[0].completed && lastMatch.players[1].completed) {
          const winner = lastMatch.players[0].score >= lastMatch.players[1].score ? lastMatch.players[0] : lastMatch.players[1];
          winnerMessage.value = `Tournament Winner: ${winner.name}`;
          showWinnerDialog.value = true;
        }
      }
    }
  };

  const fetchEventDetails = async (eventId) => {
    try {
      // Use Inertia's ability to fetch JSON from a page route
      const response = await axios.get(route('event.details', { id: eventId }), { headers: { 'Accept': 'application/json' } });
      return response.data;
    } catch (error) {
      console.error('Error fetching event details:', error);
      return null;
    }
  };

  const createBracket = async () => {
    if (!bracketName.value || !numberOfPlayers.value || !matchType.value || !selectedEvent.value) {
      showMissingFieldsDialog.value = true;
      return;
    }

    let newBracket;
    if (matchType.value === 'Single Elimination') {
      newBracket = generateBracket();
    } else if (matchType.value === 'Double Elimination') {
      newBracket = generateDoubleEliminationBracket();
    } else if (matchType.value === 'Round Robin') {
      newBracket = generateRoundRobinBracket();
    }

    const payload = {
      id: generateId(), // Set ID first
      name: bracketName.value,
      type: matchType.value,
      event_id: selectedEvent.value.id,
      matches: newBracket,
    };

    // Populate match-specific details from the event
    const populateEventDetails = (match) => {
        match.date = selectedEvent.value.startDate;
        match.time = selectedEvent.value.startTime;
        match.venue = selectedEvent.value.venue;
    };
    Object.values(payload.matches).flat().flat().forEach(populateEventDetails);

    try {
      // Use Laravel API to store the new bracket
      const response = await axios.post(route('api.brackets.store'), payload);
      // Backend returns bracket record (id, name, type, event_id, etc).
      // Build a composed bracket for immediate UI display using the client-side payload.matches
      const savedRaw = response.data;
      const savedBracket = {
        ...savedRaw,
        event: selectedEvent.value,
        // payload.matches can be either:
        // - array of rounds [ [m,m], [m] ] for single/round-robin
        // - object { winners: [...], losers: [...], grand_finals: [...] } for double-elim
        // We'll deep clone to avoid accidental refs
        matches: JSON.parse(JSON.stringify(payload.matches))
      };

      // Ensure for Single/RoundRobin: matches is an array-of-rounds
      if (savedBracket.type === 'Single Elimination' || savedBracket.type === 'Round Robin') {
        if (!Array.isArray(savedBracket.matches)) {
          // If someone passed a flat array, wrap into one round
          savedBracket.matches = Array.isArray(savedBracket.matches) ? savedBracket.matches : [savedBracket.matches || []];
        }
      }

      // For Double Elimination, ensure structure keys exist
      if (savedBracket.type === 'Double Elimination') {
        savedBracket.matches = savedBracket.matches || {};
        savedBracket.matches.winners = savedBracket.matches.winners || [];
        savedBracket.matches.losers = savedBracket.matches.losers || [];
        savedBracket.matches.grand_finals = savedBracket.matches.grand_finals || [];
      }

      // Normalize each match player entries: ensure players array exists and has two slots
      const normalizeMatchPlayers = (match) => {
        if (!match.players || !Array.isArray(match.players)) match.players = [{ id: null, name: 'TBD', score: 0, completed: false }, { id: null, name: 'TBD', score: 0, completed: false }];
        while (match.players.length < 2) match.players.push({ id: null, name: 'TBD', score: 0, completed: false });
      };

      if (savedBracket.type === 'Double Elimination') {
        savedBracket.matches.winners.flat().forEach(normalizeMatchPlayers);
        savedBracket.matches.losers.flat().forEach(normalizeMatchPlayers);
        (savedBracket.matches.grand_finals || []).forEach(normalizeMatchPlayers);
      } else {
        (savedBracket.matches || []).flat().forEach(normalizeMatchPlayers);
      }

      // Insert into client state
      brackets.value.push(savedBracket);
      expandedBrackets.value.push(false);
      const newIdx = brackets.value.length - 1;
      bracketViewModes.value[newIdx] = 'bracket';
      bracketMatchFilters.value[newIdx] = 'all';
      showDialog.value = false;

      // Setup lines / BYE handling for the new bracket
      handleByeRounds(newIdx);
      nextTick(() => updateLines(newIdx));

      successMessage.value = 'Bracket created successfully!';
      showSuccessDialog.value = true;
    } catch (error) {
      console.error('Error creating bracket:', error);
    }
  };

  const fetchBrackets = async (eventId = null) => {
    try {
      let response;
      if (eventId) {
        // This route is not yet updated for normalized data, so we'll use the main one for now.
        // We can filter client-side.
        response = await axios.get(route('api.brackets.index'));
      } else {
        // Fetch all brackets (requires auth)
        response = await axios.get(route('api.brackets.index'));
      }

      if (response.data && response.data.brackets) {
        const db = response.data;
        const allMatches = db.matches || [];
        const allMatchPlayers = db.matchPlayers || [];

        // Filter brackets if an eventId is provided
        const sourceBrackets = eventId
            ? db.brackets.filter(b => b.event_id === eventId)
            : db.brackets;

        const composedBrackets = await Promise.all(
          sourceBrackets.map(async (bracket) => {
            const eventDetails = await fetchEventDetails(bracket.event_id);
            const newBracket = { ...bracket, event: eventDetails || { title: 'Event not found' } };

            // Find matches for this bracket
            const bracketMatches = allMatches.filter(m => m.bracket_id === bracket.id);

            // Find and attach players to each match
            bracketMatches.forEach(match => {
                match.players = allMatchPlayers
                    .filter(mp => mp.match_id === match.id)
                    .sort((a, b) => a.slot - b.slot)
                    .map(mp => ({ id: mp.player_id, name: mp.name, score: mp.score, completed: mp.completed }));

                // Populate match details from the event if they are missing
                if (!match.date) match.date = newBracket.event.startDate;
                if (!match.time) match.time = newBracket.event.startTime;
                if (!match.venue) match.venue = newBracket.event.venue;
            });

            // Reconstruct the nested `matches` structure from the flat list
            if (bracket.type === 'Double Elimination') {
                const winnersMatches = bracketMatches.filter(m => m.bracket_type === 'winners');
                const losersMatches = bracketMatches.filter(m => m.bracket_type === 'losers');
                const grandFinalsMatches = bracketMatches.filter(m => m.bracket_type === 'grand_finals');

                const groupByRound = (matches) => matches.reduce((acc, match) => {
                    const round = match.round - 1;
                    if (!acc[round]) acc[round] = [];
                    acc[round].push(match);
                    return acc;
                }, []);

                newBracket.matches = {
                    winners: groupByRound(winnersMatches),
                    losers: groupByRound(losersMatches),
                    grand_finals: grandFinalsMatches,
                };
            } else {
                newBracket.matches = bracketMatches.reduce((acc, match) => {
                    const round = match.round - 1;
                    if (!acc[round]) acc[round] = [];
                    acc[round].push(match);
                    return acc;
                }, []);
            }
            return newBracket;
          })
        );

        brackets.value = composedBrackets;
        expandedBrackets.value = new Array(brackets.value.length).fill(false);
        bracketViewModes.value = {};
        bracketMatchFilters.value = {};
        for (let i = 0; i < brackets.value.length; i++) {
            bracketViewModes.value[i] = 'bracket';
            bracketMatchFilters.value[i] = 'all';
        }
      }
    } catch (error) {
      console.error('Error fetching and composing brackets:', error);
    }
  };

  const generateId = () => Math.random().toString(36).substr(2, 9);

  const openDialog = async () => {
    bracketName.value = "";
    numberOfPlayers.value = null;
    matchType.value = "";
    selectedEvent.value = null;

    try {
      // Use Laravel API route for events
      const response = await axios.get(route('api.events.index'));
      events.value = response.data.filter(event => {
        const categoryId = parseInt(event.category_id);
        return categoryId === 3 && !event.archived; // Category 3 is 'Sports'
      });
    } catch (error) {
      console.error('Error fetching events:', error);
    }

    showDialog.value = true;
  };

  const generateBracket = () => {
    const numPlayers = parseInt(numberOfPlayers.value, 10);
    const totalSlots = Math.pow(2, Math.ceil(Math.log2(numPlayers)));
    const totalByes = totalSlots - numPlayers;

    const players = Array.from({ length: numPlayers }, (_, i) => ({
      id: generateId(),
      name: `Player ${i + 1}`,
      score: 0,
      completed: false,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    }));
    const byes = Array.from({ length: totalByes }, () => ({
      id: generateId(),
      name: 'BYE',
      score: 0,
      completed: true,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    }));

    // Standard seeding: give BYEs to top seeds.
    const seedToParticipant = new Map();
    for (let i = 0; i < numPlayers; i++) {
      seedToParticipant.set(i + 1, players[i]);
    }
    // Byes get the highest seed numbers, which means they will be paired against top seeds.
    for (let i = 0; i < totalByes; i++) {
      seedToParticipant.set(numPlayers + 1 + i, byes[i]);
    }

    const slots = Array(totalSlots).fill(null);
    const seedingOrder = getSeedingOrder(totalSlots);
    for (let i = 0; i < totalSlots; i++) {
      const seed = seedingOrder[i];
      const participant = seedToParticipant.get(seed);
      slots[i] = participant;
    }

    const firstRound = [];
    for (let i = 0; i < totalSlots; i += 2) {
      const match = {
        id: generateId(),
        round: 1,
        match_number: i / 2 + 1,
        players: [slots[i], slots[i + 1]],
        winner_id: null,
        status: 'pending',
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
        date: selectedEvent.value.startDate,
        time: selectedEvent.value.startTime,
        venue: selectedEvent.value.venue,
      };
      firstRound.push(match);
    }

    const rounds = [firstRound];
    let prevMatches = firstRound;
    let roundNumber = 2;
    while (prevMatches.length > 1) {
      const nextMatches = Array.from({ length: Math.ceil(prevMatches.length / 2) }, (_, i) => ({
        id: generateId(),
        round: roundNumber,
        match_number: i + 1,
        players: [
          { id: null, name: 'TBD', score: 0, completed: false, created_at: new Date().toISOString(), updated_at: new Date().toISOString() },
          { id: null, name: 'TBD', score: 0, completed: false, created_at: new Date().toISOString(), updated_at: new Date().toISOString() },
        ],
        winner_id: null,
        status: 'pending',
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
        date: selectedEvent.value.startDate,
        time: selectedEvent.value.startTime,
        venue: selectedEvent.value.venue,
      }));
      rounds.push(nextMatches);
      prevMatches = nextMatches;
      roundNumber++;
    }
    return rounds;
  };

  const generateDoubleEliminationBracket = () => {
    const numPlayers = parseInt(numberOfPlayers.value, 10);
    const totalSlots = Math.pow(2, Math.ceil(Math.log2(numPlayers)));
    const totalByes = totalSlots - numPlayers;

    // --- Create players & BYEs ---
    const players = Array.from({ length: numPlayers }, (_, i) => ({
      id: generateId(),
      name: `Player ${i + 1}`,
      score: 0,
      completed: false,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    }));
    const byes = Array.from({ length: totalByes }, () => ({
      id: generateId(),
      name: 'BYE',
      score: 0,
      completed: true,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    }));

    // Seeding order
    const seedToParticipant = new Map();
    for (let i = 0; i < numPlayers; i++) seedToParticipant.set(i + 1, players[i]);
    for (let i = 0; i < totalByes; i++) seedToParticipant.set(numPlayers + 1 + i, byes[i]);

    const winnersSlots = Array(totalSlots).fill(null);
    const seedingOrder = getSeedingOrder(totalSlots);
    for (let i = 0; i < totalSlots; i++) {
      winnersSlots[i] = seedToParticipant.get(seedingOrder[i]);
    }

    // --- Winners Bracket Generation ---
    const winnersRounds = [];
    let currentRound = [];
    for (let i = 0; i < totalSlots; i += 2) {
      currentRound.push({
        id: generateId(),
        round: 1,
        match_number: i / 2 + 1,
        bracket_type: 'winners',
        players: [winnersSlots[i], winnersSlots[i + 1]],
        winner_id: null,
        loser_id: null,
        status: 'pending',
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
        date: selectedEvent.value.startDate,
        time: selectedEvent.value.startTime,
        venue: selectedEvent.value.venue,
      });
    }
    winnersRounds.push(currentRound);

    while (currentRound.length > 1) {
      const nextRound = Array.from({ length: Math.ceil(currentRound.length / 2) }, (_, i) => ({
        id: generateId(),
        round: winnersRounds.length + 1,
        match_number: i + 1,
        bracket_type: 'winners',
        players: [
          { id: null, name: 'TBD', score: 0, completed: false, created_at: new Date().toISOString(), updated_at: new Date().toISOString() },
          { id: null, name: 'TBD', score: 0, completed: false, created_at: new Date().toISOString(), updated_at: new Date().toISOString() },
        ],
        winner_id: null,
        loser_id: null,
        status: 'pending',
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
        date: selectedEvent.value.startDate,
        time: selectedEvent.value.startTime,
        venue: selectedEvent.value.venue,
      }));
      winnersRounds.push(nextRound);
      currentRound = nextRound;
    }

    // --- Losers Bracket Generation ---
    const losersRounds = [];
    const numWinnerRounds = winnersRounds.length;
    const totalLoserRounds = 2 * (numWinnerRounds - 1);

    // Step 1: Generate the full, empty structure of the losers bracket based on total slots.
    // This ensures the structure is correct regardless of the number of players or byes.
    for (let i = 0; i < totalLoserRounds; i++) {
      // The number of matches halves for every "minor" consolidation round (LR3, LR5, etc.)
      // which are at even-numbered indices (i=2, 4, ...).
      const matchCount = totalSlots / Math.pow(2, Math.floor(i / 2) + 2);

      if (matchCount < 1) continue;

      const round = Array.from({ length: matchCount }, (_, j) => ({
        id: generateId(),
        round: i + 1,
        match_number: j + 1,
        bracket_type: 'losers',
        players: [
          { id: null, name: 'TBD', score: 0, completed: false, created_at: new Date().toISOString(), updated_at: new Date().toISOString() },
          { id: null, name: 'TBD', score: 0, completed: false, created_at: new Date().toISOString(), updated_at: new Date().toISOString() },
        ],
        winner_id: null, loser_id: null, status: 'pending',
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
        date: selectedEvent.value.startDate,
        time: selectedEvent.value.startTime,
        venue: selectedEvent.value.venue,
      }));
      losersRounds.push(round);
    }

    // Step 2: Populate Losers Bracket with placeholders for players dropping from Winners Bracket
    // Mapping rules (0-based indices):
    // - WR1 (wrRoundIdx=0) losers drop into LR1 (lrRoundIdx=0), pairing sequential non-BYE WR1 matches: [0&1] -> LR1-0, [2&3] -> LR1-1, ...
    //   We compress indices by skipping WR1 matches that are BYE vs someone (they produce no loser).
    // - WRr (wrRoundIdx>=1) losers drop into LR(2r-1) with same match index and in the second player slot (index 1).
    for (let wrRoundIdx = 0; wrRoundIdx < winnersRounds.length - 1; wrRoundIdx++) {
        const winnersRound = winnersRounds[wrRoundIdx];

        if (wrRoundIdx === 0) {
            // Handle WR1 -> LR1 with compression to skip BYE-only matches
            let nonByeCounter = 0;
            winnersRound.forEach((wrMatch) => {
                const hasLoser = wrMatch.players[0]?.name !== 'BYE' && wrMatch.players[1]?.name !== 'BYE';
                if (!hasLoser) return; // This WR1 match will never produce a loser

                const lrRoundIdx = 0; // LR1
                const lrMatchIdx = Math.floor(nonByeCounter / 2); // pair losers 0&1, 2&3, ...
                const lrPlayerPos = nonByeCounter % 2; // 0 then 1 for each pair
                nonByeCounter++;

                const loserPlaceholder = {
                    id: null,
                    name: `Loser of R1 M${wrMatch.match_number}`,
                    score: 0, completed: false, created_at: new Date().toISOString(), updated_at: new Date().toISOString(),
                };

                if (losersRounds[lrRoundIdx] && losersRounds[lrRoundIdx][lrMatchIdx]) {
                    losersRounds[lrRoundIdx][lrMatchIdx].players[lrPlayerPos] = loserPlaceholder;
                }
            });
        } else {
            // WR2+ -> LR(2r-1), same match index, loser goes to slot 1 (faces winner coming from previous LR round)
            const lrRoundIdx = (wrRoundIdx * 2) - 1;
            winnersRound.forEach((wrMatch, wrMatchIdx) => {
                const lrMatchIdx = wrMatchIdx;
                const lrPlayerPos = 1;
                const loserPlaceholder = {
                    id: null,
                    name: `Loser of R${wrRoundIdx + 1} M${wrMatch.match_number}`,
                    score: 0, completed: false, created_at: new Date().toISOString(), updated_at: new Date().toISOString(),
                };
                if (losersRounds[lrRoundIdx] && losersRounds[lrRoundIdx][lrMatchIdx]) {
                    losersRounds[lrRoundIdx][lrMatchIdx].players[lrPlayerPos] = loserPlaceholder;
                }
            });
        }
    }

    // --- Grand Finals ---
    const grandFinals = [{
      id: generateId(),
      round: 1,
      match_number: 1,
      bracket_type: 'grand_finals',
      players: [
      { id: null, name: 'TBD', score: 0, completed: false, created_at: new Date().toISOString(), updated_at: new Date().toISOString() },
      { id: null, name: 'TBD', score: 0, completed: false, created_at: new Date().toISOString(), updated_at: new Date().toISOString() },
      ],
      winner_id: null, loser_id: null, status: 'pending',
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
      date: selectedEvent.value.startDate,
      time: selectedEvent.value.startTime,
      venue: selectedEvent.value.venue,
    }];

    return { winners: winnersRounds, losers: losersRounds, grand_finals: grandFinals };
  };

  const getBracketStats = (bracket) => {
    const allMatches = getAllMatches(bracket);
    if (!allMatches || allMatches.length === 0) {
      return { status: { text: 'Upcoming', class: 'status-upcoming' }, participants: 0, rounds: 0, winnerName: null };
    }

    const hasScores = allMatches.some(m => m.players.some(p => p.score > 0));
    const completedMatches = allMatches.filter(m => m.status === 'completed').length;
    let status;
    let winnerName = null;

    if (completedMatches === allMatches.length) {
      status = { text: 'Completed', class: 'status-completed' };
      // Determine winner based on bracket type
      if (bracket.type === 'Single Elimination') {
        const finalMatch = bracket.matches[bracket.matches.length - 1][0];
        if (finalMatch && finalMatch.winner_id) {
          winnerName = finalMatch.players.find(p => p.id === finalMatch.winner_id)?.name;
        }
      } else if (bracket.type === 'Double Elimination') {
        const grandFinalsMatch = bracket.matches.grand_finals[0];
        if (grandFinalsMatch && grandFinalsMatch.winner_id) {
          winnerName = grandFinalsMatch.players.find(p => p.id === grandFinalsMatch.winner_id)?.name;
        }
      } else if (bracket.type === 'Round Robin') {
        const bracketIdx = brackets.value.findIndex(b => b.id === bracket.id);
        if (bracketIdx !== -1) {
          const standings = getRoundRobinStandings(bracketIdx);
          if (standings.length > 0) {
            winnerName = standings[0].name; // Top of the standings
          }
        }
      }
    } else if (completedMatches > 0 || hasScores) {
      status = { text: 'Ongoing', class: 'status-ongoing' };
    } else {
      status = { text: 'Upcoming', class: 'status-upcoming' };
    }

    const players = new Set();
    allMatches.forEach(match => {
      match.players.forEach(player => {
        if (player && player.name && player.name !== 'TBD' && player.name !== 'BYE') {
          players.add(player.id);
        }
      });
    });

    let rounds = 0;
    if (bracket.type === 'Single Elimination' || bracket.type === 'Round Robin') {
      rounds = bracket.matches.length;
    } else if (bracket.type === 'Double Elimination') {
      rounds = bracket.matches.winners.length + bracket.matches.losers.length;
    }

    return {
      status,
      participants: players.size,
      rounds,
      winnerName
    };
  };

  const generateRoundRobinBracket = () => {
  const numPlayers = parseInt(numberOfPlayers.value, 10);

  // Ensure even number of participants by adding BYE when needed
  const adjustedNumPlayers = numPlayers % 2 === 0 ? numPlayers : numPlayers + 1;

  // Build players array and add BYE if necessary
  const players = Array.from({ length: numPlayers }, (_, i) => ({
    id: generateId(),
    name: `Player ${i + 1}`,
    score: 0,
    completed: false,
    created_at: new Date().toISOString(),
    updated_at: new Date().toISOString(),
  }));

  if (adjustedNumPlayers > numPlayers) {
    players.push({
      id: generateId(),
      name: 'BYE',
      score: 0,
      completed: true,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    });
  }

  // Circle method: fix the first player, rotate others
  const order = players.slice();
  const rounds = [];
  const numRounds = adjustedNumPlayers - 1;
  const halfSize = adjustedNumPlayers / 2;

  for (let round = 0; round < numRounds; round++) {
    const roundMatches = [];

    for (let i = 0; i < halfSize; i++) {
      const a = order[i];
      const b = order[adjustedNumPlayers - 1 - i];

      const match = {
        id: generateId(),
        round: round + 1,
        match_number: i + 1,
        bracket_type: 'round_robin',
        players: [{ ...a }, { ...b }],
        winner_id: null,
        loser_id: null,
        status: 'pending',
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
        date: selectedEvent.value.startDate,
        time: selectedEvent.value.startTime,
        venue: selectedEvent.value.venue,
      };

      // Auto-complete BYE matches
      if (a.name === 'BYE' || b.name === 'BYE') {
        const winner = a.name === 'BYE' ? b : a;
        match.status = 'completed';
        match.winner_id = winner.id;
        match.loser_id = (a.name === 'BYE' ? a : b).id;
        winner.completed = true;
      }

      roundMatches.push(match);
    }

    // --- NEW LOGIC: cycle Player 1’s match slot across rounds ---
    if (roundMatches.length > 1) {
        // Save original number of slots BEFORE removing P1's match
        const originalSlots = roundMatches.length;

        const p1Index = roundMatches.findIndex(m =>
            m.players.some(p => p.name === 'Player 1')
        );
        if (p1Index !== -1) {
            const [p1Match] = roundMatches.splice(p1Index, 1);

            // Use originalSlots so we can place P1 into any of the original slots (including the last)
            const newIndex = round % originalSlots;
            roundMatches.splice(newIndex, 0, p1Match);
        }
    }


    // Reassign match numbers sequentially
    roundMatches.forEach((match, index) => {
      match.match_number = index + 1;
    });

    rounds.push(roundMatches);

    // Rotate players for next round (circle method)
    const fixed = order[0];
    const rest = order.slice(1);
    rest.unshift(rest.pop()); // rotate right
    order.splice(1, rest.length, ...rest);
  }

  return rounds;
};


const updateLines = (bracketIdx) => {
    const bracket = brackets.value[bracketIdx];
    if (!bracket) return;

    // Reset lines
    if (bracket.type === 'Single Elimination') {
        bracket.lines = [];
    } else if (bracket.type === 'Double Elimination') {
        bracket.lines = { winners: [], losers: [], finals: [] };
    } else {
        return; // No lines for Round Robin
    }

    nextTick(() => {
      const bracketContentEl = document.getElementById(`bracket-content-${bracket.id}`);
      if (!bracketContentEl) {
        console.warn(`Could not find bracket container for ID: bracket-content-${bracket.id}`);
        return;
      }

      if (bracket.type === 'Single Elimination') {
        const svgContainer = bracketContentEl.querySelector('.bracket > .connection-lines');
        if (!svgContainer) return;
        const containerRect = svgContainer.getBoundingClientRect();

        for (let round = 0; round < bracket.matches.length - 1; round++) {
          const current = bracket.matches[round];
          current.forEach((match, i) => {
            const fromEl = document.getElementById(`match-${bracketIdx}-${round}-${i}`);
            const toEl = document.getElementById(`match-${bracketIdx}-${round + 1}-${Math.floor(i / 2)}`);
            if (!fromEl || !toEl) return;
            const fromRect = fromEl.getBoundingClientRect();
            const toRect = toEl.getBoundingClientRect();
            const fromCenterY = fromRect.top - containerRect.top + fromRect.height / 2;
            const toCenterY = toRect.top - containerRect.top + toRect.height / 2;
            const fromRightX = fromRect.right - containerRect.left;
            const toLeftX = toRect.left - containerRect.left;
            const midX = (fromRightX + toLeftX) / 2;
            bracket.lines.push(
              { x1: fromRightX, y1: fromCenterY, x2: midX, y2: fromCenterY },
              { x1: midX, y1: fromCenterY, x2: midX, y2: toCenterY },
              { x1: midX, y1: toCenterY, x2: toLeftX, y2: toCenterY },
            );
          });
        }
      } else if (bracket.type === 'Double Elimination') {
        // Winners Bracket Lines
        const winnersContainer = bracketContentEl.querySelector('.winners .connection-lines');
        if (winnersContainer) {
          const winnersRect = winnersContainer.getBoundingClientRect();
          for (let round = 0; round < bracket.matches.winners.length - 1; round++) {
            const current = bracket.matches.winners[round];
            current.forEach((match, i) => {
              const fromEl = document.getElementById(`winners-match-${bracketIdx}-${round}-${i}`);
              const toEl = document.getElementById(`winners-match-${bracketIdx}-${round + 1}-${Math.floor(i / 2)}`);
              if (!fromEl || !toEl) return;
              const fromRect = fromEl.getBoundingClientRect();
              const toRect = toEl.getBoundingClientRect();
              const fromCenterY = fromRect.top - winnersRect.top + fromRect.height / 2;
              const toCenterY = toRect.top - winnersRect.top + toRect.height / 2;
              const fromRightX = fromRect.right - winnersRect.left;
              const toLeftX = toRect.left - winnersRect.left;
              const midX = (fromRightX + toLeftX) / 2;
              bracket.lines.winners.push(
                { x1: fromRightX, y1: fromCenterY, x2: midX, y2: fromCenterY },
                { x1: midX, y1: fromCenterY, x2: midX, y2: toCenterY },
                { x1: midX, y1: toCenterY, x2: toLeftX, y2: toCenterY },
              );
            });
          }
        }

        // Losers Bracket Lines
        const losersContainer = bracketContentEl.querySelector('.losers .connection-lines');
        if (losersContainer) {
          const losersRect = losersContainer.getBoundingClientRect();
          for (let round = 0; round < bracket.matches.losers.length - 1; round++) {
            const current = bracket.matches.losers[round];
            current.forEach((match, i) => {
              const fromEl = document.getElementById(`losers-match-${bracketIdx}-${round}-${i}`);
              let nextMatchIdx;
              // The structure of the losers bracket alternates.
              if (round % 2 === 0) { // Advancing from a round like LR1 to LR2, or LR3 to LR4.
                nextMatchIdx = i;
              } else { // Advancing from a round like LR2 to LR3, or LR4 to LR5.
                nextMatchIdx = Math.floor(i / 2);
              }
              const toEl = document.getElementById(`losers-match-${bracketIdx}-${round + 1}-${nextMatchIdx}`);
              if (!fromEl || !toEl) return;
              const fromRect = fromEl.getBoundingClientRect();
              const toRect = toEl.getBoundingClientRect();
              const fromCenterY = fromRect.top - losersRect.top + fromRect.height / 2;
              const toCenterY = toRect.top - losersRect.top + toRect.height / 2;
              const fromRightX = fromRect.right - losersRect.left;
              const toLeftX = toRect.left - losersRect.left;
              const midX = (fromRightX + toLeftX) / 2;
              bracket.lines.losers.push(
                { x1: fromRightX, y1: fromCenterY, x2: midX, y2: fromCenterY },
                { x1: midX, y1: fromCenterY, x2: midX, y2: toCenterY },
                { x1: midX, y1: toCenterY, x2: toLeftX, y2: toCenterY },
              );
            });
          }
        }

      }
    });
  };

  const removeBracket = async (bracketIdx) => {
    deleteBracketIdx.value = bracketIdx;
    showDeleteConfirmDialog.value = true;
  };

  const calculateByes = (numPlayers) => {
    const nextPowerOfTwo = Math.pow(2, Math.ceil(Math.log2(numPlayers)));
    return nextPowerOfTwo - numPlayers;
  };

  const isFinalRound = (bracketIdx, roundIdx) => roundIdx === brackets.value[bracketIdx].matches.length - 1;

  const confirmDeleteBracket = async () => {
    if (deleteBracketIdx.value !== null) {
      try {
        const bracket = brackets.value[deleteBracketIdx.value];
        if (bracket.id) {
          await axios.delete(route('api.brackets.destroy', { bracket: bracket.id }));
        }
        brackets.value.splice(deleteBracketIdx.value, 1);
        expandedBrackets.value.splice(deleteBracketIdx.value, 1);
        if (activeBracketIdx.value === deleteBracketIdx.value) {
          currentMatchIndex.value = 0;
          activeBracketIdx.value = null;
        }
        deleteBracketIdx.value = null;
        successMessage.value = 'Bracket deleted successfully.';
        showSuccessDialog.value = true;
      } catch (e) {
        console.error('Error deleting bracket:', e);
      }
    }
    showDeleteConfirmDialog.value = false;
  };

  const cancelDeleteBracket = () => {
    showDeleteConfirmDialog.value = false;
    deleteBracketIdx.value = null;
  };

  const saveBrackets = async (bracketData) => {
    try {
      // Create a shallow copy and remove the 'event' object before sending.
      const payload = { ...bracketData };
      delete payload.event;

      // Use Laravel API to update the bracket
      await axios.put(route('api.brackets.update', { bracket: payload.id }), payload);
    } catch (e) {
      // TODO: Add user-facing error handling
      console.error('Error saving bracket:', e);
    }
  };

  const getRoundRobinStandings = (bracketIdx) => {
    const bracket = brackets.value[bracketIdx];
    if (bracket.type !== 'Round Robin') return [];

    const playerStats = {};
    const allMatches = bracket.matches.flat();

    // Initialize player stats
    allMatches.forEach(match => {
      match.players.forEach(player => {
        if (player.name !== 'BYE' && player.name !== 'TBD') {
          if (!playerStats[player.id]) {
            playerStats[player.id] = {
              id: player.id,
              name: player.name,
              wins: 0,
              losses: 0,
              draws: 0,
              totalGames: 0,
              points: 0
            };
          }
        }
      });
    });

    // Calculate wins, losses, draws, and points
    allMatches.forEach(match => {
      if (match.status === 'completed') {
        const player1 = match.players[0];
        const player2 = match.players[1];

        if (player1.name === 'BYE' || player2.name === 'BYE') {
          // Handle BYE matches
          const winner = player1.name === 'BYE' ? player2 : player1;
          if (playerStats[winner.id]) {
            playerStats[winner.id].wins++;
            playerStats[winner.id].points += Number(roundRobinScoring.value.win);
          }
        } else if (match.winner_id && match.loser_id) {
          // Regular win/loss
          const winner = match.players.find(p => p.id === match.winner_id);
          const loser = match.players.find(p => p.id === match.loser_id);

          if (winner && playerStats[winner.id]) {
            playerStats[winner.id].wins++;
            playerStats[winner.id].points += Number(roundRobinScoring.value.win);
          }
          if (loser && playerStats[loser.id]) {
            playerStats[loser.id].losses++;
            playerStats[loser.id].points += Number(roundRobinScoring.value.loss);
          }
        } else if (match.is_tie) {
          // Tie match
          if (playerStats[player1.id]) {
            playerStats[player1.id].draws++;
            playerStats[player1.id].points += Number(roundRobinScoring.value.draw);
          }
          if (playerStats[player2.id]) {
            playerStats[player2.id].draws++;
            playerStats[player2.id].points += Number(roundRobinScoring.value.draw);
          }
        }
      }
    });

    // Convert to array
    const standings = Object.values(playerStats);

    // Sort by points (descending), then by wins (descending), then by draws (descending)
    return standings.sort((a, b) => {
      if (b.points !== a.points) return b.points - a.points;
      if (b.wins !== a.wins) return b.wins - a.wins;
      return b.draws - a.draws;
    });
  };

  const isRoundRobinConcluded = (bracketIdx) => {
    const bracket = brackets.value[bracketIdx];
    if (!bracket || bracket.type !== 'Round Robin' || !bracket.matches) {
      return false;
    }
    const allMatches = bracket.matches.flat();
    if (allMatches.length === 0) {
      return false; // No matches, not concluded
    }
    return allMatches.every(match => match.status === 'completed');
  };

  const openMatchDialog = (bracketIdx, roundIdx, matchIdx, match, bracketType = 'round_robin') => {
    selectedMatch.value = { bracketIdx, roundIdx, matchIdx, bracketType };
    const matchDate = match.date || brackets.value[bracketIdx].event.startDate;
    selectedMatchData.value = {
      player1Name: match.players[0].name,
      player2Name: match.players[1].name,
      player1Score: match.players[0].score,
      player2Score: match.players[1].score,
      status: match.status,
      date: robustParseDate(matchDate),
      time: match.time || brackets.value[bracketIdx].event.startTime,
      venue: match.venue || brackets.value[bracketIdx].event.venue,
    };
    showMatchEditorDialog.value = true;
  };

  const findMatchIndices = (bracket, matchId) => {
      if (bracket.type === 'Single Elimination' || bracket.type === 'Round Robin') {
          for (let roundIdx = 0; roundIdx < bracket.matches.length; roundIdx++) {
              const matchIdx = bracket.matches[roundIdx].findIndex(m => m.id === matchId);
              if (matchIdx !== -1) {
                  return { roundIdx, matchIdx, bracketType: bracket.type === 'Single Elimination' ? 'single' : 'round_robin' };
              }
          }
      } else if (bracket.type === 'Double Elimination') {
          for (let roundIdx = 0; roundIdx < bracket.matches.winners.length; roundIdx++) {
              const matchIdx = bracket.matches.winners[roundIdx].findIndex(m => m.id === matchId);
              if (matchIdx !== -1) return { roundIdx, matchIdx, bracketType: 'winners' };
          }
          for (let roundIdx = 0; roundIdx < bracket.matches.losers.length; roundIdx++) {
              const matchIdx = bracket.matches.losers[roundIdx].findIndex(m => m.id === matchId);
              if (matchIdx !== -1) return { roundIdx, matchIdx, bracketType: 'losers' };
          }
          const matchIdx = bracket.matches.grand_finals.findIndex(m => m.id === matchId);
          if (matchIdx !== -1) return { roundIdx: 0, matchIdx, bracketType: 'grand_finals' };
      }
      return null;
  };

  const openMatchEditorFromCard = (bracketIdx, match) => {
      const bracket = brackets.value[bracketIdx];
      const indices = findMatchIndices(bracket, match.id);
      if (indices) openMatchDialog(bracketIdx, indices.roundIdx, indices.matchIdx, match, indices.bracketType);
      else console.error("Could not find match indices for", match);
  };

  const updateMatch = async () => {
    if (!selectedMatch.value) return;

    const { bracketIdx, roundIdx, matchIdx, bracketType } = selectedMatch.value;
    const bracket = brackets.value[bracketIdx];

    // Validate date/time against the parent event window
    const validationError = validateMatchDateTime(bracket?.event, selectedMatchData.value?.date, selectedMatchData.value?.time);
    if (validationError) {
      genericErrorMessage.value = validationError;
      showGenericErrorDialog.value = true;
      return;
    }

    let match;
    if (bracket.type === 'Single Elimination') {
      match = bracket.matches[roundIdx][matchIdx];
    } else if (bracket.type === 'Double Elimination') {
      switch (bracketType) {
        case 'winners':
          match = bracket.matches.winners[roundIdx][matchIdx];
          break;
        case 'losers': // roundIdx is the direct index in the losers array
          match = bracket.matches.losers[roundIdx][matchIdx];
          break;
        case 'grand_finals':
          match = bracket.matches.grand_finals[matchIdx];
          break;
      }
    } else if (bracket.type === 'Round Robin') {
      match = bracket.matches[roundIdx][matchIdx];
    }

    // Update player names directly
    if (match.players[0].name !== selectedMatchData.value.player1Name) {
      const playerId = match.players[0].id;
      const newName = selectedMatchData.value.player1Name;

      // Update player name across all matches in the bracket
      if (bracket.type === 'Single Elimination') {
        for (let r = 0; r < bracket.matches.length; r++) {
          for (let m = 0; m < bracket.matches[r].length; m++) {
            if (bracket.matches[r][m].players[0].id === playerId) {
              bracket.matches[r][m].players[0].name = newName;
              bracket.matches[r][m].players[0].updated_at = new Date().toISOString();
            }
            if (bracket.matches[r][m].players[1].id === playerId) {
              bracket.matches[r][m].players[1].name = newName;
              bracket.matches[r][m].players[1].updated_at = new Date().toISOString();
            }
          }
        }
      } else if (bracket.type === 'Double Elimination') {
        // Update in winners bracket
        for (let r = 0; r < bracket.matches.winners.length; r++) {
          for (let m = 0; m < bracket.matches.winners[r].length; m++) {
            if (bracket.matches.winners[r][m].players[0].id === playerId) {
              bracket.matches.winners[r][m].players[0].name = newName;
              bracket.matches.winners[r][m].players[0].updated_at = new Date().toISOString();
            }
            if (bracket.matches.winners[r][m].players[1].id === playerId) {
              bracket.matches.winners[r][m].players[1].name = newName;
              bracket.matches.winners[r][m].players[1].updated_at = new Date().toISOString();
            }
          }
        }
        // Update in losers bracket
        for (let r = 0; r < bracket.matches.losers.length; r++) {
          for (let m = 0; m < bracket.matches.losers[r].length; m++) {
            if (bracket.matches.losers[r][m].players[0].id === playerId) {
              bracket.matches.losers[r][m].players[0].name = newName;
              bracket.matches.losers[r][m].players[0].updated_at = new Date().toISOString();
            }
            if (bracket.matches.losers[r][m].players[1].id === playerId) {
              bracket.matches.losers[r][m].players[1].name = newName;
              bracket.matches.losers[r][m].players[1].updated_at = new Date().toISOString();
            }
          }
        }
        // Update in grand finals
        for (let m = 0; m < bracket.matches.grand_finals.length; m++) {
          if (bracket.matches.grand_finals[m].players[0].id === playerId) {
            bracket.matches.grand_finals[m].players[0].name = newName;
            bracket.matches.grand_finals[m].players[0].updated_at = new Date().toISOString();
          }
          if (bracket.matches.grand_finals[m].players[1].id === playerId) {
            bracket.matches.grand_finals[m].players[1].name = newName;
            bracket.matches.grand_finals[m].players[1].updated_at = new Date().toISOString();
          }
        }
      } else if (bracket.type === 'Round Robin') {
        // Update player name across all Round Robin matches
        for (let r = 0; r < bracket.matches.length; r++) {
          for (let m = 0; m < bracket.matches[r].length; m++) {
            if (bracket.matches[r][m].players[0].id === playerId) {
              bracket.matches[r][m].players[0].name = newName;
              bracket.matches[r][m].players[0].updated_at = new Date().toISOString();
            }
            if (bracket.matches[r][m].players[1].id === playerId) {
              bracket.matches[r][m].players[1].name = newName;
              bracket.matches[r][m].players[1].updated_at = new Date().toISOString();
            }
          }
        }
      }
    }

    if (match.players[1].name !== selectedMatchData.value.player2Name) {
      const playerId = match.players[1].id;
      const newName = selectedMatchData.value.player2Name;

      // Update player name across all matches in the bracket
      if (bracket.type === 'Single Elimination') {
        for (let r = 0; r < bracket.matches.length; r++) {
          for (let m = 0; m < bracket.matches[r].length; m++) {
            if (bracket.matches[r][m].players[0].id === playerId) {
              bracket.matches[r][m].players[0].name = newName;
              bracket.matches[r][m].players[0].updated_at = new Date().toISOString();
            }
            if (bracket.matches[r][m].players[1].id === playerId) {
              bracket.matches[r][m].players[1].name = newName;
              bracket.matches[r][m].players[1].updated_at = new Date().toISOString();
            }
          }
        }
      } else if (bracket.type === 'Double Elimination') {
        // Update in winners bracket
        for (let r = 0; r < bracket.matches.winners.length; r++) {
          for (let m = 0; m < bracket.matches.winners[r].length; m++) {
            if (bracket.matches.winners[r][m].players[0].id === playerId) {
              bracket.matches.winners[r][m].players[0].name = newName;
              bracket.matches.winners[r][m].players[0].updated_at = new Date().toISOString();
            }
            if (bracket.matches.winners[r][m].players[1].id === playerId) {
              bracket.matches.winners[r][m].players[1].name = newName;
              bracket.matches.winners[r][m].players[1].updated_at = new Date().toISOString();
            }
          }
        }
        // Update in losers bracket
        for (let r = 0; r < bracket.matches.losers.length; r++) {
          for (let m = 0; m < bracket.matches.losers[r].length; m++) {
            if (bracket.matches.losers[r][m].players[0].id === playerId) {
              bracket.matches.losers[r][m].players[0].name = newName;
              bracket.matches.losers[r][m].players[0].updated_at = new Date().toISOString();
            }
            if (bracket.matches.losers[r][m].players[1].id === playerId) {
              bracket.matches.losers[r][m].players[1].name = newName;
              bracket.matches.losers[r][m].players[1].updated_at = new Date().toISOString();
            }
          }
        }
        // Update in grand finals
        for (let m = 0; m < bracket.matches.grand_finals.length; m++) {
          if (bracket.matches.grand_finals[m].players[0].id === playerId) {
            bracket.matches.grand_finals[m].players[0].name = newName;
            bracket.matches.grand_finals[m].players[0].updated_at = new Date().toISOString();
          }
          if (bracket.matches.grand_finals[m].players[1].id === playerId) {
            bracket.matches.grand_finals[m].players[1].name = newName;
            bracket.matches.grand_finals[m].players[1].updated_at = new Date().toISOString();
          }
        }
      } else if (bracket.type === 'Round Robin') {
        // Update player name across all Round Robin matches
        for (let r = 0; r < bracket.matches.length; r++) {
          for (let m = 0; m < bracket.matches[r].length; m++) {
            if (bracket.matches[r][m].players[0].id === playerId) {
              bracket.matches[r][m].players[0].name = newName;
              bracket.matches[r][m].players[0].updated_at = new Date().toISOString();
            }
            if (bracket.matches[r][m].players[1].id === playerId) {
              bracket.matches[r][m].players[1].name = newName;
              bracket.matches[r][m].players[1].updated_at = new Date().toISOString();
            }
          }
        }
      }
    }

    // Update scores
    match.players[0].score = selectedMatchData.value.player1Score;
    match.players[1].score = selectedMatchData.value.player2Score;

    // Update date, time, venue
    if (selectedMatchData.value.date instanceof Date && isValid(selectedMatchData.value.date)) {
        match.date = format(selectedMatchData.value.date, 'yyyy-MM-dd');
    }
    match.time = selectedMatchData.value.time;
    match.venue = selectedMatchData.value.venue;


    // Update match status
    if (selectedMatchData.value.status === 'completed' && match.status !== 'completed') {
      // Check for ties in elimination brackets
      if ((bracket.type === 'Single Elimination' || bracket.type === 'Double Elimination') &&
          match.players[0].score === match.players[1].score) {
        // Cannot complete elimination bracket matches with ties
        alert('Ties are not allowed in elimination brackets. Please adjust the scores to determine a winner.');
        return;
      }

      // End the match
      match.players[0].completed = true;
      match.players[1].completed = true;
      match.status = 'completed';
      match.updated_at = new Date().toISOString();

      // Determine winner and loser (for elimination brackets)
      let winner, loser;
      if (bracket.type === 'Round Robin') {
        // Check for tie in Round Robin
        if (match.players[0].score === match.players[1].score) {
          match.is_tie = true;
          match.winner_id = null;
          match.loser_id = null;
        } else {
          winner = match.players[0].score > match.players[1].score ? match.players[0] : match.players[1];
          loser = match.players[0].score > match.players[1].score ? match.players[1] : match.players[0];
          match.winner_id = winner.id;
          match.loser_id = loser.id;
          match.is_tie = false;
        }
      } else {
        // Elimination brackets - handle BYE specially and prevent ties
        const p0IsBye = match.players[0].name === 'BYE';
        const p1IsBye = match.players[1].name === 'BYE';
        if (p0IsBye || p1IsBye) {
          if (p0IsBye) {
            match.players[0].score = 0; // BYE score always 0
            winner = match.players[1];
            loser = match.players[0];
          } else {
            match.players[1].score = 0; // BYE score always 0
            winner = match.players[0];
            loser = match.players[1];
          }
        } else {
          winner = match.players[0].score > match.players[1].score ? match.players[0] : match.players[1];
          loser = match.players[0].score > match.players[1].score ? match.players[1] : match.players[0];
        }
        match.winner_id = winner.id;
        match.loser_id = loser.id;
        match.is_tie = false;
      }

      const performedActions = [];
      // Handle bracket progression for elimination brackets
      if (bracket.type === 'Single Elimination') {
        if (roundIdx < bracket.matches.length - 1) {
          const nextRoundIdx = roundIdx + 1;
          const nextMatchIdx = Math.floor(matchIdx / 2);
          const nextPlayerPos = matchIdx % 2 === 0 ? 0 : 1;
          setPlayerWithLog(bracket, 'single', nextRoundIdx, nextMatchIdx, nextPlayerPos, { ...winner, score: 0, completed: false, created_at: new Date().toISOString(), updated_at: new Date().toISOString() }, performedActions);
        } else {
          // Final match completed
          winnerMessage.value = `Winner: ${winner.name}`;
          showWinnerDialog.value = true;
        }
      } else if (bracket.type === 'Double Elimination') {
        if (bracketType === 'winners') {
          if (roundIdx < bracket.matches.winners.length - 1) {
            const nextRoundIdx = roundIdx + 1;
            const nextMatchIdx = Math.floor(matchIdx / 2);
            const nextPlayerPos = matchIdx % 2 === 0 ? 0 : 1;
            setPlayerWithLog(bracket, 'winners', nextRoundIdx, nextMatchIdx, nextPlayerPos, { ...winner, score: 0, completed: false, created_at: new Date().toISOString(), updated_at: new Date().toISOString() }, performedActions);
          } else {
            // Winners bracket final - advance to grand finals
            const grandFinalsMatch = bracket.matches.grand_finals[0];
            if (grandFinalsMatch) {
              setPlayerWithLog(bracket, 'grand_finals', 0, 0, 0, { ...winner, score: 0, completed: false, created_at: new Date().toISOString(), updatedAt: new Date().toISOString() }, performedActions);
            }
          }
          // Send loser to losers bracket
          const newLoserPayload = loser.name === 'BYE'
            ? { ...loser } // Keep BYE as is, it should be completed
            : { ...loser, score: 0, completed: false, created_at: new Date().toISOString(), updatedAt: new Date().toISOString() };

          if (roundIdx === 0) { // Losers from Winners Round 1
            // Find the index of this match among those that produce a loser.
            // This is necessary because BYE matches in WR1 don't produce losers,
            // which skews the original matchIdx.
            const matchesWithLosers = bracket.matches.winners[0].filter(m => m.players[0]?.name !== "BYE" && m.players[1]?.name !== "BYE");
            const loserIndex = matchesWithLosers.findIndex(m => m.id === match.id);

            if (loserIndex !== -1) {
              const losersRoundIdx = 0;
              const losersMatchIdx = Math.floor(loserIndex / 2);
              const losersPlayerPos = loserIndex % 2;
              setPlayerWithLog(bracket, 'losers', losersRoundIdx, losersMatchIdx, losersPlayerPos, newLoserPayload, performedActions);
            }
          } else { // Losers from subsequent Winners Rounds
            const losersRoundIdx = (roundIdx * 2) - 1;
            const losersMatchIdx = matchIdx;
            const losersPlayerPos = 1; // They play the winner of the previous losers round
            setPlayerWithLog(bracket, 'losers', losersRoundIdx, losersMatchIdx, losersPlayerPos, newLoserPayload, performedActions);
          }

          // After a winners round match, check if the whole round is complete.
          // If so, convert any remaining TBDs in the corresponding losers round to BYEs.
          const currentWinnersRound = bracket.matches.winners[roundIdx];
          const isWinnersRoundComplete = currentWinnersRound.every(m => m.status === 'completed' || m.players[0]?.name === 'BYE' || m.players[1]?.name === 'BYE');

          if (isWinnersRoundComplete) {
            if (roundIdx === 0) { // After Winners Round 1 is complete
              const losersRound = bracket.matches.losers[0];
              if (losersRound) {
                losersRound.forEach((match, matchIndex) => {
                  if (match.players[0].name === 'TBD') { setPlayerWithLog(bracket, 'losers', 0, matchIndex, 0, { id: generateId(), name: 'BYE', score: 0, completed: true, created_at: new Date().toISOString(), updatedAt: new Date().toISOString() }, performedActions); }
                  if (match.players[1].name === 'TBD') { setPlayerWithLog(bracket, 'losers', 0, matchIndex, 1, { id: generateId(), name: 'BYE', score: 0, completed: true, created_at: new Date().toISOString(), updatedAt: new Date().toISOString() }, performedActions); }
                });
              }
            } else { // After subsequent Winners Rounds (R2, R3, etc.) are complete
              const losersRoundIdx = (roundIdx * 2) - 1;
              const losersRound = bracket.matches.losers[losersRoundIdx];
              if (losersRound) {
                losersRound.forEach((match, matchIndex) => {
                  if (match.players[1].name === 'TBD') { // Losers drop into player slot 1
                     setPlayerWithLog(bracket, 'losers', losersRoundIdx, matchIndex, 1, { id: generateId(), name: 'BYE', score: 0, completed: true, created_at: new Date().toISOString(), updatedAt: new Date().toISOString() }, performedActions);
                  }
                });
              }
            }
          }
        } else if (bracketType === 'losers') {
          if (roundIdx < bracket.matches.losers.length - 1) {
            const nextRoundIdx = roundIdx + 1;
            let nextMatchIdx;
            let nextPlayerPos;

            // The structure of the losers bracket alternates.
            // Rounds 0, 2, 4... feed into rounds 1, 3, 5... which often have the same number of matches.
            // In this case, winner of match `i` advances to match `i`.
            // Rounds 1, 3, 5... feed into rounds 2, 4, 6... which have half the number of matches.
            // In this case, winners of matches `2i` and `2i+1` play each other.
            if (roundIdx % 2 === 0) { // Advancing from a round like LR1 to LR2, or LR3 to LR4.
              nextMatchIdx = matchIdx;
              nextPlayerPos = 0; // Winners advance to the top slot of the next match.
            } else { // Advancing from a round like LR2 to LR3, or LR4 to LR5.
              nextMatchIdx = Math.floor(matchIdx / 2);
              nextPlayerPos = matchIdx % 2;
            }

            if (bracket.matches.losers[nextRoundIdx] && bracket.matches.losers[nextRoundIdx][nextMatchIdx]) {
              setPlayerWithLog(bracket, 'losers', nextRoundIdx, nextMatchIdx, nextPlayerPos, { ...winner, score: 0, completed: false, created_at: new Date().toISOString(), updatedAt: new Date().toISOString() }, performedActions);
            }
          } else {
            // Losers bracket final - advance to grand finals
            const grandFinalsMatch = bracket.matches.grand_finals[0];
            if (grandFinalsMatch) {
              const winnersFinal = bracket.matches.winners[bracket.matches.winners.length - 1][0];
              if (winnersFinal.status === 'completed') {
                setPlayerWithLog(bracket, 'grand_finals', 0, 0, 1, { ...winner, score: 0, completed: false, created_at: new Date().toISOString(), updatedAt: new Date().toISOString() }, performedActions);
              } else {
                setPlayerWithLog(bracket, 'grand_finals', 0, 0, 0, { ...winner, score: 0, completed: false, created_at: new Date().toISOString(), updatedAt: new Date().toISOString() }, performedActions);
              }
            }
          }

          // After a losers round match, check if the whole round is complete.
          // If so, convert any remaining TBDs in the next losers round to BYEs.
          const currentLosersRound = bracket.matches.losers[roundIdx];
          const isLosersRoundComplete = currentLosersRound.every(m => m.status === 'completed' || m.players[0]?.name === 'BYE' || m.players[1]?.name === 'BYE');

          if (isLosersRoundComplete && roundIdx < bracket.matches.losers.length - 1) {
            const nextRoundIdx = roundIdx + 1;
            const nextLosersRound = bracket.matches.losers[nextRoundIdx];

            // When a losers round completes, check the next round for TBD slots that should now be BYEs.
            if (nextLosersRound) {
              nextLosersRound.forEach((match, matchIndex) => {
                // For "major" rounds (odd index, e.g., LR2) that receive winners bracket losers,
                // we should NOT convert the second player slot (players[1]) to a BYE here.
                // That conversion is handled only when the corresponding WINNERS round is complete.
                // We only handle players[0] here, which is fed by the current, completed losers round.
                if (nextRoundIdx % 2 !== 0) { // e.g., advancing to LR2, LR4 (indices 1, 3, ...)
                  if (match.players[0].name === 'TBD') {
                    setPlayerWithLog(bracket, 'losers', nextRoundIdx, matchIndex, 0, { id: generateId(), name: 'BYE', score: 0, completed: true, created_at: new Date().toISOString(), updatedAt: new Date().toISOString() }, performedActions);
                  }
                } else { // For "minor" consolidation rounds (even index, e.g., LR3, LR5, ...), both slots are fed from the previous losers round.
                  if (match.players[0].name === 'TBD') {
                    setPlayerWithLog(bracket, 'losers', nextRoundIdx, matchIndex, 0, { id: generateId(), name: 'BYE', score: 0, completed: true, created_at: new Date().toISOString(), updatedAt: new Date().toISOString() }, performedActions);
                  }
                  if (match.players[1].name === 'TBD') {
                    setPlayerWithLog(bracket, 'losers', nextRoundIdx, matchIndex, 1, { id: generateId(), name: 'BYE', score: 0, completed: true, created_at: new Date().toISOString(), updatedAt: new Date().toISOString() }, performedActions);
                  }
                }
              });
            }
          }
        } else if (bracketType === 'grand_finals') {
          // Grand finals completed
          winnerMessage.value = `Tournament Winner: ${winner.name}`;
          showWinnerDialog.value = true;
        }
        if (bracket.id && performedActions.length) {
          const stack = bracketActionLog.get(bracket.id) || [];
          stack.push(performedActions);
          bracketActionLog.set(bracket.id, stack);
        }
      }
    } else if (selectedMatchData.value.status !== 'completed' && match.status === 'completed') {
      // Undo the match
      match.players[0].completed = false;
      match.players[1].completed = false;
      match.status = 'pending';
      match.winner_id = null;
      match.loser_id = null;
      // Note: Undoing bracket progression would be complex and might cause data inconsistencies
      // For now, we'll just undo the match status and let users manually fix if needed
    }

    // Process any resulting BYE matches before saving.
    if (bracket.type === 'Single Elimination' || bracket.type === 'Double Elimination') {
        handleByeRounds(bracketIdx);
    }

    try {
      await saveBrackets(bracket);
      // Update connection lines for elimination brackets
      if (bracket.type === 'Single Elimination' || bracket.type === 'Double Elimination') {
        nextTick(() => updateLines(bracketIdx));
      }
      showMatchEditorDialog.value = false;
    } catch (error) {
      console.error('Error updating Round Robin match:', error);
    }
  };

  const closeMatchEditorDialog = () => {
    showMatchEditorDialog.value = false;
    selectedMatch.value = null;
    selectedMatchData.value = null;
  };

  const proceedWithMatchUpdate = async () => {
    // This is now the primary action called after the confirmation inside MatchEditorDialog.
    // It directly proceeds to update the match.
    await updateMatch();
  };

  const openScoringConfigDialog = () => {
    tempScoringConfig.value = deepClone(roundRobinScoring.value); // Save current state
    showScoringConfigDialog.value = true;
  };

  const closeScoringConfigDialog = () => {
    roundRobinScoring.value = tempScoringConfig.value; // Restore on cancel
    showScoringConfigDialog.value = false;
  };

  const saveScoringConfig = () => {
    const SCORING_CONFIG_KEY = 'roundRobinScoringConfig';
    // Ensure values are numbers before saving
    roundRobinScoring.value.win = Number(roundRobinScoring.value.win);
    roundRobinScoring.value.draw = Number(roundRobinScoring.value.draw);
    roundRobinScoring.value.loss = Number(roundRobinScoring.value.loss);
    localStorage.setItem(SCORING_CONFIG_KEY, JSON.stringify(roundRobinScoring.value));
    standingsRevision.value++; // Force re-computation of standings
    showScoringConfigDialog.value = false;
  };

  watch(currentMatchIndex, () => {
    if (activeBracketIdx.value !== null) updateLines(activeBracketIdx.value);
  });

  watch(() => brackets.value.length, (newLength, oldLength) => {
    if (newLength > oldLength) updateLines(newLength - 1);
  });

  return {
    openDialog,
    toggleBracket,
    createBracket,
    fetchBrackets,
    updateLines,
    removeBracket,
    calculateByes,
    isFinalRound,
    confirmDeleteBracket,
    cancelDeleteBracket,
    saveBrackets,
    bracketTypeOptions,
    getRoundRobinStandings,
    isRoundRobinConcluded,
    openMatchDialog,
    updateMatch,
    closeMatchEditorDialog,
    proceedWithMatchUpdate,
    openScoringConfigDialog,
    closeScoringConfigDialog,
    saveScoringConfig,
    handleByeRounds,
    setBracketViewMode,
    setBracketMatchFilter,
    openMatchEditorFromCard,
    getBracketStats,
    getBracketTypeClass,
  };
}
