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
        type: Number
    },
    isFinalRound: { type: Function, required: true },
    openMatchDialog: { type: Function, default: null },
    getRoundRobinStandings: { type: Function },
    isRoundRobinConcluded: { type: Function },
    openScoringConfigDialog: { type: Function, default: null }
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
        // Grand finals is now an array of rounds, just like winners/losers.
        // We check if any match in the first (and only) round is not completed.
        const grandFinalsRound = bracket.matches.grand_finals?.[0];
        if (grandFinalsRound && grandFinalsRound.some(match => match.status !== 'completed' && match.players[0].name !== 'TBD' && match.players[1].name !== 'TBD')) {
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
            // grand_finals is an array of rounds, so we get the first match of the first round.
            const grandFinalsMatch = bracket.matches.grand_finals[0]?.[0];
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

const getLineStroke = (line) => {
    return line.isWinnerPath ? '#007bff' : 'black'; // Blue for winner, black for default
};
const getLineStrokeWidth = (line) => {
    return line.isWinnerPath ? 3 : 2; // Thicker for winner
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

const getMatchStyle = (roundIdx) => {
    const matchHeight = 80;
    const gap = 20;
    const totalHeightOfOneMatchUnit = matchHeight + gap; // The space one match + its gap takes up.

    const margin = (Math.pow(2, roundIdx - 1) * totalHeightOfOneMatchUnit) - (matchHeight / 2) - (gap / 2);
    return {
        marginTop: roundIdx === 0 ? '0px' : `${margin}px`,
        marginBottom: roundIdx === 0 ? '0px' : `${margin}px`,
    };
};

const getLoserMatchStyle = (roundIdx) => {

    if (roundIdx < 2) {
        // For the first two rounds of the losers bracket, which often have a different structure,
        // we can use the same logic as the winners bracket for consistent initial spacing.
        return getMatchStyle(roundIdx);
    }

    // For subsequent rounds, the structure is more regular.
    // The number of matches often halves every two rounds.
    const matchHeight = 80;
    const gap = 20;
    const totalHeightOfOneMatchUnit = matchHeight + gap;

    // We adjust the exponent to account for the slower reduction in matches.
    const margin = (Math.pow(1.7, Math.floor(roundIdx / 2))) * totalHeightOfOneMatchUnit - (totalHeightOfOneMatchUnit / 2);

    return {
        marginTop: `${margin}px`,
        marginBottom: `${margin}px`,
    };
};

const updateBracketLines = () => {
    if (!bracketContentRef.value) return;

    const bracket = props.bracket;
    const bracketIdx = props.bracketIndex;

    // Determine winner IDs for different parts of the bracket
    let tournamentWinnerId = null;
    let upperBracketWinnerId = null;
    let lowerBracketWinnerId = null;

    if (bracket.type === 'Single Elimination') {
        tournamentWinnerId = bracketAnalysis.value.singleElimWinnerId;
    } else if (bracket.type === 'Double Elimination') {
        tournamentWinnerId = bracketAnalysis.value.deGrandFinalsWinnerId;
        upperBracketWinnerId = bracketAnalysis.value.deWinnersWinnerId;
        lowerBracketWinnerId = bracketAnalysis.value.deLosersWinnerId;
    }

    // Create sets of all matches won by the relevant winners
    const overallWinnerMatchIds = new Set();
    if (tournamentWinnerId) { // This is for the final champion's path
        const allMatches = [
            ...(bracket.matches.winners?.flat() || []),
            ...(bracket.matches.losers?.flat() || []),
            ...(bracket.matches.grand_finals?.flat() || []),
            ...(bracket.type === 'Single Elimination' ? bracket.matches.flat() : [])
        ];
        allMatches.forEach(m => { if (m.winner_id === tournamentWinnerId) overallWinnerMatchIds.add(m.id); });
    }

    const upperBracketWinnerMatchIds = new Set();
    if (upperBracketWinnerId) { // This is for the upper bracket winner's path
        (bracket.matches.winners?.flat() || []).forEach(m => {
            if (m.winner_id === upperBracketWinnerId) upperBracketWinnerMatchIds.add(m.id);
        });
    }

    const lowerBracketWinnerMatchIds = new Set();
    if (lowerBracketWinnerId) { // This is for the lower bracket winner's path
        (bracket.matches.losers?.flat() || []).forEach(m => {
            if (m.winner_id === lowerBracketWinnerId) lowerBracketWinnerMatchIds.add(m.id);
        });
    }

    const drawLinesFor = (matches, svgSelector, idPrefix, part) => {
        const svgEl = bracketContentRef.value.querySelector(svgSelector);
        if (!svgEl || !matches) return [];

        const newLines = [];
        const bracketContainer = svgEl.closest('.bracket');
        if (!bracketContainer) return [];

        const containerRect = bracketContainer.getBoundingClientRect();
        svgEl.setAttribute('viewBox', `0 0 ${containerRect.width} ${containerRect.height}`);

        const isWinnerLine = (match, part) => {
            const resolveWinnerId = (match) => {
            if (!match) return null;
            if (match.winner_id) return match.winner_id;
            // If explicit winner_id is missing, try to infer from scores (only if both scores exist)
            const p0 = match.players?.[0];
            const p1 = match.players?.[1];
            if (!p0 || !p1) return null;
            if (typeof p0.score === 'number' && typeof p1.score === 'number') {
                return p0.score >= p1.score ? p0.id : p1.id;
            }
            return null;
            };
            // For the winners bracket, also highlight the path of the upper bracket winner
            if (part === 'winners' && upperBracketWinnerMatchIds.has(match.id)) {
                return true;
            }
            // For the losers bracket, also highlight the path of the lower bracket winner
            if (part === 'losers' && lowerBracketWinnerMatchIds.has(match.id)) {
                return true;
            }
            // For the grand finals, the line from the upper bracket winner should be highlighted
            if (part === 'grand_finals' && upperBracketWinnerMatchIds.has(match.id)) {
                return true;
            }
            return false;
        };
        for (let round = 0; round < matches.length - 1; round++) {
            matches[round].forEach((match, i) => {
                const fromEl = document.getElementById(`${idPrefix}-${bracketIdx}-${round}-${i}`);

                const nextRoundMatches = matches[round + 1];
                if (!nextRoundMatches) return;

                let nextMatchIdx;
                // If the number of matches is the same, the winner progresses to the same match index.
                if (matches[round].length === nextRoundMatches.length) {
                    nextMatchIdx = i;
                } else {
                    nextMatchIdx = Math.floor(i / 2); // Standard progression (2 matches feed into 1).
                }

                const toEl = document.getElementById(`${idPrefix}-${bracketIdx}-${round + 1}-${nextMatchIdx}`);

                if (fromEl) {
                    const toMatch = nextRoundMatches[nextMatchIdx];
                    const fromRect = fromEl.getBoundingClientRect();
                    const fromPoint = screenToSVG(svgEl, fromRect.right, fromRect.top + fromRect.height / 2);

                    if (toEl && toMatch) {
                        const toRect = toEl.getBoundingClientRect();
                        const toPoint = screenToSVG(svgEl, toRect.left, toRect.top + toRect.height / 2);
                        const winnerPath = isWinnerLine(match, toMatch);

                        newLines.push({
                            x1: fromPoint.x, y1: fromPoint.y, x2: (fromPoint.x + toPoint.x) / 2, y2: fromPoint.y,
                            isWinnerPath: winnerPath
                        });
                        newLines.push({
                            x1: (fromPoint.x + toPoint.x) / 2, y1: fromPoint.y, x2: (fromPoint.x + toPoint.x) / 2, y2: toPoint.y,
                            isWinnerPath: winnerPath
                        });
                        newLines.push({
                            x1: (fromPoint.x + toPoint.x) / 2, y1: toPoint.y, x2: toPoint.x, y2: toPoint.y,
                            isWinnerPath: winnerPath
                        });
                    } else {
                        // Draw a short line to indicate continuation if the next match element isn't found
                        const toPoint = { x: fromPoint.x + 20, y: fromPoint.y };
                        newLines.push({ x1: fromPoint.x, y1: fromPoint.y, x2: toPoint.x, y2: toPoint.y, isWinnerPath: false });
                    }
                }
            });
        }
        return newLines;
    };

    // Use an async function to handle positioning and line drawing in order
    const runUpdates = async () => {
        if (bracket.type === 'Single Elimination') {
            dynamicLines.value.single = drawLinesFor(bracket.matches, '.connection-lines', 'match', 'single');
        } else if (bracket.type === 'Double Elimination') {
            dynamicLines.value.winners = drawLinesFor(bracket.matches.winners, '.winners .connection-lines', 'winners-match', 'winners');
            dynamicLines.value.losers = drawLinesFor(bracket.matches.losers, '.losers .connection-lines', 'losers-match', 'losers');
            dynamicLines.value.finals = []; // Ensure finals lines are always empty
        }
    };
    runUpdates();
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
    <div v-if="bracket.type === 'Single Elimination'" class="bracket-scroll-container">
        <div class="bracket single-elimination" ref="bracketContentRef">
            <svg class="connection-lines">
            <line
                v-for="(line, i) in dynamicLines.single"
                :key="`dynamic-line-${i}`"
                :x1="line.x1"
                :y1="line.y1"
                :x2="line.x2"
                :y2="line.y2"
                :stroke="getLineStroke(line)"
                :stroke-width="getLineStrokeWidth(line)"
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
                :data-match-id="match.id"
                :style="getMatchStyle(roundIdx)"
                :class="['match', (user && (user.role === 'Admin' || user.role === 'TournamentManager')) ? 'cursor-pointer' : '']"
                @click="props.openMatchDialog && props.openMatchDialog(bracketIndex, roundIdx, matchIdx, match, 'single')"
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
            :class="['match', (user && (user.role === 'Admin' || user.role === 'TournamentManager')) ? 'cursor-pointer' : '']"
            @click="props.openMatchDialog && props.openMatchDialog(bracketIndex, roundIdx, matchIdx, match, 'round_robin')"
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
        </div>
        <div class="standings-table">
            <div class="standings-header">
                <span class="rank">Rank</span>
                <span class="player">Player</span>
                <span class="wins">Wins</span>
                <span class="draws">Draws</span>
                <span class="losses">Losses</span>
                <span class="points flex items-center">
                    Points
                    <button v-if="user?.role === 'Admin' || user?.role === 'TournamentManager'"
                        @click="props.openScoringConfigDialog(bracketIndex)"
                        class="scoring-config-btn ml-2"
                        title="Configure scoring system">
                        <i class="pi pi-cog"></i>
                    </button>
                </span>
            </div>
            <div
            v-for="(player, index) in (props.standingsRevision, props.getRoundRobinStandings(bracketIndex))"
            :key="player.id"
            class="standings-row"
            :class="{ 'winner': index === 0 && props.isRoundRobinConcluded(bracketIndex) }"
            >
            <span class="rank" data-label="Rank">{{ index + 1 }}</span>
            <span class="player" data-label="Player">
                {{ truncate(player.name, { length: 15 }) }}
                <i v-if="index === 0 && props.isRoundRobinConcluded(bracketIndex)" class="pi pi-crown winner-crown"></i>
            </span>
            <span class="wins" data-label="Wins">{{ player.wins }}</span>
            <span class="draws" data-label="Draws">{{ player.draws }}</span>
            <span class="losses" data-label="Losses">{{ player.losses }}</span>
            <span class="points" data-label="Points">{{ player.points }}</span>
            </div>
        </div>
        </div>
    </div>

    <!-- Double Elimination Display -->
    <div v-else-if="bracket.type === 'Double Elimination'" class="bracket-scroll-container">
        <div class="double-elimination-bracket" ref="bracketContentRef">
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
                    :stroke="getLineStroke(line)"
                    :stroke-width="getLineStrokeWidth(line)"
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
                    :data-match-id="match.id"
                    :style="getMatchStyle(roundIdx)"
                    :class="['match', (user && (user.role === 'Admin' || user.role === 'TournamentManager')) ? 'cursor-pointer' : '']"
                    @click="props.openMatchDialog && props.openMatchDialog(bracketIndex, roundIdx, matchIdx, match, 'winners')"
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
                    :stroke="getLineStroke(line)"
                    :stroke-width="getLineStrokeWidth(line)"
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
                    :data-match-id="match.id"
                    :style="getLoserMatchStyle(roundIdx)"
                    :class="['match', (user && (user.role === 'Admin' || user.role === 'TournamentManager')) ? 'cursor-pointer' : '']"
                    @click="props.openMatchDialog && props.openMatchDialog(bracketIndex, roundIdx, matchIdx, match, 'losers')"
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
                    :stroke="getLineStroke(line)"
                    :stroke-width="getLineStrokeWidth(line)"
                    />
                </g>
                </svg>

                <div v-for="(round, roundIdx) in bracket.matches.grand_finals" :key="`gf-round-${roundIdx}`" class="round">
                    <div v-for="(match, matchIdx) in round" :key="`grand-finals-${matchIdx}`"
                        :id="`grand-finals-match-${bracketIndex}-${matchIdx}`"
                        :data-match-id="match.id"
                        :class="['match', (user && (user.role === 'Admin' || user.role === 'TournamentManager')) ? 'cursor-pointer' : '']"
                        @click="props.openMatchDialog && props.openMatchDialog(bracketIndex, roundIdx, matchIdx, match, 'grand_finals')"
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
        </div>
    </div>
</template>
