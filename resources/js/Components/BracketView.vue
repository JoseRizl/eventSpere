<script setup>
import { computed } from 'vue';
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

const ongoingRoundIdentifier = computed(() => {
    const bracket = props.bracket;
    if (!bracket || !bracket.matches) return null;

    if (bracket.type === 'Single Elimination' || bracket.type === 'Round Robin') {
        const rounds = bracket.matches;
        for (let i = 0; i < rounds.length; i++) {
            if (rounds[i] && rounds[i].some(match => match.status !== 'completed')) {
                const part = bracket.type === 'Single Elimination' ? 'single' : 'round_robin';
                return { part, roundIdx: i };
            }
        }
    } else if (bracket.type === 'Double Elimination') {
        // Check Winners bracket
        const winnersRounds = bracket.matches.winners;
        if (winnersRounds) {
            for (let i = 0; i < winnersRounds.length; i++) {
                if (winnersRounds[i] && winnersRounds[i].some(match => match.status !== 'completed')) {
                    return { part: 'winners', roundIdx: i };
                }
            }
        }

        // If winners bracket is complete, check Losers bracket
        const losersRounds = bracket.matches.losers;
        if (losersRounds) {
            for (let i = 0; i < losersRounds.length; i++) {
                if (losersRounds[i] && losersRounds[i].some(match => match.status !== 'completed')) {
                    return { part: 'losers', roundIdx: i };
                }
            }
        }

        // If both are complete, check Grand Finals
        const grandFinals = bracket.matches.grand_finals;
        if (grandFinals && grandFinals.some(match => match.status !== 'completed')) {
            return { part: 'grand_finals', roundIdx: 0 };
        }
    }

    return null; // All matches are completed
});

const isRoundOngoing = (bracketPart, roundIdx) => {
    if (!ongoingRoundIdentifier.value) return false;
    return ongoingRoundIdentifier.value.part === bracketPart && ongoingRoundIdentifier.value.roundIdx === roundIdx;
};

const bracketAnalysis = computed(() => {
    const bracket = props.bracket;
    const analysis = {
        singleElimWinnerId: null,
        deWinnersWinnerId: null,
        deLosersWinnerId: null,
        deGrandFinalsWinnerId: null,
        eliminatedPlayerIds: new Set(), // Players out of the tournament
        demotedPlayerIds: new Set(), // Players who lost in winners and went to losers
    };

    if (!bracket.matches) return analysis;

    if (bracket.type === 'Single Elimination') {
        const allMatches = bracket.matches.flat();
        allMatches.forEach(match => {
            if (match.status === 'completed' && match.loser_id) {
                analysis.eliminatedPlayerIds.add(match.loser_id);
            }
        });

        const finalRound = bracket.matches[bracket.matches.length - 1];
        if (finalRound && finalRound[0] && finalRound[0].status === 'completed') {
            analysis.singleElimWinnerId = finalRound[0].winner_id;
        }
    } else if (bracket.type === 'Double Elimination') {
        // Winners bracket: find winner and demoted players
        if (bracket.matches.winners && bracket.matches.winners.length > 0) {
            const finalWinnersMatch = bracket.matches.winners[bracket.matches.winners.length - 1][0];
            if (finalWinnersMatch && finalWinnersMatch.status === 'completed') {
                analysis.deWinnersWinnerId = finalWinnersMatch.winner_id;
            }
            bracket.matches.winners.flat().forEach(match => {
                if (match.status === 'completed' && match.loser_id) {
                    analysis.demotedPlayerIds.add(match.loser_id);
                }
            });
        }

        // Losers bracket: find winner and eliminated players
        if (bracket.matches.losers && bracket.matches.losers.length > 0) {
            const finalLosersMatch = bracket.matches.losers[bracket.matches.losers.length - 1][0];
            if (finalLosersMatch && finalLosersMatch.status === 'completed') {
                analysis.deLosersWinnerId = finalLosersMatch.winner_id;
            }
            bracket.matches.losers.flat().forEach(match => {
                if (match.status === 'completed' && match.loser_id) {
                    analysis.eliminatedPlayerIds.add(match.loser_id);
                }
            });
        }

        // Grand finals: find winner and final eliminated player
        if (bracket.matches.grand_finals && bracket.matches.grand_finals.length > 0) {
            const grandFinalsMatch = bracket.matches.grand_finals[0];
            if (grandFinalsMatch && grandFinalsMatch.status === 'completed') {
                analysis.deGrandFinalsWinnerId = grandFinalsMatch.winner_id;
                if (grandFinalsMatch.loser_id) {
                    analysis.eliminatedPlayerIds.add(grandFinalsMatch.loser_id);
                }
            }
        }
    }

    return analysis;
});

const getPlayerStyling = (player, otherPlayer, match, part) => {
    if (!player || player.name === 'BYE' || player.name === 'TBD' || !player.id) {
        return {
            'bye-text': player.name === 'BYE',
            'tbd-text': !player.name || player.name === 'TBD',
        };
    }

    const analysis = bracketAnalysis.value;
    const bracket = props.bracket;
    let styling = {
        'facing-bye': otherPlayer.name === 'BYE'
    };

    let isEliminatedOrDemoted = false;
    if (bracket.type === 'Single Elimination') {
        isEliminatedOrDemoted = analysis.eliminatedPlayerIds.has(player.id);
    } else if (bracket.type === 'Double Elimination') {
        if (part === 'winners') {
            isEliminatedOrDemoted = analysis.demotedPlayerIds.has(player.id);
        } else if (part === 'losers' || part === 'grand_finals') {
            isEliminatedOrDemoted = analysis.eliminatedPlayerIds.has(player.id);
        }
    }

    // If a player has been eliminated or demoted from their bracket section,
    // all their names in that section turn gray.
    if (isEliminatedOrDemoted) {
        styling['loser-name'] = true;
        return styling;
    }

    // If they are still active in this section, style based on the individual match result.
    if (match.status === 'completed' && match.winner_id === player.id) {
        styling['winner-name'] = true;
    }

    return styling;
};
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
        :class="['round', `round-${roundIdx + 1}`, { 'round-ongoing': isRoundOngoing('single', roundIdx) }]">
        <h3>
            {{ props.isFinalRound(bracketIndex, roundIdx) ? 'Final Round' : `Round ${roundIdx + 1}` }}
        </h3>

        <!-- Matches Display -->
        <div
            v-for="(match, matchIdx) in round"
            :key="matchIdx"
            :id="`match-${bracketIndex}-${roundIdx}-${matchIdx}`"
            :class="['match', (user?.role === 'Admin' || user?.role === 'SportsManager') ? 'cursor-pointer' : '']"
            @click="(user?.role === 'Admin' || user?.role === 'SportsManager') && props.openMatchDialog(bracketIndex, roundIdx, matchIdx, match, 'single')"
        >
            <div class="player-box">
                <span
                :class="getPlayerStyling(match.players[0], match.players[1], match, 'single')"
                >
                {{ truncate(match.players[0].name, { length: 13 }) }}{{ (match.players[0].name && match.players[0].name !== 'TBD' && match.players[0].name !== 'BYE') ? ' | ' + match.players[0].score : '' }}
                </span>
                <hr />
                <span
                :class="getPlayerStyling(match.players[1], match.players[0], match, 'single')"
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
            :class="['round', `round-${roundIdx + 1}`, { 'round-ongoing': isRoundOngoing('round_robin', roundIdx) }]">
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
        <h3>Upper Bracket</h3>
        <div class="bracket">
            <svg class="connection-lines">
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
            :class="['round', `round-${roundIdx + 1}`, { 'round-ongoing': isRoundOngoing('winners', roundIdx) }]">
            <h3>Round {{ roundIdx + 1 }}</h3>

            <div
                v-for="(match, matchIdx) in round"
                :key="`winners-${roundIdx}-${matchIdx}`"
                :id="`winners-match-${bracketIndex}-${roundIdx}-${matchIdx}`"
                :class="['match', (user?.role === 'Admin' || user?.role === 'SportsManager') ? 'cursor-pointer' : '']"
                @click="(user?.role === 'Admin' || user?.role === 'SportsManager') && props.openMatchDialog(bracketIndex, roundIdx, matchIdx, match, 'winners')"
            >
                <div class="player-box">
                <span
                    :class="getPlayerStyling(match.players[0], match.players[1], match, 'winners')"
                >
                    {{ truncate(match.players[0].name, { length: 13 }) }}{{ (match.players[0].name && match.players[0].name !== 'TBD' && match.players[0].name !== 'BYE') ? ' | ' + match.players[0].score : '' }}
                </span>
                <hr />
                <span
                    :class="getPlayerStyling(match.players[1], match.players[0], match, 'winners')"
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
        <h3>Lower Bracket</h3>
        <div class="bracket">
            <svg class="connection-lines">
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
            :class="['round', `round-${roundIdx + 1}`, { 'round-ongoing': isRoundOngoing('losers', roundIdx) }]">
            <h3>Round {{ roundIdx + 1 }}</h3>

            <div
                v-for="(match, matchIdx) in round"
                :key="`losers-${roundIdx}-${matchIdx}`"
                :id="`losers-match-${bracketIndex}-${roundIdx}-${matchIdx}`"
                :class="['match', (user?.role === 'Admin' || user?.role === 'SportsManager') ? 'cursor-pointer' : '']"
                @click="(user?.role === 'Admin' || user?.role === 'SportsManager') && props.openMatchDialog(bracketIndex, roundIdx, matchIdx, match, 'losers')"
            >
                <div class="player-box">
                <span
                    :class="getPlayerStyling(match.players[0], match.players[1], match, 'losers')"
                >
                    {{ truncate(match.players[0].name, { length: 13 }) }}{{ (match.players[0].name && match.players[0].name !== 'TBD' && match.players[0].name !== 'BYE') ? ' | ' + match.players[0].score : '' }}
                </span>
                <hr />
                <span
                    :class="getPlayerStyling(match.players[1], match.players[0], match, 'losers')"
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
        <div :class="{ 'round-ongoing': isRoundOngoing('grand_finals', 0) }">
            <h3>Finals</h3>
        </div>
        <div class="bracket">
            <svg class="connection-lines">
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
            :id="`grand-finals-match-${bracketIndex}-${matchIdx}`"
            :class="['match', (user?.role === 'Admin' || user?.role === 'SportsManager') ? 'cursor-pointer' : '']"
            @click="(user?.role === 'Admin' || user?.role === 'SportsManager') && props.openMatchDialog(bracketIndex, 0, matchIdx, match, 'grand_finals')"
            >
            <div class="player-box">
                <span
                :class="getPlayerStyling(match.players[0], match.players[1], match, 'grand_finals')"
                >
                {{ truncate(match.players[0].name, { length: 13 }) }}{{ (match.players[0].name && match.players[0].name !== 'TBD' && match.players[0].name !== 'BYE') ? ' | ' + match.players[0].score : '' }}
                </span>
                <hr />
                <span
                :class="getPlayerStyling(match.players[1], match.players[0], match, 'grand_finals')"
                >
                {{ truncate(match.players[1].name, { length: 13 }) }}{{ (match.players[1].name && match.players[1].name !== 'TBD' && match.players[1].name !== 'BYE') ? ' | ' + match.players[1].score : '' }}
                </span>
            </div>
            </div>
        </div>
        </div>
    </div>
</template>
