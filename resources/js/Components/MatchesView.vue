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
    getAllMatches: {
        type: Function,
        required: true
    },
    openMatchEditorFromCard: {
        type: Function,
        required: true
    }
});

const matches = computed(() => {
    return props.getAllMatches(props.bracket, props.filter);
});

</script>

<template>
    <div class="matches-card-view">
        <div v-for="match in matches"
            :key="match.id"
            :class="['match-card-item', (user?.role === 'Admin' || user?.role === 'SportsManager') ? 'editable' : '']"
            @click="(user?.role === 'Admin' || user?.role === 'SportsManager') && props.openMatchEditorFromCard(bracketIndex, match)"
        >
            <div class="match-card-header">
                <span class="match-date">{{ formatDisplayDate(match.date || bracket.event.startDate) }}</span>
                <span :class="['match-status', `status-${match.status}`]">{{ match.status }}</span>
            </div>
            <div class="match-card-body">
                <div class="players-scores">
                    <div class="player">
                        <span class="player-name">{{ truncate(match.players[0].name, { length: 13 }) }}</span>
                        <span class="player-score">{{ match.players[0].score }}</span>
                    </div>
                    <div class="vs-separator">vs</div>
                    <div class="player">
                        <span class="player-name">{{ truncate(match.players[1].name, { length: 13 }) }}</span>
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
