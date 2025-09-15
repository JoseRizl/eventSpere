<script setup>
import { truncate } from '@/utils/stringUtils.js';

const props = defineProps({
    bracket: {
        type: Object,
        required: true
    },
    bracketIndex: {
        type: Number,
        required: true
    },
    user: {
        type: Object,
        default: null
    },
    standingsRevision: {
        type: Number,
        required: true
    },
    isFinalRound: { type: Function, required: true },
    openMatchDialog: { type: Function, required: true },
    getRoundRobinStandings: { type: Function, required: true },
    isRoundRobinConcluded: { type: Function, required: true },
    openScoringConfigDialog: { type: Function, required: true }
});

</script>

<template>
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
            {{ props.isFinalRound(bracketIndex, roundIdx) ? 'Final Round' : `Round ${roundIdx + 1}` }}
        </h3>

        <!-- Matches Display -->
        <div
            v-for="(match, matchIdx) in round"
            :key="matchIdx"
            :id="`match-${roundIdx}-${matchIdx}`"
            :class="['match', (user?.role === 'Admin' || user?.role === 'SportsManager') ? 'cursor-pointer' : '']"
            @click="(user?.role === 'Admin' || user?.role === 'SportsManager') && props.openMatchDialog(bracketIndex, roundIdx, matchIdx, match, 'single')"
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
                {{ truncate(match.players[0].name, { length: 13 }) }}{{ (match.players[0].name && match.players[0].name !== 'TBD' && match.players[0].name !== 'BYE') ? ' | ' + match.players[0].score : '' }}
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
                {{ truncate(match.players[1].name, { length: 13 }) }}{{ (match.players[1].name && match.players[1].name !== 'TBD' && match.players[1].name !== 'BYE') ? ' | ' + match.players[1].score : '' }}
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
            @click="(user?.role === 'Admin' || user?.role === 'SportsManager') && props.openMatchDialog(bracketIndex, roundIdx, matchIdx, match, 'round_robin')"
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
                {{ truncate(match.players[0].name, { length: 15 }) }}{{ (match.players[0].name && match.players[0].name !== 'TBD' && match.players[0].name !== 'BYE') ? ' | ' + match.players[0].score : '' }}
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
                {{ truncate(match.players[1].name, { length: 15 }) }}{{ (match.players[1].name && match.players[1].name !== 'TBD' && match.players[1].name !== 'BYE') ? ' | ' + match.players[1].score : '' }}
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
            @click="props.openScoringConfigDialog"
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
            v-for="(player, index) in (props.standingsRevision, props.getRoundRobinStandings(bracketIndex))"
            :key="player.id"
            class="standings-row"
            :class="{ 'winner': index === 0 && props.isRoundRobinConcluded(bracketIndex) }"
            >
            <span class="rank">{{ index + 1 }}</span>
            <span class="player">
                {{ truncate(player.name, { length: 15 }) }}
                <i v-if="index === 0 && props.isRoundRobinConcluded(bracketIndex)" class="pi pi-crown winner-crown"></i>
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
                @click="(user?.role === 'Admin' || user?.role === 'SportsManager') && props.openMatchDialog(bracketIndex, roundIdx, matchIdx, match, 'winners')"
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
                    {{ truncate(match.players[0].name, { length: 13 }) }}{{ (match.players[0].name && match.players[0].name !== 'TBD' && match.players[0].name !== 'BYE') ? ' | ' + match.players[0].score : '' }}
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
                    {{ truncate(match.players[1].name, { length: 13 }) }}{{ (match.players[1].name && match.players[1].name !== 'TBD' && match.players[1].name !== 'BYE') ? ' | ' + match.players[1].score : '' }}
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
                @click="(user?.role === 'Admin' || user?.role === 'SportsManager') && props.openMatchDialog(bracketIndex, roundIdx, matchIdx, match, 'losers')"
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
                    {{ truncate(match.players[0].name, { length: 13 }) }}{{ (match.players[0].name && match.players[0].name !== 'TBD' && match.players[0].name !== 'BYE') ? ' | ' + match.players[0].score : '' }}
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
                    {{ truncate(match.players[1].name, { length: 13 }) }}{{ (match.players[1].name && match.players[1].name !== 'TBD' && match.players[1].name !== 'BYE') ? ' | ' + match.players[1].score : '' }}
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
            @click="(user?.role === 'Admin' || user?.role === 'SportsManager') && props.openMatchDialog(bracketIndex, 0, matchIdx, match, 'grand_finals')"
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
                {{ truncate(match.players[0].name, { length: 13 }) }}{{ (match.players[0].name && match.players[0].name !== 'TBD' && match.players[0].name !== 'BYE') ? ' | ' + match.players[0].score : '' }}
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
                {{ truncate(match.players[1].name, { length: 13 }) }}{{ (match.players[1].name && match.players[1].name !== 'TBD' && match.players[1].name !== 'BYE') ? ' | ' + match.players[1].score : '' }}
                </span>
            </div>
            </div>
        </div>
        </div>
    </div>
</template>
