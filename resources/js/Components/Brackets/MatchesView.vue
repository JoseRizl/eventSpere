<script setup>
import { computed } from 'vue';
import { formatDisplayDate, formatDisplayTime } from '@/utils/dateUtils';
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
    filter: {
        type: String,
        default: 'all'
    },
    openMatchEditorFromCard: {
        type: Function,
        default: null
    },
    isFinalRound: {
        type: Function,
        required: true,
    }
});

const getRoundTitle = (match) => {
    if (!match || typeof match.round === 'undefined') {
        return 'Unknown Round';
    }

    const { round, bracket_type } = match;
    const mainBracketType = props.bracket.type;

    if (mainBracketType === 'Single Elimination') {
        if (props.isFinalRound(props.bracketIndex, round - 1)) {
            return 'Final Round';
        }
        return `Round ${round}`;
    }

    if (mainBracketType === 'Round Robin') {
        return `Round ${round}`;
    }

    if (mainBracketType === 'Double Elimination') {
        if (bracket_type === 'winners') {
            return `Upper Bracket Round ${round}`;
        } else if (bracket_type === 'losers') {
            return `Lower Bracket Round ${round}`;
        } else if (bracket_type === 'grand_finals') {
            return 'Grand Final';
        }
    }

    return `Round ${round}`; // Fallback
};

const groupedMatches = computed(() => {
    const bracket = props.bracket;
    if (!bracket || !bracket.matches) return [];

    let allMatchesUnsorted = [];
    if (bracket.type === 'Single Elimination' || bracket.type === 'Round Robin') {
        allMatchesUnsorted = bracket.matches.flat();
    } else if (bracket.type === 'Double Elimination') {
        allMatchesUnsorted = [
            ...(bracket.matches.winners || []).flat(),
            ...(bracket.matches.losers || []).flat(),
            ...(bracket.matches.grand_finals || []).flat()
        ];
    }

    const roundGroups = new Map();

    allMatchesUnsorted.forEach(match => {
        const roundTitle = getRoundTitle(match);
        if (!roundGroups.has(roundTitle)) {
            roundGroups.set(roundTitle, []);
        }
        roundGroups.get(roundTitle).push(match);
    });

    const result = [];
    roundGroups.forEach((matches, title) => {
        let filteredMatches = matches;
        if (props.filter && props.filter !== 'all') {
            filteredMatches = matches.filter(match => match.status === props.filter);
        }

        if (filteredMatches.length > 0) {
            // Sort matches by match_number only (natural order)
            filteredMatches.sort((a, b) => a.match_number - b.match_number);
            result.push({ title, matches: filteredMatches });
        }
    });

    return result;
});

const getMatchIdentifier = (match) => {
    if (!match || typeof match.match_number === 'undefined') {
        return '';
    }
    return `Match ${match.match_number}`;
};

</script>

<template>
    <div class="matches-card-view">
        <template v-if="groupedMatches.length > 0">
            <div v-for="group in groupedMatches" :key="group.title" class="round-group">
                <h3 class="round-separator">{{ group.title }}</h3>
                <div class="matches-grid">
                    <div v-for="match in group.matches"
                        :key="match.id"
                        :class="['match-card-item', (user && (user.role === 'Admin' || user.role === 'SportsManager')) ? 'editable' : '']"
                        @click="props.openMatchEditorFromCard && props.openMatchEditorFromCard(bracketIndex, match)"
                    >
                        <div class="card-content">
                            <div class="match-card-header">
                                <div>
                                    <div class="match-location">{{ getMatchIdentifier(match) }}</div>
                                    <span class="match-date">{{ formatDisplayDate(match.date || bracket.event.startDate) }}</span>
                                </div>
                                <span :class="['match-status', `status-${match.status}`]">{{ match.status }}</span>
                            </div>
                            <div class="match-content">
                                <div class="players-and-info">
                                    <div class="players-scores">
                                        <div class="player">
                                            <span class="player-name">{{ truncate(match.players[0].name, { length: 12 }) }}</span>
                                            <span v-if="match.status === 'completed'"
                                                :class="['player-result',
                                                        match.winner_id === null ? 'result-draw' :
                                                        (match.winner_id === match.players[0].id ? 'result-win' : 'result-loss')]">
                                                {{ match.winner_id === null ? 'D' :
                                                (match.winner_id === match.players[0].id ? 'W' : 'L') }}
                                            </span>
                                            <span v-else class="player-score">-</span>
                                        </div>
                                        <div class="vs-separator">vs</div>
                                        <div class="player">
                                            <span class="player-name">{{ truncate(match.players[1].name, { length: 12 }) }}</span>
                                            <span v-if="match.status === 'completed'"
                                                :class="['player-result',
                                                        match.winner_id === null ? 'result-draw' :
                                                        (match.winner_id === match.players[1].id ? 'result-win' : 'result-loss')]">
                                                {{ match.winner_id === null ? 'D' :
                                                (match.winner_id === match.players[1].id ? 'W' : 'L') }}
                                            </span>
                                            <span v-else class="player-score">-</span>
                                        </div>
                                    </div>
                                    <div class="time-venue">
                                        <div v-if="match.time" class="info-item">
                                            <i class="pi pi-clock"></i>
                                            <span>{{ formatDisplayTime(match.time) }}</span>
                                        </div>
                                        <div v-if="match.venue || bracket.event.venue" class="info-item">
                                            <i class="pi pi-map-marker"></i>
                                            <span>{{ match.venue || bracket.event.venue }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- <div v-if="match.status === 'completed'" class="final-score-bar">
                            <div class="final-score-content">
                                <span>Final Score</span>
                                <span class="score-display">{{ match.players[0].score || '0' }} - {{ match.players[1].score || '0' }}</span>
                            </div>
                        </div> -->
                    </div>
                </div>
            </div>
        </template>
        <div v-else class="no-matches-found">
            No matches found for the selected filter.
        </div>
    </div>
</template>

<style scoped>
.matches-card-view {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.round-group {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.round-separator {
    font-size: 1.25rem;
    font-weight: 600;
    color: #1a202c;
    padding-bottom: 0.5rem;
    border-bottom: 2px solid #0077B3;
    margin-bottom: 0.5rem;
}

.matches-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    justify-content: center;
    gap: 1rem;
}

.match-card-item {
    position: relative;
    display: flex;
    flex-direction: column;
    background: white;
    border-radius: 10px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    overflow: hidden;
    border: 1px solid #e2e8f0;
}

.match-card-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.1);
}

.card-content {
    flex: 1;
    padding: 0;
}

.match-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.6rem 1rem;
    background: linear-gradient(90deg, #0872a3 0%, #2121c8 100%);
    color: white;
    position: relative;
    overflow: hidden;
    width: 100%;
}

.match-card-header:after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 2px;
    background: linear-gradient(90deg, #0872a3, #2121c8, #6855f7);
}

.match-content {
    padding: .5rem;
    display: flex;
    flex-direction: column;
    gap: .5rem;
}

.players-and-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1rem;
}

.players-scores {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.time-venue {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    min-width: 120px;
}

.player {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.6rem 0.9rem;
    border-radius: 6px;
    background: #ffffff;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    border: 1px solid #e2e8f0;
    margin-bottom: 0.25rem;
}

.player:hover {
    background: #f8fafc;
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
    border-color: #cbd5e1;
}

.vs-separator {
    margin: 0.25rem 0;
}

.no-matches-found {
    text-align: center;
    color: #718096;
    padding: 2rem;
}

.player-result {
    margin-left: auto;
}

.result-win {
    color: #3b82f6 !important; /* Blue for win */
    background-color: #eff6ff !important; /* Light blue background */
    padding: 0.25rem 0.6rem;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.8rem;
    box-shadow: 0 1px 2px rgba(59, 130, 246, 0.1);
}

.result-loss {
    color: #6b7280 !important; /* Gray for loss */
    background-color: #f9fafb !important; /* Light gray background */
    padding: 0.25rem 0.6rem;
    border-radius: 12px;
    font-weight: 600;
    font-size: 0.8rem;
    box-shadow: 0 1px 2px rgba(107, 114, 128, 0.1);
}

.final-score-bar {
    background: linear-gradient(90deg, #4f46e5 0%, #7c3aed 100%);
    color: white;
    padding: 0.5rem 1.5rem;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.final-score-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    max-width: 1200px;
    margin: 0 auto;
    font-size: 0.9rem;
}

.score-display {
    font-weight: 700;
    font-size: 1rem;
    background: rgba(255, 255, 255, 0.15);
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    backdrop-filter: blur(4px);
}

.match-date {
    font-size: 0.85rem;
    color: #ffffff;
    font-weight: 600;
    text-shadow: 0 1px 2px rgba(0,0,0,0.2);
}

.player-name {
    font-weight: 500;
    color: #1e293b;
    background: transparent;
    padding: 0;
}

.info-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    color: #4b5563;
    background: white;
    padding: 0.4rem 0.8rem;
    border-radius: 20px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    border: 1px solid #e5e7eb;
}

.result-draw {
    color: #f59e0b; /* Amber color for draws */
    background-color: #fffbeb;
    padding: 0.15rem 0.5rem;
    border-radius: 4px;
    font-weight: 600;
    font-size: 0.8rem;
    box-shadow: 0 1px 2px rgba(245, 158, 11, 0.1);
}

/* Keep your existing result-win and result-loss styles */
.result-win {
    color: #10b981;
    background-color: #ecfdf5;
    padding: 0.15rem 0.5rem;
    border-radius: 4px;
    font-weight: 600;
    font-size: 0.8rem;
}

.result-loss {
    color: #ef4444;
    background-color: #fef2f2;
    padding: 0.15rem 0.5rem;
    border-radius: 4px;
    font-weight: 600;
    font-size: 0.8rem;
}

/* Add these styles to your CSS */
.player-score {
    margin-left: auto; /* Align to the right, same as .player-result */
    color: #9ca3af; /* Light gray color */
    font-weight: 600;
    padding: 0.25rem 0; /* Match vertical padding of results */
    min-width: 1.5rem; /* Match width of W/L/D for consistent spacing */
    text-align: center; /* Center the dash */
}
</style>
