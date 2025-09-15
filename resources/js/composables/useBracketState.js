import { ref, computed } from 'vue';

const SCORING_CONFIG_KEY = 'roundRobinScoringConfig';

const loadScoringConfig = () => {
    const savedConfig = localStorage.getItem(SCORING_CONFIG_KEY);
    if (savedConfig) {
        try {
            const parsed = JSON.parse(savedConfig);
            return { win: Number(parsed.win) || 1, draw: Number(parsed.draw) || 0.5, loss: Number(parsed.loss) || 0 };
        } catch (e) {
            console.error("Failed to parse scoring config from localStorage", e);
        }
    }
    return { win: 1, draw: 0.5, loss: 0 };
};

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
  const showSuccessDialog = ref(false);
  const bracketViewModes = ref({});
  const successMessage = ref('');

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

  const roundRobinScoring = ref(loadScoringConfig());
  const showScoringConfigDialog = ref(false);
  const tempScoringConfig = ref(null);

  const standingsRevision = ref(0);

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
    showSuccessDialog,
    successMessage,
    bracketViewModes,
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
    tempScoringConfig,
    standingsRevision,
  };
}
