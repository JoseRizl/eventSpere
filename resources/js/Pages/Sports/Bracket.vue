<script setup>
import { onMounted, computed, ref } from 'vue';
import Dialog from 'primevue/dialog';
import { format, parse, parseISO, isValid } from 'date-fns';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Button from 'primevue/button';
import SelectButton from 'primevue/selectbutton';
import { Link, usePage } from '@inertiajs/vue3';
import Skeleton from 'primevue/skeleton';
import SuccessDialog from '@/Components/SuccessDialog.vue';
import MatchesView from '@/Components/MatchesView.vue';
import BracketView from '@/Components/BracketView.vue';
import MatchEditorDialog from '@/Components/MatchEditorDialog.vue';
import ConfirmationDialog from '@/Components/ConfirmationDialog.vue';
import { useBracketState } from '@/composables/useBracketState.js';
import { useBracketActions } from '@/composables/useBracketActions.js';

const page = usePage();
const user = computed(() => page.props.auth.user);

const searchQuery = ref('');
const initialLoading = ref(true);
const state = useBracketState();
const {
  bracketName,
  numberOfPlayers,
  matchType,
  selectedEvent,
  events,
  brackets,
  showDialog,
  expandedBrackets,
  showConfirmDialog,
  showMissingFieldsDialog,
  showDeleteConfirmDialog,
  bracketTypeOptions,
  showSuccessDialog,
  successMessage,
  showMatchEditorDialog,
  bracketViewModes,
  bracketMatchFilters,
  selectedMatch,
  selectedMatchData,
  showGenericErrorDialog,
  genericErrorMessage,
  roundRobinScoring,
  showScoringConfigDialog,
  standingsRevision,
} = state;

const {
  openDialog,
  toggleBracket,
  createBracket,
  fetchBrackets,
  cancelEndMatch,
  confirmEndMatch,
  removeBracket,
  isFinalRound,
  confirmDeleteBracket,
  cancelDeleteBracket,
  getRoundRobinStandings,
  isRoundRobinConcluded,
  openMatchDialog,
  closeMatchEditorDialog,
  proceedWithMatchUpdate,
  openScoringConfigDialog,
  closeScoringConfigDialog,
  saveScoringConfig,
  setBracketViewMode,
  getAllMatches,
  openMatchEditorFromCard,
  setBracketMatchFilter
} = useBracketActions(state);

const confirmMatchUpdate = () => {
    // This now just triggers the confirmation inside MatchEditorDialog
    // The logic is handled there and it emits 'confirm' which is handled by proceedWithMatchUpdate
    // This function is passed as a prop to MatchEditorDialog
};

const matchStatusFilterOptions = ref([
    { label: 'All', value: 'all' },
    { label: 'Pending', value: 'pending' },
    { label: 'Completed', value: 'completed' }
]);

const filteredBrackets = computed(() => {
  if (!searchQuery.value) {
    return brackets.value;
  }
  const query = searchQuery.value.toLowerCase().trim();
  return brackets.value.filter(bracket => {
    const bracketNameMatch = bracket.name?.toLowerCase().includes(query);
    const eventNameMatch = bracket.event?.title?.toLowerCase().includes(query);
    return bracketNameMatch || eventNameMatch;
  });
});

const selectedBracket = computed(() => {
  if (selectedMatch.value) {
    return brackets.value[selectedMatch.value.bracketIdx];
  }
  return null;
});

const isMatchDataInvalid = computed(() => {
    if (!selectedMatchData.value || !selectedBracket.value) return true;

    const { status, player1Score, player2Score, player1Name, player2Name } = selectedMatchData.value;
    const bracketType = selectedBracket.value.type;

    // Check for ties in elimination brackets
    if (status === 'completed' && player1Score === player2Score && (bracketType === 'Single Elimination' || bracketType === 'Double Elimination')) {
        return true;
    }

    // Check for empty player names
    if (player1Name.trim() === '' || player2Name.trim() === '') {
        return true;
    }

    return false;
});

const isMultiDayEvent = computed(() => {
  if (selectedBracket.value?.event) {
      return selectedBracket.value.event.startDate !== selectedBracket.value.event.endDate;
  }
  return false;
});

const eventMinDate = computed(() => {
    if (selectedBracket.value?.event?.startDate) {
        return parseISO(selectedBracket.value.event.startDate);
    }
    return null;
});

const eventMaxDate = computed(() => {
    if (selectedBracket.value?.event?.endDate) {
        return parseISO(selectedBracket.value.event.endDate);
    }
    return null;
});

// Add onMounted hook to fetch brackets when component loads
onMounted(async () => {
  initialLoading.value = true;
  try {
    await fetchBrackets();
  } finally {
    initialLoading.value = false;
  }
});

</script>

<template>
    <div class="bracket-container">
        <h1 class="title">Brackets</h1>
        <div class="flex justify-between items-center mb-5">
          <div class="search-container">
            <div class="p-input-icon-left w-full">
                <i class="pi pi-search" />
                <InputText
                    v-model="searchQuery"
                    placeholder="Search brackets..."
                    class="w-full"
                />
            </div>
          </div>
          <button v-if="user?.role === 'Admin' || user?.role === 'SportsManager'" class="create-button" @click="openDialog">Create Bracket</button>
        </div>

      <!-- Loading Skeleton -->
      <div v-if="initialLoading">
        <div v-for="i in 2" :key="i" class="bracket-section">
            <div class="bracket-wrapper">
                <div class="bracket-header">
                    <h2><Skeleton width="10rem" height="1.5rem" class="mb-2" /></h2>
                    <div class="event-info">
                        <Skeleton width="8rem" height="1rem" />
                    </div>
                </div>
                <div class="bracket-controls">
                    <Skeleton width="8rem" height="2.5rem" />
                    <Skeleton width="8rem" height="2.5rem" />
                </div>
            </div>
        </div>
      </div>

      <!-- Display message when no brackets are created -->
      <div v-else-if="filteredBrackets.length === 0" class="no-brackets-message">
        <div v-if="searchQuery">
            <div class="icon-and-title">
                <i class="pi pi-search" style="font-size: 1.5rem; color: #007bff; margin-right: 10px;"></i>
                <h2 class="no-brackets-title">No Brackets Found</h2>
            </div>
            <p class="no-brackets-text">No brackets match your search criteria. Try adjusting your search terms.</p>
        </div>
        <div v-else>
            <div class="icon-and-title">
                <i class="pi pi-info-circle" style="font-size: 1.5rem; color: #007bff; margin-right: 10px;"></i>
                <h2 class="no-brackets-title">No Brackets Created Yet</h2>
            </div>
            <p class="no-brackets-text">Click the "Create Bracket" button above to start a new tournament.</p>
        </div>
      </div>

      <!-- Bracket Display Section -->
      <div v-else v-for="bracket in filteredBrackets" :key="bracket.id" class="bracket-section">
        <div class="bracket-wrapper">
          <div class="bracket-header">
            <h2>{{ bracket.name }} ({{ bracket.type }})</h2>
            <div class="event-info" v-if="bracket.event">
              <span class="event-label">Event:</span>
              <Link
                :href="route('event.details', { id: bracket.event_id })"
                class="event-title"
              >
                {{ bracket.event?.title || 'Loading...' }}
              </Link>
            </div>
          </div>
          <div class="bracket-controls">
            <button @click="toggleBracket(brackets.indexOf(bracket))" class="toggle-button">
              {{ expandedBrackets[brackets.indexOf(bracket)] ? 'Hide Bracket' : 'Show Bracket' }}
            </button>
            <button v-if="user?.role === 'Admin'" @click="removeBracket(brackets.indexOf(bracket))" class="delete-button">Delete Bracket</button>
          </div>

          <div v-if="expandedBrackets[brackets.indexOf(bracket)]" class="bracket-content-wrapper" :id="`bracket-content-${bracket.id}`">
            <div class="view-toggle-buttons">
                <Button
                    :label="'Bracket View'"
                    :class="['p-button-sm', bracketViewModes[brackets.indexOf(bracket)] !== 'matches' ? 'p-button-primary' : 'p-button-outlined']"
                    @click="setBracketViewMode(brackets.indexOf(bracket), 'bracket')"
                />
                <Button
                    :label="'Matches View'"
                    :class="['p-button-sm', bracketViewModes[brackets.indexOf(bracket)] === 'matches' ? 'p-button-primary' : 'p-button-outlined']"
                    @click="setBracketViewMode(brackets.indexOf(bracket), 'matches')"
                />
            </div>

            <!-- Bracket View -->
            <div v-show="bracketViewModes[brackets.indexOf(bracket)] !== 'matches'">
                <BracketView
                    :bracket="bracket"
                    :bracketIndex="brackets.indexOf(bracket)"
                    :user="user"
                    :standingsRevision="standingsRevision"
                    :isFinalRound="isFinalRound"
                    :openMatchDialog="openMatchDialog"
                    :getRoundRobinStandings="getRoundRobinStandings"
                    :isRoundRobinConcluded="isRoundRobinConcluded"
                    :openScoringConfigDialog="openScoringConfigDialog"
                />
            </div>

            <!-- Matches Card View -->
            <div v-if="bracketViewModes[brackets.indexOf(bracket)] === 'matches'">
                <div class="match-filters">
                    <SelectButton
                        :modelValue="bracketMatchFilters[brackets.indexOf(bracket)]"
                        @update:modelValue="val => setBracketMatchFilter(brackets.indexOf(bracket), val)"
                        :options="matchStatusFilterOptions"
                        optionLabel="label"
                        optionValue="value"
                        aria-labelledby="match-status-filter"
                    />
                </div>
                <MatchesView
                    :bracket="bracket"
                    :bracketIndex="brackets.indexOf(bracket)"
                    :user="user"
                    :filter="bracketMatchFilters[brackets.indexOf(bracket)]"
                    :openMatchEditorFromCard="openMatchEditorFromCard"
                    :isFinalRound="isFinalRound"
                />
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
            <label for="event">Select Event:</label>
            <Select
              v-model="selectedEvent"
              :options="events"
              optionLabel="title"
              placeholder="Select a sports event"
              filter
            />
          </div>

          <div class="p-field">
            <label for="numberOfPlayers">Number of Participants:</label>
            <InputText v-model="numberOfPlayers" type="number" min="1" placeholder="4, 8, 16, 32" />
          </div>

          <div class="p-field">
            <label for="matchType">Bracket Type:</label>
            <Select
              v-model="matchType"
              :options="bracketTypeOptions"
              placeholder="Select bracket type"
            />
          </div>

          <div class="button-container">
            <button class="modal-button-primary w-full" @click="createBracket">Create Bracket</button>
          </div>
        </div>
      </Dialog>

      <!-- End Match Confirmation Dialog -->
      <ConfirmationDialog
        v-model:show="showConfirmDialog"
        title="Confirm End Match"
        message="Are you sure you want to conclude this match?"
        confirmText="Yes"
        cancelText="No"
        confirmButtonClass="bg-green-600 hover:bg-green-700"
        @confirm="confirmEndMatch"
        @cancel="cancelEndMatch"
      />

      <!-- Missing Fields Dialog -->
      <ConfirmationDialog
        v-model:show="showMissingFieldsDialog"
        title="Missing Fields"
        message="Please fill out all fields."
        confirmText="OK"
        confirmButtonClass="bg-red-600 hover:bg-red-700"
        @confirm="showMissingFieldsDialog = false"
      />

      <!-- Delete Bracket Confirmation Dialog -->
      <ConfirmationDialog
        v-model:show="showDeleteConfirmDialog"
        title="Confirm Deletion"
        message="Are you sure you want to delete this bracket?"
        confirmText="Yes, Delete"
        cancelText="Cancel"
        confirmButtonClass="modal-button-danger"
        @confirm="confirmDeleteBracket"
        @cancel="cancelDeleteBracket"
      />

      <!-- Success Dialog -->
      <SuccessDialog
        v-model:show="showSuccessDialog"
        :message="successMessage"
      />

      <!-- Generic Error Dialog -->
      <ConfirmationDialog
        v-model:show="showGenericErrorDialog"
        title="Error"
        :message="genericErrorMessage"
        confirmText="OK"
        :show-cancel-button="false"
        confirmButtonClass="bg-red-600 hover:bg-red-700"
        @confirm="showGenericErrorDialog = false"
      />

      <!-- Scoring Configuration Dialog -->
      <Dialog v-model:visible="showScoringConfigDialog" header="Configure Scoring System" modal :style="{ width: '400px' }">
        <div class="scoring-config-dialog">
          <div class="scoring-option">
            <label>Win Points:</label>
            <InputText
              v-model="roundRobinScoring.win"
              type="number"
              step="0.5"
              min="0"
              placeholder="1"
            />
          </div>
          <div class="scoring-option">
            <label>Draw Points:</label>
            <InputText
              v-model="roundRobinScoring.draw"
              type="number"
              step="0.5"
              min="0"
              placeholder="0.5"
            />
          </div>
          <div class="scoring-option">
            <label>Loss Points:</label>
            <InputText
              v-model="roundRobinScoring.loss"
              type="number"
              step="0.5"
              min="0"
              placeholder="0"
            />
          </div>
          <div class="dialog-actions">
            <Button
              label="Cancel"
              @click="closeScoringConfigDialog"
              class="modal-button-secondary"
            />
            <Button
              label="Save"
              @click="saveScoringConfig"
              class="modal-button-primary"
            />
          </div>
        </div>
      </Dialog>

      <!-- Shared Match Editor Dialog -->
      <MatchEditorDialog
        v-model:show="showMatchEditorDialog"
        v-model:matchData="selectedMatchData"
        :bracket="selectedBracket"
        @confirm="proceedWithMatchUpdate"
        @update:show="val => !val && closeMatchEditorDialog()"
      />
    </div>
  </template>

<style scoped>
.search-container {
  display: flex;
  justify-content: flex-start;
  width: 100%;
  max-width: 400px;
}

.search-container .p-input-icon-left {
  position: relative;
  width: 100%;
}

.search-container .p-input-icon-left i {
  position: absolute;
  left: 0.75rem;
  top: 50%;
  transform: translateY(-50%);
  color: #6c757d;
}

.search-container .p-input-icon-left .p-inputtext {
  width: 100%;
  padding-left: 2.5rem;
}
</style>
