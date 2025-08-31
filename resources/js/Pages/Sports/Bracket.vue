<script setup>
import { onMounted } from 'vue';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Button from 'primevue/button';
import InputSwitch from 'primevue/inputswitch';
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
  expandedBrackets,
  activeBracketIdx,
  showWinnerDialog,
  winnerMessage,
  showConfirmDialog,
  pendingBracketIdx,
  showMissingFieldsDialog,
  showDeleteConfirmDialog,
  deleteBracketIdx,
  bracketTypeOptions,
  showRoundRobinMatchDialog,
  selectedRoundRobinMatch,
  selectedRoundRobinMatchData,
  showMatchUpdateConfirmDialog,
  roundRobinScoring,
  showScoringConfigDialog,
} = state;

const {
  openDialog,
  toggleBracket,
  createBracket,
  fetchBrackets,
  editParticipant,
  cancelEndMatch,
  confirmEndMatch,
  undoConcludeMatch,
  getRoundAndMatchIndices,
  updateLines,
  removeBracket,
  calculateByes,
  isFinalRound,
  isSemifinalRound,
  isQuarterfinalRound,
  confirmDeleteBracket,
  cancelDeleteBracket,
  saveBrackets,
  getRoundRobinStandings,
  openMatchDialog,
  openRoundRobinMatchDialog,
  updateMatch,
  closeRoundRobinMatchDialog,
  confirmMatchUpdate,
  cancelMatchUpdate,
  proceedWithMatchUpdate,
  openScoringConfigDialog,
  closeScoringConfigDialog,
  saveScoringConfig,
} = useBracketActions(state);

onMounted(() => {
  fetchBrackets();
});

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
                  :class="['match']"
                  @click="openMatchDialog(bracketIdx, roundIdx, matchIdx, match, 'single')"
                >
                  <div class="player-box">
                      <span
                        :class="{
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
                        :class="{
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

            <!-- Round Robin Display -->
            <div v-else-if="bracket.type === 'Round Robin'" class="round-robin-bracket">
              <div class="bracket">
                <!-- Scroll indicator -->
                <div v-if="bracket.matches.length > 3" class="scroll-indicator">
                  <i class="pi pi-arrow-right"></i> Scroll to see all rounds
                </div>
                <div v-for="(round, roundIdx) in bracket.matches" :key="`round-${roundIdx}`"
                  :class="['round', `round-${roundIdx + 1}`]">
                  <h3>Round {{ roundIdx + 1 }}</h3>

                  <div
                    v-for="(match, matchIdx) in round"
                    :key="`round-${roundIdx}-${matchIdx}`"
                    :id="`round-match-${roundIdx}-${matchIdx}`"
                    :class="['match']"
                    @click="openRoundRobinMatchDialog(bracketIdx, roundIdx, matchIdx, match)"
                  >
                    <div class="player-box">
                      <span
                        :class="{
                          winner: (match.players[0].name && match.players[0].name !== 'TBD') && match.players[0].completed && match.players[0].score > match.players[1].score,
                          loser: (match.players[0].name && match.players[0].name !== 'TBD') && match.players[0].completed && match.players[0].score < match.players[1].score,
                          tie: (match.players[0].name && match.players[0].name !== 'TBD') && match.players[0].completed && match.players[0].score === match.players[1].score && match.is_tie,
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
                        :class="{
                          winner: (match.players[1].name && match.players[1].name !== 'TBD') && match.players[1].completed && match.players[1].score > match.players[0].score,
                          loser: (match.players[1].name && match.players[1].name !== 'TBD') && match.players[1].completed && match.players[1].score < match.players[0].score,
                          tie: (match.players[1].name && match.players[1].name !== 'TBD') && match.players[1].completed && match.players[1].score === match.players[0].score && match.is_tie,
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

              <!-- Round Robin Standings -->
              <div class="standings-section">
                <div class="standings-header-row">
                  <h3>Standings</h3>
                  <button
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
                    v-for="(player, index) in getRoundRobinStandings(bracketIdx)"
                    :key="player.id"
                    class="standings-row"
                    :class="{ 'winner': index === 0 }"
                  >
                    <span class="rank">{{ index + 1 }}</span>
                    <span class="player">{{ player.name }}</span>
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
                      :class="['match']"
                      @click="openMatchDialog(bracketIdx, roundIdx, matchIdx, match, 'winners')"
                    >
                      <div class="player-box">
                        <span
                          :class="{
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
                          :class="{
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
                      :class="['match']"
                      @click="openMatchDialog(bracketIdx, roundIdx + bracket.matches.winners.length, matchIdx, match, 'losers')"
                    >
                      <div class="player-box">
                        <span
                          :class="{
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
                          :class="{
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
                    :class="['match']"
                    @click="openMatchDialog(bracketIdx, bracket.matches.winners.length + bracket.matches.losers.length, matchIdx, match, 'grand_finals')"
                  >
                    <div class="player-box">
                      <span
                        :class="{
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
                        :class="{
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

      <!-- Match Update Confirmation Dialog -->
      <ConfirmationDialog
        v-model:show="showMatchUpdateConfirmDialog"
        title="Confirm Match Update"
        message="Are you sure you want to update this match? This action may trigger bracket progression and cannot be easily undone."
        confirmText="Yes, Update Match"
        cancelText="Cancel"
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
               :class="['tie-indicator', selectedRoundRobinMatch?.bracketType !== 'round_robin' ? 'tie-warning-bg' : '']">
            <i class="pi pi-exclamation-triangle"></i>
            <span v-if="selectedRoundRobinMatch?.bracketType === 'round_robin'">This match is a tie!</span>
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
/* ... existing styles ... */

.round-robin-bracket {
  margin: 20px 0;
  /* Override any inherited bracket styles */
  position: relative;
  width: 100%;
  /* Add container styling for horizontal layout */
  background: #f8f9fa;
  border-radius: 12px;
  padding: 20px;
  border: 1px solid #dee2e6;
}

/* Add scrollbar styling for better UX */
.round-robin-bracket .bracket::-webkit-scrollbar {
  height: 8px;
}

.round-robin-bracket .bracket::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 4px;
}

.round-robin-bracket .bracket::-webkit-scrollbar-thumb {
  background: #c1c1c1;
  border-radius: 4px;
}

.round-robin-bracket .bracket::-webkit-scrollbar-thumb:hover {
  background: #a8a8a8;
}

/* Scroll indicator */
.scroll-indicator {
  position: absolute;
  top: 10px;
  right: 10px;
  background: rgba(0, 123, 255, 0.9);
  color: white;
  padding: 8px 12px;
  border-radius: 20px;
  font-size: 0.9rem;
  z-index: 20;
  display: flex;
  align-items: center;
  gap: 5px;
  animation: pulse 2s infinite;
}

@keyframes pulse {
  0% { opacity: 0.7; }
  50% { opacity: 1; }
  100% { opacity: 0.7; }
}

/* Round Robin Match Dialog Styles */
.round-robin-match-dialog {
  padding: 20px;
}

.match-info {
  text-align: center;
  margin-bottom: 20px;
}

.match-info h3 {
  margin: 0 0 5px 0;
  color: #333;
  font-size: 1.2rem;
}

.match-description {
  margin: 0;
  color: #666;
  font-size: 0.9rem;
}

.player-section {
  background: #f8f9fa;
  border-radius: 8px;
  padding: 15px;
  margin-bottom: 15px;
}

.player-input {
  margin-bottom: 15px;
}

.player-input label {
  display: block;
  margin-bottom: 5px;
  font-weight: 600;
  color: #333;
}

.score-section {
  display: flex;
  align-items: center;
  gap: 10px;
}

.score-section label {
  font-weight: 600;
  color: #333;
  min-width: 80px;
}

.score-controls {
  display: flex;
  align-items: center;
  gap: 10px;
}

.score-display {
  background: white;
  border: 1px solid #ced4da;
  border-radius: 4px;
  padding: 8px 12px;
  min-width: 50px;
  text-align: center;
  font-weight: bold;
  font-size: 1.1rem;
}

.vs-divider {
  text-align: center;
  font-weight: bold;
  font-size: 1.2rem;
  color: #333;
  margin: 15px 0;
  padding: 10px;
  background: #e9ecef;
  border-radius: 4px;
}

.match-status-section {
  margin: 20px 0;
}

.match-status-section label {
  display: block;
  margin-bottom: 5px;
  font-weight: 600;
  color: #333;
}

.status-toggle {
  display: flex;
  align-items: center;
  gap: 15px;
  justify-content: center;
  margin-top: 10px;
}

.status-label {
  font-weight: 500;
  color: #666;
  transition: color 0.2s ease;
}

.status-label.active {
  color: #007bff;
  font-weight: 600;
}

.status-switch {
  transform: scale(1.2);
}

.dialog-actions {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  margin-top: 20px;
  padding-top: 20px;
  border-top: 1px solid #dee2e6;
}

/* Ensure Round Robin doesn't inherit elimination bracket styles */
.round-robin-bracket .bracket {
  display: flex !important;
  flex-direction: row !important;
  gap: 20px !important;
  /* Override any inherited bracket styles */
  position: relative !important;
  width: 100% !important;
  /* Remove any inherited flex properties that might cause exponential spacing */
  flex: none !important;
  /* Enable horizontal scrolling for many rounds */
  overflow-x: auto !important;
  padding-bottom: 10px !important;
  /* Override any inherited alignment */
  align-items: flex-start !important;
  justify-content: flex-start !important;
}

.round-robin-bracket .bracket {
  display: flex;
  flex-direction: column;
  gap: 20px;
  /* Override any inherited bracket styles */
  position: relative;
  width: 100%;
}

.round-robin-bracket .round {
  background: #f8f9fa !important;
  border-radius: 8px !important;
  padding: 15px !important;
  border: 1px solid #dee2e6 !important;
  /* Remove any inherited spacing that might cause exponential growth */
  margin: 0 !important;
  /* Override any inherited positioning */
  position: relative !important;
  /* Set fixed width for each round */
  min-width: 280px !important;
  max-width: 320px !important;
  flex-shrink: 0 !important;
  /* Ensure consistent spacing */
  margin-right: 20px !important;
  margin-bottom: 0 !important;
  /* Override any inherited flex properties */
  flex: none !important;
  /* Override any inherited display properties */
  display: flex !important;
  flex-direction: column !important;
  /* Grid alignment for matches */
  justify-content: flex-start !important;
  align-items: stretch !important;
}

.round-robin-bracket .round:last-child {
  margin-bottom: 0;
}

.round-robin-bracket .round h3 {
  margin: 0 0 15px 0;
  color: #495057;
  font-size: 1.1rem;
  text-align: center;
  background: #e9ecef;
  padding: 8px;
  border-radius: 4px;
  /* Make round headers sticky */
  position: sticky !important;
  top: 0 !important;
  z-index: 10 !important;
}

.round-robin-bracket .match {
  background: white !important;
  border: 1px solid #ced4da !important;
  border-radius: 6px !important;
  margin-bottom: 10px !important;
  cursor: pointer !important;
  transition: all 0.2s ease !important;
  /* Ensure consistent spacing */
  margin-left: 0 !important;
  margin-right: 0 !important;
  margin-top: 0 !important;
  /* Override any inherited positioning */
  position: relative !important;
  width: 100% !important;
  /* Override any inherited flex properties */
  flex: none !important;
  /* Override any inherited display properties */
  display: block !important;
  /* Compact match display */
  padding: 8px !important;
  /* Uniform height for grid alignment */
  min-height: 60px !important;
  height: auto !important;
  /* Remove any inherited margins that might cause misalignment */
  transform: none !important;
  top: auto !important;
  left: auto !important;
  right: auto !important;
  bottom: auto !important;
  /* Visual feedback for clickable matches */
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1) !important;
}

.round-robin-bracket .match:hover {
  border-color: #007bff !important;
  box-shadow: 0 2px 6px rgba(0, 123, 255, 0.2) !important;
  transform: translateY(-1px) !important;
}

.round-robin-bracket .match:last-child {
  margin-bottom: 0 !important;
}

/* Override any inherited round-specific styles that might cause misalignment */
.round-robin-bracket .round-1 .match,
.round-robin-bracket .round-2 .match,
.round-robin-bracket .round-3 .match,
.round-robin-bracket .round-4 .match,
.round-robin-bracket .round-5 .match,
.round-robin-bracket .round-6 .match,
.round-robin-bracket .round-7 .match,
.round-robin-bracket .round-8 .match {
  margin-top: 0 !important;
  margin-bottom: 10px !important;
  margin-left: 0 !important;
  margin-right: 0 !important;
  position: relative !important;
  top: auto !important;
  left: auto !important;
  right: auto !important;
  bottom: auto !important;
  transform: none !important;
}

.round-robin-bracket .match:hover {
  border-color: #007bff;
  box-shadow: 0 2px 4px rgba(0, 123, 255, 0.1);
}

/* Make all bracket matches clickable with hover effects */
.bracket .match,
.double-elimination-bracket .match {
  cursor: pointer;
  transition: all 0.2s ease;
}

.bracket .match:hover,
.double-elimination-bracket .match:hover {
  border-color: #007bff;
  box-shadow: 0 2px 6px rgba(0, 123, 255, 0.2);
  transform: translateY(-1px);
}



/* Ensure player boxes are also uniformly aligned */
.round-robin-bracket .match .player-box {
  display: flex !important;
  flex-direction: column !important;
  gap: 5px !important;
  width: 100% !important;
  margin: 0 !important;
  padding: 0 !important;
}

.round-robin-bracket .match .player-box span {
  margin: 0 !important;
  padding: 4px 8px !important;
  text-align: center !important;
  width: 100% !important;
  box-sizing: border-box !important;
}

.round-robin-bracket .match .player-box hr {
  margin: 2px 0 !important;
  border: none !important;
  border-top: 1px solid #dee2e6 !important;
}

.standings-section {
  margin-top: 30px;
  background: #f8f9fa;
  border-radius: 8px;
  padding: 20px;
  border: 1px solid #dee2e6;
}

.standings-header-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.standings-header-row h3 {
  margin: 0;
  color: #495057;
  font-size: 1.2rem;
}

.scoring-config-btn {
  background: #007bff;
  color: white;
  border: none;
  border-radius: 50%;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: background-color 0.2s;
}

.scoring-config-btn:hover {
  background: #0056b3;
}

.standings-section h3 {
  margin: 0 0 20px 0;
  color: #495057;
  text-align: center;
  font-size: 1.2rem;
}

.standings-table {
  background: white;
  border-radius: 6px;
  overflow: hidden;
  border: 1px solid #ced4da;
}

.standings-header,
.standings-row {
  display: grid;
  grid-template-columns: 60px 1fr 80px 80px 80px 100px;
  gap: 10px;
  padding: 12px 15px;
  align-items: center;
}

.standings-header {
  background: #e9ecef;
  font-weight: bold;
  color: #495057;
  border-bottom: 1px solid #ced4da;
}

.standings-row {
  border-bottom: 1px solid #f1f3f4;
  transition: background-color 0.2s ease;
}

.standings-row:last-child {
  border-bottom: none;
}

.standings-row:hover {
  background: #f8f9fa;
}

.standings-row.winner {
  background: #d4edda;
  font-weight: bold;
  color: #155724;
}

.standings-row.winner:hover {
  background: #c3e6cb;
}

.standings-header .rank,
.standings-header .player,
.standings-header .wins,
.standings-header .draws,
.standings-header .losses,
.standings-header .points {
  font-size: 0.9rem;
}

.standings-row .rank {
  font-weight: bold;
  text-align: center;
}

.standings-row .wins,
.standings-row .draws,
.standings-row .losses,
.standings-row .points {
  text-align: center;
}

/* Scoring Configuration Dialog */
.scoring-config-dialog {
  padding: 10px 0;
}

.scoring-option {
  margin-bottom: 20px;
}

.scoring-option label {
  display: block;
  margin-bottom: 8px;
  font-weight: bold;
  color: #495057;
}

.scoring-option input {
  width: 100%;
  padding: 8px 12px;
  border: 1px solid #ced4da;
  border-radius: 4px;
  font-size: 14px;
}

.scoring-option input:focus {
  outline: none;
  border-color: #007bff;
  box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
}

/* Tie indicator in match dialog */
.tie-indicator {
  background: #fff3e0;
  border: 1px solid #ffcc80;
  border-radius: 6px;
  padding: 12px;
  margin: 15px 0;
  display: flex;
  align-items: center;
  gap: 8px;
  color: #e65100;
}

.tie-indicator i {
  color: #fd7e14;
  font-size: 1.1rem;
}

.tie-warning {
  color: #dc3545;
  font-weight: bold;
}

.tie-warning-bg {
  background: #f8d7da !important;
  border: 1px solid #f5c6cb !important;
  color: #721c24 !important;
}

.tie-warning-bg i {
  color: #dc3545 !important;
}


</style>
