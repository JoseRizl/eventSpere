<script setup>
import { computed, ref, onMounted, onUnmounted, watch, nextTick } from 'vue';
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

const bracketContentRef = ref(null);
let resizeObserver = null;

const dynamicLines = ref({ single: [], winners: [], losers: [], finals: [] });

const ongoingRoundIdentifiers = computed(() => {
    const bracket = props.bracket;
    const identifiers = {
        single: null,
        round_robin: null,
        winners: null,
        losers: null,
        grand_finals: null,
    };

    if (!bracket || !bracket.matches) return identifiers;

    if (bracket.type === 'Single Elimination' || bracket.type === 'Round Robin') {
        const rounds = bracket.matches;
        const part = bracket.type === 'Single Elimination' ? 'single' : 'round_robin';
        for (let i = 0; i < rounds.length; i++) {
            if (rounds[i] && rounds[i].some(match => match.status !== 'completed' && match.players[0].name !== 'TBD' && match.players[1].name !== 'TBD')) {
                identifiers[part] = i;
                break; // Found the first incomplete round
            }
        }
    } else if (bracket.type === 'Double Elimination') {
        const findOngoingRound = (rounds) => {
            if (!rounds) return null;
            for (let i = 0; i < rounds.length; i++) {
                if (rounds[i] && rounds[i].some(match => match.status !== 'completed' && match.players[0].name !== 'TBD' && match.players[1].name !== 'TBD')) {
                    return i;
                }
            }
            return null;
        };

        identifiers.winners = findOngoingRound(bracket.matches.winners);
        identifiers.losers = findOngoingRound(bracket.matches.losers);
        // Grand finals is not an array of rounds, but a single array of matches.
        // We check if any match in it is not completed.
        if (bracket.matches.grand_finals && bracket.matches.grand_finals.some(match => match.status !== 'completed' && match.players[0].name !== 'TBD' && match.players[1].name !== 'TBD')) {
            identifiers.grand_finals = 0;
        }
    }

    return identifiers;
});

const isRoundOngoing = (bracketPart, roundIdx) => {
    const ongoingIndex = ongoingRoundIdentifiers.value[bracketPart];
    return ongoingIndex !== null && ongoingIndex === roundIdx;
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

const getRoundRobinPlayerStyling = (player, otherPlayer, match) => {
    if (!player || player.name === 'BYE' || player.name === 'TBD' || !player.id) {
        return {
            'bye-text': player.name === 'BYE',
            'tbd-text': !player.name || player.name === 'TBD',
        };
    }

    if (match.status !== 'completed') {
        return { 'facing-bye': otherPlayer.name === 'BYE' };
    }

    return {
        'winner-name': match.winner_id === player.id && !match.is_tie,
        'loser-name': match.loser_id === player.id && !match.is_tie,
        'tie': match.is_tie,
    };
};

const screenToSVG = (svg, x, y) => {
    if (!svg) return { x: 0, y: 0 };
    const p = svg.createSVGPoint();
    p.x = x;
    p.y = y;
    const ctm = svg.getScreenCTM();
    if (ctm) {
        return p.matrixTransform(ctm.inverse());
    }
    return p;
};

const updateBracketLines = () => {
    if (!bracketContentRef.value) return;

    const bracket = props.bracket;
    const bracketIdx = props.bracketIndex;

    const drawLinesFor = (matches, svgSelector, idPrefix) => {
        const svgEl = bracketContentRef.value.querySelector(svgSelector);
        if (!svgEl || !matches) return [];

        const newLines = [];
        const bracketContainer = svgEl.closest('.bracket');
        if (!bracketContainer) return [];

        const containerRect = bracketContainer.getBoundingClientRect();
        svgEl.setAttribute('viewBox', `0 0 ${containerRect.width} ${containerRect.height}`);

        for (let round = 0; round < matches.length - 1; round++) {
            matches[round].forEach((match, i) => {
                const fromEl = document.getElementById(`${idPrefix}-${bracketIdx}-${round}-${i}`);
                const toEl = document.getElementById(`${idPrefix}-${bracketIdx}-${round + 1}-${Math.floor(i / 2)}`);

                if (fromEl && toEl) {
                    const fromRect = fromEl.getBoundingClientRect();
                    const toRect = toEl.getBoundingClientRect();

                    const fromPoint = screenToSVG(svgEl, fromRect.right, fromRect.top + fromRect.height / 2);
                    const toPoint = screenToSVG(svgEl, toRect.left, toRect.top + toRect.height / 2);

                    newLines.push({
                        x1: fromPoint.x, y1: fromPoint.y,
                        x2: (fromPoint.x + toPoint.x) / 2, y2: fromPoint.y
                    });
                    newLines.push({
                        x1: (fromPoint.x + toPoint.x) / 2, y1: fromPoint.y,
                        x2: (fromPoint.x + toPoint.x) / 2, y2: toPoint.y
                    });
                    newLines.push({
                        x1: (fromPoint.x + toPoint.x) / 2, y1: toPoint.y,
                        x2: toPoint.x, y2: toPoint.y
                    });
                }
            });
        }
        return newLines;
    };

    if (bracket.type === 'Single Elimination') {
        dynamicLines.value.single = drawLinesFor(bracket.matches, '.connection-lines', 'match');
    } else if (bracket.type === 'Double Elimination') {
        dynamicLines.value.winners = drawLinesFor(bracket.matches.winners, '.winners .connection-lines', 'winners-match');
        dynamicLines.value.losers = drawLinesFor(bracket.matches.losers, '.losers .connection-lines', 'losers-match');

        const svgEl = bracketContentRef.value.querySelector('.grand-finals .connection-lines');
        if (svgEl) {
            const newFinalsLines = [];
            const winnerBracketFinalMatchEl = document.getElementById(`winners-match-${bracketIdx}-${bracket.matches.winners.length - 1}-0`);
            const loserBracketFinalMatchEl = document.getElementById(`losers-match-${bracketIdx}-${bracket.matches.losers.length - 1}-0`);
            const grandFinalMatchEl = document.getElementById(`grand-finals-match-${bracketIdx}-0`);

            const drawFinalsLine = (fromEl, toEl, yOffsetMultiplier) => {
                if (fromEl && toEl) {
                    const fromRect = fromEl.getBoundingClientRect();
                    const toRect = toEl.getBoundingClientRect();
                    const fromPoint = screenToSVG(svgEl, fromRect.right, fromRect.top + fromRect.height / 2);
                    const toPoint = screenToSVG(svgEl, toRect.left, toRect.top + toRect.height * yOffsetMultiplier);
                    const midX = (fromPoint.x + toPoint.x) / 2;
                    newFinalsLines.push({ x1: fromPoint.x, y1: fromPoint.y, x2: midX, y2: fromPoint.y });
                    newFinalsLines.push({ x1: midX, y1: fromPoint.y, x2: midX, y2: toPoint.y });
                    newFinalsLines.push({ x1: midX, y1: toPoint.y, x2: toPoint.x, y2: toPoint.y });
                }
            };

            drawFinalsLine(winnerBracketFinalMatchEl, grandFinalMatchEl, 0.25);
            drawFinalsLine(loserBracketFinalMatchEl, grandFinalMatchEl, 0.75);
            dynamicLines.value.finals = newFinalsLines;
        }
    }
};

onMounted(() => {
    if (props.bracket.type !== 'Round Robin' && bracketContentRef.value) {
        resizeObserver = new ResizeObserver(() => nextTick(updateBracketLines));
        resizeObserver.observe(bracketContentRef.value);
        nextTick(updateBracketLines);
    }
});

onUnmounted(() => {
    if (resizeObserver) resizeObserver.disconnect();
});

watch(() => props.bracket, () => nextTick(updateBracketLines), { deep: true });
</script>

<template>
    <div v-if="bracket.type === 'Single Elimination'" class="bracket" ref="bracketContentRef">
        <svg class="connection-lines">
        <line
            v-for="(line, i) in dynamicLines.single"
            :key="`dynamic-line-${i}`"
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
                :class="getRoundRobinPlayerStyling(match.players[0], match.players[1], match)"
                >
                {{ truncate(match.players[0].name, { length: 15 }) }}{{ (match.players[0].name && match.players[0].name !== 'TBD' && match.players[0].name !== 'BYE') ? ' | ' + match.players[0].score : '' }}
                </span>
                <hr />
                <span
                :class="getRoundRobinPlayerStyling(match.players[1], match.players[0], match)"
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
            @click="props.openScoringConfigDialog(bracketIndex)"
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
    <div v-else-if="bracket.type === 'Double Elimination'" class="double-elimination-bracket" ref="bracketContentRef">
        <!-- Winners Bracket -->
        <div class="bracket-section winners">
        <h3>Upper Bracket</h3>
        <div class="bracket">
            <svg class="connection-lines">
            <g v-for="(line, i) in dynamicLines.winners" :key="`dynamic-winners-${i}`">
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
            <g v-for="(line, i) in dynamicLines.losers" :key="`dynamic-losers-${i}`">
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
            <g v-for="(line, i) in dynamicLines.finals" :key="`dynamic-finals-${i}`">
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
