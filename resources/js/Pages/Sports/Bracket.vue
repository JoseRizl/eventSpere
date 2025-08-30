<script setup>
import { onMounted } from 'vue';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Button from 'primevue/button';
import { Link } from '@inertiajs/vue3';
import ConfirmationDialog from '@/Components/ConfirmationDialog.vue';
import { useBracketState } from '@/composables/useBracketState.js';
import { useBracketActions } from '@/composables/useBracketActions.js';

const state = useBracketState();
const {
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
  showMissingFieldsDialog,
  showDeleteConfirmDialog,
  deleteBracketIdx,
  currentWinnersMatchIndex,
  currentLosersMatchIndex,
  currentGrandFinalsIndex,
  activeBracketSection,
  bracketTypeOptions,
} = state;

const {
  openDialog,
  toggleBracket,
  createBracket,
  fetchBrackets,
  increaseScore,
  decreaseScore,
  editParticipant,
  cancelEndMatch,
  confirmEndMatch,
  undoConcludeMatch,
  getRoundAndMatchIndices,
  isCurrentMatch,
  navigateToMatch,
  showNextMatch,
  showPreviousMatch,
  currentMatch,
  updateLines,
  removeBracket,
  calculateByes,
  isFinalRound,
  isSemifinalRound,
  isQuarterfinalRound,
  confirmDeleteBracket,
  cancelDeleteBracket,
  saveBrackets,
  concludeMatch,
  getCurrentRound,
  getTotalMatches,
  navigateToSection,
} = useBracketActions(state);

onMounted(() => {
  fetchBrackets();
});

// using composable increaseScore/decreaseScore

// using composable editParticipant

// using composable logic; local helper removed

// using composable cancelEndMatch
// using composable confirmEndMatch

// using composable isCurrentMatch


// watchers handled in composable

// Add onMounted hook to fetch brackets when component loads
onMounted(() => {
  fetchBrackets();
});

</script>

<template>
    <div class="container">
        <div class="header">
          <h1 class="title">Ongoing Games</h1>
          <button class="create-button" @click="openDialog">Create Bracket</button>
        </div>

      <!-- Display message when no brackets are created -->
      <div v-if="brackets.length === 0" class="no-brackets-message">
        <div class="icon-and-title">
          <i class="pi pi-info-circle" style="font-size: 1.5rem; color: #007bff; margin-right: 10px;"></i>
          <h2 class="no-brackets-title">No Brackets Created Yet</h2>
        </div>
        <p class="no-brackets-text">Click the "Create Bracket" button above to start a new tournament.</p>
      </div>

      <!-- Bracket Display Section -->
      <div v-for="(bracket, bracketIdx) in brackets" :key="bracketIdx" class="bracket-section">
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
            <button @click="toggleBracket(bracketIdx)" class="toggle-button">
              {{ expandedBrackets[bracketIdx] ? 'Hide Bracket' : 'Show Bracket' }}
            </button>
            <button @click="removeBracket(bracketIdx)" class="delete-button">Delete Bracket</button>
          </div>

          <div v-if="expandedBrackets[bracketIdx]">
            <!-- Single Elimination Display -->
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
                  {{ isFinalRound(bracketIdx, roundIdx) ? 'Final Round' : isSemifinalRound(bracketIdx, roundIdx) ? 'Semifinal' : isQuarterfinalRound(bracketIdx, roundIdx) ? 'Quarterfinal' : `Round ${roundIdx + 1}` }}
                </h3>

                <!-- Matches Display -->
                <div
                  v-for="(match, matchIdx) in round"
                  :key="matchIdx"
                  :id="`match-${roundIdx}-${matchIdx}`"
                  :class="['match', { 'highlight': isCurrentMatch(bracketIdx, roundIdx, matchIdx) }]"
                  @click="navigateToMatch(bracketIdx, roundIdx, matchIdx)"
                >
                  <div class="player-box">
                      <span
                        @click.stop="editParticipant(bracketIdx, roundIdx, matchIdx, 0)"
                        :class="{
                          editable: true,
                          winner: (match.players[0].name && match.players[0].name !== 'TBD') && match.players[0].completed && match.players[0].score >= match.players[1].score,
                          loser: (match.players[0].name && match.players[0].name !== 'TBD') && match.players[0].completed && match.players[0].score < match.players[1].score,
                          'bye-text': match.players[0].name === 'BYE',
                          'facing-bye': match.players[1].name === 'BYE',
                          'tbd-text': !match.players[0].name || match.players[0].name === 'TBD',
                          'loser-name': match.loser_id === match.players[0].id,
                          'winner-name': match.winner_id === match.players[0].id
                        }"
                      >
                        {{ match.players[0].name || 'TBD' }} | {{ match.players[0].score }}
                      </span>
                      <hr />
                      <span
                        @click.stop="editParticipant(bracketIdx, roundIdx, matchIdx, 1)"
                        :class="{
                          editable: true,
                          winner: (match.players[1].name && match.players[1].name !== 'TBD') && match.players[1].completed && match.players[1].score >= match.players[0].score,
                          loser: (match.players[1].name && match.players[1].name !== 'TBD') && match.players[1].completed && match.players[1].score < match.players[0].score,
                          'bye-text': match.players[1].name === 'BYE',
                          'facing-bye': match.players[0].name === 'BYE',
                          'tbd-text': !match.players[1].name || match.players[1].name === 'TBD',
                          'loser-name': match.loser_id === match.players[1].id,
                          'winner-name': match.winner_id === match.players[1].id
                        }"
                      >
                        {{ match.players[1].name || 'TBD' }} | {{ match.players[1].score }}
                      </span>
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
                      :class="['match', { 'highlight': isCurrentMatch(bracketIdx, roundIdx, matchIdx, 'winners') }]"
                      @click="navigateToMatch(bracketIdx, roundIdx, matchIdx, 'winners')"
                    >
                      <div class="player-box">
                        <span
                          @click.stop="editParticipant(bracketIdx, roundIdx, matchIdx, 0)"
                          :class="{
                            editable: true,
                            winner: (match.players[0].name && match.players[0].name !== 'TBD') && match.players[0].completed && match.players[0].score >= match.players[1].score,
                            loser: (match.players[0].name && match.players[0].name !== 'TBD') && match.players[0].completed && match.players[0].score < match.players[1].score,
                            'bye-text': match.players[0].name === 'BYE',
                            'facing-bye': match.players[1].name === 'BYE',
                            'tbd-text': !match.players[0].name || match.players[0].name === 'TBD',
                            'loser-name': match.loser_id === match.players[0].id,
                            'winner-name': match.winner_id === match.players[0].id
                          }"
                        >
                          {{ match.players[0].name || 'TBD' }} | {{ match.players[0].score }}
                        </span>
                        <hr />
                        <span
                          @click.stop="editParticipant(bracketIdx, roundIdx, matchIdx, 1)"
                          :class="{
                            editable: true,
                            winner: (match.players[1].name && match.players[1].name !== 'TBD') && match.players[1].completed && match.players[1].score >= match.players[0].score,
                            loser: (match.players[1].name && match.players[1].name !== 'TBD') && match.players[1].completed && match.players[1].score < match.players[0].score,
                            'bye-text': match.players[1].name === 'BYE',
                            'facing-bye': match.players[0].name === 'BYE',
                            'tbd-text': !match.players[1].name || match.players[1].name === 'TBD',
                            'loser-name': match.loser_id === match.players[1].id,
                            'winner-name': match.winner_id === match.players[1].id
                          }"
                        >
                          {{ match.players[1].name || 'TBD' }} | {{ match.players[1].score }}
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
                      :class="['match', { 'highlight': isCurrentMatch(bracketIdx, roundIdx + bracket.matches.winners.length, matchIdx, 'losers') }]"
                      @click="navigateToMatch(bracketIdx, roundIdx + bracket.matches.winners.length, matchIdx, 'losers')"
                    >
                      <div class="player-box">
                        <span
                          @click.stop="editParticipant(bracketIdx, roundIdx, matchIdx, 0)"
                          :class="{
                            editable: true,
                            winner: (match.players[0].name && match.players[0].name !== 'TBD') && match.players[0].completed && match.players[0].score >= match.players[1].score,
                            loser: (match.players[0].name && match.players[0].name !== 'TBD') && match.players[0].completed && match.players[0].score < match.players[1].score,
                            'bye-text': match.players[0].name === 'BYE',
                            'facing-bye': match.players[1].name === 'BYE',
                            'tbd-text': !match.players[0].name || match.players[0].name === 'TBD',
                            'loser-name': match.loser_id === match.players[0].id,
                            'winner-name': match.winner_id === match.players[0].id
                          }"
                        >
                          {{ match.players[0].name || 'TBD' }} | {{ match.players[0].score }}
                        </span>
                        <hr />
                        <span
                          @click.stop="editParticipant(bracketIdx, roundIdx, matchIdx, 1)"
                          :class="{
                            editable: true,
                            winner: (match.players[1].name && match.players[1].name !== 'TBD') && match.players[1].completed && match.players[1].score >= match.players[0].score,
                            loser: (match.players[1].name && match.players[1].name !== 'TBD') && match.players[1].completed && match.players[1].score < match.players[0].score,
                            'bye-text': match.players[1].name === 'BYE',
                            'facing-bye': match.players[0].name === 'BYE',
                            'tbd-text': !match.players[1].name || match.players[1].name === 'TBD',
                            'loser-name': match.loser_id === match.players[1].id,
                            'winner-name': match.winner_id === match.players[1].id
                          }"
                        >
                          {{ match.players[1].name || 'TBD' }} | {{ match.players[1].score }}
                        </span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Grand Finals -->
              <div class="bracket-section grand-finals">
                <h3>Grand Finals</h3>
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
                    :class="['match', { 'highlight': isCurrentMatch(bracketIdx, bracket.matches.winners.length + bracket.matches.losers.length, matchIdx, 'grand_finals') }]"
                    @click="navigateToMatch(bracketIdx, bracket.matches.winners.length + bracket.matches.losers.length, matchIdx, 'grand_finals')"
                  >
                    <div class="player-box">
                      <span
                        @click.stop="editParticipant(bracketIdx, 0, matchIdx, 0)"
                        :class="{
                          editable: true,
                          winner: (match.players[0].name && match.players[0].name !== 'TBD') && match.players[0].completed && match.players[0].score >= match.players[1].score,
                          loser: (match.players[0].name && match.players[0].name !== 'TBD') && match.players[0].completed && match.players[0].score < match.players[1].score,
                          'bye-text': match.players[0].name === 'BYE',
                          'facing-bye': match.players[1].name === 'BYE',
                          'tbd-text': !match.players[0].name || match.players[0].name === 'TBD'
                        }"
                      >
                        {{ match.players[0].name || 'TBD' }} | {{ match.players[0].score }}
                      </span>
                      <hr />
                      <span
                        @click.stop="editParticipant(bracketIdx, 0, matchIdx, 1)"
                        :class="{
                          editable: true,
                          winner: (match.players[1].name && match.players[1].name !== 'TBD') && match.players[1].completed && match.players[1].score >= match.players[0].score,
                          loser: (match.players[1].name && match.players[1].name !== 'TBD') && match.players[1].completed && match.players[1].score < match.players[0].score,
                          'bye-text': match.players[1].name === 'BYE',
                          'facing-bye': match.players[0].name === 'BYE',
                          'tbd-text': !match.players[1].name || match.players[1].name === 'TBD'
                        }"
                      >
                        {{ match.players[1].name || 'TBD' }} | {{ match.players[1].score }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Navigation & Matchup Box -->
            <div class="navigation-and-matchup">
              <!-- Navigation Controls -->
              <div class="navigation-controls">
                <button
                  @click="showPreviousMatch(bracketIdx)"
                  :disabled="bracket.type === 'Single Elimination' ? currentMatchIndex === 0 :
                    bracket.type === 'Double Elimination' && (
                      (activeBracketSection === 'winners' && currentWinnersMatchIndex === 0) ||
                      (activeBracketSection === 'losers' && currentLosersMatchIndex === 0) ||
                      (activeBracketSection === 'grand_finals' && currentGrandFinalsIndex === 0)
                    )"
                >
                  Previous
                </button>

                <button
                  @click="showNextMatch(bracketIdx)"
                  :disabled="bracket.type === 'Single Elimination' ? currentMatchIndex >= getTotalMatches(bracketIdx) - 1 :
                    bracket.type === 'Double Elimination' && (
                      (activeBracketSection === 'winners' && currentWinnersMatchIndex >= bracket.matches.winners.flat().length - 1) ||
                      (activeBracketSection === 'losers' && currentLosersMatchIndex >= bracket.matches.losers.flat().length - 1) ||
                      (activeBracketSection === 'grand_finals' && currentGrandFinalsIndex >= bracket.matches.grand_finals.length - 1)
                    )"
                >
                  Next
                </button>
              </div>

              <!-- Current Match Display -->
              <div v-if="currentMatch(bracketIdx)" class="match-card styled">
                <!-- Sport Tag with Correct Round Counter -->
                <div class="sport-tag">
                  {{ bracket.name }} - Round {{ getCurrentRound(bracketIdx) }}
                </div>

                <div class="match-content">
                  <!-- Left Team -->
                  <div class="team blue">
                    <button
                      class="score-btn"
                      @click="increaseScore(bracketIdx, 0)"
                      :disabled="currentMatch(bracketIdx).status === 'completed'"
                    >
                      +
                    </button>

                    <div class="score-display">
                      {{ currentMatch(bracketIdx).players[0]?.score.toString().padStart(2, '0') }}
                    </div>

                    <button
                      class="score-btn"
                      @click="decreaseScore(bracketIdx, 0)"
                      :disabled="currentMatch(bracketIdx).status === 'completed'"
                    >
                      -
                    </button>

                    <span class="team-name" :class="{ 'tbd-text': !currentMatch(bracketIdx).players[0]?.name || currentMatch(bracketIdx).players[0]?.name === 'TBD' }">{{ currentMatch(bracketIdx).players[0]?.name || 'TBD' }}</span>
                  </div>

                  <span class="vs">VS</span>

                  <!-- Right Team -->
                  <div class="team red">
                    <button
                      class="score-btn"
                      @click="increaseScore(bracketIdx, 1)"
                      :disabled="currentMatch(bracketIdx).status === 'completed'"
                    >
                      +
                    </button>

                    <div class="score-display">
                      {{ currentMatch(bracketIdx).players[1]?.score.toString().padStart(2, '0') }}
                    </div>

                    <button
                      class="score-btn"
                      @click="decreaseScore(bracketIdx, 1)"
                      :disabled="currentMatch(bracketIdx).status === 'completed'"
                    >
                      -
                    </button>

                    <span class="team-name" :class="{ 'tbd-text': !currentMatch(bracketIdx).players[1]?.name || currentMatch(bracketIdx).players[1]?.name === 'TBD' }">{{ currentMatch(bracketIdx).players[1]?.name || 'TBD' }}</span>
                  </div>
                </div>

                <!-- Match Status -->
                <div v-if="currentMatch(bracketIdx).status === 'completed'"
                     class="completed-text"
                     @click="undoConcludeMatch(bracketIdx)">
                  Completed
                </div>

                <button v-else
                        @click="concludeMatch(bracketIdx)"
                        class="match-over">
                  End Match
                </button>
              </div>

              <!-- Section Navigation for Double Elimination -->
              <div v-if="bracket.type === 'Double Elimination'" class="section-navigation">
                <button
                  @click="navigateToSection(bracketIdx, 'winners')"
                  :class="{ active: activeBracketSection === 'winners' }"
                >
                  Winners
                </button>
                <button
                  @click="navigateToSection(bracketIdx, 'losers')"
                  :class="{ active: activeBracketSection === 'losers' }"
                >
                  Losers
                </button>
                <button
                  @click="navigateToSection(bracketIdx, 'grand_finals')"
                  :class="{ active: activeBracketSection === 'grand_finals' }"
                >
                  Grand Finals
                </button>
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

      <!-- Winner Dialog -->
      <Dialog v-model:visible="showWinnerDialog" header="Winner!" modal dismissableMask>
        <p>{{ winnerMessage }}</p>
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
    </div>
  </template>

<style scoped>
/* ... existing styles ... */

.navigation-controls {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1rem;
}

.section-navigation {
  display: flex;
  gap: 0.5rem;
  justify-content: center;
  margin-top: 1rem;
}

.section-navigation button {
  padding: 0.5rem 1rem;
  border: 1px solid #ddd;
  border-radius: 4px;
  background-color: #e9ecef;
  color: #495057;
  cursor: pointer;
  transition: all 0.2s ease;
}

.section-navigation button:hover {
  background-color: #dee2e6;
}

.section-navigation button.active {
  background-color: #007bff;
  color: white;
  border-color: #007bff;
}

/* ... existing styles ... */
</style>
