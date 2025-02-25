<template>
    <div class="sports-event">
      <h1 class="title">Ongoing Games</h1>

      <div class="content">
        <!-- Matches Section -->
        <div class="matches">
          <div v-for="match in matches" :key="match.id" class="match-card">
            <button class="match-title">{{ match.name }}</button>

            <div class="match-content">
              <!-- Left Team -->
              <div class="team blue">
                <button @click="increaseScore(match.id, 'team')" :disabled="match.completed">+</button>
                <span class="score">{{ match.team.score.toString().padStart(3, '0') }}</span>
                <button @click="decreaseScore(match.id, 'team')" :disabled="match.completed">-</button>
                <span class="team-name">{{ match.team.name }}</span>
              </div>

              <span class="vs">VS</span>

              <!-- Right Team -->
              <div class="team red">
                <button @click="increaseScore(match.id, 'team2')" :disabled="match.completed">+</button>
                <span class="score">{{ match.team2.score.toString().padStart(3, '0') }}</span>
                <button @click="decreaseScore(match.id, 'team2')" :disabled="match.completed">-</button>
                <span class="team-name">{{ match.team2.name }}</span>
              </div>
            </div>

            <!-- Match Over Button -->
            <!-- Match Over Button and Winner Display -->
            <button class="match-over" @click="completeMatch(match.id)" v-if="!match.completed">Completed</button>
            <span v-else class="completed-text">
                Match Completed - Winner: {{ match.winner }}
            </span>
          </div>
        </div>

        <!-- Rankings Section -->
        <div class="rankings">
          <div v-for="(ranking, index) in rankings" :key="index" class="ranking-card">
            <h2>{{ ranking.tournament }}</h2>
            <ul>
              <li v-for="(team, idx) in ranking.teams" :key="idx">
                {{ idx + 1 }}. {{ team.name }} ({{ team.wins || 0 }} wins)
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </template>

  <script setup>
  import { onMounted, computed } from "vue";
  import { useMatchStore } from "@/stores/matchStore";

  const store = useMatchStore();
  const matches = computed(() => store.matches);

  // Rankings logic: Groups teams by tournament and sorts them by wins
  const rankings = computed(() => {
    const tournamentRankings = {};

    matches.value.forEach((match) => {
      if (!tournamentRankings[match.name]) {
        tournamentRankings[match.name] = [];
      }
      tournamentRankings[match.name].push(match.team, match.team2);
    });

    return Object.entries(tournamentRankings).map(([tournament, teams]) => ({
      tournament,
      teams: teams.sort((a, b) => (b.wins || 0) - (a.wins || 0)), // Sort by wins (highest first)
    }));
  });

  onMounted(() => {
    store.fetchMatches();
  });

  const increaseScore = (id, team) => {
    const match = matches.value.find((m) => m.id === id);
    if (match && !match.completed) {
      store.updateScore(id, team, match[team].score + 1);
    }
  };

  const decreaseScore = (id, team) => {
    const match = matches.value.find((m) => m.id === id);
    if (match && match[team].score > 0 && !match.completed) {
      store.updateScore(id, team, match[team].score - 1);
    }
  };

  // Mark match as completed
  const completeMatch = (id) => {
    store.completeMatch(id);
  };
  </script>

  <style scoped>
  /* Overall Layout */
  .sports-event {
    display: flex;
    flex-direction: column;
    align-items: center;
    font-family: Arial, sans-serif;
  }

  .title {
    font-size: 24px;
    font-weight: bold;
    margin-bottom: 20px;
  }

  /* Main Content: Matches on the Left, Rankings on the Right */
  .content {
    display: flex;
    gap: 30px;
    align-items: flex-start;
  }

  /* Matches Section */
  .matches {
    display: flex;
    flex-direction: column;
    gap: 20px;
  }

  .match-card {
    background: #ddd;
    padding: 15px;
    border-radius: 10px;
    text-align: center;
    width: 400px;
  }

  .match-title {
    background: #4a90e2;
    color: white;
    padding: 5px 10px;
    border: none;
    font-weight: bold;
    border-radius: 5px;
  }

  /* Match Over Button */
  .match-over {
    background: green;
    color: white;
    padding: 5px 10px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
  }

  .match-over:hover {
    background: darkgreen;
  }

  /* Completed Match Text */
  .completed-text {
    font-size: 14px;
    color: gray;
    font-weight: bold;
  }

  /* Teams */
  .match-content {
    display: flex;
    justify-content: space-around;
    align-items: center;
    margin: 10px 0;
  }

  .team {
    display: flex;
    flex-direction: column;
    align-items: center;
  }

  .blue {
    background: blue;
    color: white;
    padding: 10px;
    border-radius: 5px;
  }

  .red {
    background: red;
    color: white;
    padding: 10px;
    border-radius: 5px;
  }

  /* Score Styling */
  .score {
    font-size: 24px;
    font-weight: bold;
    margin: 5px 0;
    color: black;
    background: white;
    padding: 5px 10px;
    border-radius: 5px;
  }

  .team-name {
    font-size: 14px;
    margin-top: 5px;
    font-weight: bold;
    color: black;
  }

  .vs {
    font-size: 18px;
    font-weight: bold;
  }

  /* Rankings Section */
  .rankings {
    display: flex;
    flex-direction: column;
    gap: 20px;
  }

  .ranking-card {
    background: white;
    border: 1px solid #ccc;
    padding: 10px;
    width: 200px;
  }

  .ranking-card h2 {
    font-size: 16px;
    font-weight: bold;
    text-align: center;
    margin-bottom: 5px;
  }

  .ranking-card ul {
    padding: 0;
    list-style-type: none;
  }

  .ranking-card li {
    font-size: 14px;
    border-bottom: 1px solid #ddd;
    padding: 2px 0;
  }
  </style>
