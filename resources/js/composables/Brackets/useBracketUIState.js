import { ref, computed } from 'vue';

/**
 * Composable for managing bracket UI state
 * Separates UI concerns from business logic
 */
export function useBracketUIState() {
  // Dialog states
  const showDialog = ref(false);
  const showWinnerDialog = ref(false);
  const showConfirmDialog = ref(false);
  const showMissingFieldsDialog = ref(false);
  const showDeleteConfirmDialog = ref(false);
  const showSuccessDialog = ref(false);
  const showMatchEditorDialog = ref(false);
  const showMatchUpdateConfirmDialog = ref(false);
  const showGenericErrorDialog = ref(false);
  const showScoringConfigDialog = ref(false);

  // Messages
  const winnerMessage = ref('');
  const successMessage = ref('');
  const genericErrorMessage = ref('');

  // UI state for brackets
  const expandedBrackets = ref([]);
  const activeBracketIdx = ref(null);
  const pendingBracketIdx = ref(null);
  const deleteBracketIdx = ref(null);

  // Match navigation state
  const currentMatchIndex = ref(0);
  const currentWinnersMatchIndex = ref(0);
  const currentLosersMatchIndex = ref(0);
  const currentGrandFinalsIndex = ref(0);
  const activeBracketSection = ref('winners');

  // Match editor state
  const selectedMatch = ref(null);
  const selectedMatchData = ref(null);

  // View modes and filters
  const bracketViewModes = ref({});
  const bracketMatchFilters = ref({});

  // Scoring configuration
  const roundRobinScoring = ref({
    win: 3,
    draw: 1,
    loss: 0
  });
  const standingsRevision = ref(0);
  const tempScoringConfig = ref(null);

  // Bracket type options
  const bracketTypeOptions = ref([
    { label: 'Single Elimination', value: 'Single Elimination' },
    { label: 'Double Elimination', value: 'Double Elimination' },
    { label: 'Round Robin', value: 'Round Robin' }
  ]);

  // Helper computed properties
  const hasActiveDialog = computed(() => {
    return showDialog.value ||
           showWinnerDialog.value ||
           showConfirmDialog.value ||
           showMissingFieldsDialog.value ||
           showDeleteConfirmDialog.value ||
           showSuccessDialog.value ||
           showMatchEditorDialog.value ||
           showMatchUpdateConfirmDialog.value ||
           showGenericErrorDialog.value ||
           showScoringConfigDialog.value;
  });

  // Dialog control methods
  const closeAllDialogs = () => {
    showDialog.value = false;
    showWinnerDialog.value = false;
    showConfirmDialog.value = false;
    showMissingFieldsDialog.value = false;
    showDeleteConfirmDialog.value = false;
    showSuccessDialog.value = false;
    showMatchEditorDialog.value = false;
    showMatchUpdateConfirmDialog.value = false;
    showGenericErrorDialog.value = false;
    showScoringConfigDialog.value = false;
  };

  const resetMessages = () => {
    winnerMessage.value = '';
    successMessage.value = '';
    genericErrorMessage.value = '';
  };

  // Initialize bracket UI state
  const initializeBracketUIState = (count) => {
    expandedBrackets.value = new Array(count).fill(false);
    bracketViewModes.value = {};
    bracketMatchFilters.value = {};
    for (let i = 0; i < count; i++) {
      bracketViewModes.value[i] = 'bracket';
      bracketMatchFilters.value[i] = 'all';
    }
  };

  // Load scoring config from localStorage
  const loadScoringConfig = () => {
    const SCORING_CONFIG_KEY = 'roundRobinScoringConfig';
    const saved = localStorage.getItem(SCORING_CONFIG_KEY);
    if (saved) {
      try {
        const parsed = JSON.parse(saved);
        roundRobinScoring.value = {
          win: Number(parsed.win || 3),
          draw: Number(parsed.draw || 1),
          loss: Number(parsed.loss || 0)
        };
      } catch (e) {
        console.warn('Failed to parse scoring config:', e);
      }
    }
  };

  // Initialize scoring config on composable creation
  loadScoringConfig();

  return {
    // Dialog states
    showDialog,
    showWinnerDialog,
    showConfirmDialog,
    showMissingFieldsDialog,
    showDeleteConfirmDialog,
    showSuccessDialog,
    showMatchEditorDialog,
    showMatchUpdateConfirmDialog,
    showGenericErrorDialog,
    showScoringConfigDialog,

    // Messages
    winnerMessage,
    successMessage,
    genericErrorMessage,

    // UI state
    expandedBrackets,
    activeBracketIdx,
    pendingBracketIdx,
    deleteBracketIdx,

    // Match navigation
    currentMatchIndex,
    currentWinnersMatchIndex,
    currentLosersMatchIndex,
    currentGrandFinalsIndex,
    activeBracketSection,

    // Match editor
    selectedMatch,
    selectedMatchData,

    // View modes and filters
    bracketViewModes,
    bracketMatchFilters,

    // Scoring
    roundRobinScoring,
    standingsRevision,
    tempScoringConfig,

    // Options
    bracketTypeOptions,

    // Computed
    hasActiveDialog,

    // Methods
    closeAllDialogs,
    resetMessages,
    initializeBracketUIState,
    loadScoringConfig,
  };
}