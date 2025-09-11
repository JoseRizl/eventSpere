import { nextTick, watch } from 'vue';
import axios from 'axios';

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
    currentWinnersMatchIndex,
    currentLosersMatchIndex,
    currentGrandFinalsIndex,
    activeBracketSection,
    showRoundRobinMatchDialog,
    selectedRoundRobinMatch,
    selectedRoundRobinMatchData,
    showMatchUpdateConfirmDialog,
    roundRobinScoring,
    standingsRevision,
    showScoringConfigDialog,
    tempScoringConfig,

  } = state;

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
      const response = await axios.get('http://localhost:3000/events');
      events.value = response.data.filter(event => {
        const categoryId = parseInt(event.category_id);
        return categoryId === 3 && !event.archived;
      });
    } catch (error) {
      console.error('Error fetching events:', error);
    }

    showDialog.value = true;
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
            winner.completed = true;
            match.status = 'completed';
            match.winner_id = winner.id;
            const nextRoundIdx = roundIdx + 1;
            const nextMatchIdx = Math.floor(matchIdx / 2);
            const nextPlayerPos = matchIdx % 2 === 0 ? 0 : 1;
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
      const response = await axios.get(`http://localhost:3000/events/${eventId}`);
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

    const bracketData = {
      name: bracketName.value,
      type: matchType.value,
      event_id: selectedEvent.value.id,
      status: 'active',
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
      matches: newBracket,
      lines: matchType.value === 'Single Elimination' ? [] :
            matchType.value === 'Round Robin' ? [] :
            { winners: [], losers: [], finals: [] },
      // Attach event details so UI can render immediately
      event: selectedEvent.value,
    };

    try {
      await saveBrackets(bracketData);
      brackets.value.push(bracketData);
      if (bracketData.id) bracketActionLog.set(bracketData.id, []);
      expandedBrackets.value.push(false);
      showDialog.value = false;
      handleByeRounds(brackets.value.length - 1);
      nextTick(() => updateLines(brackets.value.length - 1));
    } catch (error) {
      console.error('Error creating bracket:', error);
    }
  };

  const fetchBrackets = async () => {
    try {
      const response = await axios.get('http://localhost:3000/brackets');
      if (response.data) {
        const bracketsWithEvents = await Promise.all(
          response.data.map(async (bracket) => {
            try {
              const eventDetails = await fetchEventDetails(bracket.event_id);
              return { ...bracket, event: eventDetails || { title: 'Event not found' } };
            } catch (error) {
              console.error(`Error fetching event details for bracket ${bracket.id}:`, error);
              return { ...bracket, event: { title: 'Event not found' } };
            }
          })
        );
        brackets.value = bracketsWithEvents;
        expandedBrackets.value = new Array(brackets.value.length).fill(false);
        for (const b of brackets.value) {
          if (b.id && !bracketActionLog.has(b.id)) bracketActionLog.set(b.id, []);
        }
      }
    } catch (error) {
      console.error('Error fetching brackets:', error);
    }
  };

  const generateId = () => Math.random().toString(36).substr(2, 9);

  const generateBracket = () => {
    const numPlayers = numberOfPlayers.value;
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

    const slots = Array(totalSlots).fill(null);
    let byeIndex = 0;
    for (let i = totalSlots - 2; i >= 0; i -= 2) {
      if (byeIndex < byes.length) {
        slots[i + 1] = byes[byeIndex++];
      }
    }

    let playerIndex = 0;
    for (let i = 0; i < totalSlots; i++) {
      if (!slots[i] && playerIndex < players.length) {
        slots[i] = players[playerIndex++];
      }
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
      }));
      rounds.push(nextMatches);
      prevMatches = nextMatches;
      roundNumber++;
    }
    return rounds;
  };

  const generateDoubleEliminationBracket = () => {
    const numPlayers = numberOfPlayers.value;
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

    const winnersSlots = Array(totalSlots).fill(null);
    const losersSlots = Array(totalSlots - 1).fill(null);
    let byeIndex = 0;
    for (let i = totalSlots - 2; i >= 0; i -= 2) {
      if (byeIndex < byes.length) {
        winnersSlots[i + 1] = byes[byeIndex++];
      }
    }
    let playerIndex = 0;
    for (let i = 0; i < totalSlots; i++) {
      if (!winnersSlots[i] && playerIndex < players.length) {
        winnersSlots[i] = players[playerIndex++];
      }
    }

    const winnersFirstRound = [];
    for (let i = 0; i < totalSlots; i += 2) {
      const match = {
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
      };
      winnersFirstRound.push(match);
      if (match.players[0].name === 'BYE' || match.players[1].name === 'BYE') {
        const winner = match.players[0].name === 'BYE' ? match.players[1] : match.players[0];
        winner.completed = true;
        match.status = 'completed';
        match.winner_id = winner.id;
      }
    }

    const winnersRounds = Math.ceil(Math.log2(totalSlots));
    const losersRounds = [];
    const firstLosersRound = [];
    for (let i = 0; i < totalSlots / 2; i++) {
      const match = {
        id: generateId(),
        round: 1,
        match_number: i + 1,
        bracket_type: 'losers',
        players: [
          { id: null, name: 'TBD', score: 0, completed: false, created_at: new Date().toISOString(), updated_at: new Date().toISOString() },
          { id: null, name: 'TBD', score: 0, completed: false, created_at: new Date().toISOString(), updated_at: new Date().toISOString() },
        ],
        winner_id: null,
        loser_id: null,
        status: 'pending',
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString(),
      };
      firstLosersRound.push(match);
    }
    losersRounds.push(firstLosersRound);

    let currentRoundMatches = totalSlots / 2;
    let losersRoundNumber = 2;
    while (currentRoundMatches > 1) {
      const nextRoundMatches = Math.ceil(currentRoundMatches / 2);
      const round = [];
      for (let i = 0; i < nextRoundMatches; i++) {
        const match = {
          id: generateId(),
          round: losersRoundNumber,
          match_number: i + 1,
          bracket_type: 'losers',
          players: [
            { id: null, name: 'TBD', score: 0, completed: false, created_at: new Date().toISOString(), updated_at: new Date().toISOString() },
            { id: null, name: 'TBD', score: 0, completed: false, created_at: new Date().toISOString(), updated_at: new Date().toISOString() },
          ],
          winner_id: null,
          loser_id: null,
          status: 'pending',
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString(),
        };
        round.push(match);
      }
      losersRounds.push(round);
      currentRoundMatches = nextRoundMatches;
      losersRoundNumber++;
    }

    const winnersRoundsArray = [winnersFirstRound];
    let prevWinnersMatches = winnersFirstRound;
    let winnersRoundNumber = 2;
    while (prevWinnersMatches.length > 1) {
      const nextMatches = Array.from({ length: Math.ceil(prevWinnersMatches.length / 2) }, (_, i) => ({
        id: generateId(),
        round: winnersRoundNumber,
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
      }));
      winnersRoundsArray.push(nextMatches);
      prevWinnersMatches = nextMatches;
      winnersRoundNumber++;
    }

    const grandFinals = [{
      id: generateId(),
      round: 1,
      match_number: 1,
      bracket_type: 'grand_finals',
      players: [
        { id: null, name: 'TBD', score: 0, completed: false, created_at: new Date().toISOString(), updated_at: new Date().toISOString() },
        { id: null, name: 'TBD', score: 0, completed: false, created_at: new Date().toISOString(), updated_at: new Date().toISOString() },
      ],
      winner_id: null,
      loser_id: null,
      status: 'pending',
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    }];

    return { winners: winnersRoundsArray, losers: losersRounds, grand_finals: grandFinals };
  };

  const generateRoundRobinBracket = () => {
    const numPlayers = numberOfPlayers.value;

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

  const increaseScore = async (bracketIdx, teamIdx) => {
    const bracket = brackets.value[bracketIdx];
    const { roundIdx, matchIdx, bracketType } = getRoundAndMatchIndices(bracketIdx, currentMatchIndex.value);
    let match;
    if (bracket.type === 'Single Elimination') {
      match = bracket.matches[roundIdx][matchIdx];
    } else if (bracket.type === 'Double Elimination') {
      switch (bracketType) {
        case 'winners':
          match = bracket.matches.winners[roundIdx][matchIdx];
          break;
        case 'losers':
          match = bracket.matches.losers[roundIdx - bracket.matches.winners.length][matchIdx];
          break;
        case 'grand_finals':
          match = bracket.matches.grand_finals[matchIdx];
          break;
      }
    }
    if (match && match.status !== 'completed') {
      match.players[teamIdx].score++;
      match.players[teamIdx].updated_at = new Date().toISOString();
      try { await saveBrackets(bracket); } catch (e) { console.error('Error updating score:', e); }
    }
  };

  const decreaseScore = async (bracketIdx, teamIdx) => {
    const bracket = brackets.value[bracketIdx];
    const { roundIdx, matchIdx, bracketType } = getRoundAndMatchIndices(bracketIdx, currentMatchIndex.value);
    let match;
    if (bracket.type === 'Single Elimination') {
      match = bracket.matches[roundIdx][matchIdx];
    } else if (bracket.type === 'Double Elimination') {
      switch (bracketType) {
        case 'winners':
          match = bracket.matches.winners[roundIdx][matchIdx];
          break;
        case 'losers':
          match = bracket.matches.losers[roundIdx - bracket.matches.winners.length][matchIdx];
          break;
        case 'grand_finals':
          match = bracket.matches.grand_finals[matchIdx];
          break;
      }
    }
    if (match && match.status !== 'completed' && match.players[teamIdx].score > 0) {
      match.players[teamIdx].score--;
      match.players[teamIdx].updated_at = new Date().toISOString();
      try { await saveBrackets(bracket); } catch (e) { console.error('Error updating score:', e); }
    }
  };

  const editParticipant = async (bracketIdx, roundIdx, matchIdx, teamIdx, bracketType = 'winners') => {
    const newName = prompt('Enter new participant name:');
    if (!newName) return;
    const bracket = brackets.value[bracketIdx];
    let match;
    if (bracket.type === 'Single Elimination') {
      match = bracket.matches[roundIdx][matchIdx];
    } else if (bracket.type === 'Double Elimination') {
      switch (bracketType) {
        case 'winners':
          match = bracket.matches.winners[roundIdx][matchIdx];
          break;
        case 'losers':
          match = bracket.matches.losers[roundIdx - bracket.matches.winners.length][matchIdx];
          break;
        case 'grand_finals':
          match = bracket.matches.grand_finals[matchIdx];
          break;
      }
    } else if (bracket.type === 'Round Robin') {
      match = bracket.matches[roundIdx][matchIdx];
    }
    if (match) {
      const playerId = match.players[teamIdx].id;
      for (let r = 0; r < (bracket.type === 'Single Elimination' ? bracket.matches.length : bracket.matches.winners.length); r++) {
        const roundMatches = bracket.type === 'Single Elimination' ? bracket.matches[r] : bracket.matches.winners[r];
        for (let m = 0; m < roundMatches.length; m++) {
          if (roundMatches[m].players[0].id === playerId) {
            roundMatches[m].players[0].name = newName; roundMatches[m].players[0].updated_at = new Date().toISOString();
          }
          if (roundMatches[m].players[1].id === playerId) {
            roundMatches[m].players[1].name = newName; roundMatches[m].players[1].updated_at = new Date().toISOString();
          }
        }
      }
      if (bracket.type === 'Double Elimination') {
        for (let r = 0; r < bracket.matches.losers.length; r++) {
          for (let m = 0; m < bracket.matches.losers[r].length; m++) {
            if (bracket.matches.losers[r][m].players[0].id === playerId) {
              bracket.matches.losers[r][m].players[0].name = newName; bracket.matches.losers[r][m].players[0].updated_at = new Date().toISOString();
            }
            if (bracket.matches.losers[r][m].players[1].id === playerId) {
              bracket.matches.losers[r][m].players[1].name = newName; bracket.matches.losers[r][m].players[1].updated_at = new Date().toISOString();
            }
          }
        }
        for (let m = 0; m < bracket.matches.grand_finals.length; m++) {
          if (bracket.matches.grand_finals[m].players[0].id === playerId) {
            bracket.matches.grand_finals[m].players[0].name = newName; bracket.matches.grand_finals[m].players[0].updated_at = new Date().toISOString();
          }
          if (bracket.matches.grand_finals[m].players[1].id === playerId) {
            bracket.matches.grand_finals[m].players[1].name = newName; bracket.matches.grand_finals[m].players[1].updated_at = new Date().toISOString();
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
      try { await saveBrackets(bracket); } catch (e) { console.error('Error updating participant:', e); }
    }
  };

  const cancelEndMatch = () => {
    showConfirmDialog.value = false;
    pendingBracketIdx.value = null;
  };

  const getRoundAndMatchIndices = (bracketIdx, currentIndex) => {
    const bracket = brackets.value[bracketIdx];
    if (bracket.type === 'Single Elimination') {
      let accumulatedMatches = 0;
      for (let roundIdx = 0; roundIdx < bracket.matches.length; roundIdx++) {
        if (currentIndex < accumulatedMatches + bracket.matches[roundIdx].length) {
          return { roundIdx, matchIdx: currentIndex - accumulatedMatches, bracketType: 'winners' };
        }
        accumulatedMatches += bracket.matches[roundIdx].length;
      }
      return { roundIdx: 0, matchIdx: 0, bracketType: 'winners' };
    } else if (bracket.type === 'Double Elimination') {
      switch (activeBracketSection.value) {
        case 'winners': {
          let winnersAccumulated = 0;
          for (let roundIdx = 0; roundIdx < bracket.matches.winners.length; roundIdx++) {
            if (currentWinnersMatchIndex.value < winnersAccumulated + bracket.matches.winners[roundIdx].length) {
              return { roundIdx, matchIdx: currentWinnersMatchIndex.value - winnersAccumulated, bracketType: 'winners' };
            }
            winnersAccumulated += bracket.matches.winners[roundIdx].length;
          }
          break;
        }
        case 'losers': {
          let losersAccumulated = 0;
          for (let roundIdx = 0; roundIdx < bracket.matches.losers.length; roundIdx++) {
            if (currentLosersMatchIndex.value < losersAccumulated + bracket.matches.losers[roundIdx].length) {
              return { roundIdx: roundIdx + bracket.matches.winners.length, matchIdx: currentLosersMatchIndex.value - losersAccumulated, bracketType: 'losers' };
            }
            losersAccumulated += bracket.matches.losers[roundIdx].length;
          }
          break;
        }
        case 'grand_finals':
          return { roundIdx: bracket.matches.winners.length + bracket.matches.losers.length, matchIdx: currentGrandFinalsIndex.value, bracketType: 'grand_finals' };
      }
    }
    return { roundIdx: 0, matchIdx: 0, bracketType: 'winners' };
  };

  const confirmEndMatch = async () => {
    if (pendingBracketIdx.value === null) return;
    const idx = pendingBracketIdx.value;
    const bracket = brackets.value[idx];
    const { roundIdx, matchIdx, bracketType } = getRoundAndMatchIndices(idx, currentMatchIndex.value);

    if (bracket.type === 'Single Elimination') {
      const currentMatch = bracket.matches[roundIdx][matchIdx];
      const winner = currentMatch.players[0].score >= currentMatch.players[1].score ? currentMatch.players[0] : currentMatch.players[1];
      const loser = currentMatch.players[0].score >= currentMatch.players[1].score ? currentMatch.players[1] : currentMatch.players[0];
      currentMatch.players[0].completed = true;
      currentMatch.players[1].completed = true;
      currentMatch.status = 'completed';
      currentMatch.winner_id = winner.id;
      currentMatch.loser_id = loser.id;
      currentMatch.updated_at = new Date().toISOString();
      try {
        if (roundIdx < bracket.matches.length - 1) {
          const nextRoundIdx = roundIdx + 1;
          const nextMatchIdx = Math.floor(matchIdx / 2);
          const nextPlayerPos = matchIdx % 2 === 0 ? 0 : 1;
          if (bracket.matches[nextRoundIdx] && bracket.matches[nextRoundIdx][nextMatchIdx]) {
            const nextMatch = bracket.matches[nextRoundIdx][nextMatchIdx];
            nextMatch.players[nextPlayerPos] = { ...winner, score: 0, completed: false, created_at: new Date().toISOString(), updated_at: new Date().toISOString() };
          }
        } else {
          winnerMessage.value = `Winner: ${winner.name}`; showWinnerDialog.value = true;
        }
        await saveBrackets(bracket);
        nextTick(() => updateLines(idx));
      } catch (e) { console.error('Error concluding match:', e); }
    } else if (bracket.type === 'Double Elimination') {
      let currentMatch;
      switch (bracketType) {
        case 'winners': currentMatch = bracket.matches.winners[roundIdx][matchIdx]; break;
        case 'losers': currentMatch = bracket.matches.losers[roundIdx - bracket.matches.winners.length][matchIdx]; break;
        case 'grand_finals': currentMatch = bracket.matches.grand_finals[matchIdx]; break;
      }
      if (currentMatch) {
        const winner = currentMatch.players[0].score >= currentMatch.players[1].score ? currentMatch.players[0] : currentMatch.players[1];
        const loser = currentMatch.players[0].score >= currentMatch.players[1].score ? currentMatch.players[1] : currentMatch.players[0];
        currentMatch.players[0].completed = true;
        currentMatch.players[1].completed = true;
        currentMatch.status = 'completed';
        currentMatch.winner_id = winner.id;
        currentMatch.loser_id = loser.id;
        currentMatch.updated_at = new Date().toISOString();
        try {
          const performedActions = [];
          if (bracketType === 'winners') {
            if (roundIdx < bracket.matches.winners.length - 1) {
              const nextRoundIdx = roundIdx + 1;
              const nextMatchIdx = Math.floor(matchIdx / 2);
              const nextPlayerPos = matchIdx % 2 === 0 ? 0 : 1;
              setPlayerWithLog(bracket, 'winners', nextRoundIdx, nextMatchIdx, nextPlayerPos, { ...winner, score: 0, completed: false, created_at: new Date().toISOString(), updated_at: new Date().toISOString() }, performedActions);
            } else {
              const grandFinalsMatch = bracket.matches.grand_finals[0];
              if (grandFinalsMatch) {
                setPlayerWithLog(bracket, 'grand_finals', 0, 0, 0, { ...winner, score: 0, completed: false, created_at: new Date().toISOString(), updated_at: new Date().toISOString() }, performedActions);
              }
            }
            // Map losers from winners bracket to losers round 1 in order, skipping BYE auto-completions
            const losersRoundIdx = 0;
            let totalLosers = 0;
            for (let r = 0; r <= roundIdx; r++) {
              for (let m = 0; m < bracket.matches.winners[r].length; m++) {
                if (r === roundIdx && m === matchIdx) { break; }
                if (bracket.matches.winners[r][m].loser_id) { totalLosers++; }
              }
            }
            const losersMatchIdx = Math.floor(totalLosers / 2);
            const losersPlayerPos = totalLosers % 2;
            while (bracket.matches.losers[losersRoundIdx].length <= losersMatchIdx) {
              bracket.matches.losers[losersRoundIdx].push({
                id: generateId(), round: 1, match_number: bracket.matches.losers[losersRoundIdx].length + 1, bracket_type: 'losers',
                players: [
                  { id: null, name: 'TBD', score: 0, completed: false, created_at: new Date().toISOString(), updated_at: new Date().toISOString() },
                  { id: null, name: 'TBD', score: 0, completed: false, created_at: new Date().toISOString(), updated_at: new Date().toISOString() },
                ], winner_id: null, loser_id: null, status: 'pending', created_at: new Date().toISOString(), updated_at: new Date().toISOString(),
              });
            }
            if (loser.name !== 'BYE') {
              setPlayerWithLog(bracket, 'losers', losersRoundIdx, losersMatchIdx, losersPlayerPos, { ...loser, score: 0, completed: false, created_at: new Date().toISOString(), updated_at: new Date().toISOString() }, performedActions);
            }
            if (roundIdx === bracket.matches.winners.length - 1) {
              const firstLosersRound = bracket.matches.losers[0];
              firstLosersRound.forEach(match => {
                if (match.players[0].name === 'TBD') { match.players[0] = { id: generateId(), name: 'BYE', score: 0, completed: true, created_at: new Date().toISOString(), updated_at: new Date().toISOString() }; }
                if (match.players[1].name === 'TBD') { match.players[1] = { id: generateId(), name: 'BYE', score: 0, completed: true, created_at: new Date().toISOString(), updated_at: new Date().toISOString() }; }
              });
              for (let r = 0; r < bracket.matches.losers.length - 1; r++) {
                const currentRound = bracket.matches.losers[r];
                const nextRound = bracket.matches.losers[r + 1];
                currentRound.forEach((match, mIdx) => {
                  if (match.players[0].name === 'BYE' || match.players[1].name === 'BYE') {
                    const winner2 = match.players[0].name === 'BYE' ? match.players[1] : match.players[0];
                    winner2.completed = true; match.status = 'completed'; match.winner_id = winner2.id;
                    const nextMatchIdx2 = Math.floor(mIdx / 2);
                    const nextPlayerPos2 = mIdx % 2 === 0 ? 0 : 1;
                    setPlayerWithLog(bracket, 'losers', r + 1, nextMatchIdx2, nextPlayerPos2, { ...winner2, score: 0, completed: false, created_at: new Date().toISOString(), updated_at: new Date().toISOString() }, performedActions);
                  }
                });
              }
            }
          } else if (bracketType === 'losers') {
            if (roundIdx < bracket.matches.winners.length + bracket.matches.losers.length - 1) {
              const nextRoundIdx = roundIdx - bracket.matches.winners.length + 1;
              const nextMatchIdx = Math.floor(matchIdx / 2);
              const nextPlayerPos = matchIdx % 2 === 0 ? 0 : 1;
              setPlayerWithLog(bracket, 'losers', nextRoundIdx, nextMatchIdx, nextPlayerPos, { ...winner, score: 0, completed: false, created_at: new Date().toISOString(), updated_at: new Date().toISOString() }, performedActions);
            }
            if (roundIdx === bracket.matches.winners.length + bracket.matches.losers.length - 1) {
              const grandFinalsMatch = bracket.matches.grand_finals[0];
              if (grandFinalsMatch) {
                const winnersFinal = bracket.matches.winners[bracket.matches.winners.length - 1][0];
                if (winnersFinal.status === 'completed') {
                  setPlayerWithLog(bracket, 'grand_finals', 0, 0, 1, { ...winner, score: 0, completed: false, created_at: new Date().toISOString(), updated_at: new Date().toISOString() }, performedActions);
                } else {
                  setPlayerWithLog(bracket, 'grand_finals', 0, 0, 0, { ...winner, score: 0, completed: false, created_at: new Date().toISOString(), updated_at: new Date().toISOString() }, performedActions);
                }
              }
            }
          } else if (bracketType === 'grand_finals') {
            winnerMessage.value = `Tournament Winner: ${winner.name}`; showWinnerDialog.value = true;
          }
          await saveBrackets(bracket);
          nextTick(() => updateLines(idx));
          if (bracket.id && performedActions.length) {
            const stack = bracketActionLog.get(bracket.id) || [];
            stack.push(performedActions);
            bracketActionLog.set(bracket.id, stack);
          }
        } catch (e) {
          console.error('Error concluding match:', e);
        }
      }
    }
    showConfirmDialog.value = false;
    pendingBracketIdx.value = null;
  };

  const undoConcludeMatch = async (bracketIdx) => {
    const { roundIdx, matchIdx, bracketType } = getRoundAndMatchIndices(bracketIdx, currentMatchIndex.value);
    const bracket = brackets.value[bracketIdx];
    if (bracket.type === 'Single Elimination') {
      const currentMatch = bracket.matches[roundIdx][matchIdx];
      if (confirm('Are you sure you want to undo this match completion?')) {
        currentMatch.players[0].completed = false;
        currentMatch.players[1].completed = false;
        currentMatch.status = 'pending';
        currentMatch.winner_id = null;
        currentMatch.updated_at = new Date().toISOString();
        if (roundIdx < bracket.matches.length - 1) {
          const nextRoundIdx = roundIdx + 1;
          const nextMatchIdx = Math.floor(matchIdx / 2);
          const nextPlayerPos = matchIdx % 2 === 0 ? 0 : 1;
          bracket.matches[nextRoundIdx][nextMatchIdx].players[nextPlayerPos] = { id: null, name: 'TBD', score: 0, completed: false, created_at: new Date().toISOString(), updated_at: new Date().toISOString() };
        }
        try { await saveBrackets(bracket); nextTick(() => updateLines(bracketIdx)); } catch (e) { console.error('Error saving bracket after undoing match:', e); }
      }
    } else if (bracket.type === 'Double Elimination') {
      let currentMatch;
      switch (bracketType) {
        case 'winners': currentMatch = bracket.matches.winners[roundIdx][matchIdx]; break;
        case 'losers': currentMatch = bracket.matches.losers[roundIdx - bracket.matches.winners.length][matchIdx]; break;
        case 'grand_finals': currentMatch = bracket.matches.grand_finals[matchIdx]; break;
      }
      if (currentMatch && confirm('Are you sure you want to undo this match completion?')) {
        currentMatch.players[0].completed = false;
        currentMatch.players[1].completed = false;
        currentMatch.status = 'pending';
        currentMatch.winner_id = null;
        currentMatch.loser_id = null;
        currentMatch.updated_at = new Date().toISOString();
        try {
          const stack = bracket.id ? bracketActionLog.get(bracket.id) : null;
          if (stack && stack.length) {
            const performedActions = stack.pop();
            for (let i = performedActions.length - 1; i >= 0; i--) {
              const a = performedActions[i];
              if (a.type === 'slot') {
                const match = resolveMatch(bracket, a.target.part, a.target.roundIdx, a.target.matchIdx);
                if (match) match.players[a.target.playerPos] = deepClone(a.prevPlayer);
              }
            }
          } else if (bracketType === 'winners') {
            if (roundIdx < bracket.matches.winners.length - 1) {
              const nextRoundIdx = roundIdx + 1;
              const nextMatchIdx = Math.floor(matchIdx / 2);
              const nextPlayerPos = matchIdx % 2 === 0 ? 0 : 1;
              if (bracket.matches.winners[nextRoundIdx] && bracket.matches.winners[nextRoundIdx][nextMatchIdx]) {
                bracket.matches.winners[nextRoundIdx][nextMatchIdx].players[nextPlayerPos] = { id: null, name: 'TBD', score: 0, completed: false, created_at: new Date().toISOString(), updated_at: new Date().toISOString() };
              }
            }
            const losersRoundIdx = 0;
            const losersMatchIdx = Math.floor(matchIdx / 2);
            const losersPlayerPos = matchIdx % 2;
            if (bracket.matches.losers[losersRoundIdx] && bracket.matches.losers[losersRoundIdx][losersMatchIdx]) {
              bracket.matches.losers[losersRoundIdx][losersMatchIdx].players[losersPlayerPos] = { id: null, name: 'TBD', score: 0, completed: false, created_at: new Date().toISOString(), updated_at: new Date().toISOString() };
            }
          } else if (!stack && bracketType === 'losers') {
            if (roundIdx < bracket.matches.winners.length + bracket.matches.losers.length - 1) {
              const nextRoundIdx = roundIdx - bracket.matches.winners.length + 1;
              const nextMatchIdx = Math.floor(matchIdx / 2);
              const nextPlayerPos = matchIdx % 2 === 0 ? 0 : 1;
              if (bracket.matches.losers[nextRoundIdx] && bracket.matches.losers[nextRoundIdx][nextMatchIdx]) {
                bracket.matches.losers[nextRoundIdx][nextMatchIdx].players[nextPlayerPos] = { id: null, name: 'TBD', score: 0, completed: false, created_at: new Date().toISOString(), updated_at: new Date().toISOString() };
              }
            }
            if (roundIdx === bracket.matches.winners.length + bracket.matches.losers.length - 1) {
              const grandFinalsMatch = bracket.matches.grand_finals[0];
              if (grandFinalsMatch) {
                const winnersFinal = bracket.matches.winners[bracket.matches.winners.length - 1][0];
                if (winnersFinal.status === 'completed') {
                  grandFinalsMatch.players[1] = { id: null, name: 'TBD', score: 0, completed: false, created_at: new Date().toISOString(), updated_at: new Date().toISOString() };
                } else {
                  grandFinalsMatch.players[0] = { id: null, name: 'TBD', score: 0, completed: false, created_at: new Date().toISOString(), updated_at: new Date().toISOString() };
                }
              }
            }
          } else if (!stack && bracketType === 'grand_finals') {
            // Keep grand finals participants; only status/winner fields were reset above
          }
          await saveBrackets(bracket);
          nextTick(() => updateLines(bracketIdx));
        } catch (e) { console.error('Error saving bracket after undoing match:', e); }
      }
    }
  };

  const isCurrentMatch = (bracketIdx, roundIdx, matchIdx, bracketType = 'winners') => {
    const bracket = brackets.value[bracketIdx];
    if (bracket.type === 'Single Elimination') {
      const { roundIdx: currentRoundIdx, matchIdx: currentMatchIdx } = getRoundAndMatchIndices(bracketIdx, currentMatchIndex.value);
      return roundIdx === currentRoundIdx && matchIdx === currentMatchIdx;
    } else if (bracket.type === 'Double Elimination') {
      if (bracketType !== activeBracketSection.value) return false;
      switch (bracketType) {
        case 'winners': {
          let winnersAccumulated = 0;
          for (let i = 0; i < roundIdx; i++) winnersAccumulated += bracket.matches.winners[i].length;
          return winnersAccumulated + matchIdx === currentWinnersMatchIndex.value;
        }
        case 'losers': {
          let losersAccumulated = 0;
          const losersRoundIdx = roundIdx - bracket.matches.winners.length;
          for (let i = 0; i < losersRoundIdx; i++) losersAccumulated += bracket.matches.losers[i].length;
          return losersAccumulated + matchIdx === currentLosersMatchIndex.value;
        }
        case 'grand_finals':
          return matchIdx === currentGrandFinalsIndex.value;
      }
    }
    return false;
  };

  const navigateToMatch = (bracketIdx, roundIdx, matchIdx, bracketType = 'winners') => {
    activeBracketIdx.value = bracketIdx;
    const bracket = brackets.value[bracketIdx];
    if (bracket.type === 'Single Elimination') {
      let accumulatedMatches = 0;
      for (let i = 0; i < roundIdx; i++) accumulatedMatches += bracket.matches[i].length;
      currentMatchIndex.value = accumulatedMatches + matchIdx;
    } else if (bracket.type === 'Double Elimination') {
      activeBracketSection.value = bracketType;
      switch (bracketType) {
        case 'winners': {
          let winnersAccumulated = 0;
          for (let i = 0; i < roundIdx; i++) winnersAccumulated += bracket.matches.winners[i].length;
          currentWinnersMatchIndex.value = winnersAccumulated + matchIdx; break;
        }
        case 'losers': {
          let losersAccumulated = 0;
          const losersRoundIdx = roundIdx - bracket.matches.winners.length;
          for (let i = 0; i < losersRoundIdx; i++) losersAccumulated += bracket.matches.losers[i].length;
          currentLosersMatchIndex.value = losersAccumulated + matchIdx; break;
        }
        case 'grand_finals': currentGrandFinalsIndex.value = matchIdx; break;
      }
    }
  };

  const showNextMatch = (bracketIdx) => {
    activeBracketIdx.value = bracketIdx;
    const bracket = brackets.value[bracketIdx];
    if (bracket.type === 'Single Elimination') {
      const totalMatches = bracket.matches.flat().length;
      if (currentMatchIndex.value < totalMatches - 1) currentMatchIndex.value++;
    } else if (bracket.type === 'Double Elimination') {
      switch (activeBracketSection.value) {
        case 'winners': {
          const totalWinnersMatches = bracket.matches.winners.flat().length;
          if (currentWinnersMatchIndex.value < totalWinnersMatches - 1) currentWinnersMatchIndex.value++;
          break;
        }
        case 'losers': {
          const totalLosersMatches = bracket.matches.losers.flat().length;
          if (currentLosersMatchIndex.value < totalLosersMatches - 1) currentLosersMatchIndex.value++;
          break;
        }
        case 'grand_finals': {
          if (currentGrandFinalsIndex.value < bracket.matches.grand_finals.length - 1) currentGrandFinalsIndex.value++;
          break;
        }
      }
    }
  };

  const showPreviousMatch = (bracketIdx) => {
    activeBracketIdx.value = bracketIdx;
    const bracket = brackets.value[bracketIdx];
    if (bracket.type === 'Single Elimination') {
      if (currentMatchIndex.value > 0) currentMatchIndex.value--;
    } else if (bracket.type === 'Double Elimination') {
      switch (activeBracketSection.value) {
        case 'winners': if (currentWinnersMatchIndex.value > 0) currentWinnersMatchIndex.value--; break;
        case 'losers': if (currentLosersMatchIndex.value > 0) currentLosersMatchIndex.value--; break;
        case 'grand_finals': if (currentGrandFinalsIndex.value > 0) currentGrandFinalsIndex.value--; break;
      }
    }
  };

  const currentMatch = (bracketIdx) => {
    const bracket = brackets.value[bracketIdx];
    if (bracket.type === 'Single Elimination') {
      let accumulatedMatches = 0;
      for (let roundIdx = 0; roundIdx < bracket.matches.length; roundIdx++) {
        if (currentMatchIndex.value < accumulatedMatches + bracket.matches[roundIdx].length) {
          const matchIdx = currentMatchIndex.value - accumulatedMatches;
          return bracket.matches[roundIdx][matchIdx];
        }
        accumulatedMatches += bracket.matches[roundIdx].length;
      }
      return null;
    } else if (bracket.type === 'Double Elimination') {
      switch (activeBracketSection.value) {
        case 'winners': {
          let winnersAccumulated = 0;
          for (let roundIdx = 0; roundIdx < bracket.matches.winners.length; roundIdx++) {
            if (currentWinnersMatchIndex.value < winnersAccumulated + bracket.matches.winners[roundIdx].length) {
              const matchIdx = currentWinnersMatchIndex.value - winnersAccumulated;
              return bracket.matches.winners[roundIdx][matchIdx];
            }
            winnersAccumulated += bracket.matches.winners[roundIdx].length;
          }
          return null;
        }
        case 'losers': {
          let losersAccumulated = 0;
          for (let roundIdx = 0; roundIdx < bracket.matches.losers.length; roundIdx++) {
            if (currentLosersMatchIndex.value < losersAccumulated + bracket.matches.losers[roundIdx].length) {
              const matchIdx = currentLosersMatchIndex.value - losersAccumulated;
              return bracket.matches.losers[roundIdx][matchIdx];
            }
            losersAccumulated += bracket.matches.losers[roundIdx].length;
          }
          return null;
        }
        case 'grand_finals':
          return bracket.matches.grand_finals[currentGrandFinalsIndex.value];
      }
    }
    return null;
  };

  const updateLines = (bracketIdx) => {
    const bracket = brackets.value[bracketIdx];
    bracket.lines = bracket.type === 'Single Elimination' ? [] : { winners: [], losers: [], finals: [] };
    nextTick(() => {
      const container = document.querySelector('.bracket');
      if (!container) return;
      const containerRect = container.getBoundingClientRect();
      if (bracket.type === 'Single Elimination') {
        for (let round = 0; round < bracket.matches.length - 1; round++) {
          const current = bracket.matches[round];
          current.forEach((match, i) => {
            const fromEl = document.getElementById(`match-${round}-${i}`);
            const toEl = document.getElementById(`match-${round + 1}-${Math.floor(i / 2)}`);
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
        const winnersContainer = document.querySelector('.winners-lines');
        if (winnersContainer) {
          const winnersRect = winnersContainer.getBoundingClientRect();
          for (let round = 0; round < bracket.matches.winners.length - 1; round++) {
            const current = bracket.matches.winners[round];
            current.forEach((match, i) => {
              const fromEl = document.getElementById(`winners-match-${round}-${i}`);
              const toEl = document.getElementById(`winners-match-${round + 1}-${Math.floor(i / 2)}`);
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
        const losersContainer = document.querySelector('.losers-lines');
        if (losersContainer) {
          const losersRect = losersContainer.getBoundingClientRect();
          for (let round = 0; round < bracket.matches.losers.length - 1; round++) {
            const current = bracket.matches.losers[round];
            current.forEach((match, i) => {
              const fromEl = document.getElementById(`losers-match-${round}-${i}`);
              const toEl = document.getElementById(`losers-match-${round + 1}-${Math.floor(i / 2)}`);
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
  const isSemifinalRound = (bracketIdx, roundIdx) => roundIdx === brackets.value[bracketIdx].matches.length - 2;
  const isQuarterfinalRound = (bracketIdx, roundIdx) => roundIdx === brackets.value[bracketIdx].matches.length - 3;

  const confirmDeleteBracket = async () => {
    if (deleteBracketIdx.value !== null) {
      try {
        const bracket = brackets.value[deleteBracketIdx.value];
        if (bracket.id) {
          await axios.delete(`http://localhost:3000/brackets/${bracket.id}`);
        }
        brackets.value.splice(deleteBracketIdx.value, 1);
        expandedBrackets.value.splice(deleteBracketIdx.value, 1);
        if (activeBracketIdx.value === deleteBracketIdx.value) {
          currentMatchIndex.value = 0;
          activeBracketIdx.value = null;
        }
        deleteBracketIdx.value = null;
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
      if (bracketData.id) {
        await axios.put(`http://localhost:3000/brackets/${bracketData.id}`, bracketData);
      } else {
        const response = await axios.post('http://localhost:3000/brackets', bracketData);
        bracketData.id = response.data.id;
      }
    } catch (e) {
      console.error('Error saving bracket:', e);
    }
  };

  const concludeMatch = (bracketIdx) => {
    pendingBracketIdx.value = bracketIdx;
    showConfirmDialog.value = true;
  };

  const getCurrentRound = (bracketIdx) => {
    const bracket = brackets.value[bracketIdx];
    if (bracket.type === 'Single Elimination') {
      let totalMatches = 0;
      for (let roundIdx = 0; roundIdx < bracket.matches.length; roundIdx++) {
        totalMatches += bracket.matches[roundIdx].length;
        if (currentMatchIndex.value < totalMatches) return roundIdx + 1;
      }
      return 1;
    } else if (bracket.type === 'Double Elimination') {
      const { roundIdx } = getRoundAndMatchIndices(bracketIdx, currentMatchIndex.value);
      if (roundIdx < bracket.matches.winners.length) return `Winners Round ${roundIdx + 1}`;
      if (roundIdx < bracket.matches.winners.length + bracket.matches.losers.length) return `Losers Round ${roundIdx - bracket.matches.winners.length + 1}`;
      return 'Grand Finals';
    }
  };

  const getTotalMatches = (bracketIdx) => {
    const bracket = brackets.value[bracketIdx];
    if (bracket.type === 'Single Elimination') return bracket.matches.flat().length;
    if (bracket.type === 'Double Elimination') return bracket.matches.winners.reduce((s, r) => s + r.length, 0) + bracket.matches.losers.reduce((s, r) => s + r.length, 0) + bracket.matches.grand_finals.length;
    return 0;
  };

  const navigateToSection = (bracketIdx, section) => {
    activeBracketIdx.value = bracketIdx;
    activeBracketSection.value = section;
    switch (section) {
      case 'winners': currentWinnersMatchIndex.value = 0; break;
      case 'losers': currentLosersMatchIndex.value = 0; break;
      case 'grand_finals': currentGrandFinalsIndex.value = 0; break;
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
    selectedRoundRobinMatch.value = { bracketIdx, roundIdx, matchIdx, bracketType };
    selectedRoundRobinMatchData.value = {
      player1Name: match.players[0].name,
      player2Name: match.players[1].name,
      player1Score: match.players[0].score,
      player2Score: match.players[1].score,
      status: match.status
    };
    showRoundRobinMatchDialog.value = true;
  };

  const openRoundRobinMatchDialog = (bracketIdx, roundIdx, matchIdx, match) => {
    openMatchDialog(bracketIdx, roundIdx, matchIdx, match, 'round_robin');
  };

  const updateMatch = async () => {
    if (!selectedRoundRobinMatch.value) return;

    const { bracketIdx, roundIdx, matchIdx, bracketType } = selectedRoundRobinMatch.value;
    const bracket = brackets.value[bracketIdx];

    let match;
    if (bracket.type === 'Single Elimination') {
      match = bracket.matches[roundIdx][matchIdx];
    } else if (bracket.type === 'Double Elimination') {
      switch (bracketType) {
        case 'winners':
          match = bracket.matches.winners[roundIdx][matchIdx];
          break;
        case 'losers':
          match = bracket.matches.losers[roundIdx - bracket.matches.winners.length][matchIdx];
          break;
        case 'grand_finals':
          match = bracket.matches.grand_finals[matchIdx];
          break;
      }
    } else if (bracket.type === 'Round Robin') {
      match = bracket.matches[roundIdx][matchIdx];
    }

    // Update player names directly
    if (match.players[0].name !== selectedRoundRobinMatchData.value.player1Name) {
      const playerId = match.players[0].id;
      const newName = selectedRoundRobinMatchData.value.player1Name;

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

    if (match.players[1].name !== selectedRoundRobinMatchData.value.player2Name) {
      const playerId = match.players[1].id;
      const newName = selectedRoundRobinMatchData.value.player2Name;

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
    match.players[0].score = selectedRoundRobinMatchData.value.player1Score;
    match.players[1].score = selectedRoundRobinMatchData.value.player2Score;

    // Update match status
    if (selectedRoundRobinMatchData.value.status === 'completed' && match.status !== 'completed') {
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

      // Handle bracket progression for elimination brackets
      if (bracket.type === 'Single Elimination') {
        if (roundIdx < bracket.matches.length - 1) {
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
              updated_at: new Date().toISOString()
            };
          }
        } else {
          // Final match completed
          winnerMessage.value = `Winner: ${winner.name}`;
          showWinnerDialog.value = true;
        }
      } else if (bracket.type === 'Double Elimination') {
        const performedActions = [];
        if (bracketType === 'winners') {
          if (roundIdx < bracket.matches.winners.length - 1) {
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
                updated_at: new Date().toISOString()
              };
            }
          } else {
            // Winners bracket final - advance to grand finals
            const grandFinalsMatch = bracket.matches.grand_finals[0];
            if (grandFinalsMatch) {
              grandFinalsMatch.players[0] = {
                ...winner,
                score: 0,
                completed: false,
                created_at: new Date().toISOString(),
                updated_at: new Date().toISOString()
              };
            }

            // Convert TBD players to BYE in losers bracket round 1
            const firstLosersRound = bracket.matches.losers[0];
            firstLosersRound.forEach(match => {
              if (match.players[0].name === 'TBD') {
                match.players[0] = {
                  id: generateId(),
                  name: 'BYE',
                  score: 0,
                  completed: true,
                  created_at: new Date().toISOString(),
                  updated_at: new Date().toISOString()
                };
              }
              if (match.players[1].name === 'TBD') {
                match.players[1] = {
                  id: generateId(),
                  name: 'BYE',
                  score: 0,
                  completed: true,
                  created_at: new Date().toISOString(),
                  updated_at: new Date().toISOString()
                };
              }
            });

            // Auto-complete BYE matches in losers bracket
            for (let r = 0; r < bracket.matches.losers.length - 1; r++) {
              const currentRound = bracket.matches.losers[r];
              const nextRound = bracket.matches.losers[r + 1];
              currentRound.forEach((match, mIdx) => {
                if (match.players[0].name === 'BYE' || match.players[1].name === 'BYE') {
                  const winner2 = match.players[0].name === 'BYE' ? match.players[1] : match.players[0];
                  winner2.completed = true;
                  match.status = 'completed';
                  match.winner_id = winner2.id;

                  const nextMatchIdx2 = Math.floor(mIdx / 2);
                  const nextPlayerPos2 = mIdx % 2 === 0 ? 0 : 1;
                  if (nextRound && nextRound[nextMatchIdx2]) {
                    nextRound[nextMatchIdx2].players[nextPlayerPos2] = {
                      ...winner2,
                      score: 0,
                      completed: false,
                      created_at: new Date().toISOString(),
                      updated_at: new Date().toISOString()
                    };
                  }
                }
              });
            }
          }
          // Send loser to losers bracket
          if (loser.name !== 'BYE') {
            const losersRoundIdx = 0;
            let totalLosers = 0;
            for (let r = 0; r <= roundIdx; r++) {
              for (let m = 0; m < bracket.matches.winners[r].length; m++) {
                if (r === roundIdx && m === matchIdx) { break; }
                if (bracket.matches.winners[r][m].loser_id) { totalLosers++; }
              }
            }
            const losersMatchIdx = Math.floor(totalLosers / 2);
            const losersPlayerPos = totalLosers % 2;
            while (bracket.matches.losers[losersRoundIdx].length <= losersMatchIdx) {
              bracket.matches.losers[losersRoundIdx].push({
                id: generateId(),
                round: 1,
                match_number: bracket.matches.losers[losersRoundIdx].length + 1,
                bracket_type: 'losers',
                players: [
                  { id: null, name: 'TBD', score: 0, completed: false, created_at: new Date().toISOString(), updated_at: new Date().toISOString() },
                  { id: null, name: 'TBD', score: 0, completed: false, created_at: new Date().toISOString(), updated_at: new Date().toISOString() },
                ],
                winner_id: null,
                loser_id: null,
                status: 'pending',
                created_at: new Date().toISOString(),
                updated_at: new Date().toISOString(),
              });
            }
            bracket.matches.losers[losersRoundIdx][losersMatchIdx].players[losersPlayerPos] = {
              ...loser,
              score: 0,
              completed: false,
              created_at: new Date().toISOString(),
              updated_at: new Date().toISOString()
            };
          }
        } else if (bracketType === 'losers') {
          if (roundIdx < bracket.matches.winners.length + bracket.matches.losers.length - 1) {
            const nextRoundIdx = roundIdx - bracket.matches.winners.length + 1;
            const nextMatchIdx = Math.floor(matchIdx / 2);
            const nextPlayerPos = matchIdx % 2 === 0 ? 0 : 1;
            if (bracket.matches.losers[nextRoundIdx] && bracket.matches.losers[nextRoundIdx][nextMatchIdx]) {
              const nextMatch = bracket.matches.losers[nextRoundIdx][nextMatchIdx];
              nextMatch.players[nextPlayerPos] = {
                ...winner,
                score: 0,
                completed: false,
                created_at: new Date().toISOString(),
                updated_at: new Date().toISOString()
              };
            }
          }
          if (roundIdx === bracket.matches.winners.length + bracket.matches.losers.length - 1) {
            // Losers bracket final - advance to grand finals
            const grandFinalsMatch = bracket.matches.grand_finals[0];
            if (grandFinalsMatch) {
              const winnersFinal = bracket.matches.winners[bracket.matches.winners.length - 1][0];
              if (winnersFinal.status === 'completed') {
                grandFinalsMatch.players[1] = {
                  ...winner,
                  score: 0,
                  completed: false,
                  created_at: new Date().toISOString(),
                  updated_at: new Date().toISOString()
                };
              } else {
                grandFinalsMatch.players[0] = {
                  ...winner,
                  score: 0,
                  completed: false,
                  created_at: new Date().toISOString(),
                  updated_at: new Date().toISOString()
                };
              }
            }
          }
        } else if (bracketType === 'grand_finals') {
          // Grand finals completed
          winnerMessage.value = `Tournament Winner: ${winner.name}`;
          showWinnerDialog.value = true;
        }
      }
    } else if (selectedRoundRobinMatchData.value.status !== 'completed' && match.status === 'completed') {
      // Undo the match
      match.players[0].completed = false;
      match.players[1].completed = false;
      match.status = 'pending';
      match.winner_id = null;
      match.loser_id = null;
      // Note: Undoing bracket progression would be complex and might cause data inconsistencies
      // For now, we'll just undo the match status and let users manually fix if needed
    }

    try {
      await saveBrackets(bracket);
      // Update connection lines for elimination brackets
      if (bracket.type === 'Single Elimination' || bracket.type === 'Double Elimination') {
        nextTick(() => updateLines(bracketIdx));
      }
      showRoundRobinMatchDialog.value = false;
    } catch (error) {
      console.error('Error updating Round Robin match:', error);
    }
  };

  const closeRoundRobinMatchDialog = () => {
    showRoundRobinMatchDialog.value = false;
    selectedRoundRobinMatch.value = null;
    selectedRoundRobinMatchData.value = null;
  };

  const confirmMatchUpdate = () => {
    showMatchUpdateConfirmDialog.value = true;
  };

  const cancelMatchUpdate = () => {
    showMatchUpdateConfirmDialog.value = false;
  };

  const proceedWithMatchUpdate = async () => {
    showMatchUpdateConfirmDialog.value = false;
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
    editParticipant,
    cancelEndMatch,
    confirmEndMatch,
    undoConcludeMatch,
    getRoundAndMatchIndices,
    isCurrentMatch,
    updateLines,
    removeBracket,
    calculateByes,
    isFinalRound,
    isSemifinalRound,
    isQuarterfinalRound,
    confirmDeleteBracket,
    cancelDeleteBracket,
    saveBrackets,
    bracketTypeOptions,
    getRoundRobinStandings,
    isRoundRobinConcluded,
    openMatchDialog,
    openRoundRobinMatchDialog,
    updateMatch,
    closeRoundRobinMatchDialog,
    confirmMatchUpdate,
    cancelMatchUpdate,
    proceedWithMatchUpdate,
    openScoringConfigDialog,
    closeScoringConfigDialog,
    saveScoringConfig,
    handleByeRounds,
  };
}
