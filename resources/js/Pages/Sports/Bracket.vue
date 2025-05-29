<script setup>
import { ref, nextTick, watch, onMounted } from "vue";
import Dialog from "primevue/dialog";
import InputText from "primevue/inputtext";
import Select from "primevue/select";
import Button from "primevue/button";
import { computed } from "vue";
import axios from "axios";
import { Link } from '@inertiajs/vue3';

// Reactive State
const bracketName = ref("");
const numberOfPlayers = ref();
const matchType = ref("");
const selectedEvent = ref(null);
const events = ref([]);
const brackets = ref([]);
const showDialog = ref(false);
const currentMatchIndex = ref(0);
const lines = ref([]);
const expandedBrackets = ref([]); // Track expanded state of each bracket
const activeBracketIdx = ref(null);
const showWinnerDialog = ref(false);
const winnerMessage = ref("");
const showConfirmDialog = ref(false);
let pendingBracketIdx = null; // To store the bracket index for the pending confirmation
const showMissingFieldsDialog = ref(false);
const showDeleteConfirmDialog = ref(false);
const deleteBracketIdx = ref(null);

// Game Number Indicator
const currentGameNumber = computed(() => `Game ${currentMatchIndex.value + 1}`);

// Add new state variables at the top with other refs
const currentWinnersMatchIndex = ref(0);
const currentLosersMatchIndex = ref(0);
const currentGrandFinalsIndex = ref(0);
const activeBracketSection = ref('winners'); // 'winners', 'losers', or 'grand_finals'

// Options
const bracketTypeOptions = ["Single Elimination", "Double Elimination"];

// Open Dialog for Bracket Creation
const openDialog = async () => {
  bracketName.value = "";
  numberOfPlayers.value = null;
  matchType.value = "";
  selectedEvent.value = null;

  // Fetch sports events
  try {
    const response = await axios.get("http://localhost:3000/events");
    // Filter events where category_id is 3 (Sports) and not archived
    events.value = response.data.filter(event => {
      // Handle both string and number category_id
      const categoryId = parseInt(event.category_id);
      return categoryId === 3 && !event.archived;
    });
  } catch (error) {
    console.error("Error fetching events:", error);
  }

  showDialog.value = true;
};

// Add new function to handle BYE rounds
const handleByeRounds = (bracketIdx) => {
  const bracket = brackets.value[bracketIdx];

  if (bracket.type === 'Single Elimination') {
    // Existing single elimination BYE handling
    for (let roundIdx = 0; roundIdx < bracket.matches.length - 1; roundIdx++) {
      const currentRound = bracket.matches[roundIdx];

      currentRound.forEach((match, matchIdx) => {
        if (match.players[0].name === "BYE" || match.players[1].name === "BYE") {
          const winner = match.players[0].name === "BYE" ? match.players[1] : match.players[0];
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
              updated_at: new Date().toISOString()
            };
          }
        }
      });
    }
  } else if (bracket.type === 'Double Elimination') {
    // Handle BYEs in winners bracket
    for (let roundIdx = 0; roundIdx < bracket.matches.winners.length - 1; roundIdx++) {
      const currentRound = bracket.matches.winners[roundIdx];

      currentRound.forEach((match, matchIdx) => {
        if (match.players[0].name === "BYE" || match.players[1].name === "BYE") {
          const winner = match.players[0].name === "BYE" ? match.players[1] : match.players[0];
          winner.completed = true;
          match.status = 'completed';
          match.winner_id = winner.id;

          // Move winner to next winners bracket match
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
        }
      });
    }

    // Handle BYEs in losers bracket
    for (let roundIdx = 0; roundIdx < bracket.matches.losers.length - 1; roundIdx++) {
      const currentRound = bracket.matches.losers[roundIdx];

      currentRound.forEach((match, matchIdx) => {
        if (match.players[0].name === "BYE" || match.players[1].name === "BYE") {
          const winner = match.players[0].name === "BYE" ? match.players[1] : match.players[0];
          winner.completed = true;
          match.status = 'completed';
          match.winner_id = winner.id;

          // Move winner to next losers bracket match
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
              updated_at: new Date().toISOString()
            };
          }
        }
      });
    }
  }
};

// Modify toggleBracket to include BYE round handling
const toggleBracket = (bracketIdx) => {
  if (expandedBrackets.value[bracketIdx]) {
    expandedBrackets.value[bracketIdx] = false;
  } else {
    // Reset all expanded states
    expandedBrackets.value = expandedBrackets.value.map((expanded, idx) => idx === bracketIdx);

    // Handle BYE rounds when bracket is shown
    handleByeRounds(bracketIdx);

    // Update lines after the bracket is shown
    nextTick(() => {
      updateLines(bracketIdx);
    });

    // Check if a winner has been decided
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
      // Check grand finals for winner
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

// Add event details fetching
const fetchEventDetails = async (eventId) => {
  try {
    const response = await axios.get(`http://localhost:3000/events/${eventId}`);
    return response.data;
  } catch (error) {
    console.error('Error fetching event details:', error);
    return null;
  }
};

// Modify createBracket to handle BYE rounds immediately
const createBracket = async () => {
  if (!bracketName.value || !numberOfPlayers.value || !matchType.value || !selectedEvent.value) {
    showMissingFieldsDialog.value = true;
    return;
  }

  let newBracket;
  if (matchType.value === "Single Elimination") {
    newBracket = generateBracket();
  } else if (matchType.value === "Double Elimination") {
    newBracket = generateDoubleEliminationBracket();
  }

  const bracketData = {
    name: bracketName.value,
    type: matchType.value,
    event_id: selectedEvent.value.id,
    status: 'active',
    created_at: new Date().toISOString(),
    updated_at: new Date().toISOString(),
    matches: newBracket,
    lines: matchType.value === "Single Elimination" ? [] : { winners: [], losers: [], finals: [] }
  };

  try {
    await saveBrackets(bracketData);
    brackets.value.push(bracketData);
    expandedBrackets.value.push(false);
    showDialog.value = false;

    // Handle BYE rounds immediately after creation
    handleByeRounds(brackets.value.length - 1);

    // Call updateLines for the newly created bracket
    nextTick(() => {
      updateLines(brackets.value.length - 1);
    });
  } catch (error) {
    console.error('Error creating bracket:', error);
  }
};

// Modify fetchBrackets to include event details
const fetchBrackets = async () => {
  try {
    const response = await axios.get('http://localhost:3000/brackets');
    if (response.data) {
      // Fetch event details for each bracket
      const bracketsWithEvents = await Promise.all(
        response.data.map(async (bracket) => {
          try {
            const eventDetails = await fetchEventDetails(bracket.event_id);
            return {
              ...bracket,
              event: eventDetails || { title: 'Event not found' } // Provide fallback if event not found
            };
          } catch (error) {
            console.error(`Error fetching event details for bracket ${bracket.id}:`, error);
            return {
              ...bracket,
              event: { title: 'Event not found' }
            };
          }
        })
      );
      brackets.value = bracketsWithEvents;
      // Initialize expanded state for each bracket
      expandedBrackets.value = new Array(brackets.value.length).fill(false);
    }
  } catch (error) {
    console.error('Error fetching brackets:', error);
  }
};

const generateBracket = () => {
  const numPlayers = numberOfPlayers.value;
  const totalSlots = Math.pow(2, Math.ceil(Math.log2(numPlayers)));
  const totalByes = totalSlots - numPlayers;

  // Create arrays for players and BYEs with IDs
  const players = Array.from({ length: numPlayers }, (_, i) => ({
    id: null, // Will be set when saved to database
    name: `Player ${i + 1}`,
    score: 0,
    completed: false,
    created_at: new Date().toISOString(),
    updated_at: new Date().toISOString()
  }));
  const byes = Array.from({ length: totalByes }, () => ({
    id: null,
    name: "BYE",
    score: 0,
    completed: true,
    created_at: new Date().toISOString(),
    updated_at: new Date().toISOString()
  }));

  // Initialize slots array
  const slots = Array(totalSlots).fill(null);

  // 3. Alternate BYEs and Participants
  let byeIndex = 0;
  for (let i = totalSlots-2; i >=0; i-=2) {
    if (byeIndex < byes.length) {
      slots[i + 1] = byes[byeIndex++];
    }
  }

  // 4. Fill remaining slots with players
  let playerIndex = 0;
  for (let i = 0; i < totalSlots; i++) {
    if (!slots[i] && playerIndex < players.length) {
      slots[i] = players[playerIndex++];
    }
  }

  // 5. Pair into matches with IDs
  const firstRound = [];
  for (let i = 0; i < totalSlots; i += 2) {
    const match = {
      id: null, // Will be set when saved to database
      round: 1,
      match_number: i/2 + 1,
      players: [slots[i], slots[i + 1]],
      winner_id: null,
      status: 'pending', // pending, completed, cancelled
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString()
    };
    firstRound.push(match);

    // Automatically conclude matches with a BYE
    if (match.players[0].name === "BYE" || match.players[1].name === "BYE") {
      const winner = match.players[0].name === "BYE" ? match.players[1] : match.players[0];
      winner.completed = true;
      match.status = 'completed';
      match.winner_id = winner.id;
    }
  }

  // 6. Build empty placeholders for later rounds
  const rounds = [firstRound];
  let prevMatches = firstRound;
  let roundNumber = 2;

  while (prevMatches.length > 1) {
    const nextMatches = Array.from(
      { length: Math.ceil(prevMatches.length / 2) },
      (_, i) => ({
        id: null,
        round: roundNumber,
        match_number: i + 1,
        players: [
          { id: null, name: "TBD", score: 0, completed: false, created_at: new Date().toISOString(), updated_at: new Date().toISOString() },
          { id: null, name: "TBD", score: 0, completed: false, created_at: new Date().toISOString(), updated_at: new Date().toISOString() }
        ],
        winner_id: null,
        status: 'pending',
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString()
      })
    );
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

  // Create arrays for players and BYEs with IDs
  const players = Array.from({ length: numPlayers }, (_, i) => ({
    id: null,
    name: `Player ${i + 1}`,
    score: 0,
    completed: false,
    created_at: new Date().toISOString(),
    updated_at: new Date().toISOString()
  }));
  const byes = Array.from({ length: totalByes }, () => ({
    id: null,
    name: "BYE",
    score: 0,
    completed: true,
    created_at: new Date().toISOString(),
    updated_at: new Date().toISOString()
  }));

  // Initialize slots array for winners bracket
  const winnersSlots = Array(totalSlots).fill(null);
  const losersSlots = Array(totalSlots - 1).fill(null);

  // Alternate BYEs and Participants in winners bracket
  let byeIndex = 0;
  for (let i = totalSlots-2; i >= 0; i -= 2) {
    if (byeIndex < byes.length) {
      winnersSlots[i + 1] = byes[byeIndex++];
    }
  }

  // Fill remaining slots with players in winners bracket
  let playerIndex = 0;
  for (let i = 0; i < totalSlots; i++) {
    if (!winnersSlots[i] && playerIndex < players.length) {
      winnersSlots[i] = players[playerIndex++];
    }
  }

  // Generate winners bracket first round
  const winnersFirstRound = [];
  for (let i = 0; i < totalSlots; i += 2) {
    const match = {
      id: null,
      round: 1,
      match_number: i/2 + 1,
      bracket_type: 'winners',
      players: [winnersSlots[i], winnersSlots[i + 1]],
      winner_id: null,
      loser_id: null,
      status: 'pending',
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString()
    };
    winnersFirstRound.push(match);

    // Handle BYE matches
    if (match.players[0].name === "BYE" || match.players[1].name === "BYE") {
      const winner = match.players[0].name === "BYE" ? match.players[1] : match.players[0];
      winner.completed = true;
      match.status = 'completed';
      match.winner_id = winner.id;
    }
  }

  // Calculate number of rounds in winners bracket
  const winnersRounds = Math.ceil(Math.log2(totalSlots));

  // Generate losers bracket rounds
  const losersRounds = [];

  // First round of losers bracket (matches losers from winners bracket round 1)
  const firstLosersRound = [];
  for (let i = 0; i < totalSlots/2; i++) {
    const match = {
      id: null,
      round: 1,
      match_number: i + 1,
      bracket_type: 'losers',
      players: [
        { id: null, name: "TBD", score: 0, completed: false, created_at: new Date().toISOString(), updated_at: new Date().toISOString() },
        { id: null, name: "TBD", score: 0, completed: false, created_at: new Date().toISOString(), updated_at: new Date().toISOString() }
      ],
      winner_id: null,
      loser_id: null,
      status: 'pending',
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString()
    };
    firstLosersRound.push(match);
  }
  losersRounds.push(firstLosersRound);

  // Generate subsequent losers bracket rounds
  let currentRoundMatches = totalSlots/2;
  let losersRoundNumber = 2;

  while (currentRoundMatches > 1) {
    const nextRoundMatches = Math.ceil(currentRoundMatches / 2);
    const round = [];

    for (let i = 0; i < nextRoundMatches; i++) {
      const match = {
        id: null,
        round: losersRoundNumber,
        match_number: i + 1,
        bracket_type: 'losers',
        players: [
          { id: null, name: "TBD", score: 0, completed: false, created_at: new Date().toISOString(), updated_at: new Date().toISOString() },
          { id: null, name: "TBD", score: 0, completed: false, created_at: new Date().toISOString(), updated_at: new Date().toISOString() }
        ],
        winner_id: null,
        loser_id: null,
        status: 'pending',
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString()
      };
      round.push(match);
    }

    losersRounds.push(round);
    currentRoundMatches = nextRoundMatches;
    losersRoundNumber++;
  }

  // Build empty placeholders for later winners bracket rounds
  const winnersRoundsArray = [winnersFirstRound];
  let prevWinnersMatches = winnersFirstRound;
  let winnersRoundNumber = 2;

  // Generate winners bracket rounds
  while (prevWinnersMatches.length > 1) {
    const nextMatches = Array.from(
      { length: Math.ceil(prevWinnersMatches.length / 2) },
      (_, i) => ({
        id: null,
        round: winnersRoundNumber,
        match_number: i + 1,
        bracket_type: 'winners',
        players: [
          { id: null, name: "TBD", score: 0, completed: false, created_at: new Date().toISOString(), updated_at: new Date().toISOString() },
          { id: null, name: "TBD", score: 0, completed: false, created_at: new Date().toISOString(), updated_at: new Date().toISOString() }
        ],
        winner_id: null,
        loser_id: null,
        status: 'pending',
        created_at: new Date().toISOString(),
        updated_at: new Date().toISOString()
      })
    );
    winnersRoundsArray.push(nextMatches);
    prevWinnersMatches = nextMatches;
    winnersRoundNumber++;
  }

  // Add grand finals match
  const grandFinals = [{
    id: null,
    round: 1,
    match_number: 1,
    bracket_type: 'grand_finals',
    players: [
      { id: null, name: "TBD", score: 0, completed: false, created_at: new Date().toISOString(), updated_at: new Date().toISOString() },
      { id: null, name: "TBD", score: 0, completed: false, created_at: new Date().toISOString(), updated_at: new Date().toISOString() }
    ],
    winner_id: null,
    loser_id: null,
    status: 'pending',
    created_at: new Date().toISOString(),
    updated_at: new Date().toISOString()
  }];

  return {
    winners: winnersRoundsArray,
    losers: losersRounds,
    grand_finals: grandFinals
  };
};

// Update increaseScore function
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

    try {
      await saveBrackets(bracket);
    } catch (error) {
      console.error('Error updating score:', error);
    }
  }
};

// Update decreaseScore function
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

    try {
      await saveBrackets(bracket);
    } catch (error) {
      console.error('Error updating score:', error);
    }
  }
};

// Update editParticipant function
const editParticipant = async (bracketIdx, roundIdx, matchIdx, teamIdx, bracketType = 'winners') => {
  const newName = prompt("Enter new participant name:");
  if (newName) {
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
    }

    if (match) {
      match.players[teamIdx].name = newName;
      match.players[teamIdx].updated_at = new Date().toISOString();

      try {
        await saveBrackets(bracket);
      } catch (error) {
        console.error('Error updating participant:', error);
      }
    }
  }
};

// Update handleDoubleEliminationByes function to handle all matches
const handleDoubleEliminationByes = (bracket) => {
  // Check if winners bracket final is completed
  const winnersFinal = bracket.matches.winners[bracket.matches.winners.length - 1][0];
  if (winnersFinal.status === 'completed') {
    // Convert remaining TBDs in losers bracket round 1 to BYEs
    const firstLosersRound = bracket.matches.losers[0];
    firstLosersRound.forEach(match => {
      if (match.players[0].name === 'TBD') {
        match.players[0] = {
          id: null,
          name: 'BYE',
          score: 0,
          completed: true,
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString()
        };
      }
      if (match.players[1].name === 'TBD') {
        match.players[1] = {
          id: null,
          name: 'BYE',
          score: 0,
          completed: true,
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString()
        };
      }
    });

    // Handle all matches in losers bracket
    for (let roundIdx = 0; roundIdx < bracket.matches.losers.length - 1; roundIdx++) {
      const currentRound = bracket.matches.losers[roundIdx];
      const nextRound = bracket.matches.losers[roundIdx + 1];

      currentRound.forEach((match, matchIdx) => {
        // Handle BYE matches
        if (match.players[0].name === 'BYE' || match.players[1].name === 'BYE') {
          const winner = match.players[0].name === 'BYE' ? match.players[1] : match.players[0];
          winner.completed = true;
          match.status = 'completed';
          match.winner_id = winner.id;

          // Move winner to next round
          const nextMatchIdx = Math.floor(matchIdx / 2);
          const nextPlayerPos = matchIdx % 2 === 0 ? 0 : 1;

          if (nextRound[nextMatchIdx]) {
            nextRound[nextMatchIdx].players[nextPlayerPos] = {
              ...winner,
              score: 0,
              completed: false,
              created_at: new Date().toISOString(),
              updated_at: new Date().toISOString()
            };
          }
        }
        // Handle completed PvP matches
        else if (match.status === 'completed' && match.winner_id) {
          const winner = match.players[0].id === match.winner_id ? match.players[0] : match.players[1];

          // Move winner to next round
          const nextMatchIdx = Math.floor(matchIdx / 2);
          const nextPlayerPos = matchIdx % 2 === 0 ? 0 : 1;

          if (nextRound[nextMatchIdx]) {
            nextRound[nextMatchIdx].players[nextPlayerPos] = {
              ...winner,
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
};

// Add back the cancelEndMatch function
const cancelEndMatch = () => {
  showConfirmDialog.value = false;
  pendingBracketIdx = null;
};

// Update confirmEndMatch function to handle losers bracket progression
const confirmEndMatch = async () => {
  if (pendingBracketIdx !== null) {
    const bracket = brackets.value[pendingBracketIdx];
    const { roundIdx, matchIdx, bracketType } = getRoundAndMatchIndices(pendingBracketIdx, currentMatchIndex.value);

    if (bracket.type === 'Single Elimination') {
      // ... existing single elimination logic ...
    } else if (bracket.type === 'Double Elimination') {
      // Get the current match based on bracket type
      let currentMatch;
      if (bracketType === 'winners') {
        currentMatch = bracket.matches.winners[roundIdx][matchIdx];
      } else if (bracketType === 'losers') {
        currentMatch = bracket.matches.losers[roundIdx - bracket.matches.winners.length][matchIdx];
      } else {
        currentMatch = bracket.matches.grand_finals[matchIdx];
      }

      const winner = currentMatch.players[0].score >= currentMatch.players[1].score ? currentMatch.players[0] : currentMatch.players[1];
      const loser = currentMatch.players[0].score >= currentMatch.players[1].score ? currentMatch.players[1] : currentMatch.players[0];

      currentMatch.players[0].completed = true;
      currentMatch.players[1].completed = true;
      currentMatch.status = 'completed';
      currentMatch.winner_id = winner.id;
      currentMatch.loser_id = loser.id;
      currentMatch.updated_at = new Date().toISOString();

      try {
        if (bracketType === 'winners') {
          // Move winner to next winners bracket match or grand finals
          if (roundIdx < bracket.matches.winners.length - 1) {
            const nextRoundIdx = roundIdx + 1;
            const nextMatchIdx = Math.floor(matchIdx / 2);
            const nextMatchPos = matchIdx % 2 === 0 ? 0 : 1;

            const nextMatch = bracket.matches.winners[nextRoundIdx][nextMatchIdx];
            nextMatch.players[nextMatchPos] = {
              ...winner,
              score: 0,
              completed: false,
              created_at: new Date().toISOString(),
              updated_at: new Date().toISOString()
            };
          } else {
            // This is the winners bracket final - winner goes to grand finals
            const grandFinalsMatch = bracket.matches.grand_finals[0];
            grandFinalsMatch.players[0] = {
              ...winner,
              score: 0,
              completed: false,
              created_at: new Date().toISOString(),
              updated_at: new Date().toISOString()
            };
          }

          // Move loser to first round of losers bracket
          const firstLosersRound = bracket.matches.losers[0];
          for (let i = 0; i < firstLosersRound.length; i++) {
            const losersMatch = firstLosersRound[i];
            if (losersMatch.players[0].name === 'TBD') {
              losersMatch.players[0] = {
                ...loser,
                score: 0,
                completed: false,
                created_at: new Date().toISOString(),
                updated_at: new Date().toISOString()
              };
              break;
            } else if (losersMatch.players[1].name === 'TBD') {
              losersMatch.players[1] = {
                ...loser,
                score: 0,
                completed: false,
                created_at: new Date().toISOString(),
                updated_at: new Date().toISOString()
              };
              break;
            }
          }

          // Handle BYEs after winners bracket final is completed
          handleDoubleEliminationByes(bracket);
        } else if (bracketType === 'losers') {
          // Move winner to next losers bracket match or grand finals
          const losersRoundIdx = roundIdx - bracket.matches.winners.length;
          if (losersRoundIdx < bracket.matches.losers.length - 1) {
            const nextRoundIdx = losersRoundIdx + 1;
            const nextMatchIdx = Math.floor(matchIdx / 2);
            const nextMatchPos = matchIdx % 2 === 0 ? 0 : 1;

            const nextMatch = bracket.matches.losers[nextRoundIdx][nextMatchIdx];
            nextMatch.players[nextMatchPos] = {
              ...winner,
              score: 0,
              completed: false,
              created_at: new Date().toISOString(),
              updated_at: new Date().toISOString()
            };
          } else {
            // This is the losers bracket final - winner goes to grand finals
            const grandFinalsMatch = bracket.matches.grand_finals[0];
            grandFinalsMatch.players[1] = {
              ...winner,
              score: 0,
              completed: false,
              created_at: new Date().toISOString(),
              updated_at: new Date().toISOString()
            };
          }
        } else if (bracketType === 'grand_finals') {
          // Check if we need a second grand finals match
          const winnersBracketWinner = bracket.matches.winners[bracket.matches.winners.length - 1][0].winner_id;

          if (winner.id === winnersBracketWinner) {
            // Winners bracket winner won the grand finals
            winnerMessage.value = `Tournament Winner: ${winner.name}`;
            showWinnerDialog.value = true;
          } else {
            // Losers bracket winner won, need a second grand finals match
            const secondGrandFinals = {
              id: null,
              round: 2,
              match_number: 1,
              bracket_type: 'grand_finals',
              players: [
                {
                  ...winner,
                  score: 0,
                  completed: false,
                  created_at: new Date().toISOString(),
                  updated_at: new Date().toISOString()
                },
                {
                  ...loser,
                  score: 0,
                  completed: false,
                  created_at: new Date().toISOString(),
                  updated_at: new Date().toISOString()
                }
              ],
              winner_id: null,
              loser_id: null,
              status: 'pending',
              created_at: new Date().toISOString(),
              updated_at: new Date().toISOString()
            };
            bracket.matches.grand_finals.push(secondGrandFinals);
          }
        }

        await saveBrackets(bracket);
      } catch (error) {
        console.error('Error concluding match:', error);
      }
    }

    showConfirmDialog.value = false;
    pendingBracketIdx = null;
  }
};

// Update undoConcludeMatch function
const undoConcludeMatch = (bracketIdx) => {
  const { roundIdx, matchIdx, bracketType } = getRoundAndMatchIndices(bracketIdx, currentMatchIndex.value);
  const bracket = brackets.value[bracketIdx];

  if (bracket.type === 'Single Elimination') {
    // ... existing single elimination logic ...
  } else if (bracket.type === 'Double Elimination') {
    let currentMatch;
    if (bracketType === 'winners') {
      currentMatch = bracket.matches.winners[roundIdx][matchIdx];
    } else if (bracketType === 'losers') {
      currentMatch = bracket.matches.losers[roundIdx - bracket.matches.winners.length][matchIdx];
    } else {
      currentMatch = bracket.matches.grand_finals[matchIdx];
    }

    if (confirm('Are you sure you want to undo this match completion?')) {
      currentMatch.players[0].completed = false;
      currentMatch.players[1].completed = false;
      currentMatch.status = 'pending';
      currentMatch.winner_id = null;
      currentMatch.loser_id = null;

      if (bracketType === 'winners') {
        // Clear next winners bracket match
        if (roundIdx < bracket.matches.winners.length - 1) {
          const nextRoundIdx = roundIdx + 1;
          const nextMatchIdx = Math.floor(matchIdx / 2);
          const nextMatchPos = matchIdx % 2 === 0 ? 0 : 1;

          bracket.matches.winners[nextRoundIdx][nextMatchIdx].players[nextMatchPos] = {
            id: null,
            name: 'TBD',
            score: 0,
            completed: false,
            created_at: new Date().toISOString(),
            updated_at: new Date().toISOString()
          };
        } else {
          // Clear grand finals if this was the winners bracket final
          const grandFinalsMatch = bracket.matches.grand_finals[0];
          grandFinalsMatch.players[0] = {
            id: null,
            name: 'TBD',
            score: 0,
            completed: false,
            created_at: new Date().toISOString(),
            updated_at: new Date().toISOString()
          };
        }

        // Clear corresponding losers bracket match
        const firstLosersRound = bracket.matches.losers[0];
        for (let i = 0; i < firstLosersRound.length; i++) {
          const losersMatch = firstLosersRound[i];
          if (losersMatch.players[0].id === currentMatch.winner_id || losersMatch.players[0].id === currentMatch.loser_id) {
            losersMatch.players[0] = {
              id: null,
              name: 'TBD',
              score: 0,
              completed: false,
              created_at: new Date().toISOString(),
              updated_at: new Date().toISOString()
            };
          }
          if (losersMatch.players[1].id === currentMatch.winner_id || losersMatch.players[1].id === currentMatch.loser_id) {
            losersMatch.players[1] = {
              id: null,
              name: 'TBD',
              score: 0,
              completed: false,
              created_at: new Date().toISOString(),
              updated_at: new Date().toISOString()
            };
          }
        }
      } else if (bracketType === 'losers') {
        // Clear next losers bracket match
        const losersRoundIdx = roundIdx - bracket.matches.winners.length;
        if (losersRoundIdx < bracket.matches.losers.length - 1) {
          const nextRoundIdx = losersRoundIdx + 1;
          const nextMatchIdx = Math.floor(matchIdx / 2);
          const nextMatchPos = matchIdx % 2 === 0 ? 0 : 1;

          bracket.matches.losers[nextRoundIdx][nextMatchIdx].players[nextMatchPos] = {
            id: null,
            name: 'TBD',
            score: 0,
            completed: false,
            created_at: new Date().toISOString(),
            updated_at: new Date().toISOString()
          };
        } else {
          // Clear grand finals if this was the losers bracket final
          const grandFinalsMatch = bracket.matches.grand_finals[0];
          grandFinalsMatch.players[1] = {
            id: null,
            name: 'TBD',
            score: 0,
            completed: false,
            created_at: new Date().toISOString(),
            updated_at: new Date().toISOString()
          };
        }
      }
    }
  }
};

// Add getRoundAndMatchIndices function
const getRoundAndMatchIndices = (bracketIdx, currentIndex) => {
  const bracket = brackets.value[bracketIdx];

  if (bracket.type === 'Single Elimination') {
    let accumulatedMatches = 0;
    for (let roundIdx = 0; roundIdx < bracket.matches.length; roundIdx++) {
      if (currentIndex < accumulatedMatches + bracket.matches[roundIdx].length) {
        return {
          roundIdx,
          matchIdx: currentIndex - accumulatedMatches,
          bracketType: 'winners'
        };
      }
      accumulatedMatches += bracket.matches[roundIdx].length;
    }
    return { roundIdx: 0, matchIdx: 0, bracketType: 'winners' };
  } else if (bracket.type === 'Double Elimination') {
    switch (activeBracketSection.value) {
      case 'winners':
        let winnersAccumulated = 0;
        for (let roundIdx = 0; roundIdx < bracket.matches.winners.length; roundIdx++) {
          if (currentWinnersMatchIndex.value < winnersAccumulated + bracket.matches.winners[roundIdx].length) {
            return {
              roundIdx,
              matchIdx: currentWinnersMatchIndex.value - winnersAccumulated,
              bracketType: 'winners'
            };
          }
          winnersAccumulated += bracket.matches.winners[roundIdx].length;
        }
        break;

      case 'losers':
        let losersAccumulated = 0;
        for (let roundIdx = 0; roundIdx < bracket.matches.losers.length; roundIdx++) {
          if (currentLosersMatchIndex.value < losersAccumulated + bracket.matches.losers[roundIdx].length) {
            return {
              roundIdx: roundIdx + bracket.matches.winners.length,
              matchIdx: currentLosersMatchIndex.value - losersAccumulated,
              bracketType: 'losers'
            };
          }
          losersAccumulated += bracket.matches.losers[roundIdx].length;
        }
        break;

      case 'grand_finals':
        return {
          roundIdx: bracket.matches.winners.length + bracket.matches.losers.length,
          matchIdx: currentGrandFinalsIndex.value,
          bracketType: 'grand_finals'
        };
    }
  }
  return { roundIdx: 0, matchIdx: 0, bracketType: 'winners' };
};

// Update isCurrentMatch function
const isCurrentMatch = (bracketIdx, roundIdx, matchIdx, bracketType = 'winners') => {
  const bracket = brackets.value[bracketIdx];

  if (bracket.type === 'Single Elimination') {
    const { roundIdx: currentRoundIdx, matchIdx: currentMatchIdx } = getRoundAndMatchIndices(bracketIdx, currentMatchIndex.value);
    return roundIdx === currentRoundIdx && matchIdx === currentMatchIdx;
  } else if (bracket.type === 'Double Elimination') {
    // First check if we're in the correct bracket section
    if (bracketType !== activeBracketSection.value) {
      return false;
    }

    switch (bracketType) {
      case 'winners':
        let winnersAccumulated = 0;
        for (let i = 0; i < roundIdx; i++) {
          winnersAccumulated += bracket.matches.winners[i].length;
        }
        return winnersAccumulated + matchIdx === currentWinnersMatchIndex.value;

      case 'losers':
        let losersAccumulated = 0;
        const losersRoundIdx = roundIdx - bracket.matches.winners.length;
        for (let i = 0; i < losersRoundIdx; i++) {
          losersAccumulated += bracket.matches.losers[i].length;
        }
        return losersAccumulated + matchIdx === currentLosersMatchIndex.value;

      case 'grand_finals':
        return matchIdx === currentGrandFinalsIndex.value;
    }
  }
  return false;
};

// Update the navigation functions
const navigateToMatch = (bracketIdx, roundIdx, matchIdx, bracketType = 'winners') => {
  activeBracketIdx.value = bracketIdx;
  const bracket = brackets.value[bracketIdx];

  if (bracket.type === 'Single Elimination') {
    let accumulatedMatches = 0;
    for (let i = 0; i < roundIdx; i++) {
      accumulatedMatches += bracket.matches[i].length;
    }
    currentMatchIndex.value = accumulatedMatches + matchIdx;
  } else if (bracket.type === 'Double Elimination') {
    // Set the active bracket section first
    activeBracketSection.value = bracketType;

    switch (bracketType) {
      case 'winners':
        let winnersAccumulated = 0;
        for (let i = 0; i < roundIdx; i++) {
          winnersAccumulated += bracket.matches.winners[i].length;
        }
        currentWinnersMatchIndex.value = winnersAccumulated + matchIdx;
        break;

      case 'losers':
        let losersAccumulated = 0;
        const losersRoundIdx = roundIdx - bracket.matches.winners.length;
        for (let i = 0; i < losersRoundIdx; i++) {
          losersAccumulated += bracket.matches.losers[i].length;
        }
        currentLosersMatchIndex.value = losersAccumulated + matchIdx;
        break;

      case 'grand_finals':
        currentGrandFinalsIndex.value = matchIdx;
        break;
    }
  }
};

// Update showNextMatch function
const showNextMatch = (bracketIdx) => {
  activeBracketIdx.value = bracketIdx;
  const bracket = brackets.value[bracketIdx];

  if (bracket.type === 'Single Elimination') {
    const totalMatches = bracket.matches.flat().length;
    if (currentMatchIndex.value < totalMatches - 1) {
      currentMatchIndex.value++;
    }
  } else if (bracket.type === 'Double Elimination') {
    switch (activeBracketSection.value) {
      case 'winners':
        const totalWinnersMatches = bracket.matches.winners.reduce((sum, round) => sum + round.length, 0);
        if (currentWinnersMatchIndex.value < totalWinnersMatches - 1) {
          currentWinnersMatchIndex.value++;
        }
        break;

      case 'losers':
        const totalLosersMatches = bracket.matches.losers.reduce((sum, round) => sum + round.length, 0);
        if (currentLosersMatchIndex.value < totalLosersMatches - 1) {
          currentLosersMatchIndex.value++;
        }
        break;

      case 'grand_finals':
        if (currentGrandFinalsIndex.value < bracket.matches.grand_finals.length - 1) {
          currentGrandFinalsIndex.value++;
        }
        break;
    }
  }
};

// Update showPreviousMatch function
const showPreviousMatch = (bracketIdx) => {
  activeBracketIdx.value = bracketIdx;
  const bracket = brackets.value[bracketIdx];

  if (bracket.type === 'Single Elimination') {
    if (currentMatchIndex.value > 0) {
      currentMatchIndex.value--;
    }
  } else if (bracket.type === 'Double Elimination') {
    switch (activeBracketSection.value) {
      case 'winners':
        if (currentWinnersMatchIndex.value > 0) {
          currentWinnersMatchIndex.value--;
        }
        break;

      case 'losers':
        if (currentLosersMatchIndex.value > 0) {
          currentLosersMatchIndex.value--;
        }
        break;

      case 'grand_finals':
        if (currentGrandFinalsIndex.value > 0) {
          currentGrandFinalsIndex.value--;
        }
        break;
    }
  }
};

// Update currentMatch function
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
      case 'winners':
        let winnersAccumulated = 0;
        for (let roundIdx = 0; roundIdx < bracket.matches.winners.length; roundIdx++) {
          if (currentWinnersMatchIndex.value < winnersAccumulated + bracket.matches.winners[roundIdx].length) {
            const matchIdx = currentWinnersMatchIndex.value - winnersAccumulated;
            return bracket.matches.winners[roundIdx][matchIdx];
          }
          winnersAccumulated += bracket.matches.winners[roundIdx].length;
        }
        return null;

      case 'losers':
        let losersAccumulated = 0;
        for (let roundIdx = 0; roundIdx < bracket.matches.losers.length; roundIdx++) {
          if (currentLosersMatchIndex.value < losersAccumulated + bracket.matches.losers[roundIdx].length) {
            const matchIdx = currentLosersMatchIndex.value - losersAccumulated;
            return bracket.matches.losers[roundIdx][matchIdx];
          }
          losersAccumulated += bracket.matches.losers[roundIdx].length;
        }
        return null;

      case 'grand_finals':
        return bracket.matches.grand_finals[currentGrandFinalsIndex.value];
    }
  }
  return null;
};

// Update lines dynamically using SVG
const updateLines = (bracketIdx) => {
  const bracket = brackets.value[bracketIdx];
  bracket.lines = bracket.type === 'Single Elimination' ? [] : { winners: [], losers: [], finals: [] };

  nextTick(() => {
    const container = document.querySelector('.bracket');
    if (!container) return;

    const containerRect = container.getBoundingClientRect();

    if (bracket.type === 'Single Elimination') {
      // Single elimination line generation
      for (let round = 0; round < bracket.matches.length - 1; round++) {
        const current = bracket.matches[round];
        const next = bracket.matches[round + 1];

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
            { x1: midX, y1: toCenterY, x2: toLeftX, y2: toCenterY }
          );
        });
      }
    } else if (bracket.type === 'Double Elimination') {
      // Winners bracket lines
      const winnersContainer = document.querySelector('.winners-lines');
      if (winnersContainer) {
        const winnersRect = winnersContainer.getBoundingClientRect();

        for (let round = 0; round < bracket.matches.winners.length - 1; round++) {
          const current = bracket.matches.winners[round];
          const next = bracket.matches.winners[round + 1];

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
              { x1: midX, y1: toCenterY, x2: toLeftX, y2: toCenterY }
            );
          });
        }
      }

      // Losers bracket lines
      const losersContainer = document.querySelector('.losers-lines');
      if (losersContainer) {
        const losersRect = losersContainer.getBoundingClientRect();

        for (let round = 0; round < bracket.matches.losers.length - 1; round++) {
          const current = bracket.matches.losers[round];
          const next = bracket.matches.losers[round + 1];

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
              { x1: midX, y1: toCenterY, x2: toLeftX, y2: toCenterY }
            );
          });
        }
      }

      // Connect winners and losers brackets to grand finals
      const finalsContainer = document.querySelector('.finals-lines');
      if (finalsContainer) {
        const finalsRect = finalsContainer.getBoundingClientRect();
        const lastWinnersMatch = document.getElementById(`winners-match-${bracket.matches.winners.length - 1}-0`);
        const lastLosersMatch = document.getElementById(`losers-match-${bracket.matches.losers.length - 1}-0`);
        const grandFinalsMatch = document.getElementById('grand-finals-match-0');

        if (lastWinnersMatch && lastLosersMatch && grandFinalsMatch) {
          const winnersRect = lastWinnersMatch.getBoundingClientRect();
          const losersRect = lastLosersMatch.getBoundingClientRect();
          const finalsRect = grandFinalsMatch.getBoundingClientRect();

          // Only draw lines if there are actual players in the grand finals
          const grandFinalsPlayers = bracket.matches.grand_finals[0].players;
          if (grandFinalsPlayers[0].name !== 'TBD' || grandFinalsPlayers[1].name !== 'TBD') {
            // Connect winners bracket to finals
            const winnersCenterY = winnersRect.top - finalsRect.top + winnersRect.height / 2;
            const winnersRightX = winnersRect.right - finalsRect.left;
            const finalsLeftX = finalsRect.left - finalsRect.left;
            const finalsCenterY = finalsRect.top - finalsRect.top + finalsRect.height / 2;

            bracket.lines.finals.push(
              { x1: winnersRightX, y1: winnersCenterY, x2: finalsLeftX, y2: finalsCenterY }
            );

            // Connect losers bracket to finals
            const losersCenterY = losersRect.top - finalsRect.top + losersRect.height / 2;
            const losersRightX = losersRect.right - finalsRect.left;

            bracket.lines.finals.push(
              { x1: losersRightX, y1: losersCenterY, x2: finalsLeftX, y2: finalsCenterY }
            );
          }
        }
      }
    }
  });
};

// Call updateLines whenever the component is updated
watch(currentMatchIndex, () => {
  if (activeBracketIdx.value !== null) {
    updateLines(activeBracketIdx.value);
  }
});

// Modify removeBracket to handle both bracket types
const removeBracket = async (bracketIdx) => {
  deleteBracketIdx.value = bracketIdx;
  showDeleteConfirmDialog.value = true;
};

watch(() => brackets.value.length, (newLength, oldLength) => {
  if (newLength > oldLength) {
    updateLines(newLength - 1);
  }
});

const calculateByes = (numPlayers) => {
  const nextPowerOfTwo = Math.pow(2, Math.ceil(Math.log2(numPlayers)));
  return nextPowerOfTwo - numPlayers;
};

const isFinalRound = (bracketIdx, roundIdx) => {
  return roundIdx === brackets.value[bracketIdx].matches.length - 1;
};

const isSemifinalRound = (bracketIdx, roundIdx) => {
  return roundIdx === brackets.value[bracketIdx].matches.length - 2;
};

const isQuarterfinalRound = (bracketIdx, roundIdx) => {
  return roundIdx === brackets.value[bracketIdx].matches.length - 3;
};

const confirmDeleteBracket = async () => {
  if (deleteBracketIdx.value !== null) {
    try {
      const bracket = brackets.value[deleteBracketIdx.value];
      if (bracket.id) {
        await axios.delete(`http://localhost:3000/brackets/${bracket.id}`);
      }

      // Remove the bracket and its expanded state
      brackets.value.splice(deleteBracketIdx.value, 1);
      expandedBrackets.value.splice(deleteBracketIdx.value, 1);

      // Reset the current match index if we're deleting the active bracket
      if (activeBracketIdx.value === deleteBracketIdx.value) {
        currentMatchIndex.value = 0;
        activeBracketIdx.value = null;
      }

      deleteBracketIdx.value = null;
    } catch (error) {
      console.error('Error deleting bracket:', error);
    }
  }
  showDeleteConfirmDialog.value = false;
};

const cancelDeleteBracket = () => {
  showDeleteConfirmDialog.value = false;
  deleteBracketIdx.value = null;
};

// Add onMounted hook to fetch brackets when component loads
onMounted(() => {
  fetchBrackets();
});

// Add new function to save brackets
const saveBrackets = async (bracketData) => {
  try {
    if (bracketData.id) {
      // Update existing bracket
      await axios.put(`http://localhost:3000/brackets/${bracketData.id}`, bracketData);
    } else {
      // Create new bracket
      const response = await axios.post('http://localhost:3000/brackets', bracketData);
      bracketData.id = response.data.id;
    }
  } catch (error) {
    console.error('Error saving bracket:', error);
  }
};

// Add back the concludeMatch function
const concludeMatch = (bracketIdx) => {
  pendingBracketIdx = bracketIdx;
  showConfirmDialog.value = true;
};

// Add back the getCurrentRound function
const getCurrentRound = (bracketIdx) => {
  const bracket = brackets.value[bracketIdx];

  if (bracket.type === 'Single Elimination') {
    let totalMatches = 0;
    for (let roundIdx = 0; roundIdx < bracket.matches.length; roundIdx++) {
      totalMatches += bracket.matches[roundIdx].length;
      if (currentMatchIndex.value < totalMatches) {
        return roundIdx + 1;
      }
    }
    return 1;
  } else if (bracket.type === 'Double Elimination') {
    const { roundIdx } = getRoundAndMatchIndices(bracketIdx, currentMatchIndex.value);

    if (roundIdx < bracket.matches.winners.length) {
      return `Winners Round ${roundIdx + 1}`;
    } else if (roundIdx < bracket.matches.winners.length + bracket.matches.losers.length) {
      return `Losers Round ${roundIdx - bracket.matches.winners.length + 1}`;
    } else {
      return 'Grand Finals';
    }
  }
};

// Add helper function to calculate total matches
const getTotalMatches = (bracketIdx) => {
  const bracket = brackets.value[bracketIdx];

  if (bracket.type === 'Single Elimination') {
    return bracket.matches.flat().length;
  } else if (bracket.type === 'Double Elimination') {
    return bracket.matches.winners.reduce((sum, round) => sum + round.length, 0) +
           bracket.matches.losers.reduce((sum, round) => sum + round.length, 0) +
           bracket.matches.grand_finals.length;
  }
  return 0;
};

</script>

<template>
    <div class="container">
        <div class="header">
          <h1 class="title">Ongoing Games</h1>
          <button class="create-button" @click="openDialog">Create Bracket</button>
        </div>

      <!-- Display message when no brackets are created -->
      <div v-if="brackets.length === 0" class="no-brackets-message">
        <div class="icon-and-title">
          <i class="pi pi-info-circle" style="font-size: 1.5rem; color: #007bff; margin-right: 10px;"></i>
          <h2 class="no-brackets-title">No Brackets Created Yet</h2>
        </div>
        <p class="no-brackets-text">Click the "Create Bracket" button above to start a new tournament.</p>
      </div>

      <!-- Bracket Display Section -->
      <div v-for="(bracket, bracketIdx) in brackets" :key="bracketIdx" class="bracket-section">
        <div class="bracket-wrapper">
          <div class="bracket-header">
            <h2>{{ bracket.name }} ({{ bracket.type }})</h2>
            <div class="event-info" v-if="bracket.event">
              <span class="event-label">Event:</span>
              <Link
                :href="route('event.details', { id: bracket.event_id })"
                class="event-title"
              >
                {{ bracket.event?.title || 'Loading...' }}
              </Link>
            </div>
          </div>
          <div class="bracket-controls">
            <button @click="toggleBracket(bracketIdx)" class="toggle-button">
              {{ expandedBrackets[bracketIdx] ? 'Hide Bracket' : 'Show Bracket' }}
            </button>
            <button @click="removeBracket(bracketIdx)" class="delete-button">Delete Bracket</button>
          </div>

          <div v-if="expandedBrackets[bracketIdx]">
            <!-- Single Elimination Display -->
            <div v-if="bracket.type === 'Single Elimination'" class="bracket">
              <svg class="connection-lines">
                <line
                  v-for="(line, i) in bracket.lines"
                  :key="i"
                  :x1="line.x1"
                  :y1="line.y1"
                  :x2="line.x2"
                  :y2="line.y2"
                  stroke="black"
                  stroke-width="2"
                />
              </svg>

              <div v-for="(round, roundIdx) in bracket.matches" :key="roundIdx"
                :class="['round', `round-${roundIdx + 1}`]">
                <h3>
                  {{ isFinalRound(bracketIdx, roundIdx) ? 'Final Round' : isSemifinalRound(bracketIdx, roundIdx) ? 'Semifinal' : isQuarterfinalRound(bracketIdx, roundIdx) ? 'Quarterfinal' : `Round ${roundIdx + 1}` }}
                </h3>

                <!-- Matches Display -->
                <div
                  v-for="(match, matchIdx) in round"
                  :key="matchIdx"
                  :id="`match-${roundIdx}-${matchIdx}`"
                  :class="['match', { 'highlight': isCurrentMatch(bracketIdx, roundIdx, matchIdx) }]"
                  @click="navigateToMatch(bracketIdx, roundIdx, matchIdx)"
                >
                  <div class="player-box">
                      <span
                        @click.stop="editParticipant(bracketIdx, roundIdx, matchIdx, 0)"
                        :class="{
                          editable: true,
                          winner: match.players[0].completed && match.players[0].score >= match.players[1].score,
                          loser: match.players[0].completed && match.players[0].score < match.players[1].score,
                          'bye-text': match.players[0].name === 'BYE',
                          'facing-bye': match.players[1].name === 'BYE'
                        }"
                      >
                        {{ match.players[0].name || 'TBD' }} | {{ match.players[0].score }}
                      </span>
                      <hr />
                      <span
                        @click.stop="editParticipant(bracketIdx, roundIdx, matchIdx, 1)"
                        :class="{
                          editable: true,
                          winner: match.players[1].completed && match.players[1].score >= match.players[0].score,
                          loser: match.players[1].completed && match.players[1].score < match.players[0].score,
                          'bye-text': match.players[1].name === 'BYE',
                          'facing-bye': match.players[0].name === 'BYE'
                        }"
                      >
                        {{ match.players[1].name || 'TBD' }} | {{ match.players[1].score }}
                      </span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Double Elimination Display -->
            <div v-else-if="bracket.type === 'Double Elimination'" class="double-elimination-bracket">
              <!-- Winners Bracket -->
              <div class="bracket-section winners">
                <h3>Winners Bracket</h3>
                <div class="bracket">
                  <svg class="connection-lines winners-lines">
                    <g v-for="(line, i) in bracket.lines?.winners" :key="`winners-${i}`">
                      <line
                        :x1="line.x1"
                        :y1="line.y1"
                        :x2="line.x2"
                        :y2="line.y2"
                        stroke="black"
                        stroke-width="2"
                      />
                    </g>
                  </svg>

                  <div v-for="(round, roundIdx) in bracket.matches.winners" :key="`winners-${roundIdx}`"
                    :class="['round', `round-${roundIdx + 1}`]">
                    <h4>Round {{ roundIdx + 1 }}</h4>

                    <div
                      v-for="(match, matchIdx) in round"
                      :key="`winners-${roundIdx}-${matchIdx}`"
                      :id="`winners-match-${roundIdx}-${matchIdx}`"
                      :class="['match', { 'highlight': isCurrentMatch(bracketIdx, roundIdx, matchIdx, 'winners') }]"
                      @click="navigateToMatch(bracketIdx, roundIdx, matchIdx, 'winners')"
                    >
                      <div class="player-box">
                        <span
                          @click.stop="editParticipant(bracketIdx, roundIdx, matchIdx, 0)"
                          :class="{
                            editable: true,
                            winner: match.players[0].completed && match.players[0].score >= match.players[1].score,
                            loser: match.players[0].completed && match.players[0].score < match.players[1].score,
                            'bye-text': match.players[0].name === 'BYE',
                            'facing-bye': match.players[1].name === 'BYE'
                          }"
                        >
                          {{ match.players[0].name || 'TBD' }} | {{ match.players[0].score }}
                        </span>
                        <hr />
                        <span
                          @click.stop="editParticipant(bracketIdx, roundIdx, matchIdx, 1)"
                          :class="{
                            editable: true,
                            winner: match.players[1].completed && match.players[1].score >= match.players[0].score,
                            loser: match.players[1].completed && match.players[1].score < match.players[0].score,
                            'bye-text': match.players[1].name === 'BYE',
                            'facing-bye': match.players[0].name === 'BYE'
                          }"
                        >
                          {{ match.players[1].name || 'TBD' }} | {{ match.players[1].score }}
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Losers Bracket -->
              <div class="bracket-section losers">
                <h3>Losers Bracket</h3>
                <div class="bracket">
                  <svg class="connection-lines losers-lines">
                    <g v-for="(line, i) in bracket.lines?.losers" :key="`losers-${i}`">
                      <line
                        :x1="line.x1"
                        :y1="line.y1"
                        :x2="line.x2"
                        :y2="line.y2"
                        stroke="black"
                        stroke-width="2"
                      />
                    </g>
                  </svg>

                  <div v-for="(round, roundIdx) in bracket.matches.losers" :key="`losers-${roundIdx}`"
                    :class="['round', `round-${roundIdx + 1}`]">
                    <h4>Round {{ roundIdx + 1 }}</h4>

                    <div
                      v-for="(match, matchIdx) in round"
                      :key="`losers-${roundIdx}-${matchIdx}`"
                      :id="`losers-match-${roundIdx}-${matchIdx}`"
                      :class="['match', { 'highlight': isCurrentMatch(bracketIdx, roundIdx + bracket.matches.winners.length, matchIdx, 'losers') }]"
                      @click="navigateToMatch(bracketIdx, roundIdx + bracket.matches.winners.length, matchIdx, 'losers')"
                    >
                      <div class="player-box">
                        <span
                          @click.stop="editParticipant(bracketIdx, roundIdx, matchIdx, 0)"
                          :class="{
                            editable: true,
                            winner: match.players[0].completed && match.players[0].score >= match.players[1].score,
                            loser: match.players[0].completed && match.players[0].score < match.players[1].score,
                            'bye-text': match.players[0].name === 'BYE',
                            'facing-bye': match.players[1].name === 'BYE',
                            'losers-bracket-loser': match.players[0].completed && match.players[0].score < match.players[1].score
                          }"
                        >
                          {{ match.players[0].name || 'TBD' }} | {{ match.players[0].score }}
                        </span>
                        <hr />
                        <span
                          @click.stop="editParticipant(bracketIdx, roundIdx, matchIdx, 1)"
                          :class="{
                            editable: true,
                            winner: match.players[1].completed && match.players[1].score >= match.players[0].score,
                            loser: match.players[1].completed && match.players[1].score < match.players[0].score,
                            'bye-text': match.players[1].name === 'BYE',
                            'facing-bye': match.players[0].name === 'BYE',
                            'losers-bracket-loser': match.players[1].completed && match.players[1].score < match.players[1].score
                          }"
                        >
                          {{ match.players[1].name || 'TBD' }} | {{ match.players[1].score }}
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Grand Finals -->
              <div class="bracket-section grand-finals">
                <h3>Grand Finals</h3>
                <div class="bracket">
                  <svg class="connection-lines finals-lines">
                    <g v-for="(line, i) in bracket.lines?.finals" :key="`finals-${i}`">
                      <line
                        :x1="line.x1"
                        :y1="line.y1"
                        :x2="line.x2"
                        :y2="line.y2"
                        stroke="black"
                        stroke-width="2"
                      />
                    </g>
                  </svg>

                  <div v-for="(match, matchIdx) in bracket.matches.grand_finals" :key="`grand-finals-${matchIdx}`"
                    :id="`grand-finals-match-${matchIdx}`"
                    :class="['match', { 'highlight': isCurrentMatch(bracketIdx, bracket.matches.winners.length + bracket.matches.losers.length, matchIdx, 'grand_finals') }]"
                    @click="navigateToMatch(bracketIdx, bracket.matches.winners.length + bracket.matches.losers.length, matchIdx, 'grand_finals')"
                  >
                    <div class="player-box">
                      <span
                        @click.stop="editParticipant(bracketIdx, 0, matchIdx, 0)"
                        :class="{
                          editable: true,
                          winner: match.players[0].completed && match.players[0].score >= match.players[1].score,
                          loser: match.players[0].completed && match.players[0].score < match.players[1].score
                        }"
                      >
                        {{ match.players[0].name || 'TBD' }} | {{ match.players[0].score }}
                      </span>
                      <hr />
                      <span
                        @click.stop="editParticipant(bracketIdx, 0, matchIdx, 1)"
                        :class="{
                          editable: true,
                          winner: match.players[1].completed && match.players[1].score >= match.players[0].score,
                          loser: match.players[1].completed && match.players[1].score < match.players[0].score
                        }"
                      >
                        {{ match.players[1].name || 'TBD' }} | {{ match.players[1].score }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Navigation & Matchup Box -->
            <div class="navigation-and-matchup">
              <!-- Navigation Controls -->
              <div class="navigation-controls">
                <button
                  @click="showPreviousMatch(bracketIdx)"
                  :disabled="currentMatchIndex === 0"
                >
                  Previous
                </button>

                <button
                  @click="showNextMatch(bracketIdx)"
                  :disabled="currentMatchIndex >= getTotalMatches(bracketIdx) - 1"
                >
                  Next
                </button>
              </div>

              <!-- Current Match Display -->
              <div v-if="currentMatch(bracketIdx)" class="match-card styled">
                <!-- Sport Tag with Correct Round Counter -->
                <div class="sport-tag">
                  {{ bracket.name }} - Round {{ getCurrentRound(bracketIdx) }}
                </div>

                <div class="match-content">
                  <!-- Left Team -->
                  <div class="team blue">
                    <button
                      class="score-btn"
                      @click="increaseScore(bracketIdx, 0)"
                      :disabled="currentMatch(bracketIdx).status === 'completed'"
                    >
                      +
                    </button>

                    <div class="score-display">
                      {{ currentMatch(bracketIdx).players[0]?.score.toString().padStart(2, '0') }}
                    </div>

                    <button
                      class="score-btn"
                      @click="decreaseScore(bracketIdx, 0)"
                      :disabled="currentMatch(bracketIdx).status === 'completed'"
                    >
                      -
                    </button>

                    <span class="team-name">{{ currentMatch(bracketIdx).players[0]?.name || 'TBD' }}</span>
                  </div>

                  <span class="vs">VS</span>

                  <!-- Right Team -->
                  <div class="team red">
                    <button
                      class="score-btn"
                      @click="increaseScore(bracketIdx, 1)"
                      :disabled="currentMatch(bracketIdx).status === 'completed'"
                    >
                      +
                    </button>

                    <div class="score-display">
                      {{ currentMatch(bracketIdx).players[1]?.score.toString().padStart(2, '0') }}
                    </div>

                    <button
                      class="score-btn"
                      @click="decreaseScore(bracketIdx, 1)"
                      :disabled="currentMatch(bracketIdx).status === 'completed'"
                    >
                      -
                    </button>

                    <span class="team-name">{{ currentMatch(bracketIdx).players[1]?.name || 'TBD' }}</span>
                  </div>
                </div>

                <!-- Match Status -->
                <div v-if="currentMatch(bracketIdx).status === 'completed'"
                     class="completed-text"
                     @click="undoConcludeMatch(bracketIdx)">
                  Completed
                </div>

                <button v-else
                        @click="concludeMatch(bracketIdx)"
                        class="match-over">
                  End Match
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Dialog for Bracket Setup -->
      <Dialog v-model:visible="showDialog" header="Bracket Setup" modal :style="{ width: '400px' }">
        <div class="dialog-content">
          <div class="p-field">
            <label for="bracketName">Bracket Name:</label>
            <InputText v-model="bracketName" placeholder="Enter bracket name" />
          </div>

          <div class="p-field">
            <label for="event">Select Event:</label>
            <Select
              v-model="selectedEvent"
              :options="events"
              optionLabel="title"
              placeholder="Select a sports event"
              filter
            />
          </div>

          <div class="p-field">
            <label for="numberOfPlayers">Number of Participants:</label>
            <InputText v-model="numberOfPlayers" type="number" min="1" placeholder="4, 8, 16, 32" />
          </div>

          <div class="p-field">
            <label for="matchType">Bracket Type:</label>
            <Select
              v-model="matchType"
              :options="bracketTypeOptions"
              placeholder="Select bracket type"
            />
          </div>

          <div class="button-container">
            <Button label="Create Bracket" class="p-button-success" @click="createBracket" />
          </div>
        </div>
      </Dialog>

      <Dialog v-model:visible="showWinnerDialog" header="Winner!" modal dismissableMask>
        <p>{{ winnerMessage }}</p>
      </Dialog>

      <Dialog v-model:visible="showConfirmDialog" header="Confirm End Match" modal>
        <div class="confirmation-content">
          <i class="pi pi-question-circle" style="font-size: 2rem; color: #007bff;"></i>
          <p>Are you sure you want to conclude this match?</p>
          <div class="confirmation-buttons">
            <Button label="Yes" icon="pi pi-check" class="p-button-success" @click="confirmEndMatch" />
            <Button label="No" icon="pi pi-times" class="p-button-secondary" @click="cancelEndMatch" />
          </div>
        </div>
      </Dialog>

      <Dialog v-model:visible="showMissingFieldsDialog" header="Missing Fields" modal>
        <div class="dialog-content centered">
          <i class="pi pi-exclamation-triangle" style="font-size: 2rem; color: #ff4757;"></i>
          <p>Please fill out all fields.</p>
          <div class="button-container">
            <Button label="OK" class="p-button-danger" @click="showMissingFieldsDialog = false" />
          </div>
        </div>
      </Dialog>

      <Dialog v-model:visible="showDeleteConfirmDialog" header="Confirm Deletion" modal>
        <div class="confirmation-content">
          <i class="pi pi-question-circle" style="font-size: 2rem; color: #007bff;"></i>
          <p>Are you sure you want to delete this bracket?</p>
          <div class="confirmation-buttons">
            <Button label="Yes" icon="pi pi-check" class="p-button-success" @click="confirmDeleteBracket" />
            <Button label="No" icon="pi pi-times" class="p-button-secondary" @click="cancelDeleteBracket" />
          </div>
        </div>
      </Dialog>
    </div>
  </template>

<style scoped>
/** Bracket*/

      /* Container & General Styling */
    .container {
    padding: 20px;
  }

  .bracket-wrapper {
    background-color: #f0f0f0;
    padding: 15px;
    border-radius: 12px;
    margin-bottom: 20px;
  }

  .match {
    background: #fff;
    position: relative;
    border: 2px solid #007bff;
    padding: 6px;
    border-radius: 8px;
    text-align: center;
    width: 150px; /* Match size */
    margin: 10px 0px; /* Spacing between matches */
    margin-top: 10px;
    box-shadow: 2px 2px 5px rgba(0, 0, 0, 0.1);
  }

  .match-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
    text-align: center;
  }

  .team {
    display: flex;
    flex-direction: column;
    align-items: center;
    width: 100px;
    padding: 10px;
    border-radius: 8px;
    color: #fff;
  }

  .team .team-name {
    font-weight: bold;
    margin-bottom: 5px;
  }

  .score-controls {
    display: flex;
    gap: 5px;
    align-items: center;
  }

  .vs {
    font-weight: bold;
    color: #333;
    font-size: 1.5rem;
    padding: 0 15px;
  }

  .match-over, .completed-text {
    width: 100%;
    padding: 5px 10px;
    border-radius: 5px;
    font-weight: bold;
    text-align: center;
  }

  .match-over {
    background-color: #ff4757;
    color: white;
    border: none;
    cursor: pointer;
    margin-top: 5px;
  }

  .completed-text {
    background-color: #008000;
    color: #fff;
    cursor: pointer;
    transition: background-color 0.3s ease;
  }

  .completed-text:hover {
    background-color: #006400;
  }

  /* Editable Players */
  .editable {
    cursor: pointer;
    color: #007bff;
    transition: color 0.2s ease-in-out;
  }

  .editable:hover {
    color: #0056b3;
  }

  .match::before {
    top: 50%;
    right: 100%;
    transform: translateY(-50%);
  }

  .match::after {
    top: 50%;
    left: 100%;
    transform: translateY(-50%);
  }

  .round .match {
    margin-top: 0; /* Remove fixed margins */
  }

  /* Balanced Line Design (Pairs only) */
  .round .match:nth-child(even)::before {
      top: 50%; /* Centered to the match box */
      transform: translateY(-50%); /* Aligns perfectly */}

  .round .match:nth-child(odd)::before {
      top: 50%; /* Centered to the match box */
      transform: translateY(-50%); /* Aligns perfectly */
  }

  /* Remove Line for Final Round */
  .round:last-child .match::after {
    display: none;
  }

  .bracket {
    display: flex;
    gap: 40px;
    align-items: flex-start; /* Aligns rounds at the top */
    justify-content: center;
    position: relative;
  }

  .round {
    display: flex;
    flex-direction: column;
    gap: 20px; /* Creates balanced gaps in early rounds */
    position: relative;
  }

  /** Bracket position individual cell */
  .round-1 .match { margin-top: 0; margin-bottom: auto; }
  .round-2 .match { margin-top: 39px; margin-bottom: 30px;}
  .round-3 .match { margin-top: 110px; margin-bottom: 75px }
  .round-4 .match { margin-top: 250px; margin-bottom: 225px;}
  .round-5 .match { margin-top: 420px; margin-bottom: auto;}

  /* Navigation & Matchup Section */
  .navigation-and-matchup {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 15px;
  }

  .navigation-controls {
    display: flex;
    justify-content: center;
    gap: 10px;
  }

  .navigation-controls button {
    background-color: #007bff;
    color: #fff;
    padding: 5px 15px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
  }

  .navigation-controls button:disabled {
    background-color: #aaa;
    cursor: not-allowed;
  }

  .navigation-controls button:hover {
    background-color: #0056b3; /* Darker blue on hover */
  }

  /* Matchup Box */
  .match-card {
    text-align: center;
    background: #f8f9fa;
    border: 2px solid #007bff;
    border-radius: 8px;
    padding: 15px;
    max-width: 300px;
  }

  /* Improved Button Design */
  .score-btn {
    background-color: #4caf50;
    margin: 0 3px; /* Improved spacing between score controls */
    color: #fff;
    border: none;
    padding: 5px 10px;
    border-radius: 5px;
    cursor: pointer;
    font-weight: bold;
  }

  .score-btn:disabled {
    background-color: #aaa;
    cursor: not-allowed;
  }

  .team .score-display {
    background-color: #fff;
    color: #000;
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: bold;
    margin: 5px 0;
  }

  .match-over {
    background-color: #ff4757;
    color: white;
    border: none;
    padding: 5px 10px;
    border-radius: 5px;
    cursor: pointer;
    width: 100%;
    margin-top: 5px;
  }
        .match-card.styled {
          background-color: #d1d5db;
          padding: 20px;
          border-radius: 12px;
          box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
          text-align: center;
          position: relative;
        }

        .sport-tag {
          background-color: #007bff;
          color: #fff;
          padding: 5px 15px;
          border-radius: 15px;
          display: inline-block;
          margin-bottom: 15px;
        }

        .match-content {
          display: flex;
          justify-content: space-around;
          align-items: center;
          margin-bottom: 10px;
        }

        .team {
          display: flex;
          flex-direction: column;
          align-items: center;
          padding: 15px;
          border-radius: 8px;
          color: #fff;
          width: 80px;
        }

        .team.blue {
          background-color: #0047ab;
        }

        .team.red {
          background-color: #d70000;
        }

        .team-name {
          font-weight: bold;
          margin-bottom: 10px;
          font-size: 1.1rem;
        }

        .score-controls {
          display: flex;
          align-items: center;
          gap: 8px;
        }

        .score-btn {
          background-color: rgba(255, 255, 255, 0.2);
          color: #fff;
          border: none;
          width: 32px;
          height: 32px;
          border-radius: 50%;
          cursor: pointer;
          font-size: 1.2rem;
          display: flex;
          align-items: center;
          justify-content: center;
          transition: all 0.2s ease;
        }

        .score-btn:hover:not(:disabled) {
          background-color: rgba(255, 255, 255, 0.3);
          transform: scale(1.1);
        }

        .score-btn:disabled {
          opacity: 0.5;
          cursor: not-allowed;
        }

        .score-display {
          background-color: rgba(255, 255, 255, 0.9);
          color: #000;
          padding: 8px 16px;
          border-radius: 8px;
          font-weight: bold;
          font-size: 1.2rem;
          min-width: 50px;
        }

        .vs {
          font-weight: bold;
          color: #333;
          font-size: 1.5rem;
          padding: 0 15px;
        }

        .match-status {
          margin-top: 20px;
        }

        .match-over, .completed-text {
          width: 100%;
          padding: 12px;
          border-radius: 8px;
          font-weight: 600;
          text-align: center;
          display: flex;
          align-items: center;
          justify-content: center;
          gap: 8px;
          transition: all 0.2s ease;
        }

        .match-over {
          background-color: #ff4757;
          color: white;
          border: none;
          cursor: pointer;
        }

        .match-over:hover {
          background-color: #e63946;
          transform: translateY(-2px);
        }

        .completed-text {
          background-color: #28a745;
          color: #fff;
          cursor: pointer;
        }

        .completed-text:hover {
          background-color: #218838;
          transform: translateY(-2px);
        }

        .completed-text i, .match-over i {
          font-size: 1.2rem;
        }

.connection-lines {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  pointer-events: none;
  z-index: 1;
}

.winners-lines {
  stroke: #007bff;
}

.losers-lines {
  stroke: #dc3545;
}

.finals-lines {
  stroke: #28a745;
}

.bracket {
  position: relative;
  display: flex;
  gap: 40px;
  align-items: flex-start;
  justify-content: center;
}

.bracket-section {
  position: relative;
  margin-bottom: 30px;
}

.bracket-section.winners {
  border-right: 2px solid #007bff;
  padding-right: 20px;
}

.bracket-section.losers {
  border-left: 2px solid #dc3545;
  padding-left: 20px;
}

.bracket-section.grand-finals {
  border-top: 2px solid #28a745;
  padding-top: 20px;
  margin-top: 20px;
}

.delete-button {
  background-color: #ff4757;
  color: white;
  padding: 5px 10px;
  border: none;
  border-radius: 5px;
  cursor: pointer;
  margin-bottom: 10px;
}

.delete-button:hover {
  background-color: #e63946;

}

.match-over:hover {
  background-color: #e63946; /* Lighter red on hover */
}

.toggle-button {
  background-color: #007bff;
  color: white;
  padding: 5px 10px;
  border: none;
  border-radius: 5px;
  cursor: pointer;
  margin-bottom: 10px;
}

.toggle-button:hover {
  background-color: #0056b3;
}

.no-brackets-message {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  margin-top: 20px;
  padding: 20px;
  background-color: #f9f9f9;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.icon-and-title {
  display: flex;
  align-items: center;
  margin-bottom: 10px;
}

.no-brackets-title {
  color: #333;
  margin: 0;
  font-size: 1.5rem; /* Match the icon size */
  font-weight: bold;
}

.no-brackets-text {
  color: #555;
  margin: 5px 0 0 0;
}

.header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 20px;
}

.title {
  font-size: 1.5rem;
  font-weight: bold;
  color: #333;
}

.winner {
  color: #28a745; /* Green for winner */
}

.loser {
  color: #dc3545; /* Red for loser */
}

.bye-text {
  color: #dc3545; /* Red color for BYE text */
}

.facing-bye {
  color: #28a745; /* Green color for players facing a BYE */
}

.bracket-wrapper h2 {
  font-size: 1.8rem;
  font-weight: bold;
  color: #333;
  margin-bottom: 10px;
  text-align: left;
}

.round h3 {
  font-size: 1.5rem;
  font-weight: bold;
  color: #555;
  margin-bottom: 15px;
  text-align: center;
}

.p-dialog .p-dialog-content p {
  font-size: 1.2rem;
  font-weight: bold;
  color: #28a745; /* Green color for winner text */
  text-align: center;
  margin: 20px 0;
}

.confirmation-content {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
}

.confirmation-buttons {
  display: flex;
  gap: 10px;
  margin-top: 20px;
}

.centered {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
}

.button-container {
  display: flex;
  justify-content: center;
  margin-top: 20px;
}

.bracket-header {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-bottom: 15px;
}

.event-info {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 1rem;
  color: #666;
}

.event-label {
  font-weight: bold;
}

.event-title {
  color: #007bff;
  text-decoration: none;
  transition: color 0.2s ease;
}

.event-title:hover {
  color: #0056b3;
  text-decoration: underline;
}

.match.highlight {
  border: 2px solid black;
  background-color: lightgray;
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
}

/* Double Elimination Styles */
.double-elimination-bracket {
  display: flex;
  flex-direction: column;
  gap: 40px;
  padding: 20px;
}

.bracket-section {
  margin-bottom: 30px;
}

.bracket-section h3 {
  font-size: 1.5rem;
  font-weight: bold;
  color: #333;
  margin-bottom: 20px;
  text-align: center;
}

.bracket-section.winners {
  border-right: 2px solid #007bff;
  padding-right: 20px;
}

.bracket-section.losers {
  border-left: 2px solid #dc3545;
  padding-left: 20px;
}

.bracket-section.grand-finals {
  border-top: 2px solid #28a745;
  padding-top: 20px;
  margin-top: 20px;
}

.grand-finals .match {
  background-color: #f8f9fa;
  border: 2px solid #28a745;
  transition: all 0.3s ease;
}

.grand-finals .match:hover {
  background-color: #e8f5e9;
  transform: translateY(-2px);
}

.winners .match {
  border-color: #007bff;
}

.losers .match {
  border-color: #dc3545;
}

.create-button {
  background-color: #28a745;
  color: white;
  padding: 10px 20px;
  border: none;
  border-radius: 5px;
  cursor: pointer;
  font-weight: bold;
  transition: background-color 0.2s ease;
}

.create-button:hover {
  background-color: #218838;
}

.bracket-controls {
  display: flex;
  gap: 10px;
  margin-bottom: 15px;
}

.toggle-button {
  background-color: #007bff;
  color: white;
  padding: 8px 16px;
  border: none;
  border-radius: 5px;
  cursor: pointer;
  font-weight: bold;
  transition: background-color 0.2s ease;
}

.toggle-button:hover {
  background-color: #0056b3;
}

.delete-button {
  background-color: #dc3545;
  color: white;
  padding: 8px 16px;
  border: none;
  border-radius: 5px;
  cursor: pointer;
  font-weight: bold;
  transition: background-color 0.2s ease;
}

.delete-button:hover {
  background-color: #c82333;
}

.losers-bracket-loser {
  color: #dc3545 !important; /* Red color for losers in losers bracket */
  font-weight: bold;
}
</style>
