<script setup>
import { ref } from "vue";
import Dialog from "primevue/dialog";
import InputText from "primevue/inputtext";
import Dropdown from "primevue/dropdown";
import Button from "primevue/button";

const bracketName = ref("");
const numberOfPlayers = ref();
const matchType = ref("");
const bracket = ref([]);
const showDialog = ref(false); // Dialog visibility control

// Options for participants and bracket type
const participantOptions = [4, 8, 16, 32];
const bracketTypeOptions = ["single", "double"];

// Show dialog and reset fields
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

  generateBracket();
  showDialog.value = false; // Close dialog after setup
};

// Generate bracket with editable placeholders
const generateBracket = () => {
  const players = Array.from({ length: numberOfPlayers.value }, (_, i) => ({
    name: `Player ${i + 1}`,
    score: 0, // Initial score
  }));

  let rounds = Math.log2(players.length);
  let generatedBracket = [];

  for (let i = 0; i < rounds; i++) {
    let roundMatches = [];
    for (let j = 0; j < players.length / Math.pow(2, i); j += 2) {
      roundMatches.push([
        { ...players[j], completed: false }, // Track match status
        { ...players[j + 1], completed: false },
      ]);
    }
    generatedBracket.push(roundMatches);
  }

  bracket.value = generatedBracket;
};

// Edit participant names in place
const editParticipant = (roundIdx, matchIdx, teamIdx) => {
  const newName = prompt("Enter new participant name:");
  if (newName) {
    bracket.value[roundIdx][matchIdx][teamIdx].name = newName;
  }
};

// Score Controls
const increaseScore = (roundIdx, matchIdx, teamIdx) => {
  if (!bracket.value[roundIdx][matchIdx].completed) {
    bracket.value[roundIdx][matchIdx][teamIdx].score++;
  }
};

const decreaseScore = (roundIdx, matchIdx, teamIdx) => {
  if (!bracket.value[roundIdx][matchIdx].completed) {
    if (bracket.value[roundIdx][matchIdx][teamIdx].score > 0) {
      bracket.value[roundIdx][matchIdx][teamIdx].score--;
    }
  }
};

// Conclude Match Logic
const concludeMatch = (roundIdx, matchIdx) => {
  const match = bracket.value[roundIdx][matchIdx];
  const winner = match[0].score >= match[1].score ? match[0] : match[1];

  match.completed = true; // Lock match scores after conclusion

  if (bracket.value[roundIdx + 1]) {
    bracket.value[roundIdx + 1][Math.floor(matchIdx / 2)][matchIdx % 2] = {
      ...winner,
      score: 0, // Reset score when moving to the next round
      completed: false
    };
  } else {
    alert(`Winner: ${winner.name}`);
  }
};
</script>

<template>
  <div class="container">
    <aside class="options">
      <h1>PureBracket</h1>

      <h3>Step 1: Create the Bracket</h3>
      <button @click="openDialog">Create Bracket</button>

      <h3 v-if="bracketName">Bracket Name: {{ bracketName }}</h3>
      <h3 v-if="matchType">Type: {{ matchType }}</h3>

      <section id="projectInfoSection">
        <h3>Project Info</h3>
        <p>
          PureBracket is released under the MIT Open Source License. Check out the repo on
          <a href="https://github.com/tmose1106/PureBracket">GitHub</a>.
        </p>
      </section>
    </aside>

    <!-- Bracket Display -->
    <main class="bracket" v-if="bracket.length">
      <div v-for="(round, index) in bracket" :key="index" class="round">
        <h3>Round {{ index + 1 }}</h3>

        <div v-for="(match, idx) in round" :key="idx" class="match">
          <!-- Player 1 -->
          <div class="player">
            <button
              @click="increaseScore(index, idx, 0)"
              :disabled="match.completed"
            >+</button>
            <span @click="editParticipant(index, idx, 0)" class="editable">
              {{ match[0].name }} ({{ match[0].score }})
            </span>
            <button
              @click="decreaseScore(index, idx, 0)"
              :disabled="match.completed"
            >-</button>
          </div>

          <!-- Score and Conclude Section -->
          <div class="score-box">
            <span>Score: {{ match[0].score }} - {{ match[1].score }}</span>
            <button
              class="conclude-btn"
              @click="concludeMatch(index, idx)"
              :disabled="match.completed"
            >
              {{ match.completed ? "Finished" : "End" }}
            </button>
          </div>

          <!-- Player 2 -->
          <div class="player">
            <button
              @click="increaseScore(index, idx, 1)"
              :disabled="match.completed"
            >+</button>
            <span @click="editParticipant(index, idx, 1)" class="editable">
              {{ match[1].name }} ({{ match[1].score }})
            </span>
            <button
              @click="decreaseScore(index, idx, 1)"
              :disabled="match.completed"
            >-</button>
          </div>
        </div>
      </div>
    </main>

    <!-- PrimeVue Dialog for Setup -->
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

        <Button
          label="Create Bracket"
          class="p-button-success"
          @click="createBracket"
        />
      </div>
    </Dialog>
  </div>
</template>

<style scoped>
/* Main container */
.container {
  display: flex;
  height: 100vh;
  z-index: 0;
}

/* Sidebar */
.options {
  width: 30%;
  padding: 20px;
  background: #1e293b;
  color: white;
}

.options h1 {
  margin-bottom: 15px;
}

.options button {
  background: #007bff;
  color: white;
  padding: 10px 15px;
  border: none;
  border-radius: 5px;
  cursor: pointer;
  font-size: 1rem;
  font-weight: bold;
  transition: background 0.3s ease-in-out;
}

.options button:hover {
  background: #0056b3;
}

/* Bracket Section */
.bracket {
  width: 70%;
  padding: 20px;
  background: #d1d5db;
  display: flex;
  gap: 30px;
  overflow-x: auto;
}

.round {
  display: flex;
  flex-direction: column;
  gap: 20px;
  align-items: center;
  position: relative;
}

.match {
  background: white;
  padding: 10px;
  border-radius: 5px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  min-width: 200px;
  position: relative;
  border: 2px solid #007bff;
}

.match .vs {
  font-weight: bold;
  color: #1e293b;
}

.editable {
  cursor: pointer;
  color: #007bff;
  text-decoration: underline;
}

/* Connecting lines */
.round::before {
  content: "";
  position: absolute;
  top: 0;
  bottom: 0;
  left: -15px;
  width: 2px;
  background: #007bff;
}

/* Dialog Content */
.dialog-content {
  display: flex;
  flex-direction: column;
  gap: 15px;
}

.p-field label {
  font-weight: bold;
}

/* Score Box */
.score-box {
  background: #f3f4f6;
  padding: 5px 10px;
  border: 2px solid #007bff;
  border-radius: 5px;
  text-align: center;
  margin: 5px 0;
}

.score-box button {
  background: #ff9800;
  border: none;
  color: white;
  padding: 3px 8px;
  border-radius: 4px;
  cursor: pointer;
  font-weight: bold;
  margin-top: 5px;
}

.score-box button:hover {
  background: #f57c00;
}
</style>
