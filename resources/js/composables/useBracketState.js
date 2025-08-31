import { ref, computed } from 'vue';

export function useBracketState() {
  const bracketName = ref("");
  const numberOfPlayers = ref();
  const matchType = ref("");
  const selectedEvent = ref(null);
  const events = ref([]);
  const brackets = ref([]);
  const showDialog = ref(false);
  const currentMatchIndex = ref(0);
  const expandedBrackets = ref([]);
  const activeBracketIdx = ref(null);
  const showWinnerDialog = ref(false);
  const winnerMessage = ref("");
  const showConfirmDialog = ref(false);
  const pendingBracketIdx = ref(null);
  const showMissingFieldsDialog = ref(false);
  const showDeleteConfirmDialog = ref(false);
  const deleteBracketIdx = ref(null);

  const currentGameNumber = computed(() => `Game ${currentMatchIndex.value + 1}`);

  const currentWinnersMatchIndex = ref(0);
  const currentLosersMatchIndex = ref(0);
  const currentGrandFinalsIndex = ref(0);
  const activeBracketSection = ref('winners');

  const bracketTypeOptions = ["Single Elimination", "Double Elimination", "Round Robin"];

  const showRoundRobinMatchDialog = ref(false);
  const selectedRoundRobinMatch = ref(null);
  const selectedRoundRobinMatchData = ref(null);
  const showMatchUpdateConfirmDialog = ref(false);

  // Round Robin scoring configuration
  const roundRobinScoring = ref({
    win: 1,
    draw: 0.5,
    loss: 0
  });
  const showScoringConfigDialog = ref(false);

  return {
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
    currentGameNumber,
    currentWinnersMatchIndex,
    currentLosersMatchIndex,
    currentGrandFinalsIndex,
    activeBracketSection,
    bracketTypeOptions,
    showRoundRobinMatchDialog,
    selectedRoundRobinMatch,
    selectedRoundRobinMatchData,
    showMatchUpdateConfirmDialog,
    roundRobinScoring,
    showScoringConfigDialog,
  };
}


