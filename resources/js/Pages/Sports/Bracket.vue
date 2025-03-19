<script setup>
import { ref } from "vue";
import Dialog from "primevue/dialog";
import InputText from "primevue/inputtext";
import Dropdown from "primevue/dropdown";
import Button from "primevue/button";
import { computed } from "vue";

// Reactive State
const bracketName = ref("");
const numberOfPlayers = ref();
const matchType = ref("");
const brackets = ref([]);
const showDialog = ref(false);
const currentMatchIndex = ref(0);

// Game Number Indicator
const currentGameNumber = computed(() => `Game ${currentMatchIndex.value + 1}`);

/*const calculateSpacing = (roundIdx, matchIdx) => {
  const baseSpacing = 80; // Increased base spacing for clarity
  const totalMatches = Math.pow(2, roundIdx);
  return (matchIdx % 2 === 0) ? baseSpacing / totalMatches : baseSpacing;
};*/

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

// Create Bracket
const createBracket = () => {
  if (!bracketName.value || !numberOfPlayers.value || !matchType.value) {
    alert("Please fill out all fields.");
    return;
  }

  const newBracket = generateBracket();
  brackets.value.push({ name: bracketName.value, type: matchType.value, matches: newBracket });
  showDialog.value = false;
};

const generateBracket = () => {
  const players = Array.from({ length: numberOfPlayers.value }, (_, i) => ({
    name: `Player ${i + 1}`,
    score: 0,
  }));

  let rounds = Math.log2(players.length);
  let generatedBracket = [];

  for (let i = 0; i < rounds; i++) {
    let roundMatches = [];

    for (let j = 0; j < players.length / Math.pow(2, i); j += 2) {
      roundMatches.push([
        { ...players[j], completed: false },
        { ...players[j + 1], completed: false },
      ]);
    }

    generatedBracket.push(roundMatches);
  }

  return generatedBracket;
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

// Update concludeMatch to use the helper function
const concludeMatch = (bracketIdx) => {
  const { roundIdx, matchIdx } = getRoundAndMatchIndices(bracketIdx, currentMatchIndex.value);
  const match = brackets.value[bracketIdx].matches[roundIdx][matchIdx];

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
const showNextMatch = (bracketIdx) => {
  const totalMatches = brackets.value[bracketIdx].matches.flat().length;
  if (currentMatchIndex.value < totalMatches - 1) {
    currentMatchIndex.value++;
  }
};

const showPreviousMatch = (bracketIdx) => {
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

</script>

<template>
    <div class="container">
        <div class="title">Ongoing Games</div>
      <!-- Create Bracket Button -->
      <button class="create-button" @click="openDialog">Create Bracket</button>

      <!-- Bracket Display Section -->
      <div v-for="(bracket, bracketIdx) in brackets" :key="bracketIdx" class="bracket-section">
        <div class="bracket-wrapper">
          <h2>{{ bracket.name }} ({{ bracket.type }})</h2>

          <div class="bracket">
            <div v-for="(round, roundIdx) in bracket.matches" :key="roundIdx"
            :class="['round', `round-${roundIdx + 1}`]">
              <h3>Round {{ roundIdx + 1 }}</h3>

              <!-- Matches Display -->
              <div
                v-for="(match, matchIdx) in round"
                :key="matchIdx"
                :class="['match', { 'highlight': isCurrentMatch(bracketIdx, roundIdx, matchIdx) }]"
                >
                <div class="player-box">
                    <span @click="editParticipant(bracketIdx, roundIdx, matchIdx, 0)" class="editable">
                    {{ match[0].name }} | {{ match[0].score }}
                    </span>
                    <hr />
                    <span @click="editParticipant(bracketIdx, roundIdx, matchIdx, 1)" class="editable">
                    {{ match[1].name }} | {{ match[1].score }}
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

      <span class="team-name">{{ currentMatch(bracketIdx)[0]?.name }}</span>
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

      <span class="team-name">{{ currentMatch(bracketIdx)[1]?.name }}</span>
    </div>
</div>

    <!-- Dynamic Button/Label -->
    <div v-if="currentMatch(bracketIdx).completed" class="completed-text">
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

      <!-- Dialog for Bracket Setup -->
      <Dialog v-model:visible="showDialog" header="Bracket Setup" modal :style="{ width: '400px' }">
        <div class="dialog-content">
          <div class="p-field">
            <label for="bracketName">Bracket Name:</label>
            <InputText v-model="bracketName" placeholder="Enter bracket name" />
          </div>

          <div class="p-field">
            <label for="numberOfPlayers">Number of Participants:</label>
            <Dropdown
              v-model="numberOfPlayers"
              :options="participantOptions"
              placeholder="Select participants"
            />
          </div>

          <div class="p-field">
            <label for="matchType">Bracket Type:</label>
            <Dropdown
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
