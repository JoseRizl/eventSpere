<script setup>
import { onMounted, computed, ref, watch } from 'vue';
import LoadingSpinner from '@/Components/LoadingSpinner.vue';
import { usePage } from '@inertiajs/vue3';
import BracketCard from '@/Components/Brackets/BracketCard.vue';
import MatchEditorDialog from '@/Components/Brackets/MatchEditorDialog.vue';
import { useBracketState } from '@/composables/Brackets/useBracketState.js';
import { useBracketActions } from '@/composables/Brackets/useBracketActions.js';

const page = usePage();
const user = computed(() => page.props.auth.user);

const searchQuery = ref('');
const showFilters = ref(false);

// Filtering and Sorting State
const filterByEvents = ref([]);
const filterByTypes = ref([]);
const filterByStatus = ref([]);
const sortBy = ref({ name: 'Creation Date (Newest)', code: 'created_desc' });

const sortOptions = ref([
    { name: 'Creation Date (Newest)', code: 'created_desc' },
    { name: 'Creation Date (Oldest)', code: 'created_asc' },
    { name: 'Bracket Name (A-Z)', code: 'name_asc' },
    { name: 'Bracket Name (Z-A)', code: 'name_desc' },
    { name: 'Event Name (A-Z)', code: 'event_asc' },
    { name: 'Event Name (Z-A)', code: 'event_desc' },
]);

const uniqueEvents = computed(() => {
    const seen = new Set();
    return brackets.value
        .map(b => b.event)
        .filter(e => {
            if (!e || seen.has(e.id)) return false;
            seen.add(e.id);
            return true;
        })
        .sort((a, b) => a.title.localeCompare(b.title));
});

const areFiltersActive = computed(() => {
    return filterByEvents.value.length > 0 ||
           filterByTypes.value.length > 0 ||
           filterByStatus.value.length > 0;
});

const clearFilters = () => {
    filterByEvents.value = [];
    filterByTypes.value = [];
    filterByStatus.value = [];
};

const initialLoading = ref(true);

// Data state only
const bracketState = useBracketState();
const {
  bracketName,
  numberOfPlayers,
  matchType,
  selectedEvent,
  includeThirdPlace,
  events,
  brackets,
} = bracketState;

// Actions + UI state (UI state is now included in useBracketActions)
const {
  // Actions
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
  toggleConsolationMatch,
  toggleAllowDraws,
  confirmToggleDraws,
  cancelToggleDraws,
  confirmToggleConsolation,
  cancelToggleConsolation,
  openTiebreakerDialog,
  closeTiebreakerDialog,
  saveTiebreakers,
  dismissTiebreakerNotice,
  openScoringConfigDialog,
  closeScoringConfigDialog,
  saveScoringConfig,
  setBracketViewMode,
  getAllMatches,
  openMatchEditorFromCard,
  setBracketMatchFilter,
  getBracketStats,
  getBracketTypeClass,

  // UI State (automatically included from useBracketActions)
  showDialog,
  expandedBrackets,
  pendingBracketIdx,
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
  showToggleConsolationDialog,
  showScoringConfigDialog,
  showToggleDrawsDialog,
  showTiebreakerDialog,
  dismissedTiebreakerNotices,
  standingsRevision,
  isCreatingBracket,
  isDeletingBracket,
  isUpdatingMatch,
} = useBracketActions(bracketState);

const confirmMatchUpdate = () => {
    // This now just triggers the confirmation inside MatchEditorDialog
    // The logic is handled there and it emits 'confirm' which is handled by proceedWithMatchUpdate
    // This function is passed as a prop to MatchEditorDialog
};

// Tiebreaker helpers
const tiedPlayersData = ref([]);

const getTiedPlayers = (bracketIdx) => {
    const bracket = brackets.value[bracketIdx];
    if (!bracket || bracket.type !== 'Round Robin') return [];

    if (!bracket.matches || !Array.isArray(bracket.matches)) return [];

    // Calculate stats for all players
    const playerStatsMap = new Map();

    bracket.matches.forEach(round => {
        if (!Array.isArray(round)) return;
        round.forEach(match => {
            if (!match.players || !Array.isArray(match.players)) return;
            if (match.status !== 'completed') return;

            match.players.forEach(p => {
                if (!playerStatsMap.has(p.id)) {
                    playerStatsMap.set(p.id, {
                        id: p.id,
                        name: p.name,
                        wins: 0,
                        losses: 0,
                        draws: 0,
                        scored: null,
                        allowed: null
                    });
                }

                const stats = playerStatsMap.get(p.id);

                if (match.is_tie) {
                    stats.draws++;
                } else if (match.winner_id === p.id) {
                    stats.wins++;
                } else if (match.loser_id === p.id) {
                    stats.losses++;
                }
            });
        });
    });

    // Convert to array and calculate win ratios
    const allPlayers = Array.from(playerStatsMap.values());
    allPlayers.forEach(p => {
        const total = p.wins + p.losses + p.draws;
        p.winRatio = total > 0 ? p.wins / total : 0;
    });

    // Sort by win ratio, then by wins
    allPlayers.sort((a, b) => {
        if (b.winRatio !== a.winRatio) return b.winRatio - a.winRatio;
        return b.wins - a.wins;
    });

    // Get only players tied for 1st place
    if (allPlayers.length === 0) return [];

    const firstPlace = allPlayers[0];
    const tiedPlayers = allPlayers.filter(p =>
        p.winRatio === firstPlace.winRatio && p.wins === firstPlace.wins
    );

    // Load existing tiebreaker data if available
    if (bracket.tiebreaker_data) {
        tiedPlayers.forEach(p => {
            if (bracket.tiebreaker_data[p.id]) {
                p.scored = bracket.tiebreaker_data[p.id].scored || null;
                p.allowed = bracket.tiebreaker_data[p.id].allowed || null;
            }
        });
    }

    return tiedPlayers;
};

const calculateQuotient = (scored, allowed) => {
    if (scored === null || scored === undefined || scored === '' ||
        allowed === null || allowed === undefined || allowed === '' ||
        allowed === 0) {
        return '-';
    }
    const quotient = parseFloat(scored) / parseFloat(allowed);
    return isNaN(quotient) ? '-' : quotient.toFixed(2);
};

const handleSaveTiebreakers = () => {
    const bracketIdx = pendingBracketIdx.value;

    const tiebreakerData = {};
    tiedPlayersData.value.forEach(p => {
        const scored = p.scored || 0;
        const allowed = p.allowed || 0;
        const quotient = (scored && allowed && allowed !== 0) ? (scored / allowed) : 0;

        tiebreakerData[p.id] = {
            scored: scored,
            allowed: allowed,
            quotient: quotient
        };
    });

    saveTiebreakers(bracketIdx, tiebreakerData);
};

// Watch for dialog opening and populate tied players data
watch(showTiebreakerDialog, (newVal) => {
    if (newVal && pendingBracketIdx.value !== null) {
        tiedPlayersData.value = getTiedPlayers(pendingBracketIdx.value);
    }
});

const matchStatusFilterOptions = ref([
    { label: 'All', value: 'all' },
    { label: 'Pending', value: 'pending' },
    { label: 'Completed', value: 'completed' }
]);

const filteredBrackets = computed(() => {
    let results = [...brackets.value];

    // Exclude brackets from archived events
    results = results.filter(bracket => {
        return !bracket.event || !bracket.event.archived;
    });

    // 1. Search Query Filter
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase().trim();
        results = results.filter(bracket => {
            const bracketNameMatch = bracket.name?.toLowerCase().includes(query);
            const eventNameMatch = bracket.event?.title?.toLowerCase().includes(query);
            return bracketNameMatch || eventNameMatch;
        });
    }

    // 2. Multi-Select Filters
    if (filterByEvents.value.length > 0) {
        const eventIds = filterByEvents.value.map(e => e.id);
        results = results.filter(b => b.event_id && eventIds.includes(b.event_id));
    }
    if (filterByTypes.value.length > 0) {
        const types = filterByTypes.value.map(t => t.code);
        results = results.filter(b => types.includes(b.type));
    }
    if (filterByStatus.value.length > 0) {
        const statuses = filterByStatus.value.map(s => s.code);
        results = results.filter(b => {
            const status = getBracketStats(b).status.text;
            return statuses.includes(status);
        });
    }

    // 3. Sorting
    const sortCode = sortBy.value?.code || 'created_desc';
    results.sort((a, b) => {
        switch (sortCode) {
            case 'created_asc': return new Date(a.created_at) - new Date(b.created_at);
            case 'name_asc': return a.name.localeCompare(b.name);
            case 'name_desc': return b.name.localeCompare(a.name);
            case 'event_asc': return (a.event?.title || '').localeCompare(b.event?.title || '');
            case 'event_desc': return (b.event?.title || '').localeCompare(a.event?.title || '');
            case 'created_desc':
            default:
                return new Date(b.created_at) - new Date(a.created_at);
        }
    });

    return results;
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

const isLoading = computed(() =>
    isCreatingBracket.value ||
    isDeletingBracket.value ||
    isUpdatingMatch.value
);

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
        <LoadingSpinner :show="isLoading" message="Processing..." />

        <h1 class="title">Brackets List</h1>
        <div class="toolbar-container mb-5">
            <div class="search-container">
                <SearchFilterBar
                    v-model:searchQuery="searchQuery"
                    placeholder="Search brackets..."
                    :show-date-filter="true"
                    :is-date-filter-active="showFilters"
                    filter-icon="pi pi-filter"
                    filter-tooltip="Show filters"
                    @toggle-date-filter="showFilters = !showFilters"
                />
            </div>
            <div class="action-section">
                <button v-if="user?.role === 'Admin' " class="create-button" @click="openDialog">Create Bracket</button>
            </div>
        </div>
        <div v-if="showFilters" class="filter-panel mb-5">
            <div class="filter-controls">
                <MultiSelect v-model="filterByEvents" :options="uniqueEvents" optionLabel="title" placeholder="Filter by Event" display="chip" class="filter-select" />
                <MultiSelect v-model="filterByTypes" :options="bracketTypeOptions.map(t => ({name: t, code: t}))" optionLabel="name" placeholder="Filter by Type" display="chip" class="filter-select" />
                <MultiSelect v-model="filterByStatus" :options="['Upcoming', 'Ongoing', 'Completed'].map(s => ({name: s, code: s}))" optionLabel="name" placeholder="Filter by Status" display="chip" class="filter-select" />
            </div>
            <div class="sort-and-actions">
                <Button v-if="areFiltersActive" label="Clear Filters" severity="danger" text @click="clearFilters" />
                <div class="sort-control">
                    <Select v-model="sortBy" :options="sortOptions" optionLabel="name" placeholder="Sort by" class="w-full md:w-14rem" />
                </div>
            </div>
        </div>

      <!-- Loading Skeleton -->
      <div v-if="initialLoading" key="loading-skeleton">
        <div v-for="i in 2" :key="i" class="bracket-section">
            <div class="bracket-wrapper">
                <div class="bracket-header responsive-skeleton-header">
                    <div class="flex justify-between items-start w-full">
                        <div class="flex-grow">
                            <Skeleton width="12rem" height="1.6rem" />
                            <div class="info-tags flex gap-2 mt-2">
                                <Skeleton width="7rem" height="1.25rem" borderRadius="1rem" />
                                <Skeleton width="5rem" height="1.25rem" borderRadius="1rem" />
                            </div>
                        </div>
                        <div class="bracket-controls flex gap-2">
                            <Skeleton shape="circle" size="2.5rem" />
                            <Skeleton shape="circle" size="2.5rem" />
                        </div>
                    </div>

                    <div class="event-link-container group mt-3">
                        <Skeleton shape="circle" size="2.5rem" />
                        <div class="event-link-info">
                            <Skeleton width="10rem" height="1.2rem" />
                            <Skeleton width="6rem" height="0.8rem" class="mt-1" />
                        </div>
                    </div>

                    <div class="bracket-stats">
                        <Skeleton width="10rem" height="1rem" />
                        <Skeleton width="8rem" height="1rem" />
                    </div>
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
      <div v-else>
          <BracketCard
            v-for="bracket in filteredBrackets"
            :key="bracket.id"
            :bracket="bracket"
            :bracketIndex="brackets.indexOf(bracket)"
            :user="user"
            :isExpanded="expandedBrackets[brackets.indexOf(bracket)]"
            :viewMode="bracketViewModes[brackets.indexOf(bracket)]"
            :matchFilter="bracketMatchFilters[brackets.indexOf(bracket)]"
            :standingsRevision="standingsRevision"
            :getBracketStats="getBracketStats"
            :getBracketTypeClass="getBracketTypeClass"
            :isFinalRound="isFinalRound"
            :getRoundRobinStandings="getRoundRobinStandings"
            :isRoundRobinConcluded="isRoundRobinConcluded"
            :onOpenMatchDialog="openMatchDialog"
            :onOpenScoringConfigDialog="openScoringConfigDialog"
            :onOpenMatchEditorFromCard="openMatchEditorFromCard"
            :onToggleConsolationMatch="toggleConsolationMatch"
            :onToggleAllowDraws="toggleAllowDraws"
            :onOpenTiebreakerDialog="openTiebreakerDialog"
            :onDismissTiebreakerNotice="dismissTiebreakerNotice"
            :dismissedTiebreakerNotices="dismissedTiebreakerNotices"
            @toggle-bracket="toggleBracket"
            @remove-bracket="removeBracket"
            @set-view-mode="({ index, mode }) => setBracketViewMode(index, mode)"
            @set-match-filter="({ index, filter }) => setBracketMatchFilter(index, filter)"
          />
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
              class="w-full"
            />
          </div>

          <div class="p-field">
            <label for="numberOfPlayers">Number of Participants:</label>
            <InputText v-model="numberOfPlayers" type="number" min="1" placeholder="Insert number of teams" />
          </div>

          <div class="p-field">
            <label for="matchType">Bracket Type:</label>
            <Select
              v-model="matchType"
              :options="bracketTypeOptions"
              placeholder="Select bracket type"
            />
          </div>

          <div class="p-field" v-if="matchType === 'Single Elimination'">
            <div class="flex items-center gap-2">
              <Checkbox v-model="includeThirdPlace" inputId="thirdPlace" :binary="true" />
              <label for="thirdPlace" class="cursor-pointer">Include 3rd Place Match</label>
            </div>
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
        confirmButtonClass="modal-button-danger"
        :show-cancel-button="false"
        @confirm="showMissingFieldsDialog = false"
      />

      <!-- Delete Bracket Confirmation Dialog -->
      <ConfirmationDialog
        v-model:show="showDeleteConfirmDialog"
        title="Confirm Deletion"
        message="Are you sure you want to remove this bracket?"
        confirmText="Yes, Remove Bracket"
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
        confirmButtonClass="modal-button-danger"
        @confirm="showGenericErrorDialog = false"
      />

      <!-- Toggle Draws Confirmation Dialog -->
      <ConfirmationDialog
        v-model:show="showToggleDrawsDialog"
        title="Toggle Draws"
        :message="brackets[pendingBracketIdx]?.allow_draws ? 'Disable draws for this Round Robin tournament?' : 'Enable draws for this Round Robin tournament?'"
        :confirmText="brackets[pendingBracketIdx]?.allow_draws ? 'Disable Draws' : 'Enable Draws'"
        cancelText="Cancel"
        @confirm="confirmToggleDraws"
        @cancel="cancelToggleDraws"
      />

      <!-- Toggle Consolation Match Confirmation Dialog -->
      <ConfirmationDialog
        v-model:show="showToggleConsolationDialog"
        title="Toggle 3rd Place Match"
        :message="pendingBracketIdx !== null && brackets[pendingBracketIdx]?.matches.flat().some(m => m.bracket_type === 'consolation')
            ? 'Are you sure you want to remove the 3rd place match?'
            : 'Are you sure you want to add a 3rd place match?'"
        :confirmText="pendingBracketIdx !== null && brackets[pendingBracketIdx]?.matches.flat().some(m => m.bracket_type === 'consolation') ? 'Yes, Remove' : 'Yes, Add'"
        cancelText="Cancel"
        :confirmButtonClass="pendingBracketIdx !== null && brackets[pendingBracketIdx]?.matches.flat().some(m => m.bracket_type === 'consolation') ? 'modal-button-danger' : 'modal-button-primary'"
        @confirm="confirmToggleConsolation"
        @cancel="cancelToggleConsolation"
      />

      <!-- Tiebreaker Dialog -->
      <Dialog v-model:visible="showTiebreakerDialog" header="Set Tiebreakers" modal :style="{ width: '500px' }">
        <div class="tiebreaker-dialog">
          <p class="mb-4 text-sm text-gray-600">Enter points scored and allowed for each tied player to calculate quotient rankings.</p>
          <div v-if="pendingBracketIdx !== null && brackets[pendingBracketIdx]" class="tiebreaker-inputs">
            <div v-for="(player, index) in tiedPlayersData" :key="player.id" class="tiebreaker-row">
              <label class="player-label">{{ player.name }}</label>
              <div class="input-group">
                <InputText
                  v-model.number="player.scored"
                  type="number"
                  placeholder="Points Scored"
                  class="tiebreaker-input"
                />
                <InputText
                  v-model.number="player.allowed"
                  type="number"
                  placeholder="Points Allowed"
                  class="tiebreaker-input"
                />
                <span class="quotient-display">Quotient: {{ calculateQuotient(player.scored, player.allowed) }}</span>
              </div>
            </div>
          </div>
          <div class="dialog-actions mt-4">
            <button @click="closeTiebreakerDialog" class="modal-button-secondary">Cancel</button>
            <button @click="handleSaveTiebreakers" class="modal-button-primary">Save Tiebreakers</button>
          </div>
        </div>
      </Dialog>

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
        :loading="isUpdatingMatch"
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

.search-container {
    display: flex;
    gap: 0.5rem;
}

.toolbar-container {
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
}

.search-and-filter-section {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.75rem;
    flex-grow: 1;
}

.filter-controls {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.filter-select {
    min-width: 14rem;
}

.sort-and-actions {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.sort-control {
    flex-grow: 1;
    min-width: 14rem;
}

.action-section {
    flex-shrink: 0;
}

@media (max-width: 768px) {
    .responsive-skeleton-header {
        flex-direction: column;
        align-items: flex-start;
    }
}

@media (max-width: 768px) { /* Breakpoint for filters to stack */
    .filter-panel {
        flex-direction: column;
        align-items: stretch;
        gap: 1rem;
    }
}

/* Tiebreaker Dialog Styles */
.tiebreaker-dialog {
    padding: 1rem 0;
}

.tiebreaker-inputs {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    max-height: 400px;
    overflow-y: auto;
}

.tiebreaker-row {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    padding: 0.75rem;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    background: #f8f9fa;
}

.player-label {
    font-weight: 600;
    color: #495057;
    font-size: 0.95rem;
}

.input-group {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    flex-wrap: wrap;
}

.tiebreaker-input {
    flex: 1;
    min-width: 120px;
}

.quotient-display {
    font-weight: 600;
    color: #007bff;
    font-size: 0.9rem;
    white-space: nowrap;
}

</style>
