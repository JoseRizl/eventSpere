<script setup>
import { ref, nextTick, watch } from "vue";
import Dialog from "primevue/dialog";
import InputText from "primevue/inputtext";
import Select from "primevue/select";
import Button from "primevue/button";
import { computed } from "vue";

// Reactive State
const bracketName = ref("");
const numberOfPlayers = ref();
const matchType = ref("");
const brackets = ref([]);
const showDialog = ref(false);
const currentMatchIndex = ref(0);
const lines = ref([]);
const expandedBrackets = ref([]); // Track expanded state of each bracket
const activeBracketIdx = ref(null);

// Game Number Indicator
const currentGameNumber = computed(() => `Game ${currentMatchIndex.value + 1}`);


// Options
const participantOptions = [4, 8, 16, 32];
const bracketTypeOptions = ["single", "double"];

// Open Dialog for Bracket Creation
const openDialog = () => {
  bracketName.value = "";
  numberOfPlayers.value = null;
  matchType.value = "";
  showDialog.value = true;
};

// Toggle bracket visibility
const toggleBracket = (bracketIdx) => {
  if (expandedBrackets.value[bracketIdx]) {
    expandedBrackets.value[bracketIdx] = false; // Hide the current bracket if it's already open
  } else {
    expandedBrackets.value = expandedBrackets.value.map((expanded, idx) => idx === bracketIdx);
    updateLines(bracketIdx); // Load SVG lines only for the expanded bracket
  }
};

// Create Bracket
const createBracket = () => {
  if (!bracketName.value || !numberOfPlayers.value || !matchType.value) {
    alert("Please fill out all fields.");
    return;
  }

  const newBracket = generateBracket();
  brackets.value.push({
    name: bracketName.value,
    type: matchType.value,
    matches: newBracket,
    currentMatchIndex: 0,
    lines: [], // Initialize lines for each bracket
  });
  expandedBrackets.value.push(false); // Initialize expanded state
  showDialog.value = false;

  // Call updateLines for the newly created bracket
  updateLines(brackets.value.length - 1);
};

const generateBracket = () => {
  const numPlayers = numberOfPlayers.value;
  const totalSlots = Math.pow(2, Math.ceil(Math.log2(numPlayers)));
  const totalByes = totalSlots - numPlayers;

  // Create arrays for players and BYEs
  const players = Array.from({ length: numPlayers }, (_, i) => ({
    name: `Player ${i + 1}`,
    score: 0,
    completed: false,
  }));
  const byes = Array.from({ length: totalByes }, () => ({
    name: "BYE",
    score: 0,
    completed: false,
  }));

  // Initialize slots array
  const slots = Array(totalSlots).fill(null);

  // 3. Alternate BYEs and Participants
  let byeIndex = 0;
  for (let i = 0; i < totalSlots; i += 2) {
    if (byeIndex < byes.length) {
      slots[i] = byes[byeIndex++];
    }
  }

  // 4. Fill remaining slots with players
  let playerIndex = 0;
  for (let i = 0; i < totalSlots; i++) {
    if (!slots[i] && playerIndex < players.length) {
      slots[i] = players[playerIndex++];
    }
  }

  // 5. Pair into matches
  const firstRound = [];
  for (let i = 0; i < totalSlots; i += 2) {
    firstRound.push([slots[i], slots[i + 1]]);
  }

  // 6. Build empty placeholders for later rounds
  const rounds = [firstRound];
  let prevMatches = firstRound;
  while (prevMatches.length > 1) {
    const nextMatches = Array.from(
      { length: Math.ceil(prevMatches.length / 2) },
      () => [
        { name: "TBD", score: 0, completed: false },
        { name: "TBD", score: 0, completed: false },
      ]
    );
    rounds.push(nextMatches);
    prevMatches = nextMatches;
  }

  return rounds;
};


// Edit Participant Names
const editParticipant = (bracketIdx, roundIdx, matchIdx, teamIdx) => {
  const newName = prompt("Enter new participant name:");
  if (newName) {
    brackets.value[bracketIdx].matches[roundIdx][matchIdx][teamIdx].name = newName;
  }
};

// Helper function to map currentMatchIndex to roundIdx and matchIdx
const getRoundAndMatchIndices = (bracketIdx, currentMatchIndex) => {
  let accumulatedMatches = 0;
  const bracket = brackets.value[bracketIdx];

  for (let roundIdx = 0; roundIdx < bracket.matches.length; roundIdx++) {
    const matchesInRound = bracket.matches[roundIdx].length;
    if (currentMatchIndex < accumulatedMatches + matchesInRound) {
      return { roundIdx, matchIdx: currentMatchIndex - accumulatedMatches };
    }
    accumulatedMatches += matchesInRound;
  }
  return { roundIdx: 0, matchIdx: 0 }; // Fallback
};

// Update increaseScore and decreaseScore to use the helper function
const increaseScore = (bracketIdx, teamIdx) => {
  const { roundIdx, matchIdx } = getRoundAndMatchIndices(bracketIdx, currentMatchIndex.value);
  const match = brackets.value[bracketIdx].matches[roundIdx][matchIdx];
  match[teamIdx].score++;
};

const decreaseScore = (bracketIdx, teamIdx) => {
  const { roundIdx, matchIdx } = getRoundAndMatchIndices(bracketIdx, currentMatchIndex.value);
  const match = brackets.value[bracketIdx].matches[roundIdx][matchIdx];
  if (match[teamIdx].score > 0) {
    match[teamIdx].score--;
  }
};

// Function to conclude a match with confirmation
const concludeMatch = (bracketIdx) => {
  const { roundIdx, matchIdx } = getRoundAndMatchIndices(bracketIdx, currentMatchIndex.value);
  const match = brackets.value[bracketIdx].matches[roundIdx][matchIdx];

  // Check if one of the participants is a BYE
  const isByeMatch = match[0].name === "BYE" || match[1].name === "BYE";

  if (isByeMatch) {
    // Automatically determine the winner
    const winner = match[0].name === "BYE" ? match[1] : match[0];

    match[0].completed = true;
    match[1].completed = true;
    match.completed = true; // Mark match as completed

    // Advance Winner to Next Round
    if (brackets.value[bracketIdx].matches[roundIdx + 1]) {
      const nextRoundIdx = Math.floor(matchIdx / 2); // Correct positioning logic
      const nextMatchPos = matchIdx % 2 === 0 ? 0 : 1;

      brackets.value[bracketIdx].matches[roundIdx + 1][nextRoundIdx][nextMatchPos] = {
        ...winner,
        score: 0,
        completed: false,
      };
    } else {
      alert(`Winner: ${winner.name}`);
    }
  } else {
    // Show confirmation dialog for regular matches
    if (confirm('Are you sure you want to conclude this match?')) {
      // Determine Winner
      const winner = match[0].score >= match[1].score ? match[0] : match[1];

      match[0].completed = true;
      match[1].completed = true;
      match.completed = true; // Mark match as completed

      // Advance Winner to Next Round
      if (brackets.value[bracketIdx].matches[roundIdx + 1]) {
        const nextRoundIdx = Math.floor(matchIdx / 2); // Correct positioning logic
        const nextMatchPos = matchIdx % 2 === 0 ? 0 : 1;

        brackets.value[bracketIdx].matches[roundIdx + 1][nextRoundIdx][nextMatchPos] = {
          ...winner,
          score: 0,
          completed: false,
        };
      } else {
        alert(`Winner: ${winner.name}`);
      }
    }
  }
};

// Function to undo a concluded match with confirmation
const undoConcludeMatch = (bracketIdx) => {
  const { roundIdx, matchIdx } = getRoundAndMatchIndices(bracketIdx, currentMatchIndex.value);
  const match = brackets.value[bracketIdx].matches[roundIdx][matchIdx];

  // Show confirmation dialog
  if (confirm('Are you sure you want to undo this match completion?')) {
    match[0].completed = false;
    match[1].completed = false;
    match.completed = false;

    // Remove the winner from the next round
    if (brackets.value[bracketIdx].matches[roundIdx + 1]) {
      const nextRoundIdx = Math.floor(matchIdx / 2);
      const nextMatchPos = matchIdx % 2 === 0 ? 0 : 1;

      brackets.value[bracketIdx].matches[roundIdx + 1][nextRoundIdx][nextMatchPos] = {
        name: '',
        score: 0,
        completed: false,
      };
    }
  }
};

// Correct Round Display
const getCurrentRound = (bracketIdx) => {
  const flatBracket = brackets.value[bracketIdx].matches.flat();
  let totalMatches = 0;

  for (let roundIdx = 0; roundIdx < brackets.value[bracketIdx].matches.length; roundIdx++) {
    totalMatches += brackets.value[bracketIdx].matches[roundIdx].length;
    if (currentMatchIndex.value < totalMatches) {
      return roundIdx + 1; // Correct round display
    }
  }
  return 1; // Default to round 1 if no match is found
};

const isCurrentMatch = (bracketIdx, roundIdx, matchIdx) => {
  const { roundIdx: currentRoundIdx, matchIdx: currentMatchIdx } = getRoundAndMatchIndices(bracketIdx, currentMatchIndex.value);
  return roundIdx === currentRoundIdx && matchIdx === currentMatchIdx;
};

// Improved Navigation Logic
const navigateToMatch = (bracketIdx, roundIdx, matchIdx) => {
  activeBracketIdx.value = bracketIdx;
  const bracket = brackets.value[bracketIdx];
  let accumulatedMatches = 0;

  for (let i = 0; i < roundIdx; i++) {
    accumulatedMatches += bracket.matches[i].length;
  }

  currentMatchIndex.value = accumulatedMatches + matchIdx;
};

const showNextMatch = (bracketIdx) => {
  activeBracketIdx.value = bracketIdx;
  const totalMatches = brackets.value[bracketIdx].matches.flat().length;
  if (currentMatchIndex.value < totalMatches - 1) {
    currentMatchIndex.value++;
  }
};

const showPreviousMatch = (bracketIdx) => {
  activeBracketIdx.value = bracketIdx;
  if (currentMatchIndex.value > 0) {
    currentMatchIndex.value--;
  }
};

const currentMatch = (bracketIdx) => {
  const totalMatchesPerRound = brackets.value[bracketIdx].matches.map(round => round.length);

  let accumulatedMatches = 0;

  for (let roundIdx = 0; roundIdx < totalMatchesPerRound.length; roundIdx++) {
    if (currentMatchIndex.value < accumulatedMatches + totalMatchesPerRound[roundIdx]) {
      const matchIdx = currentMatchIndex.value - accumulatedMatches;
      return brackets.value[bracketIdx].matches[roundIdx][matchIdx];
    }
    accumulatedMatches += totalMatchesPerRound[roundIdx];
  }

  return null; // Fallback
};

// Update lines dynamically using SVG
const updateLines = (bracketIdx) => {
  const bracket = brackets.value[bracketIdx];
  bracket.lines = []; // Reset lines for the specific bracket

  nextTick(() => {
    const container = document.querySelector('.bracket');
    if (!container) return;

    const containerRect = container.getBoundingClientRect();
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
  });
};

// Call updateLines whenever the component is updated
watch(currentMatchIndex, () => {
  if (activeBracketIdx.value !== null) {
    updateLines(activeBracketIdx.value);
  }
});

// Function to remove a bracket
const removeBracket = (bracketIdx) => {
  if (confirm('Are you sure you want to delete this bracket?')) {
    brackets.value.splice(bracketIdx, 1);
  }
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
          <h2>{{ bracket.name }} ({{ bracket.type }})</h2>
          <button @click="toggleBracket(bracketIdx)" class="toggle-button">
            {{ expandedBrackets[bracketIdx] ? 'Hide Bracket' : 'Show Bracket' }}
          </button>
          <button @click="removeBracket(bracketIdx)" class="delete-button">Delete Bracket</button>

          <div v-if="expandedBrackets[bracketIdx]">
            <div class="bracket">
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
                <h3>Round {{ roundIdx + 1 }}</h3>

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
                          winner: match[0].completed && match[0].score >= match[1].score,
                          loser: match[0].completed && match[0].score < match[1].score
                        }"
                      >
                        {{ match[0].name || 'TBD' }} | {{ match[0].score }}
                      </span>
                      <hr />
                      <span
                        @click.stop="editParticipant(bracketIdx, roundIdx, matchIdx, 1)"
                        :class="{
                          editable: true,
                          winner: match[1].completed && match[1].score >= match[0].score,
                          loser: match[1].completed && match[1].score < match[0].score
                        }"
                      >
                        {{ match[1].name || 'TBD' }} | {{ match[1].score }}
                      </span>
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
                  :disabled="currentMatchIndex === brackets[bracketIdx].matches.flat().length - 1"
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
                      :disabled="currentMatch(bracketIdx).completed"
                    >
                      +
                    </button>

                    <div class="score-display">
                      {{ currentMatch(bracketIdx)[0]?.score.toString().padStart(3, '0') }}
                    </div>

                    <button
                      class="score-btn"
                      @click="decreaseScore(bracketIdx, 0)"
                      :disabled="currentMatch(bracketIdx).completed"
                    >
                      -
                    </button>

                    <span class="team-name">{{ currentMatch(bracketIdx)[0]?.name || 'TBD' }}</span>
                  </div>

                  <span class="vs">VS</span>

                  <!-- Right Team -->
                  <div class="team red">
                    <button
                      class="score-btn"
                      @click="increaseScore(bracketIdx, 1)"
                      :disabled="currentMatch(bracketIdx).completed"
                    >
                      +
                    </button>

                    <div class="score-display">
                      {{ currentMatch(bracketIdx)[1]?.score.toString().padStart(3, '0') }}
                    </div>

                    <button
                      class="score-btn"
                      @click="decreaseScore(bracketIdx, 1)"
                      :disabled="currentMatch(bracketIdx).completed"
                    >
                      -
                    </button>

                    <span class="team-name">{{ currentMatch(bracketIdx)[1]?.name || 'TBD' }}</span>
                  </div>
                </div>

                <!-- Dynamic Button/Label -->
                <div v-if="currentMatch(bracketIdx).completed" class="completed-text" @click="undoConcludeMatch(bracketIdx)">
                Completed
                </div>

                <button
                v-else
                @click="concludeMatch(bracketIdx)"
                class="match-over"
                >
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
            <label for="numberOfPlayers">Number of Participants:</label>
            <InputText v-model="numberOfPlayers" type="number" min="1" placeholder="Enter number of participants" />
          </div>

          <div class="p-field">
            <label for="matchType">Bracket Type:</label>
            <Select
              v-model="matchType"
              :options="bracketTypeOptions"
              placeholder="Select bracket type"
            />
          </div>

          <Button label="Create Bracket" class="p-button-success" @click="createBracket" />
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

  .create-button {
    background: #007bff;
    color: white;
    padding: 10px 15px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 1rem;
    font-weight: bold;
    margin-bottom: 20px;
  }

  .create-button:hover {
    background-color: #0056b3; /* Darker blue on hover */
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
          background-color: #d1d5db; /* Gray background */
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

        .match.highlight {
            border: 2px solid black; /* Border for Highlight */
            background-color: lightgray;  /* Background */
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); /* Subtle Shadow */
          }

.connection-lines {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  pointer-events: none;
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
</style>
