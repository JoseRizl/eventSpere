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

const getAllMatches = (bracket, filter = 'all') => {
    let allMatches = [];
    if (!bracket || !bracket.matches) return allMatches;

    if (bracket.type === 'Single Elimination' || bracket.type === 'Round Robin') {
        allMatches = bracket.matches.flat();
    } else if (bracket.type === 'Double Elimination') {
        allMatches = [
            ...bracket.matches.winners.flat(),
            ...bracket.matches.losers.flat(),
            ...(bracket.matches.grand_finals || []).flat()
        ];
    } else {
        return [];
    }

    let filteredMatches = allMatches;
    if (filter && filter !== 'all') {
        filteredMatches = allMatches.filter(match => match.status === filter);
    }

    const statusOrder = { 'ongoing': 1, 'pending': 2, 'completed': 3 };
    filteredMatches.sort((a, b) => {
        const statusA = statusOrder[a.status] || 99;
        const statusB = statusOrder[b.status] || 99;
        return statusA !== statusB ? statusA - statusB : a.id - b.id;
    });

    return filteredMatches;
};

const getBracketStats = (bracket, roundRobinScoring) => {
    const allMatches = getAllMatches(bracket);
    if (!allMatches || allMatches.length === 0) {
        return { status: { text: 'Upcoming', class: 'status-upcoming' }, participants: 0, rounds: 0 };
    }

    const hasScores = allMatches.some(m => m.players.some(p => p.score > 0));
    const completedMatches = allMatches.filter(m => m.status === 'completed').length;
    let status;

    if (completedMatches === allMatches.length) {
        status = { text: 'Completed', class: 'status-completed' };
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
        rounds
    };
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

  const bracketTypeOptions = state.bracketTypeOptions;

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

    if (bracket.type === 'Single Elimination') {
      for (let roundIdx = 0; roundIdx < bracket.matches.length - 1; roundIdx++) {
        const currentRound = bracket.matches[roundIdx];
        currentRound.forEach((match, matchIdx) => {
          if (match.players[0].name === 'BYE' || match.players[1].name === 'BYE') {
            const winner = match.players[0].name === 'BYE' ? match.players[1] : match.players[0];
            if (winner.name === 'TBD') {
                return; // Skip TBD vs BYE
            }

            winner.completed = true;
            match.status = 'completed';
            match.winner_id = winner.id;

            const nextRoundIdx = roundIdx + 1;
            const nextMatchIdx = Math.floor(matchIdx / 2);
            const nextPlayerPos = matchIdx % 2 === 0 ? 0 : 1;
            if (bracket.matches[nextRoundIdx] && bracket.matches[nextRoundIdx][nextMatchIdx]) {
              const nextMatch = bracket.matches[nextRoundIdx][nextMatchIdx];
              nextMatch.players[nextPlayerPos] = {
                ...winner,
                score: 0,
                completed: false,
                created_at: new Date().toISOString(),
                updated_at: new Date().toISOString(),
              };
            }
          }
        });
      }
    } else if (bracket.type === 'Double Elimination') {
      for (let roundIdx = 0; roundIdx < bracket.matches.winners.length - 1; roundIdx++) {
        const currentRound = bracket.matches.winners[roundIdx];
        currentRound.forEach((match, matchIdx) => {
          if (match.players[0].name === 'BYE' || match.players[1].name === 'BYE') {
            const winner = match.players[0].name === 'BYE' ? match.players[1] : match.players[0];
            if (winner.name === 'TBD') {
                return; // Skip TBD vs BYE
            }

            winner.completed = true;
            match.status = 'completed';
            match.winner_id = winner.id;
            const nextRoundIdx = roundIdx + 1;
            const nextMatchIdx = Math.floor(matchIdx / 2);
            const nextPlayerPos = matchIdx % 2 === 0 ? 0 : 1;
            if (bracket.matches.winners[nextRoundIdx] && bracket.matches.winners[nextRoundIdx][nextMatchIdx]) {
              const nextMatch = bracket.matches.winners[nextRoundIdx][nextMatchIdx];
              nextMatch.players[nextPlayerPos] = {
                ...winner,
                score: 0,
                completed: false,
                created_at: new Date().toISOString(),
                updated_at: new Date().toISOString(),
              };
            }
          }
        });
      }

      for (let roundIdx = 0; roundIdx < bracket.matches.losers.length - 1; roundIdx++) {
        const currentRound = bracket.matches.losers[roundIdx];
        currentRound.forEach((match, matchIdx) => {
          if (match.players[0].name === 'BYE' || match.players[1].name === 'BYE') {
            const winner = match.players[0].name === 'BYE' ? match.players[1] : match.players[0];
            if (winner.name === 'TBD') {
                return; // Skip TBD vs BYE
            }

            winner.completed = true;
            match.status = 'completed';
            match.winner_id = winner.id;
            const nextRoundIdx = roundIdx + 1;
            let nextMatchIdx;
            let nextPlayerPos;

            if (roundIdx % 2 === 0) { // Advancing from LR1->LR2, LR3->LR4
              nextMatchIdx = matchIdx;
              nextPlayerPos = 0;
            } else { // Advancing from LR2->LR3, LR4->LR5
              nextMatchIdx = Math.floor(matchIdx / 2);
              nextPlayerPos = matchIdx % 2;
            }

            if (bracket.matches.losers[nextRoundIdx] && bracket.matches.losers[nextRoundIdx][nextMatchIdx]) {
              const nextMatch = bracket.matches.losers[nextRoundIdx][nextMatchIdx];
              nextMatch.players[nextPlayerPos] = {
                ...winner,
                score: 0,
                completed: false,
                created_at: new Date().toISOString(),
                updated_at: new Date().toISOString(),
              };
            }
          }
        });
      }
    }
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
      // The backend now returns a bracket without the event object.
      // We'll add it back on the client-side for immediate display.
      const savedBracket = { ...response.data, event: selectedEvent.value };

      brackets.value.push(savedBracket);
      expandedBrackets.value.push(false);
      bracketViewModes.value[brackets.value.length - 1] = 'bracket';
      bracketMatchFilters.value[brackets.value.length - 1] = 'all';
      showDialog.value = false;
      handleByeRounds(brackets.value.length - 1);
      nextTick(() => updateLines(brackets.value.length - 1));
      successMessage.value = 'Bracket created successfully!';
      showSuccessDialog.value = true;
    } catch (error) {
      console.error('Error creating bracket:', error);
    }
  };

  const fetchBrackets = async () => {
    try {
      // Use Laravel API to fetch brackets
      const response = await axios.get(route('api.brackets.index'));
      if (response.data) {
        const bracketsWithEvents = await Promise.all(
          response.data.map(async (bracket) => {
            if (bracket.event_id) {
              const eventDetails = await fetchEventDetails(bracket.event_id);
              const newBracket = { ...bracket, event: eventDetails || { title: 'Event not found' } };

              const populateMatch = (match) => {
                  if (!match.date) match.date = newBracket.event.startDate;
                  if (!match.time) match.time = newBracket.event.startTime;
                  if (!match.venue) match.venue = newBracket.event.venue;
                  return match;
              };

              if (newBracket.matches && typeof newBracket.matches === 'object') {
                  Object.values(newBracket.matches).forEach(part => {
                      if (Array.isArray(part)) part.flat().forEach(populateMatch);
                  });
              }
              return newBracket;
            } else {
              return { ...bracket, event: { title: 'Event not found' } };
            }
          })
        );
        brackets.value = bracketsWithEvents;
        expandedBrackets.value = new Array(brackets.value.length).fill(false);
        bracketViewModes.value = {};
        bracketMatchFilters.value = {};
        for (let i = 0; i < brackets.value.length; i++) {
            bracketViewModes.value[i] = 'bracket';
            bracketMatchFilters.value[i] = 'all';
        }
      }
    } catch (error) {
      console.error('Error fetching brackets:', error);
    }
  };

  const generateId = () => Math.random().toString(36).substr(2, 9);

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
      if (match.players[0].name === 'BYE' || match.players[1].name === 'BYE') {
        const winner = match.players[0].name === 'BYE' ? match.players[1] : match.players[0];
        winner.completed = true;
        match.status = 'completed';
        match.winner_id = winner.id;
      }
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

    // Step 2: Populate Losers Round 1 with actual losers from Winners Round 1.
    const wr1Matches = winnersRounds[0];
    const wr1Losers = wr1Matches
      .filter(m => m.players[0]?.name !== "BYE" && m.players[1]?.name !== "BYE")
      .map(m => ({
        id: null,
        name: `Loser of WR1 M${m.match_number}`,
        score: 0,
        completed: false,
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
      }));

    if (losersRounds.length > 0 && losersRounds[0]) {
      const lr1 = losersRounds[0];
      let loserIndex = 0;
      for (let i = 0; i < lr1.length; i++) {
        // Player 1
        lr1[i].players[0] = wr1Losers[loserIndex]
          ? wr1Losers[loserIndex++]
          : { id: generateId(), name: 'BYE', score: 0, completed: true, created_at: new Date().toISOString(), updated_at: new Date().toISOString() };

        // Player 2
        lr1[i].players[1] = wr1Losers[loserIndex]
          ? wr1Losers[loserIndex++]
          : { id: generateId(), name: 'BYE', score: 0, completed: true, created_at: new Date().toISOString(), updated_at: new Date().toISOString() };
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

    // We'll pair by taking the current 'order' array and pairing i with (n-1-i).
    // After each round rotate the whole array to the right by 1 so first slot changes.
    const order = players.slice(); // working order
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
          players: [
            { ...a },
            { ...b }
          ],
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
        if (match.players[0].name === 'BYE' || match.players[1].name === 'BYE') {
          const winner = match.players[0].name === 'BYE' ? match.players[1] : match.players[0];
          winner.completed = true;
          match.status = 'completed';
          match.winner_id = winner.id;
          match.loser_id = match.players[0].name === 'BYE' ? match.players[0].id : match.players[1].id;
        }

        roundMatches.push(match);
      }

      rounds.push(roundMatches);

      // rotate entire order right by one element (so first position changes next round)
      order.unshift(order.pop());
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
      // Use Laravel API to update the bracket
      await axios.put(route('api.brackets.update', { bracket: bracketData.id }), bracketData);
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
        // Elimination brackets - no ties allowed (already checked above)
        winner = match.players[0].score > match.players[1].score ? match.players[0] : match.players[1];
        loser = match.players[0].score > match.players[1].score ? match.players[1] : match.players[0];
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
