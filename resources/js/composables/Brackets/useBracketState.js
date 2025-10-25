import { ref, computed } from 'vue';

/**
 * Composable for managing bracket data state
 * This contains only the data/business logic state, not UI state
 * UI state is managed separately in useBracketUIState
 */

// Form data for creating brackets
const bracketName = ref("");
const numberOfPlayers = ref();
const matchType = ref("");
const selectedEvent = ref(null);
const includeThirdPlace = ref(false);
const allowDraws = ref(false);

// Data collections
const events = ref([]);
const brackets = ref([]);

// Computed properties
const currentGameNumber = computed(() => `Game ${1}`);

export function useBracketState() {
  return {
    // Form data
    bracketName,
    numberOfPlayers,
    matchType,
    selectedEvent,
    includeThirdPlace,
    allowDraws,

    // Data collections
    events,
    brackets,

    // Computed
    currentGameNumber,
  };
}
