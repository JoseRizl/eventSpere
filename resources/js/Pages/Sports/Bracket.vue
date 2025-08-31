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
  getRoundRobinStandings,
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
                    :class="['match', { 'highlight': isCurrentMatch(bracketIdx, roundIdx, matchIdx, 'round_robin') }]"
                    @click="navigateToMatch(bracketIdx, roundIdx, matchIdx, 'round_robin')"
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

              <!-- Round Robin Standings -->
              <div class="standings-section">
                <h3>Standings</h3>
                <div class="standings-table">
                  <div class="standings-header">
                    <span class="rank">Rank</span>
                    <span class="player">Player</span>
                    <span class="wins">Wins</span>
                    <span class="losses">Losses</span>
                    <span class="win-rate">Win Rate</span>
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
                    <span class="losses">{{ player.losses }}</span>
                    <span class="win-rate">{{ player.winRate }}%</span>
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
                  :disabled="bracket.type === 'Round Robin' ? currentMatchIndex === 0 :
                    bracket.type === 'Single Elimination' ? currentMatchIndex === 0 :
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
                  :disabled="bracket.type === 'Round Robin' ? currentMatchIndex >= getTotalMatches(bracketIdx) - 1 :
                    bracket.type === 'Single Elimination' ? currentMatchIndex >= getTotalMatches(bracketIdx) - 1 :
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

.round-robin-bracket .match.highlight {
  border-color: #007bff !important;
  box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.2) !important;
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
  grid-template-columns: 60px 1fr 80px 80px 100px;
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
.standings-header .losses,
.standings-header .win-rate {
  font-size: 0.9rem;
}

.standings-row .rank {
  font-weight: bold;
  text-align: center;
}

.standings-row .wins,
.standings-row .losses,
.standings-row .win-rate {
  text-align: center;
}

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
