<script setup>
import { ref } from "vue";

const numberOfPlayers = ref();
const playerNames = ref("");
const matchType = ref("double");
const orderType = ref("randomized");
const bracket = ref(null);
const winners = ref({});

const shuffleArray = (arr) => {
  let currentIndex = arr.length,
    randomIndex;
  while (currentIndex !== 0) {
    randomIndex = Math.floor(Math.random() * currentIndex);
    currentIndex--;
    [arr[currentIndex], arr[randomIndex]] = [arr[randomIndex], arr[currentIndex]];
  }
  return arr;
};

const generateBracket = () => {
  const players = playerNames.value.split("\n").map((p) => p.trim()).filter((p) => p);
  if (![4, 8, 16, 32].includes(parseInt(numberOfPlayers.value))) {
    alert("Please enter a valid number of players (4, 8, 16, 32)");
    return;
  }
  if (players.length !== parseInt(numberOfPlayers.value)) {
    alert("Number of players does not match the names entered.");
    return;
  }
  let matchArray = orderType.value === "randomized" ? shuffleArray(players) : players;
  let rounds = Math.log2(players.length);
  let generatedBracket = [];

  for (let i = 0; i < rounds; i++) {
    let splits = Math.pow(2, i);
    let roundMatches = [];
    for (let j = 0; j < players.length / splits; j += 2) {
      roundMatches.push([matchArray[j * splits], matchArray[(j + 1) * splits]]);
    }
    generatedBracket.push(roundMatches);
  }
  bracket.value = generatedBracket;
};
</script>

<template>
    <div class="container">
      <aside class="options">
        <h1>PureBracket</h1>
        <h3>Step 1: Enter the number of players</h3>
        <input v-model="numberOfPlayers" type="number" />

        <h3>Step 2: Enter player's names</h3>
        <textarea v-model="playerNames" rows="6"></textarea>

        <h3>Step 3: Select options</h3>
        <div>
          <label><input type="radio" v-model="matchType" value="single" /> Single Elimination</label>
          <label><input type="radio" v-model="matchType" value="double" /> Double Elimination</label>
        </div>
        <div>
          <label><input type="radio" v-model="orderType" value="ordered" /> Ordered</label>
          <label><input type="radio" v-model="orderType" value="randomized" /> Randomized</label>
        </div>

        <h3>Step 4: Generate the bracket!</h3>
        <button @click="generateBracket">Generate</button>

        <section id="projectInfoSection">
          <h3>Project Info</h3>
          <div id="projectInfoFlex">
            <p>
              PureBracket is released under the terms of the MIT Open Source License.
              Check out the repo on
              <a href="https://github.com/tmose1106/PureBracket">GitHub</a>.
            </p>
            <img :src="'/github.svg'" alt="GitHub logo">
          </div>
        </section>
      </aside>

      <main class="bracket">
        <div v-if="bracket">
          <div v-for="(round, index) in bracket" :key="index" class="round">
            <h3>Round {{ index + 1 }}</h3>
            <div v-for="(match, idx) in round" :key="idx" class="match">
              <span>{{ match[0] }}</span>
              <select v-model="winners[`${index}-${idx}`]">
                <option :value="match[0]">{{ match[0] }}</option>
                <option :value="match[1]">{{ match[1] }}</option>
              </select>
              <span>{{ match[1] }}</span>
            </div>
          </div>
        </div>
      </main>
    </div>
  </template>

  <style scoped>
  /* Main container */
  .container {
    display: flex;
    height: 100vh;
    z-index: 0;
  }

  /* Sidebar Options */
  .options {
    display: flex;
    flex-direction: column;
    justify-content: space-between; /* Pushes project info to bottom */
    width: 30%;
    padding: 20px;
    background: #1e293b;
    color: white;
    z-index: 0;
  }

  .options h1 {
    font-size: 1.5rem;
    margin-bottom: 15px;
  }

  .options h3 {
    margin-bottom: 8px;
  }

  /* Input fields */
  .options input,
  .options textarea {
    width: 100%;
    margin-bottom: 10px;
    padding: 8px;
    border: 1px solid #ccc;
    border-radius: 5px;
    color: black;
    background: white;
  }

  /* Ensure radio buttons align properly */
  .options div {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    align-items: center;
    margin-bottom: 10px;
  }

  .options label {
    display: flex;
    align-items: center;
    gap: 5px;
    margin-bottom: 5px;
    white-space: nowrap;
  }

  .options input[type="radio"] {
    margin: 0;
  }

  /* Bracket Section */
  .bracket {
    width: 70%;
    padding: 20px;
    background: #d1d5db;
    display: flex;
    gap: 20px; /* Space between rounds */
    overflow-x: auto; /* Allow horizontal scrolling if needed */
  }

  /* Round Styling */
  .round {
    display: flex;
    flex-direction: column;
    gap: 20px; /* Space between matches */
  }

  .round h3 {
    text-align: center;
    margin-bottom: 10px;
  }

  /* Match Styling */
  .match {
    background: white;
    padding: 10px;
    border-radius: 5px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    min-width: 200px; /* Ensure matches have enough width */
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  }

  .match span {
    flex: 1;
    text-align: center;
  }

  .match select {
    margin: 0 10px;
  }

  /* Project Info Section */
  #projectInfoSection {
    margin-top: auto; /* Push to bottom */
    padding-top: 10px;
  }

  #projectInfoFlex {
    display: flex;
    align-items: center;
    gap: 10px;
  }

  /* Make GitHub link blue */
  #projectInfoSection a {
    color: #007bff;
    text-decoration: none;
    font-weight: bold;
  }

  #projectInfoSection a:hover {
    text-decoration: underline;
  }

  /* Blue button styling */
  button {
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

  button:hover {
    background: #0056b3;
  }
  </style>
