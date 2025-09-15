<script setup>
import { computed } from 'vue';
import { useBracketActions } from '@/composables/useBracketActions.js';
import { useBracketState } from '@/composables/useBracketState.js';
import { format, parse, parseISO, isValid } from 'date-fns';

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
    }
});

const state = useBracketState(); // Required to pass to useBracketActions
const { getAllMatches, openMatchEditorFromCard } = useBracketActions(state);

// Helper functions for this component's template
const truncateNameElimination = (name) => {
  if (!name) return 'TBD';
  return name.length > 13 ? name.substring(0, 13) + '...' : name;
};

const formatDisplayDate = (dateString) => {
  if (!dateString) return '';
  try {
    let date = parseISO(dateString);
    if (isValid(date)) return format(date, 'MMM-dd-yyyy');

    date = parse(dateString, 'yyyy-MM-dd', new Date());
    if (isValid(date)) return format(date, 'MMM-dd-yyyy');

    date = parse(dateString, 'MMM-dd-yyyy', new Date());
    return isValid(date) ? format(date, 'MMM-dd-yyyy') : 'Invalid Date';
  } catch {
    return 'Invalid Date';
  }
};

const formatDisplayTime = (timeString) => {
  if (!timeString) return '';
  try {
    const parsed = parse(timeString, 'HH:mm', new Date());
    return format(parsed, 'hh:mm a');
  } catch {
    return 'Invalid Time';
  }
};

const matches = computed(() => {
    return getAllMatches(props.bracket, props.filter);
});

</script>

<template>
    <div class="matches-card-view">
        <div v-for="match in matches"
            :key="match.id"
            :class="['match-card-item', (user?.role === 'Admin' || user?.role === 'SportsManager') ? 'editable' : '']"
            @click="(user?.role === 'Admin' || user?.role === 'SportsManager') && openMatchEditorFromCard(bracketIndex, match)"
        >
            <div class="match-card-header">
                <span class="match-date">{{ formatDisplayDate(match.date || bracket.event.startDate) }}</span>
                <span :class="['match-status', `status-${match.status}`]">{{ match.status }}</span>
            </div>
            <div class="match-card-body">
                <div class="players-scores">
                    <div class="player">
                        <span class="player-name">{{ truncateNameElimination(match.players[0].name) }}</span>
                        <span class="player-score">{{ match.players[0].score }}</span>
                    </div>
                    <div class="vs-separator">vs</div>
                    <div class="player">
                        <span class="player-name">{{ truncateNameElimination(match.players[1].name) }}</span>
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
</template>
