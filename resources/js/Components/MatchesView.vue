<script setup>
import { computed } from 'vue';
import { formatDisplayDate, formatDisplayTime } from '@/utils/dateUtils.js';
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
            ...bracket.matches.winners.flat(),
            ...bracket.matches.losers.flat(),
            ...bracket.matches.grand_finals.flat()
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
            const statusOrder = { 'ongoing': 1, 'pending': 2, 'completed': 3 };
            filteredMatches.sort((a, b) => {
                const statusA = statusOrder[a.status] || 99;
                const statusB = statusOrder[b.status] || 99;
                if (statusA !== statusB) return statusA - statusB;
                return a.match_number - b.match_number;
            });
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
                        <div class="match-card-header">
                            <div>
                                <div class="match-location">{{ getMatchIdentifier(match) }}</div>
                                <span class="match-date">{{ formatDisplayDate(match.date || bracket.event.startDate) }}</span>
                            </div>
                            <span :class="['match-status', `status-${match.status}`]">{{ match.status }}</span>
                        </div>
                        <div class="match-card-body">
                            <div class="players-scores">
                                <div class="player">
                                    <span class="player-name">{{ truncate(match.players[0].name, { length: 12 }) }}</span>
                                    <span class="player-score">{{ match.players[0].score }}</span>
                                </div>
                                <div class="vs-separator">vs</div>
                                <div class="player">
                                    <span class="player-name">{{ truncate(match.players[1].name, { length: 12 }) }}</span>
                                    <span class="player-score">{{ match.players[1].score }}</span>
                                </div>
                            </div>
                            <div class="time-venue">
                                <div class="info-item">
                                    <i class="pi pi-clock"></i>
                                    <span>{{ formatDisplayTime(match.time || bracket.event.startTime) }}</span>
                                </div>
                                <div class="info-item">
                                    <i class="pi pi-map-marker"></i>
                                    <span>{{ match.venue || bracket.event.venue }}</span>
                                </div>
                            </div>
                        </div>
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

.match-card-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding-bottom: 0.5rem;
    border-bottom: 1px solid #e2e8f0;
    margin-bottom: 0.5rem;
}

.match-location {
    font-weight: 600;
    font-size: 0.9rem;
    color: #2d3748;
}
.match-date {
    font-size: 0.8rem;
    color: #718096;
}

.no-matches-found {
    text-align: center;
    color: #718096;
    padding: 2rem;
}
</style>
