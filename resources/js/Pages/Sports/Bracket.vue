<script setup>
import { onMounted, computed, ref } from 'vue';
import Dialog from 'primevue/dialog';
import { format, parse, parseISO, isValid } from 'date-fns';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Button from 'primevue/button';
import InputSwitch from 'primevue/inputswitch';
import { Link, usePage } from '@inertiajs/vue3';
import Skeleton from 'primevue/skeleton';
import SuccessDialog from '@/Components/SuccessDialog.vue';
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
  showRoundRobinMatchDialog,
  bracketViewModes,
  selectedRoundRobinMatch,
  selectedRoundRobinMatchData,
  showMatchUpdateConfirmDialog,
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
  openRoundRobinMatchDialog,
  closeRoundRobinMatchDialog,
  confirmMatchUpdate,
  cancelMatchUpdate,
  proceedWithMatchUpdate,
  openScoringConfigDialog,
  closeScoringConfigDialog,
  saveScoringConfig,
} = useBracketActions(state);

const { setBracketViewMode, getAllMatches } = useBracketActions(state);

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
  if (selectedRoundRobinMatch.value) {
    return brackets.value[selectedRoundRobinMatch.value.bracketIdx];
  }
  return null;
});

onMounted(async () => {
  initialLoading.value = true;
  try {
    await fetchBrackets();
  } finally {
    initialLoading.value = false;
  }
});

// Helper function to truncate names for elimination brackets (13 characters)
const truncateNameElimination = (name) => {
  if (!name) return 'TBD';
  return name.length > 13 ? name.substring(0, 13) + '...' : name;
};

// Helper function to truncate names for Round Robin brackets (15 characters)
const truncateNameRoundRobin = (name) => {
  if (!name) return 'TBD';
  return name.length > 15 ? name.substring(0, 15) + '...' : name;
};

// Helper functions for date and time formatting
const formatDisplayDate = (dateString) => {
  if (!dateString) return '';
  try {
    let date = parseISO(dateString);
    if (!isValid(date)) {
      date = parse(dateString, 'MMM-dd-yyyy', new Date());
    }
    return isValid(date) ? format(date, 'MMM-dd-yyyy') : 'Invalid Date';
  } catch {
    return 'Invalid Date';
  }
};

const formatDisplayTime = (timeString) => {
  if (!timeString) return '';
  try {
    const parsed = parse(timeString, 'HH:mm', new Date());
    return format(parsed, 'hh:mm a');
  } catch {
    return 'Invalid Time';
  }
};
// using composable increaseScore/decreaseScore

// using composable editParticipant

// using composable logic; local helper removed

// using composable cancelEndMatch
// using composable confirmEndMatch




// watchers handled in composable

// Add onMounted hook to fetch brackets when component loads
onMounted(() => {
  fetchBrackets();
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
            <button v-if="user?.role === 'Admin' || user?.role === 'SportsManager'" @click="removeBracket(brackets.indexOf(bracket))" class="delete-button">Delete Bracket</button>
          </div>

          <div v-if="expandedBrackets[brackets.indexOf(bracket)]" class="bracket-content-wrapper">
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
            <div v-if="bracket.type === 'Single Elimination'" class="bracket">
              <svg class="connection-lines">
                <line
                  v-for="(line, i) in bracket.lines"
                  :key="i"
                  :x1="line.x1"
                  :y1="line.y1"
                  :x2="line.x2"
                  :y2="line.y2"
                  stroke="black"
                  stroke-width="2"
                />
              </svg>

              <div v-for="(round, roundIdx) in bracket.matches" :key="roundIdx"
                :class="['round', `round-${roundIdx + 1}`]">
                <h3>
                  {{ isFinalRound(brackets.indexOf(bracket), roundIdx) ? 'Final Round' : `Round ${roundIdx + 1}` }}
                </h3>

                <!-- Matches Display -->
                <div
                  v-for="(match, matchIdx) in round"
                  :key="matchIdx"
                  :id="`match-${roundIdx}-${matchIdx}`"
                  :class="['match', (user?.role === 'Admin' || user?.role === 'SportsManager') ? 'cursor-pointer' : '']"
                  @click="(user?.role === 'Admin' || user?.role === 'SportsManager') && openMatchDialog(brackets.indexOf(bracket), roundIdx, matchIdx, match, 'single')"
                >
                  <div class="player-box">
                      <span
                        :class="{
                          winner: (match.players[0].name && match.players[0].name !== 'TBD') && match.players[0].completed && match.players[0].score >= match.players[1].score,
                          'bye-text': match.players[0].name === 'BYE',
                          'facing-bye': match.players[1].name === 'BYE',
                          'tbd-text': (!match.players[0].name || match.players[0].name === 'TBD') || ((match.players[0].name && match.players[0].name !== 'TBD') && match.players[0].completed && match.players[0].score < match.players[1].score),
                          'loser-name': match.loser_id === match.players[0].id,
                          'winner-name': match.winner_id === match.players[0].id
                        }"
                      >
                        {{ truncateNameElimination(match.players[0].name) }}{{ (match.players[0].name && match.players[0].name !== 'TBD' && match.players[0].name !== 'BYE') ? ' | ' + match.players[0].score : '' }}
                      </span>
                      <hr />
                      <span
                        :class="{
                          winner: (match.players[1].name && match.players[1].name !== 'TBD') && match.players[1].completed && match.players[1].score >= match.players[0].score,
                          'bye-text': match.players[1].name === 'BYE',
                          'facing-bye': match.players[0].name === 'BYE',
                          'tbd-text': (!match.players[1].name || match.players[1].name === 'TBD') || ((match.players[1].name && match.players[1].name !== 'TBD') && match.players[1].completed && match.players[1].score < match.players[0].score),
                          'loser-name': match.loser_id === match.players[1].id,
                          'winner-name': match.winner_id === match.players[1].id
                        }"
                      >
                        {{ truncateNameElimination(match.players[1].name) }}{{ (match.players[1].name && match.players[1].name !== 'TBD' && match.players[1].name !== 'BYE') ? ' | ' + match.players[1].score : '' }}
                      </span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Round Robin Display -->
            <div v-else-if="bracket.type === 'Round Robin'" class="round-robin-bracket">
              <div class="bracket">
                <div v-for="(round, roundIdx) in bracket.matches" :key="`round-${roundIdx}`"
                  :class="['round', `round-${roundIdx + 1}`]">
                  <h3>Round {{ roundIdx + 1 }}</h3>

                  <div
                    v-for="(match, matchIdx) in round"
                    :key="`round-${roundIdx}-${matchIdx}`"
                    :id="`round-match-${roundIdx}-${matchIdx}`"
                    :class="['match', (user?.role === 'Admin' || user?.role === 'SportsManager') ? 'cursor-pointer' : '']"
                    @click="(user?.role === 'Admin' || user?.role === 'SportsManager') && openRoundRobinMatchDialog(brackets.indexOf(bracket), roundIdx, matchIdx, match)"
                  >
                    <div class="player-box">
                      <span
                        :class="{
                          winner: (match.players[0].name && match.players[0].name !== 'TBD') && match.players[0].completed && match.players[0].score > match.players[1].score,
                          'bye-text': match.players[0].name === 'BYE',
                          'facing-bye': match.players[1].name === 'BYE',
                          'tbd-text': (!match.players[0].name || match.players[0].name === 'TBD') || ((match.players[0].name && match.players[0].name !== 'TBD') && match.players[0].completed && (match.players[0].score < match.players[1].score || (match.players[0].score === match.players[1].score && match.is_tie))),
                          'loser-name': match.loser_id === match.players[0].id,
                          'winner-name': match.winner_id === match.players[0].id
                        }"
                      >
                        {{ truncateNameRoundRobin(match.players[0].name) }}{{ (match.players[0].name && match.players[0].name !== 'TBD' && match.players[0].name !== 'BYE') ? ' | ' + match.players[0].score : '' }}
                      </span>
                      <hr />
                      <span
                        :class="{
                          winner: (match.players[1].name && match.players[1].name !== 'TBD') && match.players[1].completed && match.players[1].score > match.players[0].score,
                          'bye-text': match.players[1].name === 'BYE',
                          'facing-bye': match.players[0].name === 'BYE',
                          'tbd-text': (!match.players[1].name || match.players[1].name === 'TBD') || ((match.players[1].name && match.players[1].name !== 'TBD') && match.players[1].completed && (match.players[1].score < match.players[0].score || (match.players[1].score === match.players[0].score && match.is_tie))),
                          'loser-name': match.loser_id === match.players[1].id,
                          'winner-name': match.winner_id === match.players[1].id
                        }"
                      >
                        {{ truncateNameRoundRobin(match.players[1].name) }}{{ (match.players[1].name && match.players[1].name !== 'TBD' && match.players[1].name !== 'BYE') ? ' | ' + match.players[1].score : '' }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Round Robin Standings -->
              <div class="standings-section">
                <div class="standings-header-row">
                  <h3 class="text-lg font-semibold">Standings</h3>
                  <button v-if="user?.role === 'Admin' || user?.role === 'SportsManager'"
                    @click="openScoringConfigDialog"
                    class="scoring-config-btn"
                    title="Configure scoring system"
                  >
                    <i class="pi pi-cog"></i>
                  </button>
                </div>
                <div class="standings-table">
                  <div class="standings-header">
                    <span class="rank">Rank</span>
                    <span class="player">Player</span>
                    <span class="wins">Wins</span>
                    <span class="draws">Draws</span>
                    <span class="losses">Losses</span>
                    <span class="points">Points</span>
                  </div>
                  <div
                    v-for="(player, index) in (standingsRevision, getRoundRobinStandings(brackets.indexOf(bracket)))"
                    :key="player.id"
                    class="standings-row"
                    :class="{ 'winner': index === 0 && isRoundRobinConcluded(brackets.indexOf(bracket)) }"
                  >
                    <span class="rank">{{ index + 1 }}</span>
                    <span class="player">
                      {{ truncateNameRoundRobin(player.name) }}
                      <i v-if="index === 0 && isRoundRobinConcluded(brackets.indexOf(bracket))" class="pi pi-crown winner-crown"></i>
                    </span>
                    <span class="wins">{{ player.wins }}</span>
                    <span class="draws">{{ player.draws }}</span>
                    <span class="losses">{{ player.losses }}</span>
                    <span class="points">{{ player.points }}</span>
                  </div>
                </div>
              </div>
            </div>

            <!-- Double Elimination Display -->
            <div v-else-if="bracket.type === 'Double Elimination'" class="double-elimination-bracket">
              <!-- Winners Bracket -->
              <div class="bracket-section winners">
                <h3>Winners Bracket</h3>
                <div class="bracket">
                  <svg class="connection-lines winners-lines">
                    <g v-for="(line, i) in bracket.lines?.winners" :key="`winners-${i}`">
                      <line
                        :x1="line.x1"
                        :y1="line.y1"
                        :x2="line.x2"
                        :y2="line.y2"
                        stroke="black"
                        stroke-width="2"
                      />
                    </g>
                  </svg>

                  <div v-for="(round, roundIdx) in bracket.matches.winners" :key="`winners-${roundIdx}`"
                    :class="['round', `round-${roundIdx + 1}`]">
                    <h4>Round {{ roundIdx + 1 }}</h4>

                    <div
                      v-for="(match, matchIdx) in round"
                      :key="`winners-${roundIdx}-${matchIdx}`"
                      :id="`winners-match-${roundIdx}-${matchIdx}`"
                      :class="['match', (user?.role === 'Admin' || user?.role === 'SportsManager') ? 'cursor-pointer' : '']"
                                @click="(user?.role === 'Admin' || user?.role === 'SportsManager') && openMatchDialog(brackets.indexOf(bracket), roundIdx, matchIdx, match, 'winners')"
                    >
                      <div class="player-box">
                        <span
                          :class="{
                            winner: (match.players[0].name && match.players[0].name !== 'TBD') && match.players[0].completed && match.players[0].score >= match.players[1].score,
                            'bye-text': match.players[0].name === 'BYE',
                            'facing-bye': match.players[1].name === 'BYE',
                            'tbd-text': (!match.players[0].name || match.players[0].name === 'TBD') || ((match.players[0].name && match.players[0].name !== 'TBD') && match.players[0].completed && match.players[0].score < match.players[1].score),
                            'loser-name': match.loser_id === match.players[0].id,
                            'winner-name': match.winner_id === match.players[0].id
                          }"
                        >
                          {{ truncateNameElimination(match.players[0].name) }}{{ (match.players[0].name && match.players[0].name !== 'TBD' && match.players[0].name !== 'BYE') ? ' | ' + match.players[0].score : '' }}
                        </span>
                        <hr />
                        <span
                          :class="{
                            winner: (match.players[1].name && match.players[1].name !== 'TBD') && match.players[1].completed && match.players[1].score >= match.players[0].score,
                            'bye-text': match.players[1].name === 'BYE',
                            'facing-bye': match.players[0].name === 'BYE',
                            'tbd-text': (!match.players[1].name || match.players[1].name === 'TBD') || ((match.players[1].name && match.players[1].name !== 'TBD') && match.players[1].completed && match.players[1].score < match.players[0].score),
                            'loser-name': match.loser_id === match.players[1].id,
                            'winner-name': match.winner_id === match.players[1].id
                          }"
                        >
                          {{ truncateNameElimination(match.players[1].name) }}{{ (match.players[1].name && match.players[1].name !== 'TBD' && match.players[1].name !== 'BYE') ? ' | ' + match.players[1].score : '' }}
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Losers Bracket -->
              <div class="bracket-section losers">
                <h3>Losers Bracket</h3>
                <div class="bracket">
                  <svg class="connection-lines losers-lines">
                    <g v-for="(line, i) in bracket.lines?.losers" :key="`losers-${i}`">
                      <line
                        :x1="line.x1"
                        :y1="line.y1"
                        :x2="line.x2"
                        :y2="line.y2"
                        stroke="black"
                        stroke-width="2"
                      />
                    </g>
                  </svg>

                  <div v-for="(round, roundIdx) in bracket.matches.losers" :key="`losers-${roundIdx}`"
                    :class="['round', `round-${roundIdx + 1}`]">
                    <h4>Round {{ roundIdx + 1 }}</h4>

                    <div
                      v-for="(match, matchIdx) in round"
                      :key="`losers-${roundIdx}-${matchIdx}`"
                      :id="`losers-match-${roundIdx}-${matchIdx}`"
                      :class="['match', (user?.role === 'Admin' || user?.role === 'SportsManager') ? 'cursor-pointer' : '']"
                                @click="(user?.role === 'Admin' || user?.role === 'SportsManager') && openMatchDialog(brackets.indexOf(bracket), roundIdx + bracket.matches.winners.length, matchIdx, match, 'losers')"
                    >
                      <div class="player-box">
                        <span
                          :class="{
                            winner: (match.players[0].name && match.players[0].name !== 'TBD') && match.players[0].completed && match.players[0].score >= match.players[1].score,
                            'bye-text': match.players[0].name === 'BYE',
                            'facing-bye': match.players[1].name === 'BYE',
                            'tbd-text': (!match.players[0].name || match.players[0].name === 'TBD') || ((match.players[0].name && match.players[0].name !== 'TBD') && match.players[0].completed && match.players[0].score < match.players[1].score),
                            'loser-name': match.loser_id === match.players[0].id,
                            'winner-name': match.winner_id === match.players[0].id
                          }"
                        >
                          {{ truncateNameElimination(match.players[0].name) }}{{ (match.players[0].name && match.players[0].name !== 'TBD' && match.players[0].name !== 'BYE') ? ' | ' + match.players[0].score : '' }}
                        </span>
                        <hr />
                        <span
                          :class="{
                            winner: (match.players[1].name && match.players[1].name !== 'TBD') && match.players[1].completed && match.players[1].score >= match.players[0].score,
                            'bye-text': match.players[1].name === 'BYE',
                            'facing-bye': match.players[0].name === 'BYE',
                            'tbd-text': (!match.players[1].name || match.players[1].name === 'TBD') || ((match.players[1].name && match.players[1].name !== 'TBD') && match.players[1].completed && match.players[1].score < match.players[0].score),
                            'loser-name': match.loser_id === match.players[1].id,
                            'winner-name': match.winner_id === match.players[1].id
                          }"
                        >
                          {{ truncateNameElimination(match.players[1].name) }}{{ (match.players[1].name && match.players[1].name !== 'TBD' && match.players[1].name !== 'BYE') ? ' | ' + match.players[1].score : '' }}
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Grand Finals -->
              <div class="bracket-section grand-finals">
                <h3>Finals</h3>
                <div class="bracket">
                  <svg class="connection-lines finals-lines">
                    <g v-for="(line, i) in bracket.lines?.finals" :key="`finals-${i}`">
                      <line
                        :x1="line.x1"
                        :y1="line.y1"
                        :x2="line.x2"
                        :y2="line.y2"
                        stroke="black"
                        stroke-width="2"
                      />
                    </g>
                  </svg>

                  <div v-for="(match, matchIdx) in bracket.matches.grand_finals" :key="`grand-finals-${matchIdx}`"
                    :id="`grand-finals-match-${matchIdx}`"
                    :class="['match', (user?.role === 'Admin' || user?.role === 'SportsManager') ? 'cursor-pointer' : '']"
                    @click="(user?.role === 'Admin' || user?.role === 'SportsManager') && openMatchDialog(brackets.indexOf(bracket), bracket.matches.winners.length + bracket.matches.losers.length, matchIdx, match, 'grand_finals')"
                  >
                    <div class="player-box">
                      <span
                        :class="{
                          winner: (match.players[0].name && match.players[0].name !== 'TBD') && match.players[0].completed && match.players[0].score >= match.players[1].score,
                          'bye-text': match.players[0].name === 'BYE',
                          'facing-bye': match.players[1].name === 'BYE',
                          'tbd-text': (!match.players[0].name || match.players[0].name === 'TBD') || ((match.players[0].name && match.players[0].name !== 'TBD') && match.players[0].completed && match.players[0].score < match.players[1].score)
                        }"
                      >
                        {{ truncateNameElimination(match.players[0].name) }}{{ (match.players[0].name && match.players[0].name !== 'TBD' && match.players[0].name !== 'BYE') ? ' | ' + match.players[0].score : '' }}
                      </span>
                      <hr />
                      <span
                        :class="{
                          winner: (match.players[1].name && match.players[1].name !== 'TBD') && match.players[1].completed && match.players[1].score >= match.players[0].score,
                          'bye-text': match.players[1].name === 'BYE',
                          'facing-bye': match.players[0].name === 'BYE',
                          'tbd-text': (!match.players[1].name || match.players[1].name === 'TBD') || ((match.players[1].name && match.players[1].name !== 'TBD') && match.players[1].completed && match.players[1].score < match.players[0].score)
                        }"
                      >
                        {{ truncateNameElimination(match.players[1].name) }}{{ (match.players[1].name && match.players[1].name !== 'TBD' && match.players[1].name !== 'BYE') ? ' | ' + match.players[1].score : '' }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            </div>

            <!-- Matches Card View -->
            <div v-if="bracketViewModes[brackets.indexOf(bracket)] === 'matches'" class="matches-card-view">
                <div v-for="match in getAllMatches(bracket)" :key="match.id" class="match-card-item">
                    <div class="match-card-header">
                        <span class="match-date">{{ formatDisplayDate(bracket.event.startDate) }}</span>
                        <span :class="['match-status', `status-${match.status}`]">{{ match.status }}</span>
                    </div>
                    <div class="match-card-body">
                        <div class="players-scores">
                            <div class="player">
                                <span class="player-name">{{ truncateNameElimination(match.players[0].name) }}</span>
                                <span class="player-score">{{ match.players[0].score }}</span>
                            </div>
                            <div class="vs-separator">vs</div>
                            <div class="player">
                                <span class="player-name">{{ truncateNameElimination(match.players[1].name) }}</span>
                                <span class="player-score">{{ match.players[1].score }}</span>
                            </div>
                        </div>
                        <div class="time-venue">
                            <div class="info-item">
                                <i class="pi pi-clock"></i>
                                <span>{{ formatDisplayTime(bracket.event.startTime) }}</span>
                            </div>
                            <div class="info-item">
                                <i class="pi pi-map-marker"></i>
                                <span>{{ bracket.event.venue }}</span>
                            </div>
                        </div>
                    </div>
                </div>
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
            <Button label="Create Bracket" class="p-button-success" @click="createBracket" />
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
        confirmText="Yes"
        cancelText="No"
        confirmButtonClass="bg-red-600 hover:bg-red-700"
        @confirm="confirmDeleteBracket"
        @cancel="cancelDeleteBracket"
      />

      <!-- Success Dialog -->
      <SuccessDialog
        v-model:show="showSuccessDialog"
        :message="successMessage"
      />

      <!-- Match Update Confirmation Dialog -->
      <ConfirmationDialog
        v-model:show="showMatchUpdateConfirmDialog"
        title="Confirm Match Update"
        message="Are you sure you want to update this match? This action may trigger bracket progression and cannot be easily undone."
        confirmText="Yes, Update Match"
        cancelText="Cancel"
        :style="{ zIndex: 1102 }"
        confirmButtonClass="bg-green-600 hover:bg-green-700"
        @confirm="proceedWithMatchUpdate"
        @cancel="cancelMatchUpdate"
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
              class="p-button-secondary"
            />
            <Button
              label="Save"
              @click="saveScoringConfig"
              class="p-button-success"
            />
          </div>
        </div>
      </Dialog>

      <!-- Round Robin Match Dialog -->
      <Dialog v-model:visible="showRoundRobinMatchDialog" header="Edit Match" modal :style="{ width: '500px' }">
        <div v-if="selectedRoundRobinMatchData" class="round-robin-match-dialog">
          <div class="match-info">
            <h3>Round Robin Match</h3>
            <p class="match-description">Edit player names, scores, and match status</p>
          </div>

          <div class="player-section">
            <div class="player-input">
              <label>Player 1 Name:</label>
              <InputText
                v-model="selectedRoundRobinMatchData.player1Name"
                placeholder="Enter player name"
                :disabled="selectedRoundRobinMatchData?.player1Name === 'BYE'"
              />
            </div>

            <div class="score-section">
              <label>Player 1 Score:</label>
              <div class="score-controls">
                <Button
                  @click="selectedRoundRobinMatchData.player1Score--"
                  :disabled="selectedRoundRobinMatchData.player1Score <= 0"
                  icon="pi pi-minus"
                  class="p-button-sm"
                />
                <span class="score-display">{{ selectedRoundRobinMatchData.player1Score }}</span>
                <Button
                  @click="selectedRoundRobinMatchData.player1Score++"
                  icon="pi pi-plus"
                  class="p-button-sm"
                />
              </div>
            </div>
          </div>

          <div class="vs-divider">VS</div>

          <div class="player-section">
            <div class="player-input">
              <label>Player 2 Name:</label>
              <InputText
                v-model="selectedRoundRobinMatchData.player2Name"
                placeholder="Enter player name"
                :disabled="selectedRoundRobinMatchData?.player2Name === 'BYE'"
              />
            </div>

            <div class="score-section">
              <label>Player 2 Score:</label>
              <div class="score-controls">
                <Button
                  @click="selectedRoundRobinMatchData.player2Score--"
                  :disabled="selectedRoundRobinMatchData.player2Score <= 0"
                  icon="pi pi-minus"
                  class="p-button-sm"
                />
                <span class="score-display">{{ selectedRoundRobinMatchData.player2Score }}</span>
                <Button
                  @click="selectedRoundRobinMatchData.player2Score++"
                  icon="pi pi-plus"
                  class="p-button-sm"
                />
              </div>
            </div>
          </div>

          <div class="match-status-section">
            <label>Match Status:</label>
            <div class="status-toggle">
              <span class="status-label" :class="{ 'active': selectedRoundRobinMatchData.status === 'pending' }">Pending</span>
              <InputSwitch
                :modelValue="selectedRoundRobinMatchData.status === 'completed'"
                @update:modelValue="(value) => selectedRoundRobinMatchData.status = value ? 'completed' : 'pending'"
                class="status-switch"
              />
              <span class="status-label" :class="{ 'active': selectedRoundRobinMatchData.status === 'completed' }">Completed</span>
            </div>
          </div>

          <div v-if="selectedRoundRobinMatchData.status === 'completed' && selectedRoundRobinMatchData.player1Score === selectedRoundRobinMatchData.player2Score"
               :class="['tie-indicator', selectedBracket?.type !== 'Round Robin' ? 'tie-warning-bg' : '']">
            <i class="pi pi-exclamation-triangle"></i>
            <span v-if="selectedBracket?.type === 'Round Robin'">This match is a tie!</span>
            <span v-else class="tie-warning">Ties are not allowed in elimination brackets. Please adjust scores to determine a winner.</span>
          </div>

          <div class="dialog-actions">
            <Button
              label="Cancel"
              @click="closeRoundRobinMatchDialog"
              class="p-button-secondary"
            />
            <Button
              label="Update Match"
              @click="confirmMatchUpdate"
              class="p-button-success"
            />
          </div>
        </div>
      </Dialog>
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
