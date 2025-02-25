import { defineStore } from "pinia";
import { ref, computed } from "vue";

export const useMatchStore = defineStore("matchStore", () => {
  const matches = ref([]);

  // Fetch matches from db.json
  const fetchMatches = async () => {
    try {
      const response = await fetch("http://localhost:3000/matches");
      matches.value = await response.json();
    } catch (error) {
      console.error("Error fetching matches:", error);
    }
  };

  // Determine winner
  const determineWinner = (match) => {
    if (match.team.score > match.team2.score) return "team";
    if (match.team2.score > match.team.score) return "team2";
    return null; // Tie
  };

  // Mark match as completed and update wins
  // Mark match as completed and store the final winner
const completeMatch = async (matchId) => {
    const matchIndex = matches.value.findIndex((m) => m.id === matchId);
    if (matchIndex === -1) return;

    const match = matches.value[matchIndex];

    if (match.completed) return; // Prevent duplicate completion

    const winner = determineWinner(match);

    if (winner) {
      match[winner].wins = (match[winner].wins || 0) + 1;
      match.winner = match[winner].name; // Store the winner's name
    } else {
      match.winner = "Draw"; // Explicitly set draw if no winner
    }

    match.completed = true; // Mark as completed

    try {
      await fetch(`http://localhost:3000/matches/${matchId}`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          completed: true,
          team: match.team,
          team2: match.team2,
          winner: match.winner, // Store winner in db.json
        }),
      });
    } catch (error) {
      console.error("Error marking match as complete:", error);
    }
  };

  // Update Score (Only if match isn't completed)
  const updateScore = async (matchId, team, newScore) => {
    const matchIndex = matches.value.findIndex((m) => m.id === matchId);
    if (matchIndex === -1) return;

    const match = matches.value[matchIndex];
    if (match.completed) return; // Prevent changes to completed matches

    match[team].score = newScore;

    try {
      await fetch(`http://localhost:3000/matches/${matchId}`, {
        method: "PATCH",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ [team]: match[team] }),
      });
    } catch (error) {
      console.error("Error updating score:", error);
    }
  };

  return {
    matches,
    fetchMatches,
    updateScore,
    completeMatch,
  };
});
