<script setup>
import { ref } from "vue";
import Dialog from "primevue/dialog";
import InputText from "primevue/inputtext";
import Dropdown from "primevue/dropdown";
import Button from "primevue/button";

// Reactive State
const bracketName = ref("");
const numberOfPlayers = ref();
const matchType = ref("");
const brackets = ref([]);
const showDialog = ref(false);
const currentMatchIndex = ref(0);

const calculateSpacing = (roundIdx, matchIdx) => {
  const baseSpacing = 80; // Increased base spacing for clarity
  const totalMatches = Math.pow(2, roundIdx);
  return (matchIdx % 2 === 0) ? baseSpacing / totalMatches : baseSpacing;
};


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

// Conclude Match Logic
const concludeMatch = (bracketIdx, roundIdx, matchIdx) => {
  const match = brackets.value[bracketIdx].matches[roundIdx][matchIdx];
  const winner = match[0].score >= match[1].score ? match[0] : match[1];

  match.completed = true;

  if (brackets.value[bracketIdx].matches[roundIdx + 1]) {
    const nextRoundIdx = Math.floor(matchIdx / 2);

    // Correct Position Logic
    const nextMatchPos = matchIdx % 2 === 0 ? 0 : 1;

    brackets.value[bracketIdx].matches[roundIdx + 1][nextRoundIdx][nextMatchPos] = {
      ...winner,
      score: 0,
      completed: false
    };
  } else {
    alert(`Winner: ${winner.name}`);
  }
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
  const flatBracket = brackets.value[bracketIdx].matches.flat();
  return flatBracket[currentMatchIndex.value];
};

// Improved Spacing Logic
const spacing = (roundIdx) => 40 * Math.pow(2, roundIdx);
</script>

<template>
    <div class="container">
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
                class="match"
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
            <div v-if="currentMatch(bracketIdx)" class="match-card centered">
              <div class="match-content">
                <!-- Left Team -->
                <div class="team blue">
                  <button
                    class="score-btn"
                    @click="increaseScore(bracketIdx, 0, currentMatchIndex, 0)"
                    :disabled="currentMatch(bracketIdx).completed"
                  >
                    +
                  </button>
                  <span class="score">{{ currentMatch(bracketIdx)[0].score }}</span>
                  <button
                    class="score-btn"
                    @click="decreaseScore(bracketIdx, 0, currentMatchIndex, 0)"
                    :disabled="currentMatch(bracketIdx).completed"
                  >
                    -
                  </button>
                  <span class="team-name">{{ currentMatch(bracketIdx)[0].name }}</span>
                </div>

                <span class="vs">VS</span>

                <!-- Right Team -->
                <div class="team red">
                  <button
                    class="score-btn"
                    @click="increaseScore(bracketIdx, 0, currentMatchIndex, 1)"
                    :disabled="currentMatch(bracketIdx).completed"
                  >
                    +
                  </button>
                  <span class="score">{{ currentMatch(bracketIdx)[1].score }}</span>
                  <button
                    class="score-btn"
                    @click="decreaseScore(bracketIdx, 0, currentMatchIndex, 1)"
                    :disabled="currentMatch(bracketIdx).completed"
                  >
                    -
                  </button>
                  <span class="team-name">{{ currentMatch(bracketIdx)[1].name }}</span>
                </div>
              </div>

              <!-- Conclude Match Button -->
              <button
                class="match-over"
                @click="concludeMatch(bracketIdx, 0, currentMatchIndex)"
                v-if="!currentMatch(bracketIdx).completed"
              >
                Completed
              </button>

              <!-- Completed Match Display -->
              <span v-else class="completed-text">
                Match Completed - Winner:
                {{ currentMatch(bracketIdx)[0].score >= currentMatch(bracketIdx)[1].score
                ? currentMatch(bracketIdx)[0].name
                : currentMatch(bracketIdx)[1].name }}
              </span>
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

<style scoped>
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

/* Bracket Wrapper */
.bracket-wrapper {
  background-color: #f0f0f0;
  padding: 15px;
  border-radius: 12px;
  margin-bottom: 20px;
}

/* Bracket Layout */
.bracket {
  display: flex;
  gap: 40px;
  align-items: flex-start;
  justify-content: center;
}

.round {
  display: flex;
  flex-direction: column;
  align-items: center; /* Added for better visual balance */
  gap: 20px; /* Increased gap for improved spacing */
  position: relative;
}

/* Match Box Design */
.match {
  background: #fff;
  border: 2px solid #007bff;
  padding: 10px;
  border-radius: 8px;
  text-align: center;
  width: 150px; /* Match size */
  margin: 10px 0px; /* Spacing between matches */
  box-shadow: 2px 2px 5px rgba(0, 0, 0, 0.1);
}

/* New Match Box Content Layout */
.match-content {
  display: flex;
  flex-direction: column;
  gap: 5px;
  text-align: left;
  padding: 5px 10px;
  border-top: 2px solid #007bff;
}

.match-content .player-box {
  display: flex;
  flex-direction: column;
  gap: 4px;
  text-align: center;
  padding: 5px;
  font-weight: bold;
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

/* Match Lines */
.match::before,
.match::after {
  content: "";
  position: absolute;
  width: 40px; /* Ensure the lines extend properly */
  height: 2px; /* Consistent thickness */
  background-color: #007bff;
  display: block; /* Ensure lines are visible */
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
  top: -25px; /* Line leads upward */
}

.round .match:nth-child(odd)::before {
  bottom: -25px; /* Line leads downward */
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
}

.round {
  display: flex;
  flex-direction: column;
  gap: 20px; /* Creates balanced gaps in early rounds */
  position: relative;
}

.match {
  position: relative;
  margin: 10px 0; /* Consistent spacing */
}

.round-1 .match { margin-top: 0; }
.round-2 .match { margin-top: 40px; }
.round-3 .match { margin-top: 80px; }

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

/* Matchup Box */
.match-card {
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

/* Score Display */
.score-display {
  background-color: #e3f2fd;
  border-radius: 5px;
  padding: 3px 8px;
  font-weight: bold;
  display: inline-block;
}

/* Match Over Button */
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
</style>
