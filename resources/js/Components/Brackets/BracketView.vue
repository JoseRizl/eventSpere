<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import html2canvas from 'html2canvas';
import { truncate } from '@/utils/stringUtils.js';
import { useToast } from '@/composables/useToast.js';

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
    openScoringConfigDialog: { type: Function, default: null },
    openTiebreakerDialog: { type: Function, default: null },
    dismissTiebreakerNotice: { type: Function, default: null },
    dismissedTiebreakerNotices: { type: Set, default: () => new Set() },
    playerColors: { type: Object, required: true }
});

const emit = defineEmits(['repopulate-colors']);

const bracketContentRef = ref(null);
let resizeObserver = null;

const dynamicLines = ref({ single: [], winners: [], losers: [], finals: [], dropouts: [] });

const { showSuccess, showError } = useToast();

// Player name editing
const showPlayerEditModal = ref(false);

// Zoom functionality for elimination brackets
const defaultDesktopZoom = 0.7;
const defaultMobileZoom = 0.35; // Smaller zoom to fit bracket on mobile
const zoomLevel = ref(defaultDesktopZoom);
const minZoom = computed(() => 0.5 * (window.innerWidth <= 768 ? defaultMobileZoom : defaultDesktopZoom));
const maxZoom = computed(() => 1.5 * (window.innerWidth <= 768 ? defaultMobileZoom : defaultDesktopZoom));
const zoomStep = computed(() => 0.1 * (window.innerWidth <= 768 ? defaultMobileZoom : defaultDesktopZoom));

const zoomIn = () => {
    if (zoomLevel.value < maxZoom.value) {
        zoomLevel.value = Math.min(maxZoom.value, zoomLevel.value + zoomStep.value);
        recalculateLinesAfterZoom();
    }
};

const zoomOut = () => {
    if (zoomLevel.value > minZoom.value) {
        zoomLevel.value = Math.max(minZoom.value, zoomLevel.value - zoomStep.value);
        recalculateLinesAfterZoom();
    }
};

const recalculateLinesAfterZoom = () => {
    setTimeout(() => {
        nextTick(updateBracketLines);
        nextTick(alignConsolationWithFinals);
    }, 50);
};

const resetZoom = () => {
    adjustZoomForScreenSize();
    // Recalculate lines after zoom with a small delay to ensure zoom is applied
    setTimeout(() => {
        nextTick(() => {
            updateBracketLines();
            alignConsolationWithFinals();
        });
    }, 50);
};

const adjustZoomForScreenSize = () => {
    if (window.innerWidth <= 768) {
        zoomLevel.value = defaultMobileZoom;
    } else {
        zoomLevel.value = defaultDesktopZoom;
    }
};

const printBracket = () => {
    if (!bracketContentRef.value) return;

    // Temporarily reset zoom to get a high-quality capture
    const originalZoom = zoomLevel.value;
    zoomLevel.value = 1; // Use 1 for the capture to get a clear image

    nextTick(() => {
        html2canvas(bracketContentRef.value, {
            scale: 2, // Increase scale for better resolution
            useCORS: true,
            onclone: (document) => {
                // You can apply print-specific styles here if needed
            }
        }).then(canvas => {
            const printWindow = window.open('', '_blank');
            if (printWindow) {
                printWindow.document.write('<html><head><title>Print Bracket</title>');
                printWindow.document.write('<style>body { margin: 0; } img { width: 100%; }</style>');
                printWindow.document.write('</head><body>');
                printWindow.document.write('<img src="' + canvas.toDataURL() + '" />');
                printWindow.document.write('</body></html>');
                printWindow.document.close();
                printWindow.focus();
                setTimeout(() => {
                    printWindow.print();
                }, 500); // Delay to ensure image loads
            }
            // Restore original zoom level
            zoomLevel.value = originalZoom;
        });
    });
};

const zoomPercentage = computed(() => {
    const base = window.innerWidth <= 768 ? defaultMobileZoom : defaultDesktopZoom;
    return Math.round((zoomLevel.value / base) * 100);
});

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

// Check if Single Elimination has consolation match (3rd place)
const hasConsolationMatch = computed(() => {
    if (props.bracket.type !== 'Single Elimination' || !props.bracket.matches) return false;
    const finalRound = props.bracket.matches[props.bracket.matches.length - 1];
    if (!finalRound) return false;
    return finalRound.some(m => m.bracket_type === 'consolation');
});

// Check if Single Elimination should use split bracket layout (12+ players always)
const shouldSplitBracket = computed(() => {
    if (props.bracket.type !== 'Single Elimination' || !props.bracket.matches) return false;

    // Count total players in first round (excluding BYEs)
    const firstRound = props.bracket.matches[0];
    if (!firstRound) return false;

    const playerCount = firstRound.reduce((count, match) => {
        const p1 = match.players[0]?.name !== 'BYE' && match.players[0]?.name !== 'TBD' ? 1 : 0;
        const p2 = match.players[1]?.name !== 'BYE' && match.players[1]?.name !== 'TBD' ? 1 : 0;
        return count + p1 + p2;
    }, 0);

    // Use split layout for 12+ players (regardless of consolation match)
    return playerCount >= 12;
});

const playerCount = computed(() => {
    if (props.bracket.type !== 'Single Elimination' || !props.bracket.matches) return 0;

    const firstRound = props.bracket.matches[0];
    if (!firstRound) return 0;

    return firstRound.reduce((count, match) => {
        const p1 = match.players[0]?.name !== 'BYE' && match.players[0]?.name !== 'TBD' ? 1 : 0;
        const p2 = match.players[1]?.name !== 'BYE' && match.players[1]?.name !== 'TBD' ? 1 : 0;
        return count + p1 + p2;
    }, 0);
});

// Split bracket into A and B sides with proper semifinal tracking
const splitBracketData = computed(() => {
    if (!shouldSplitBracket.value) return null;

    const matches = props.bracket.matches;
    const totalRounds = matches.length;

    // For a proper split: each half progresses independently until semifinals
    // Semifinals = second-to-last round (2 matches)
    // Finals = last round (1 match)

    const semifinalRoundIdx = totalRounds - 2;
    const finalRoundIdx = totalRounds - 1;

    // Split all rounds INCLUDING semifinals
    const bracketA = [];
    const bracketB = [];

    for (let roundIdx = 0; roundIdx <= semifinalRoundIdx; roundIdx++) {
        const round = matches[roundIdx];

        if (roundIdx === semifinalRoundIdx) {
            // Semifinals: first match to A, second match to B
            if (round[0]) bracketA.push([round[0]]);
            if (round[1]) bracketB.push([round[1]]);
        } else {
            // Earlier rounds: split by position
            const midPoint = Math.ceil(round.length / 2);

            // Top half goes to Bracket A
            bracketA.push(round.slice(0, midPoint));
            // Bottom half goes to Bracket B
            bracketB.push(round.slice(midPoint));
        }
    }

    // Finals and Consolation (both in the final round)
    const finalRound = matches[finalRoundIdx] || [];
    const finals = finalRound.find(m => m.bracket_type !== 'consolation') || finalRound[0];
    const consolation = finalRound.find(m => m.bracket_type === 'consolation');

    // Get semifinal matches
    const semifinals = matches[semifinalRoundIdx] || [];

    return {
        bracketA,
        bracketB,
        semifinals: { a: semifinals[0], b: semifinals[1] },
        finals: finals,
        consolation: consolation,
        semifinalRoundIdx,
        finalRoundIdx
    };
});

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

const generatePlayerColor = (playerName) => {
    if (!playerName || playerName === 'TBD' || playerName === 'BYE' || playerName.includes('Winner') || playerName.includes('Loser')) {
        return null;
    }
    return props.playerColors[playerName] || null;
};

const getMatchResultIndicator = (player, match) => {
    if (match.status !== 'completed' || !player || !player.id || player.name === 'BYE' || player.name === 'TBD') {
        return '';
    }
    if (match.winner_id === player.id) {
        return ' | W';
    }
    if (match.loser_id === player.id) {
        return ' | L';
    }
    return '';
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

// Round Robin Grid System
const roundRobinPlayers = computed(() => {
    if (props.bracket.type !== 'Round Robin' || !props.bracket.matches) return [];

    const playerMap = new Map();
    const allMatches = props.bracket.matches.flat();
    const players = [];

    // First pass: collect all players and extract their numbers
    allMatches.forEach(match => {
        match.players.forEach(player => {
            if (player.name !== 'BYE' && player.name !== 'TBD' && !playerMap.has(player.id)) {
                // Extract number from player name (e.g., "Player 1" -> 1)
                const playerNumber = parseInt(player.name.replace(/\D/g, '')) || 0;
                playerMap.set(player.id, {
                    id: player.id,
                    name: player.name,
                    number: playerNumber
                });
                players.push(playerMap.get(player.id));
            }
        });
    });

    // Sort players by their extracted number
    return players.sort((a, b) => a.number - b.number);
});

const roundRobinGrid = computed(() => {
    if (props.bracket.type !== 'Round Robin' || !props.bracket.matches) return [];

    const players = roundRobinPlayers.value;
    const allMatches = props.bracket.matches.flat();

    // Create a grid matrix
    const grid = players.map(rowPlayer => {
        return players.map(colPlayer => {
            // Diagonal cells (same player)
            if (rowPlayer.id === colPlayer.id) {
                return { type: 'diagonal', player: rowPlayer };
            }

            // Find match between these two players
            const match = allMatches.find(m => {
                const p1 = m.players[0];
                const p2 = m.players[1];
                return (p1.id === rowPlayer.id && p2.id === colPlayer.id) ||
                       (p1.id === colPlayer.id && p2.id === rowPlayer.id);
            });

            if (match) {
                // Determine if rowPlayer won, lost, or tied
                const rowPlayerInMatch = match.players.find(p => p.id === rowPlayer.id);
                const colPlayerInMatch = match.players.find(p => p.id === colPlayer.id);

                return {
                    type: 'match',
                    match: match,
                    rowPlayer: rowPlayerInMatch,
                    colPlayer: colPlayerInMatch,
                    result: match.status === 'completed'
                        ? (match.is_tie ? 'tie' : (match.winner_id === rowPlayer.id ? 'win' : 'loss'))
                        : 'pending'
                };
            }

            return { type: 'empty' };
        });
    });

    return grid;
});

const getGridCellClass = (cell) => {
    if (cell.type === 'diagonal') return 'grid-cell-diagonal';
    if (cell.type === 'empty') return 'grid-cell-empty';
    if (cell.type === 'match') {
        const classes = ['grid-cell-match'];
        if (cell.match.status === 'completed') {
            if (cell.result === 'win') classes.push('grid-cell-win');
            else if (cell.result === 'loss') classes.push('grid-cell-loss');
            else if (cell.result === 'tie') classes.push('grid-cell-tie');
        } else {
            classes.push('grid-cell-pending');
        }
        return classes.join(' ');
    }
    return '';
};

const getGridCellContent = (cell) => {
    if (cell.type === 'diagonal') return '—';
    if (cell.type === 'empty') return '';
    if (cell.type === 'match') {
        if (cell.match.status === 'completed') {
            if (cell.result === 'win') return '✓';
            if (cell.result === 'loss') return '✕';
            if (cell.result === 'tie') return '=';
        }
        return '—';
    }
    return '';
};

const findMatchIndices = (match) => {
    if (!props.bracket.matches) return null;
    for (let roundIdx = 0; roundIdx < props.bracket.matches.length; roundIdx++) {
        const matchIdx = props.bracket.matches[roundIdx].findIndex(m => m.id === match.id);
        if (matchIdx !== -1) {
            return { roundIdx, matchIdx };
        }
    }
    return null;
};

const getLineStroke = (line) => {
    return line.isWinnerPath ? '#007bff' : 'black'; // Blue for winner, black for default
};
const getLineStrokeWidth = (line) => {
    return line.isWinnerPath ? 3 : 2; // Thicker for winner
};

// Calculate cumulative winner match number across all rounds
const getWinnerMatchNumber = (roundIdx, matchIdx) => {
    if (!props.bracket.matches?.winners) return matchIdx + 1;

    let cumulativeCount = 0;

    // Add all matches from previous rounds
    for (let i = 0; i < roundIdx; i++) {
        cumulativeCount += props.bracket.matches.winners[i].length;
    }

    // Add current match index
    cumulativeCount += matchIdx + 1;

    return cumulativeCount;
};

// Get 3rd place (loser of lower bracket final)
const getThirdPlaceWinner = () => {
    // Get 3rd place (loser of lower bracket final)
    if (!props.bracket.matches?.losers) return 'TBD';

    const lastLoserRound = props.bracket.matches.losers[props.bracket.matches.losers.length - 1];
    if (!lastLoserRound || lastLoserRound.length === 0) return 'TBD';

    const finalMatch = lastLoserRound[0];
    if (finalMatch.status !== 'completed') return 'TBD';

    // The loser of the lower bracket final gets 3rd place
    const loserId = finalMatch.loser_id;
    if (!loserId) return 'TBD';

    const loser = finalMatch.players.find(p => p.id === loserId);
    return loser ? loser.name : 'TBD';
};

const lowerBracketFinalMatchNumber = computed(() => {
    if (!props.bracket.matches?.losers || props.bracket.matches.losers.length === 0) return 'X';
    const lastLoserRound = props.bracket.matches.losers[props.bracket.matches.losers.length - 1];
    if (!lastLoserRound || lastLoserRound.length === 0) return 'X';
    const finalMatch = lastLoserRound[0];
    return finalMatch?.match_number || 'X';
});

const upperBracketFinalMatchNumber = computed(() => {
    if (!props.bracket.matches?.winners || props.bracket.matches.winners.length === 0) return 'X';
    const lastWinnerRound = props.bracket.matches.winners[props.bracket.matches.winners.length - 1];
    if (!lastWinnerRound || lastWinnerRound.length === 0) return 'X';
    const finalMatch = lastWinnerRound[0];
    return finalMatch?.match_number || 'X';
});

const hasTiedRank1Players = computed(() => {
    if (!props.bracket.matches || props.bracket.type !== 'Round Robin') return false;
    if (!props.isRoundRobinConcluded || !props.isRoundRobinConcluded(props.bracketIndex)) return false;
    if (props.bracket.tiebreaker_data && Object.keys(props.bracket.tiebreaker_data).length > 0) {
        // If tiebreakers are set, no need to show the notice
        return false;
    }

    const allStats = roundRobinPlayers.value.map(player => {
        let wins = 0, losses = 0, draws = 0;
        props.bracket.matches.forEach(round => {
            round.forEach(match => {
                if (match.status !== 'completed') return;
                const p = match.players.find(pl => pl.id === player.id);
                if (!p) return;
                if (match.is_tie) {
                    draws++;
                } else if (match.winner_id === player.id) {
                    wins++;
                } else if (match.loser_id === player.id) {
                    losses++;
                }
            });
        });
        const total = wins + losses + draws;
        const winRatio = total > 0 ? wins / total : 0;
        return { id: player.id, wins, winRatio };
    });

    allStats.sort((a, b) => {
        if (b.winRatio !== a.winRatio) return b.winRatio - a.winRatio;
        return b.wins - a.wins;
    });

    // Check if there are multiple players with same stats as rank 1
    if (allStats.length < 2) return false;
    const first = allStats[0];
    const second = allStats[1];
    return first.winRatio === second.winRatio && first.wins === second.wins;
});

// Get player stats for Round Robin (W, L, D, Rank)
const getPlayerStats = (playerId) => {
    if (!props.bracket.matches || props.bracket.type !== 'Round Robin') {
        return { wins: 0, losses: 0, draws: 0, rank: '-' };
    }

    let wins = 0, losses = 0, draws = 0;

    // Count wins, losses, draws
    props.bracket.matches.forEach(round => {
        round.forEach(match => {
            if (match.status !== 'completed') return;

            const player = match.players.find(p => p.id === playerId);
            if (!player) return;

            if (match.is_tie) {
                draws++;
            } else if (match.winner_id === playerId) {
                wins++;
            } else if (match.loser_id === playerId) {
                losses++;
            }
        });
    });

    // Calculate win ratio for ranking
    const totalGames = wins + losses + draws;
    const winRatio = totalGames > 0 ? wins / totalGames : 0;

    // Get all players' stats for ranking
    const allStats = roundRobinPlayers.value.map(player => {
        let pWins = 0, pLosses = 0, pDraws = 0;
        props.bracket.matches.forEach(round => {
            round.forEach(match => {
                if (match.status !== 'completed') return;
                const p = match.players.find(pl => pl.id === player.id);
                if (!p) return;
                if (match.is_tie) {
                    pDraws++;
                } else if (match.winner_id === player.id) {
                    pWins++;
                } else if (match.loser_id === player.id) {
                    pLosses++;
                }
            });
        });
        const pTotal = pWins + pLosses + pDraws;
        const pWinRatio = pTotal > 0 ? pWins / pTotal : 0;
        return { id: player.id, wins: pWins, winRatio: pWinRatio };
    });

    // Sort by win ratio (descending), then by wins (descending), then by quotient if available
    allStats.sort((a, b) => {
        if (b.winRatio !== a.winRatio) return b.winRatio - a.winRatio;
        if (b.wins !== a.wins) return b.wins - a.wins;

        // Use tiebreaker quotient if available
        if (props.bracket.tiebreaker_data) {
            const aQuotient = props.bracket.tiebreaker_data[a.id]?.quotient || 0;
            const bQuotient = props.bracket.tiebreaker_data[b.id]?.quotient || 0;
            if (bQuotient !== aQuotient) return bQuotient - aQuotient;
        }

        return a.losses - b.losses; // Fewer losses is better
    });

    // Find rank with tie handling
    let rank = 1;
    let currentRank = 1;
    for (let i = 0; i < allStats.length; i++) {
        if (i > 0) {
            const prev = allStats[i - 1];
            const curr = allStats[i];

            // Get quotients for comparison
            const prevQuotient = props.bracket.tiebreaker_data?.[prev.id]?.quotient || 0;
            const currQuotient = props.bracket.tiebreaker_data?.[curr.id]?.quotient || 0;

            // If stats are different, increment rank
            if (prev.winRatio !== curr.winRatio ||
                prev.wins !== curr.wins ||
                prevQuotient !== currQuotient) {
                currentRank = i + 1;
            }
            // Otherwise, keep the same rank (tied)
        }
        if (allStats[i].id === playerId) {
            rank = currentRank;
            break;
        }
    }

    return { wins, losses, draws, rank: rank > 0 ? rank : '-' };
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
    // With the new split-cell layout (no result cell), we use simpler spacing
    const matchGroupHeight = 72; // Height of player cells (32px * 2 + 8px gap)
    const gap = 15;

    if (roundIdx === 0) {
        return { marginTop: '0px', marginBottom: '0px' };
    }

    // Calculate spacing to align with previous round matches
    const spacing = Math.pow(2, roundIdx - 1) * (matchGroupHeight + gap) - (matchGroupHeight / 2);
    return {
        marginTop: `${spacing}px`,
        marginBottom: `${spacing}px`,
    };
};

const getLoserMatchStyle = (roundIdx) => {
    // Losers bracket alternates between two patterns:
    // - Even rounds (LR1, LR3, LR5...): Receive losers from winners bracket, spacing increases
    // - Odd rounds (LR2, LR4, LR6...): Receive from previous losers round (1 parent), maintain alignment

    // LR1 (roundIdx 0): Receives from R1, uses WR2 spacing
    // LR2 (roundIdx 1): Receives from LR1 (1 parent), same vertical position as LR1
    // LR3 (roundIdx 2): Receives from WR2, uses WR3 spacing
    // LR4 (roundIdx 3): Receives from LR3 (1 parent), same vertical position as LR3

    if (roundIdx % 2 === 0) {
        // Even rounds: LR1, LR3, LR5... - these receive from winners bracket
        // LR1 (roundIdx 0) → WR2 spacing (roundIdx 1)
        // LR3 (roundIdx 2) → WR3 spacing (roundIdx 2)
        // LR5 (roundIdx 4) → WR4 spacing (roundIdx 3)
        const equivalentWinnersRound = (roundIdx / 2) + 1;
        return getMatchStyle(equivalentWinnersRound);
    } else {
        // Odd rounds: LR2, LR4, LR6... - these receive from previous losers round (1 parent)
        // Should maintain same vertical alignment as previous losers round
        // LR2 uses same spacing as LR1, LR4 uses same spacing as LR3, etc.
        const previousLoserRound = roundIdx - 1;
        return getLoserMatchStyle(previousLoserRound);
    }
};

// Position losers rounds horizontally - LR1 and LR2 align with R1, then shift right
const getLoserRoundStyle = (roundIdx) => {
    // LR1 (roundIdx 0) = no margin (aligns with R1/initial)
    // LR2 (roundIdx 1) = no margin (stays with LR1, connected by straight line)
    // LR3 (roundIdx 2) = shift right to align with WR2
    // LR4 (roundIdx 3) = stays with LR3
    // LR5 (roundIdx 4) = shift right to align with WR3
    // Pattern: shift happens at even roundIdx >= 2

    if (roundIdx < 2) {
        // LR1 and LR2 stay at initial position (no margin)
        return {};
    }

    // For LR3+, calculate position based on which winners round they align with
    // LR3-4 align with WR2 (position 1), LR5-6 align with WR3 (position 2), etc.
    const position = Math.floor(roundIdx / 2);
    const roundWidth = 220; // Width of one round column (180px + 40px gap)

    return {
        marginLeft: `${position * roundWidth}px`
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
            // Check if using split bracket layout (with consolation)
            if (shouldSplitBracket.value) {
                // Use unified SVG for split bracket
                const unifiedSvg = bracketContentRef.value?.querySelector('.unified-connection-lines');
                if (!unifiedSvg) {
                    console.warn('Unified SVG not found for split bracket');
                    return;
                }

                const svgRect = unifiedSvg.getBoundingClientRect();
                dynamicLines.value.single = [];

                // Draw lines for all rounds in the bracket
                for (let roundIdx = 0; roundIdx < bracket.matches.length - 1; roundIdx++) {
                    const currentRound = bracket.matches[roundIdx];
                    const nextRound = bracket.matches[roundIdx + 1];

                    currentRound.forEach((match, matchIdx) => {
                        // Determine next match index
                        let nextMatchIdx;

                        if (roundIdx === bracket.matches.length - 2) {
                            // Semifinals to finals/consolation
                            const consolationMatch = nextRound.find(m => m.bracket_type === 'consolation');
                            if (consolationMatch) {
                                // Has consolation - first SF goes to finals, both go to consolation for losers
                                const finalsMatchIdx = nextRound.findIndex(m => m.bracket_type !== 'consolation');
                                nextMatchIdx = finalsMatchIdx >= 0 ? finalsMatchIdx : 0;
                            } else {
                                nextMatchIdx = 0; // Both semifinals go to finals
                            }
                        } else {
                            // Normal progression
                            nextMatchIdx = Math.floor(matchIdx / 2);
                        }

                        const fromEl = bracketContentRef.value?.querySelector(`#match-${props.bracketIndex}-${roundIdx}-${matchIdx}`);
                        const toEl = bracketContentRef.value?.querySelector(`#match-${props.bracketIndex}-${roundIdx + 1}-${nextMatchIdx}`);

                        if (!fromEl || !toEl) return;

                        const fromRect = fromEl.getBoundingClientRect();
                        const toRect = toEl.getBoundingClientRect();

                        // Adjust for zoom level
                        const zoom = zoomLevel.value;
                        const fromCenterY = (fromRect.top - svgRect.top + fromRect.height / 2) / zoom;
                        const toCenterY = (toRect.top - svgRect.top + toRect.height / 2) / zoom;
                        const fromRightX = (fromRect.right - svgRect.left) / zoom;
                        const toLeftX = (toRect.left - svgRect.left) / zoom;

                        // 3-segment elbow line
                        const midX = (fromRightX + toLeftX) / 2;

                        dynamicLines.value.single.push(
                            { x1: fromRightX, y1: fromCenterY, x2: midX, y2: fromCenterY },
                            { x1: midX, y1: fromCenterY, x2: midX, y2: toCenterY },
                            { x1: midX, y1: toCenterY, x2: toLeftX, y2: toCenterY }
                        );
                    });
                }
            } else {
                // Standard single elimination - filter out consolation matches from line drawing
                const matchesWithoutConsolation = bracket.matches.map(round =>
                    round.filter(m => m.bracket_type !== 'consolation')
                );
                dynamicLines.value.single = drawLinesFor(matchesWithoutConsolation, '.connection-lines', 'match', 'single');
            }
        } else if (bracket.type === 'Double Elimination') {
            // For unified layout, use single SVG for all lines
            const unifiedSvg = bracketContentRef.value?.querySelector('.unified-connection-lines');
            if (!unifiedSvg) {
                console.warn('Unified SVG not found');
                return;
            }
            //console.log('Drawing lines for Double Elimination bracket');

            const svgRect = unifiedSvg.getBoundingClientRect();

            // Winners lines - connect from game indicator to cell (always displayed)
            dynamicLines.value.winners = [];
            for (let round = 0; round < bracket.matches.winners.length - 1; round++) {
                bracket.matches.winners[round].forEach((match, i) => {
                    const nextMatchIdx = Math.floor(i / 2);
                    const nextPlayerIdx = i % 2; // 0 for top, 1 for bottom

                    // Connect from game indicator to next match's player cell
                    const fromEl = bracketContentRef.value?.querySelector(`#winners-match-${props.bracketIndex}-${round}-${i} .match-label`);
                    const toEl = bracketContentRef.value?.querySelector(`#winners-match-${props.bracketIndex}-${round + 1}-${nextMatchIdx}-p${nextPlayerIdx}`);

                    if (!fromEl || !toEl) return;

                    const fromRect = fromEl.getBoundingClientRect();
                    const toRect = toEl.getBoundingClientRect();
                    const zoom = zoomLevel.value;

                    const fromCenterY = (fromRect.top - svgRect.top + fromRect.height / 2) / zoom;
                    const toCenterY = (toRect.top - svgRect.top + toRect.height / 2) / zoom;
                    const fromRightX = (fromRect.right - svgRect.left) / zoom;
                    const toLeftX = (toRect.left - svgRect.left) / zoom;
                    const midX = (fromRightX + toLeftX) / 2;

                    // Check if this is the winner's path for styling
                    const winnerId = match.winner_id;
                    const isWinnerPath = winnerId && match.players[nextPlayerIdx].id === winnerId;

                    dynamicLines.value.winners.push(
                        { x1: fromRightX, y1: fromCenterY, x2: midX, y2: fromCenterY, isWinnerPath },
                        { x1: midX, y1: fromCenterY, x2: midX, y2: toCenterY, isWinnerPath },
                        { x1: midX, y1: toCenterY, x2: toLeftX, y2: toCenterY, isWinnerPath }
                    );
                });
            }

            // Losers lines (flow left) - connect from game indicator to cell (always displayed)
            dynamicLines.value.losers = [];
            for (let round = 0; round < bracket.matches.losers.length - 1; round++) {
                bracket.matches.losers[round].forEach((match, i) => {
                    let nextMatchIdx = (round % 2 === 0) ? i : Math.floor(i / 2);
                    const nextPlayerIdx = (round % 2 === 0) ? 0 : (i % 2);

                    // Connect from game indicator to next match's player cell
                    const fromEl = bracketContentRef.value?.querySelector(`#losers-match-${props.bracketIndex}-${round}-${i} .match-label`);
                    const toEl = bracketContentRef.value?.querySelector(`#losers-match-${props.bracketIndex}-${round + 1}-${nextMatchIdx}-p${nextPlayerIdx}`);

                    if (!fromEl || !toEl) return;

                    const fromRect = fromEl.getBoundingClientRect();
                    const toRect = toEl.getBoundingClientRect();
                    const zoom = zoomLevel.value;

                    const fromCenterY = (fromRect.top - svgRect.top + fromRect.height / 2) / zoom;
                    const toCenterY = (toRect.top - svgRect.top + toRect.height / 2) / zoom;
                    const fromLeftX = (fromRect.left - svgRect.left) / zoom;
                    const toRightX = (toRect.right - svgRect.left) / zoom;
                    const midX = (fromLeftX + toRightX) / 2;

                    // Check if this is the winner's path for styling
                    const winnerId = match.winner_id;
                    const isWinnerPath = winnerId && match.players[nextPlayerIdx].id === winnerId;

                    dynamicLines.value.losers.push(
                        { x1: fromLeftX, y1: fromCenterY, x2: midX, y2: fromCenterY, isWinnerPath },
                        { x1: midX, y1: fromCenterY, x2: midX, y2: toCenterY, isWinnerPath },
                        { x1: midX, y1: toCenterY, x2: toRightX, y2: toCenterY, isWinnerPath }
                    );
                });
            }

            // Draw lines for all R1 matches to LR1 (always displayed, cell to cell)
            const wr1Matches = bracket.matches.winners[0] || [];
            wr1Matches.forEach((match, i) => {
                // Find which LR1 match this R1 match feeds into
                const numLoserMatches = bracket.matches.losers[0]?.length || 0;

                // Formula for 1v3, 2v4, 5v7, 6v8 pairing
                const loserMatchIdx = Math.floor(i / 4) * 2 + (i % 2);
                const playerPosition = Math.floor((i % 4) / 2);

                // console.log(`R1 Match ${i}: loserMatchIdx=${loserMatchIdx}, playerPosition=${playerPosition}`);

                if (loserMatchIdx >= numLoserMatches) {
                    console.warn(`R1 Match ${i} has no LR1 connection`);
                    return;
                }

                const loserMatch = bracket.matches.losers[0]?.[loserMatchIdx];
                if (!loserMatch) {
                    console.warn(`LR1 Match ${loserMatchIdx} not found`);
                    return;
                }

                // Connect from R1 MATCH CENTER (between cells) to LR1 player cell
                const fromEl = bracketContentRef.value?.querySelector(`#winners-match-${props.bracketIndex}-0-${i}`);
                const toEl = bracketContentRef.value?.querySelector(`#losers-match-${props.bracketIndex}-0-${loserMatchIdx}-p${playerPosition}`);

                if (!fromEl) console.warn(`From element not found: #winners-match-${props.bracketIndex}-0-${i}`);
                if (!toEl) console.warn(`To element not found: #losers-match-${props.bracketIndex}-0-${loserMatchIdx}-p${playerPosition}`);

                if (!fromEl || !toEl) return;

                const fromRect = fromEl.getBoundingClientRect();
                const toRect = toEl.getBoundingClientRect();
                const zoom = zoomLevel.value;

                const fromCenterY = (fromRect.top - svgRect.top + fromRect.height / 2) / zoom;
                const toCenterY = (toRect.top - svgRect.top + toRect.height / 2) / zoom;
                const fromLeftX = (fromRect.left - svgRect.left) / zoom;
                const toRightX = (toRect.right - svgRect.left) / zoom;

                //console.log(`Drawing R1-${i} to LR1-${loserMatchIdx}: (${fromLeftX}, ${fromCenterY}) -> (${toRightX}, ${toCenterY})`);

                // Always use diagonal line from R1 cell to LR1 cell
                dynamicLines.value.losers.push(
                    { x1: fromLeftX, y1: fromCenterY, x2: toRightX, y2: toCenterY }
                );
            });

            // Finals lines with elbow routing
            dynamicLines.value.finals = [];
            const lastWinnerRound = bracket.matches.winners.length - 1;
            const lastLoserRound = bracket.matches.losers.length - 1;

            // Winner to Finals (go right 30px, then down to finals level, then straight to finals)
            const winnerEl = bracketContentRef.value?.querySelector(`#winners-match-${props.bracketIndex}-${lastWinnerRound}-0`);
            const finalsEl = bracketContentRef.value?.querySelector(`#grand-finals-match-${props.bracketIndex}-0`);
            if (winnerEl && finalsEl) {
                const winnerRect = winnerEl.getBoundingClientRect();
                const finalsRect = finalsEl.getBoundingClientRect();
                // Adjust for zoom level
                const zoom = zoomLevel.value;
                const fromCenterY = (winnerRect.top - svgRect.top + winnerRect.height / 2) / zoom;
                const toCenterY = (finalsRect.top - svgRect.top + finalsRect.height / 2) / zoom;
                const fromRightX = (winnerRect.right - svgRect.left) / zoom;
                const toRightX = (finalsRect.right - svgRect.left) / zoom;

                // Simple 3-segment path: right 30px, down to finals level, straight to finals
                const elbowX = fromRightX + 30;

                dynamicLines.value.finals.push(
                    { x1: fromRightX, y1: fromCenterY, x2: elbowX, y2: fromCenterY }, // horizontal right 30px
                    { x1: elbowX, y1: fromCenterY, x2: elbowX, y2: toCenterY }, // vertical down to finals level
                    { x1: elbowX, y1: toCenterY, x2: toRightX, y2: toCenterY } // horizontal straight to finals
                );
            }

            // Loser to Finals (go left 30px, then down to finals level, then straight to finals)
            const loserEl = bracketContentRef.value?.querySelector(`#losers-match-${props.bracketIndex}-${lastLoserRound}-0`);
            if (loserEl && finalsEl) {
                const loserRect = loserEl.getBoundingClientRect();
                const finalsRect = finalsEl.getBoundingClientRect();
                // Adjust for zoom level
                const zoom = zoomLevel.value;
                const fromCenterY = (loserRect.top - svgRect.top + loserRect.height / 2) / zoom;
                const toCenterY = (finalsRect.top - svgRect.top + finalsRect.height / 2) / zoom;
                const fromLeftX = (loserRect.left - svgRect.left) / zoom;
                const toLeftX = (finalsRect.left - svgRect.left) / zoom;

                // Simple 3-segment path: left 30px, down to finals level, straight to finals
                const elbowX = fromLeftX - 30;

                dynamicLines.value.finals.push(
                    { x1: fromLeftX, y1: fromCenterY, x2: elbowX, y2: fromCenterY }, // horizontal left 30px
                    { x1: elbowX, y1: fromCenterY, x2: elbowX, y2: toCenterY }, // vertical down to finals level
                    { x1: elbowX, y1: toCenterY, x2: toLeftX, y2: toCenterY } // horizontal straight to finals
                );
            }

            // Dotted dropout lines from winners bracket to losers bracket
            // These show where losers from upper bracket rounds drop into lower bracket
            // Connect from game indicator to their destination cell in lower bracket (always displayed)
            // SPLIT-CROSS PATTERN: Matches are paired and crossed within pairs
            dynamicLines.value.dropouts = [];

            // Map winners rounds to losers rounds based on the image pattern
            // WR2 losers → LR2 (position 1), WR3 losers → LR4 (position 1), WR4 losers → LR6 (position 1), etc.
            for (let winRound = 1; winRound < bracket.matches.winners.length; winRound++) {
                const loserRound = winRound * 2 - 1; // WR2→LR2, WR3→LR4, WR4→LR6

                if (loserRound < bracket.matches.losers.length) {
                    const numWinMatches = bracket.matches.winners[winRound].length;

                    bracket.matches.winners[winRound].forEach((winMatch, winIdx) => {
                        // SPLIT-CROSS PATTERN: Pairs cross within themselves
                        // For 4 matches: pairs (0,1) and (2,3) each cross
                        // 0→1, 1→0, 2→3, 3→2
                        // For 2 matches: simple cross
                        // 0→1, 1→0
                        let loserIdx;
                        if (numWinMatches === 1) {
                            loserIdx = 0;
                        } else {
                            // Cross within pairs
                            const pairIdx = Math.floor(winIdx / 2);
                            const posInPair = winIdx % 2;
                            const pairStart = pairIdx * 2;

                            if (posInPair === 0) {
                                // First in pair goes to second position
                                loserIdx = pairStart + 1;
                            } else {
                                // Second in pair goes to first position
                                loserIdx = pairStart;
                            }
                        }

                        if (loserIdx < bracket.matches.losers[loserRound].length) {
                            // Connect from game indicator to destination cell (always displayed)
                            const fromEl = bracketContentRef.value?.querySelector(`#winners-match-${props.bracketIndex}-${winRound}-${winIdx} .match-label`);
                            const toEl = bracketContentRef.value?.querySelector(`#losers-match-${props.bracketIndex}-${loserRound}-${loserIdx}-p1`);

                            if (fromEl && toEl) {
                                const fromRect = fromEl.getBoundingClientRect();
                                const toRect = toEl.getBoundingClientRect();
                                const zoom = zoomLevel.value;

                                const fromX = (fromRect.left - svgRect.left) / zoom;
                                const fromY = (fromRect.top - svgRect.top + fromRect.height / 2) / zoom;
                                const toX = (toRect.right - svgRect.left) / zoom;
                                const toY = (toRect.top - svgRect.top + toRect.height / 2) / zoom;

                                // Simple routing like reference image
                                // Go left a bit, then diagonal to destination
                                const horizontalOffset = 40;
                                const midX = fromX - horizontalOffset;

                                dynamicLines.value.dropouts.push(
                                    // Horizontal segment from source
                                    { x1: fromX, y1: fromY, x2: midX, y2: fromY, isDotted: true },
                                    // Diagonal to destination
                                    { x1: midX, y1: fromY, x2: toX, y2: toY, isDotted: true }
                                );
                            }
                        }
                    });
                }
            }
        }
    };
    runUpdates();
};

// Align consolation match with finals match
const alignConsolationWithFinals = () => {
    if (props.bracket.type !== 'Single Elimination' || !hasConsolationMatch.value || shouldSplitBracket.value) {
        return;
    }

    nextTick(() => {
        const finalsMatch = bracketContentRef.value?.querySelector('.finals-match-standard');
        const consolationMatch = bracketContentRef.value?.querySelector('.consolation-match-standard');

        if (finalsMatch && consolationMatch) {
            const finalsRect = finalsMatch.getBoundingClientRect();
            const consolationRect = consolationMatch.getBoundingClientRect();
            const containerRect = bracketContentRef.value.getBoundingClientRect();

            // Calculate the offset needed to align consolation with finals
            const finalsTop = finalsRect.top - containerRect.top;
            const consolationTop = consolationRect.top - containerRect.top;
            const offset = finalsTop - consolationTop;

            // Apply the offset
            consolationMatch.style.marginTop = `${offset}px`;
        }
    });
};

onMounted(() => {
    adjustZoomForScreenSize();
    window.addEventListener('resize', adjustZoomForScreenSize);
    if (props.bracket.type !== 'Round Robin' && bracketContentRef.value) {
        resizeObserver = new ResizeObserver(() => {
            nextTick(updateBracketLines);
            alignConsolationWithFinals();
        });
        resizeObserver.observe(bracketContentRef.value);
        // Add delay to ensure DOM is fully rendered
        setTimeout(() => {
            nextTick(updateBracketLines);
            alignConsolationWithFinals();
        }, 100);
    }
});

onUnmounted(() => {
    window.removeEventListener('resize', adjustZoomForScreenSize);
    if (resizeObserver) resizeObserver.disconnect();
});

watch(() => props.bracket, () => {
    emit('repopulate-colors'); // Ask parent to clear and repopulate colors
    nextTick(updateBracketLines);
    alignConsolationWithFinals();
}, { deep: true });

</script>

<template>
    <div :class="['bracket-view-wrapper', bracket.type === 'Single Elimination' ? 'single-elim' : '']">
        <!-- Zoom Controls - Placed outside the scroll container for fixed positioning -->
        <div v-if="bracket.type === 'Single Elimination' || bracket.type === 'Double Elimination'" class="zoom-controls">
            <button @click="zoomOut" :disabled="zoomLevel <= minZoom" class="zoom-btn" title="Zoom Out">
                <i class="pi pi-minus"></i>
            </button>
            <button @click="resetZoom" class="zoom-btn zoom-reset" title="Reset Zoom">
                {{ zoomPercentage }}%
            </button>
            <button @click="zoomIn" :disabled="zoomLevel >= maxZoom" class="zoom-btn" title="Zoom In">
                <i class="pi pi-plus"></i>
            </button>
            <!-- <button v-if="user && (user.role === 'Admin' || user.role === 'TournamentManager')" @click="printBracket" class="zoom-btn" title="Print Bracket">
                <i class="pi pi-print"></i>
            </button> -->
        </div>

    <!-- Single Elimination - Split Bracket Layout (with consolation) -->
    <div v-if="bracket.type === 'Single Elimination' && shouldSplitBracket" class="bracket-scroll-container">
        <div class="bracket-zoom-wrapper" :style="{ zoom: zoomLevel }">
            <div class="split-bracket-container-horizontal" ref="bracketContentRef">
                <svg class="connection-lines unified-connection-lines">
                <line
                    v-for="(line, i) in dynamicLines.single"
                    :key="`split-line-${i}`"
                    :x1="line.x1"
                    :y1="line.y1"
                    :x2="line.x2"
                    :y2="line.y2"
                    stroke="#0077B3"
                    stroke-width="2"
                />
            </svg>

            <div class="split-bracket-header">
                <h3>Single Elimination Tournament</h3>
            </div>

            <!-- Horizontal Layout: A flows right, B flows left, meet at finals -->
            <div class="horizontal-split-layout">
                <!-- Left Side: Bracket A (flows left to right) -->
                <div class="bracket-side bracket-a-side">
                    <div class="section-label">BRACKET A</div>
                    <div class="bracket single-elimination bracket-flow-right">
                        <div v-for="(round, roundIdx) in splitBracketData.bracketA" :key="`a-${roundIdx}`"
                            :class="['round', `round-${roundIdx + 1}`]">
                            <h4>{{ roundIdx === splitBracketData.bracketA.length - 1 ? 'SF' : `R${roundIdx + 1}` }}</h4>
                            <div
                                v-for="(match, matchIdx) in round"
                                :key="`a-${roundIdx}-${matchIdx}`"
                                :id="`match-${bracketIndex}-${roundIdx}-${matchIdx}`"
                                :data-match-id="match.id"
                                :style="getMatchStyle(roundIdx)"
                                :class="['match', (user && (user.role === 'Admin' || user.role === 'TournamentManager')) ? 'cursor-pointer' : '']"
                                @click="props.openMatchDialog && props.openMatchDialog(bracketIndex, roundIdx, matchIdx, match, 'single')"
                            >
                                <div class="player-box">
                                    <span :class="getPlayerStyling(match.players[0], match.players[1], match, 'single')">
                                        {{ truncate(match.players[0].name, { length: 13 }) }}{{ getMatchResultIndicator(match.players[0], match) }}
                                    </span>
                                    <hr />
                                    <span :class="getPlayerStyling(match.players[1], match.players[0], match, 'single')">
                                        {{ truncate(match.players[1].name, { length: 13 }) }}{{ getMatchResultIndicator(match.players[1], match) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Center: Finals and Consolation -->
                <div class="bracket-center-column">
                    <!-- Finals -->
                    <div class="finals-container">
                        <div class="section-label">FINALS</div>
                        <div class="bracket finals-bracket" v-if="splitBracketData.finals">
                            <div class="round finals-round">
                                <div
                                    :id="`match-${bracketIndex}-${splitBracketData.finalRoundIdx}-0`"
                                    :data-match-id="splitBracketData.finals.id"
                                    :class="['match', 'finals-match', (user && (user.role === 'Admin' || user.role === 'TournamentManager')) ? 'cursor-pointer' : '']"
                                    @click="props.openMatchDialog && props.openMatchDialog(bracketIndex, splitBracketData.finalRoundIdx, 0, splitBracketData.finals, 'single')"
                                >
                                    <div class="player-box">
                                        <span :class="getPlayerStyling(splitBracketData.finals.players[0], splitBracketData.finals.players[1], splitBracketData.finals, 'single')">
                                            {{ truncate(splitBracketData.finals.players[0].name, { length: 13 }) }}{{ getMatchResultIndicator(splitBracketData.finals.players[0], splitBracketData.finals) }}
                                        </span>
                                        <hr />
                                        <span :class="getPlayerStyling(splitBracketData.finals.players[1], splitBracketData.finals.players[0], splitBracketData.finals, 'single')">
                                            {{ truncate(splitBracketData.finals.players[1].name, { length: 13 }) }}{{ getMatchResultIndicator(splitBracketData.finals.players[1], splitBracketData.finals) }}
                                        </span>
                                    </div>
                                </div>
                                <div class="placement-labels">
                                    <div class="placement-label champion">Champion</div>
                                    <!-- <div class="placement-label second">Second</div> -->
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Consolation (3rd Place) -->
                    <div class="consolation-container" v-if="splitBracketData.consolation">
                        <div class="section-label">3RD PLACE</div>
                        <div class="bracket consolation-bracket">
                            <div class="round consolation-round">
                                <div
                                    :id="`match-${bracketIndex}-${splitBracketData.finalRoundIdx}-${bracket.matches[splitBracketData.finalRoundIdx].indexOf(splitBracketData.consolation)}`"
                                    :data-match-id="splitBracketData.consolation.id"
                                    :class="['match', 'consolation-match', (user && (user.role === 'Admin' || user.role === 'TournamentManager')) ? 'cursor-pointer' : '']"
                                    @click="props.openMatchDialog && props.openMatchDialog(bracketIndex, splitBracketData.finalRoundIdx, bracket.matches[splitBracketData.finalRoundIdx].indexOf(splitBracketData.consolation), splitBracketData.consolation, 'single')"
                                >
                                    <div class="player-box">
                                        <span :class="getPlayerStyling(splitBracketData.consolation.players[0], splitBracketData.consolation.players[1], splitBracketData.consolation, 'single')">
                                            {{ truncate(splitBracketData.consolation.players[0].name, { length: 13 }) }}{{ getMatchResultIndicator(splitBracketData.consolation.players[0], splitBracketData.consolation) }}
                                        </span>
                                        <hr />
                                        <span :class="getPlayerStyling(splitBracketData.consolation.players[1], splitBracketData.consolation.players[0], splitBracketData.consolation, 'single')">
                                            {{ truncate(splitBracketData.consolation.players[1].name, { length: 13 }) }}{{ getMatchResultIndicator(splitBracketData.consolation.players[1], splitBracketData.consolation) }}
                                        </span>
                                    </div>
                                </div>
                                <div class="placement-label third">Third</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Side: Bracket B (flows right to left) -->
                <div class="bracket-side bracket-b-side">
                    <div class="section-label">BRACKET B</div>
                    <div class="bracket single-elimination bracket-flow-left">
                        <div v-for="(round, roundIdx) in splitBracketData.bracketB" :key="`b-${roundIdx}`"
                            :class="['round', `round-${roundIdx + 1}`]">
                            <h4>{{ roundIdx === splitBracketData.bracketB.length - 1 ? 'SF' : `R${roundIdx + 1}` }}</h4>
                            <div
                                v-for="(match, matchIdx) in round"
                                :key="`b-${roundIdx}-${matchIdx}`"
                                :id="`match-${bracketIndex}-${roundIdx}-${Math.ceil(bracket.matches[roundIdx].length / 2) + matchIdx}`"
                                :data-match-id="match.id"
                                :style="getMatchStyle(roundIdx)"
                                :class="['match', (user && (user.role === 'Admin' || user.role === 'TournamentManager')) ? 'cursor-pointer' : '']"
                                @click="props.openMatchDialog && props.openMatchDialog(bracketIndex, roundIdx, Math.ceil(bracket.matches[roundIdx].length / 2) + matchIdx, match, 'single')"
                            >
                                <div class="player-box">
                                    <span :class="getPlayerStyling(match.players[0], match.players[1], match, 'single')">
                                        {{ truncate(match.players[0].name, { length: 13 }) }}{{ getMatchResultIndicator(match.players[0], match) }}
                                    </span>
                                    <hr />
                                    <span :class="getPlayerStyling(match.players[1], match.players[0], match, 'single')">
                                        {{ truncate(match.players[1].name, { length: 13 }) }}{{ getMatchResultIndicator(match.players[1], match) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </div>

    <!-- Single Elimination - Standard Layout (without consolation or with consolation for <12 players) -->
    <div v-else-if="bracket.type === 'Single Elimination'" class="bracket-scroll-container">
        <div class="bracket-zoom-wrapper" :style="{ zoom: zoomLevel }">
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
            <h3 :class="{ 'final-round-header': props.isFinalRound(bracketIndex, roundIdx) }">
                {{ props.isFinalRound(bracketIndex, roundIdx) ? 'Final Round' : `Round ${roundIdx + 1}` }}
            </h3>

            <!-- Matches Display - Traditional Box Layout -->
            <div
                v-for="(match, matchIdx) in round.filter(m => m.bracket_type !== 'consolation')"
                :key="matchIdx"
                :id="`match-${bracketIndex}-${roundIdx}-${matchIdx}`"
                :data-match-id="match.id"
                :style="getMatchStyle(roundIdx)"
                :class="['match', (user && (user.role === 'Admin' || user.role === 'TournamentManager')) ? 'cursor-pointer' : '']"
                @click="props.openMatchDialog && props.openMatchDialog(bracketIndex, roundIdx, matchIdx, match, 'single')"
            >
                <div class="player-box">
                    <span :class="getPlayerStyling(match.players[0], match.players[1], match, 'single')">
                        {{ truncate(match.players[0].name, { length: 13 }) }}{{ getMatchResultIndicator(match.players[0], match) }}
                    </span>
                    <hr />
                    <span :class="getPlayerStyling(match.players[1], match.players[0], match, 'single')">
                        {{ truncate(match.players[1].name, { length: 13 }) }}{{ getMatchResultIndicator(match.players[1], match) }}
                    </span>
                </div>
            </div>
            </div>

            <!-- Consolation Match (3rd Place) - shown to the right of finals -->
            <div v-if="hasConsolationMatch" class="round round-consolation">
                <h3>3rd Place</h3>
                <div
                    v-for="(match, matchIdx) in bracket.matches[bracket.matches.length - 1].filter(m => m.bracket_type === 'consolation')"
                    :key="`consolation-${matchIdx}`"
                    :id="`match-${bracketIndex}-${bracket.matches.length - 1}-${bracket.matches[bracket.matches.length - 1].indexOf(match)}`"
                    :data-match-id="match.id"
                    :style="{ marginTop: playerCount > 8 && playerCount < 12 ? '50px' : '10px' }"
                    :class="['match', 'consolation-match-standard', (user && (user.role === 'Admin' || user.role === 'TournamentManager')) ? 'cursor-pointer' : '']"
                    @click="props.openMatchDialog && props.openMatchDialog(bracketIndex, bracket.matches.length - 1, bracket.matches[bracket.matches.length - 1].indexOf(match), match, 'single')"
                >
                    <div class="player-box">
                        <span :class="getPlayerStyling(match.players[0], match.players[1], match, 'single')">
                            {{ truncate(match.players[0].name, { length: 13 }) }}{{ getMatchResultIndicator(match.players[0], match) }}
                        </span>
                        <hr />
                        <span :class="getPlayerStyling(match.players[1], match.players[0], match, 'single')">
                            {{ truncate(match.players[1].name, { length: 13 }) }}{{ getMatchResultIndicator(match.players[1], match) }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </div>

    <!-- Round Robin Display - Grid System -->
    <div v-else-if="bracket.type === 'Round Robin'" class="round-robin-bracket" ref="bracketContentRef">
        <div class="round-robin-grid-container" >
            <div class="round-robin-header">
                <h3 class="text-xl font-bold mb-4">Round Robin Tournament</h3>
                <div v-if="user && (user.role === 'Admin' || user.role === 'TournamentManager')" class="flex items-center gap-2">
                    <!-- <button v-if="user && (user.role === 'Admin' || user.role === 'TournamentManager')" @click="printBracket" class="zoom-btn" title="Print Bracket">
                        <i class="pi pi-print"></i>
                    </button> -->
                <!-- Persistent Tiebreaker Button -->
                <button
                    v-if="isRoundRobinConcluded && isRoundRobinConcluded(bracketIndex) && (user && (user.role === 'Admin' || user.role === 'TournamentManager'))"
                    @click="openTiebreakerDialog && openTiebreakerDialog(bracketIndex)"
                    class="tiebreaker-settings-button"
                    :title="bracket.tiebreaker_data && Object.keys(bracket.tiebreaker_data).length > 0 ? 'Edit Tiebreakers' : 'Set Tiebreakers'"
                >
                    <i class="pi pi-cog"></i>
                    <span>{{ bracket.tiebreaker_data && Object.keys(bracket.tiebreaker_data).length > 0 ? 'Edit Tiebreakers' : 'Set Tiebreakers' }}</span>
                </button>
            </div>
        </div>

            <!-- Tiebreaker Notice -->
            <div v-if="hasTiedRank1Players && !dismissedTiebreakerNotices.has(bracket.id) && (user && (user.role === 'Admin' || user.role === 'TournamentManager'))" class="tiebreaker-notice">
                <div class="tiebreaker-notice-content">
                    <i class="pi pi-exclamation-circle"></i>
                    <span>Multiple players are tied for 1st place. Set tiebreakers to determine final rankings.</span>
                    <button @click="openTiebreakerDialog && openTiebreakerDialog(bracketIndex)" class="tiebreaker-button">
                        <i class="pi pi-cog"></i> Set Tiebreakers
                    </button>
                    <button @click="dismissTiebreakerNotice && dismissTiebreakerNotice(bracket.id)" class="dismiss-button">
                        <i class="pi pi-times"></i>
                    </button>
                </div>
            </div>

            <!-- Grid Table -->
            <div class="round-robin-grid-wrapper">
                <table class="round-robin-grid-table">
                    <thead>
                        <tr>
                            <th class="grid-corner-cell"></th>
                            <th
                                v-for="(player, idx) in roundRobinPlayers"
                                :key="`header-${player.id}`"
                                class="grid-header-cell"
                            >
                                <div class="grid-header-content">
                                    <span class="team-number">Team {{ idx + 1 }}</span>
                                    <span class="team-name">{{ truncate(player.name, { length: 12 }) }}</span>
                                </div>
                            </th>
                            <!-- Stats columns -->
                            <th class="grid-stats-header">W</th>
                            <th class="grid-stats-header">L</th>
                            <th v-if="bracket.allow_draws" class="grid-stats-header">D</th>
                            <th class="grid-stats-header">Rank</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(row, rowIdx) in roundRobinGrid" :key="`row-${roundRobinPlayers[rowIdx].id}`">
                            <th class="grid-row-header">
                                <div class="grid-row-header-content">
                                    <span class="team-number">Team {{ rowIdx + 1 }}</span>
                                    <span class="team-name">{{ truncate(roundRobinPlayers[rowIdx].name, { length: 12 }) }}</span>
                                </div>
                            </th>
                            <td
                                v-for="(cell, colIdx) in row"
                                :key="`cell-${rowIdx}-${colIdx}`"
                                :class="[getGridCellClass(cell), (user && (user.role === 'Admin' || user.role === 'TournamentManager') && cell.type === 'match') ? 'cursor-pointer' : '']"
                                @click="cell.type === 'match' && props.openMatchDialog && (() => {
                                    const indices = findMatchIndices(cell.match);
                                    if (indices) {
                                        props.openMatchDialog(bracketIndex, indices.roundIdx, indices.matchIdx, cell.match, 'round_robin');
                                    }
                                })()"
                            >
                                <div class="grid-cell-content">
                                    {{ getGridCellContent(cell) }}
                                </div>
                            </td>
                            <!-- Stats cells -->
                            <td class="grid-stats-cell">{{ getPlayerStats(roundRobinPlayers[rowIdx].id).wins }}</td>
                            <td class="grid-stats-cell">{{ getPlayerStats(roundRobinPlayers[rowIdx].id).losses }}</td>
                            <td v-if="bracket.allow_draws" class="grid-stats-cell">{{ getPlayerStats(roundRobinPlayers[rowIdx].id).draws }}</td>
                            <td class="grid-stats-cell rank-cell" :class="{ 'rank-first': getPlayerStats(roundRobinPlayers[rowIdx].id).rank === 1 }">
                                <span class="rank-value">
                                {{ getPlayerStats(roundRobinPlayers[rowIdx].id).rank }}
                                <span
                                    v-if="getPlayerStats(roundRobinPlayers[rowIdx].id).rank === 1 && props.isRoundRobinConcluded(bracketIndex)"
                                    class="trophy-icon"
                                >🏆</span>
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Double Elimination Display - Center-Out Layout -->
    <div v-else-if="bracket.type === 'Double Elimination'" class="bracket-scroll-container">
        <div class="bracket-zoom-wrapper" :style="{ zoom: zoomLevel }">
            <div class="double-elimination-unified" ref="bracketContentRef">
                <div class="unified-bracket-header">
                    <h3>Double Elimination Tournament</h3>
                </div>

                <div class="unified-bracket-wrapper">
                    <!-- Dropout lines in separate SVG layer (behind everything) -->
                    <svg class="dropout-lines-layer">
                        <g v-for="(line, i) in dynamicLines.dropouts" :key="`dropout-${i}`">
                            <line
                                :x1="line.x1"
                                :y1="line.y1"
                                :x2="line.x2"
                                :y2="line.y2"
                                class="dropout-line"
                            />
                        </g>
                    </svg>

                    <!-- Main connection lines (above dropout lines) -->
                    <svg class="unified-connection-lines">
                    <!-- Winners bracket lines -->
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
                    <!-- Losers bracket lines -->
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
                    <!-- Finals lines -->
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

                <div class="three-column-bracket-container">
                    <!-- Left Column: Losers Bracket (flows left from center) -->
                    <div class="left-column losers-section">
                        <div class="section-label losers-label">
                            <span>← LOWER BRACKET</span>
                        </div>
                        <div class="bracket losers-left-flow">
                            <div v-for="(round, roundIdx) in bracket.matches.losers"
                                :key="`losers-${roundIdx}`"
                                :class="['round', `losers-round-${roundIdx + 1}`, { 'round-ongoing': isRoundOngoing('losers', roundIdx) }]">
                                <h4 class="round-label">R{{ roundIdx + 1 }}</h4>

                                <div
                                    v-for="(match, matchIdx) in round"
                                    :key="`losers-${roundIdx}-${matchIdx}`"
                                    :id="`losers-match-${bracketIndex}-${roundIdx}-${matchIdx}`"
                                    :data-match-id="match.id"
                                    :style="getLoserMatchStyle(roundIdx)"
                                    class="match-group"
                                >
                                    <!-- Player Cells -->
                                    <div class="player-cells">
                                        <div
                                            :id="`losers-match-${bracketIndex}-${roundIdx}-${matchIdx}-p0`"
                                            :class="['player-cell', getPlayerStyling(match.players[0], match.players[1], match, 'losers')]"
                                            @click="(user && (user.role === 'Admin' || user.role === 'TournamentManager')) && props.openMatchDialog && props.openMatchDialog(bracketIndex, roundIdx, matchIdx, match, 'losers')"
                                        >
                                            <span v-if="generatePlayerColor(match.players[0].name)" class="player-color-chip" :style="{ backgroundColor: generatePlayerColor(match.players[0].name) }"></span>
                                            {{ truncate(match.players[0].name, { length: 13 }) }}{{ getMatchResultIndicator(match.players[0], match) }}
                                        </div>
                                        <div
                                            :id="`losers-match-${bracketIndex}-${roundIdx}-${matchIdx}-p1`"
                                            :class="['player-cell', getPlayerStyling(match.players[1], match.players[0], match, 'losers')]"
                                            @click="(user && (user.role === 'Admin' || user.role === 'TournamentManager')) && props.openMatchDialog && props.openMatchDialog(bracketIndex, roundIdx, matchIdx, match, 'losers')"
                                        >
                                            <span v-if="generatePlayerColor(match.players[1].name)" class="player-color-chip" :style="{ backgroundColor: generatePlayerColor(match.players[1].name) }"></span>
                                            {{ truncate(match.players[1].name, { length: 13 }) }}{{ getMatchResultIndicator(match.players[1], match) }}
                                        </div>
                                    </div>

                                    <!-- Connector Lines with Label (flipped for left flow) -->
                                    <div class="match-connector">
                                        <svg viewBox="0 0 40 80" preserveAspectRatio="none">
                                            <line x1="40" y1="20" x2="20" y2="40" />
                                            <line x1="40" y1="60" x2="20" y2="40" />
                                            <line x1="20" y1="40" x2="0" y2="40" />
                                        </svg>
                                        <!-- Game Number in Circle -->
                                        <div class="match-label loser-label">
                                            G{{ match.match_number }}
                                        </div>
                                        <!-- LG-x Label on Line -->
                                        <div class="line-label loser-line">
                                            LG{{ match.match_number }}
                                        </div>
                                    </div>
                                </div>
                                <!-- 3rd Place Indicator (under losers bracket final) -->
                                <div v-if="roundIdx === bracket.matches.losers.length - 1" class="bracket-info-section">
                                    <div class="third-place-indicator">
                                        <div class="placement-label third">
                                            🥉 3rd Place: {{ getThirdPlaceWinner() || 'TBD' }}
                                        </div>
                                    </div>
                                    <div class="third-place-note">
                                        <span class="bracket-note">(Loser of G{{ lowerBracketFinalMatchNumber }} is 3rd place)</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Center Column: Initial Rounds -->
                    <div class="center-column initial-section">
                        <div class="section-label initial-label">
                            <span>INITIAL ROUNDS</span>
                        </div>
                        <div class="bracket initial-center-flow">
                            <div class="round round-1">
                                <h4 class="round-label">R1</h4>
                                <div
                                    v-for="(match, matchIdx) in bracket.matches.winners[0]"
                                    :key="`initial-${matchIdx}`"
                                    :id="`winners-match-${bracketIndex}-0-${matchIdx}`"
                                    :data-match-id="match.id"
                                    :style="getMatchStyle(0)"
                                    class="match-group"
                                >
                                    <!-- Player Cells -->
                                    <div class="player-cells">
                                        <div
                                            :id="`winners-match-${bracketIndex}-0-${matchIdx}-p0`"
                                            :class="['player-cell', getPlayerStyling(match.players[0], match.players[1], match, 'winners')]"
                                            @click="(user && (user.role === 'Admin' || user.role === 'TournamentManager')) && props.openMatchDialog && props.openMatchDialog(bracketIndex, 0, matchIdx, match, 'winners')"
                                        >
                                            <span v-if="generatePlayerColor(match.players[0].name)" class="player-color-chip" :style="{ backgroundColor: generatePlayerColor(match.players[0].name) }"></span>
                                            {{ truncate(match.players[0].name, { length: 13 }) }}{{ getMatchResultIndicator(match.players[0], match) }}
                                        </div>
                                        <div
                                            :id="`winners-match-${bracketIndex}-0-${matchIdx}-p1`"
                                            :class="['player-cell', getPlayerStyling(match.players[1], match.players[0], match, 'winners')]"
                                            @click="(user && (user.role === 'Admin' || user.role === 'TournamentManager')) && props.openMatchDialog && props.openMatchDialog(bracketIndex, 0, matchIdx, match, 'winners')"
                                        >
                                            <span v-if="generatePlayerColor(match.players[1].name)" class="player-color-chip" :style="{ backgroundColor: generatePlayerColor(match.players[1].name) }"></span>
                                            {{ truncate(match.players[1].name, { length: 13 }) }}{{ getMatchResultIndicator(match.players[1], match) }}
                                        </div>
                                    </div>

                                    <!-- Connector Lines with Label -->
                                    <div class="match-connector">
                                        <svg viewBox="0 0 40 80" preserveAspectRatio="none">
                                            <line x1="0" y1="20" x2="20" y2="40" />
                                            <line x1="0" y1="60" x2="20" y2="40" />
                                            <line x1="20" y1="40" x2="40" y2="40" />
                                        </svg>
                                        <!-- Game Number in Circle -->
                                        <div class="match-label winner-label">
                                            G{{ match.match_number }}
                                        </div>
                                        <!-- W-x Label on Line -->
                                        <div class="line-label winner-line">
                                            W-{{ match.match_number }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Winners Bracket (flows right from center) -->
                    <div class="right-column winners-section">
                        <div class="section-label winners-label">
                            <span>UPPER BRACKET →</span>
                        </div>
                        <div class="bracket winners-right-flow">
                            <div v-for="(round, roundIdx) in bracket.matches.winners.slice(1)" :key="`winners-${roundIdx + 1}`"
                                :class="['round', `round-${roundIdx + 2}`, { 'round-ongoing': isRoundOngoing('winners', roundIdx + 1) }]">
                                <h4 class="round-label">WR{{ roundIdx + 2 }}</h4>

                                <div
                                    v-for="(match, matchIdx) in round"
                                    :key="`winners-${roundIdx + 1}-${matchIdx}`"
                                    :id="`winners-match-${bracketIndex}-${roundIdx + 1}-${matchIdx}`"
                                    :data-match-id="match.id"
                                    :style="getMatchStyle(roundIdx + 1)"
                                    class="match-group"
                                >
                                    <!-- Player Cells -->
                                    <div class="player-cells">
                                        <div
                                            :id="`winners-match-${bracketIndex}-${roundIdx + 1}-${matchIdx}-p0`"
                                            :class="['player-cell', getPlayerStyling(match.players[0], match.players[1], match, 'winners')]"
                                            @click="(user && (user.role === 'Admin' || user.role === 'TournamentManager')) && props.openMatchDialog && props.openMatchDialog(bracketIndex, roundIdx + 1, matchIdx, match, 'winners')"
                                        >
                                            <span v-if="generatePlayerColor(match.players[0].name)" class="player-color-chip" :style="{ backgroundColor: generatePlayerColor(match.players[0].name) }"></span>
                                            {{ truncate(match.players[0].name, { length: 13 }) }}{{ getMatchResultIndicator(match.players[0], match) }}
                                        </div>
                                        <div
                                            :id="`winners-match-${bracketIndex}-${roundIdx + 1}-${matchIdx}-p1`"
                                            :class="['player-cell', getPlayerStyling(match.players[1], match.players[0], match, 'winners')]"
                                            @click="(user && (user.role === 'Admin' || user.role === 'TournamentManager')) && props.openMatchDialog && props.openMatchDialog(bracketIndex, roundIdx + 1, matchIdx, match, 'winners')"
                                        >
                                            <span v-if="generatePlayerColor(match.players[1].name)" class="player-color-chip" :style="{ backgroundColor: generatePlayerColor(match.players[1].name) }"></span>
                                            {{ truncate(match.players[1].name, { length: 13 }) }}{{ getMatchResultIndicator(match.players[1], match) }}
                                        </div>
                                    </div>

                                    <!-- Connector Lines with Label -->
                                    <div class="match-connector">
                                        <svg viewBox="0 0 40 80" preserveAspectRatio="none">
                                            <line x1="0" y1="20" x2="20" y2="40" />
                                            <line x1="0" y1="60" x2="20" y2="40" />
                                            <line x1="20" y1="40" x2="40" y2="40" />
                                        </svg>
                                        <!-- Game Number in Circle -->
                                        <div class="match-label winner-label">
                                            G{{ match.match_number }}
                                        </div>
                                        <!-- W-x Label on Line -->
                                        <div class="line-label winner-line">
                                            W-{{ match.match_number }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bottom: Finals Section (spans all columns) -->
                    <div class="finals-bottom-row">
                        <div class="section-label finals-label">
                            <span>FINALS</span>
                            <div class="finals-rules-notice">
                                <div class="rule-item">
                                    <span class="rule-icon">👑</span>
                                    <span>If winner {{ upperBracketFinalMatchNumber }} win this match, they are the <strong>Champion</strong>.</span>
                                </div>
                                <div class="rule-item">
                                    <span class="rule-icon">🔄</span>
                                    <span>If the winner {{ lowerBracketFinalMatchNumber }} wins,<strong>Replay</strong> for one final match.</span>
                                </div>
                            </div>
                        </div>
                        <div class="bracket finals-center-flow">
                            <div class="round finals-round">
                                <div v-for="(round, roundIdx) in bracket.matches.grand_finals" :key="`gf-round-${roundIdx}`">
                                    <div v-for="(match, matchIdx) in round" :key="`grand-finals-${matchIdx}`"
                                        :id="`grand-finals-match-${bracketIndex}-${matchIdx}`"
                                        :data-match-id="match.id"
                                        class="match-group finals-match"
                                    >
                                        <!-- Player Cells -->
                                        <div class="player-cells">
                                            <div
                                                :class="['player-cell', getPlayerStyling(match.players[0], match.players[1], match, 'grand_finals')]"
                                                @click="(user && (user.role === 'Admin' || user.role === 'TournamentManager')) && props.openMatchDialog && props.openMatchDialog(bracketIndex, roundIdx, matchIdx, match, 'grand_finals')"
                                            >
                                                <span v-if="generatePlayerColor(match.players[0].name)" class="player-color-chip" :style="{ backgroundColor: generatePlayerColor(match.players[0].name) }"></span>
                                                {{ truncate(match.players[0].name, { length: 13 }) }}{{ getMatchResultIndicator(match.players[0], match) }}
                                            </div>
                                            <div
                                                :class="['player-cell', getPlayerStyling(match.players[1], match.players[0], match, 'grand_finals')]"
                                                @click="(user && (user.role === 'Admin' || user.role === 'TournamentManager')) && props.openMatchDialog && props.openMatchDialog(bracketIndex, roundIdx, matchIdx, match, 'grand_finals')"
                                            >
                                                <span v-if="generatePlayerColor(match.players[1].name)" class="player-color-chip" :style="{ backgroundColor: generatePlayerColor(match.players[1].name) }"></span>
                                                {{ truncate(match.players[1].name, { length: 13 }) }}{{ getMatchResultIndicator(match.players[1], match) }}
                                            </div>
                                        </div>

                                        <!-- Connector Lines with Label -->
                                        <div class="match-connector">
                                            <svg viewBox="0 0 40 80" preserveAspectRatio="none">
                                                <line x1="0" y1="20" x2="20" y2="40" />
                                                <line x1="0" y1="60" x2="20" y2="40" />
                                                <line x1="20" y1="40" x2="40" y2="40" />
                                            </svg>
                                            <!-- Grand Finals Label -->
                                            <div class="match-label" style="border-color: #ffc107; color: #ffc107;">
                                                🏆
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </div>

    <!-- Player Name Edit Modal -->
    <div v-if="showPlayerEditModal" class="modal-overlay" @click.self="showPlayerEditModal = false">
        <div class="player-edit-modal">
            <div class="modal-header">
                <h3>Edit Player Names</h3>
                <button @click="showPlayerEditModal = false" class="close-btn">
                    <i class="pi pi-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div v-for="(player, index) in editablePlayers" :key="index" class="player-edit-row">
                    <input
                        type="color"
                        v-model="playerColors[player.oldName]"
                        class="player-color-picker"
                        :title="`Change color for ${player.oldName}`"
                    />
                    <input
                        v-model="player.newName"
                        type="text"
                        class="player-name-input"
                        :placeholder="player.oldName"
                    />
                </div>
            </div>
            <div class="modal-footer">
                <button @click="showPlayerEditModal = false" class="btn-cancel">Cancel</button>
                <button @click="savePlayerNames" class="btn-save">Save Changes</button>
            </div>
        </div>
    </div>
    </div>
</template>

<style scoped>
/* Wrapper for single root element */
.bracket-view-wrapper {
    width: 100%;
    height: 100%; /* Ensure it takes full height of its parent */
    position: relative; /* Establish a positioning context for absolute children */
}

/* Zoom Controls */
.zoom-controls {
    position: absolute; /* Changed from fixed to absolute */
    top: 10px;
    right: 10px;
    z-index: 40; /* Lowered from 100 to be below the main navbar (z-50) */
    display: flex;
    gap: 8px;
    justify-content: flex-end;
    padding: 0 10px;
    background: transparent;
}

.zoom-btn {
    background: white;
    border: 2px solid #0077B3;
    border-radius: 6px;
    padding: 8px 12px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 600;
    color: #0077B3;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 40px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.zoom-btn:hover:not(:disabled) {
    background: #0077B3;
    color: white;
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0, 119, 179, 0.2);
}

.zoom-btn:active:not(:disabled) {
    transform: translateY(0);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.zoom-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.zoom-btn i {
    font-size: 14px;
}

.zoom-reset {
    min-width: 60px;
    font-weight: 700;
}

/* Bracket scroll container adjustments for zoom */
.bracket-scroll-container {
    position: relative;
    overflow: auto;
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
}

/* Ensure proper layout with CSS zoom */
.split-bracket-container-horizontal,
.bracket.single-elimination,
.double-elimination-unified {
    min-height: 100%;
}

/* Header with button */
.unified-bracket-header {
    display: flex;
    justify-content: center;
    align-items: center;
    width: 100%;
    margin-bottom: 20px;
    padding: 0 20px;
    gap: 16px;
}

.unified-bracket-header h3 {
    margin: 0;
}

/* Round Robin Header with Tiebreaker Button */
.round-robin-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    gap: 1rem;
}

.round-robin-header h3 {
    margin: 0;
}

.tiebreaker-settings-button {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: linear-gradient(135deg, #0077B3 0%, #005a8c 100%);
    color: white;
    border: none;
    border-radius: 8px;
    padding: 0.6rem 1.2rem;
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 2px 6px rgba(0, 119, 179, 0.3);
    white-space: nowrap;
}

.tiebreaker-settings-button:hover {
    background: linear-gradient(135deg, #005a8c 0%, #004466 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 119, 179, 0.4);
}

.tiebreaker-settings-button:active {
    transform: translateY(0);
    box-shadow: 0 2px 6px rgba(0, 119, 179, 0.3);
}

.tiebreaker-settings-button i {
    font-size: 1rem;
}

/* Edit Players Button */
.unified-bracket-header .edit-players-btn {
    flex-shrink: 0;
}

.unified-bracket-header .edit-players-btn * {
    pointer-events: none !important;
    user-select: none !important;
}

/* Player Name Edit Modal */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
}

.player-edit-modal {
    background: white;
    border-radius: 12px;
    width: 90%;
    max-width: 600px;
    max-height: 80vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
}

.modal-header {
    padding: 20px;
    border-bottom: 1px solid #e0e0e0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    font-size: 20px;
    color: #333;
}

.close-btn {
    background: none;
    border: none;
    font-size: 20px;
    cursor: pointer;
    color: #666;
    padding: 4px 8px;
    border-radius: 4px;
    transition: all 0.2s;
}

.close-btn:hover {
    background: #f0f0f0;
    color: #333;
}

.modal-body {
    padding: 20px;
    overflow-y: auto;
    flex: 1;
}

.player-edit-row {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
}

.player-color-picker {
    width: 40px;
    height: 40px;
    border: 2px solid #e0e0e0;
    border-radius: 6px;
    cursor: pointer;
    flex-shrink: 0;
}

.player-color-picker:hover {
    border-color: #0077B3;
}

.player-name-input {
    flex: 1;
    padding: 10px 12px;
    border: 2px solid #e0e0e0;
    border-radius: 6px;
    font-size: 14px;
    transition: border-color 0.2s;
}

.player-name-input:focus {
    outline: none;
    border-color: #0077B3;
}

.modal-footer {
    padding: 20px;
    border-top: 1px solid #e0e0e0;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}

.btn-cancel, .btn-save {
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-cancel {
    background: #f0f0f0;
    color: #666;
}

.btn-cancel:hover {
    background: #e0e0e0;
}

.btn-save {
    background: #0077B3;
    color: white;
}

.btn-save:hover {
    background: #005a87;
}

.third-place-note {
    margin-top: 4px;
    text-align: center;
}

.third-place-note .bracket-note {
    display: inline-block;
    font-size: 16px;
    font-weight: 500;
    color: #5d4037;
    background-color: #f5f5f5;
    padding: 4px 10px;
    border-radius: 4px;
    border-left: 3px solid #bdbdbd;
    font-style: normal;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
}

/* Player Color Chip */
.player-color-chip {
    display: inline-block;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    margin-right: 6px;
    vertical-align: middle;
    position: relative;
    top: -1px;
}
</style>

<style>
/* Non-scoped styles for global overrides if needed, or for media queries on component classes */
@media (max-width: 768px) {
    .zoom-controls {
        display: none !important;
    }
}
</style>
