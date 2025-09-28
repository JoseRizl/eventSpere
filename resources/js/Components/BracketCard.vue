<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Button from 'primevue/button';
import SelectButton from 'primevue/selectbutton';
import BracketView from '@/Components/BracketView.vue';
import MatchesView from '@/Components/MatchesView.vue';
import { formatDisplayDate } from '@/utils/dateUtils.js';

const props = defineProps({
    bracket: Object,
    bracketIndex: Number,
    user: Object,
    isExpanded: Boolean,
    viewMode: String,
    matchFilter: String,
    standingsRevision: Number,
    // Functions from composable
    getBracketStats: Function,
    getBracketTypeClass: Function,
    isFinalRound: Function,
    getRoundRobinStandings: Function,
    isRoundRobinConcluded: Function,
    // Event emitters
    onToggleBracket: Function,
    onRemoveBracket: Function,
    onSetViewMode: Function,
    onSetMatchFilter: Function,
    onOpenMatchDialog: Function,
    isArchived: Boolean,
    onOpenScoringConfigDialog: Function,
    onOpenMatchEditorFromCard: Function,
    // Configuration
    showEventLink: {
        type: Boolean,
        default: true
    },
    showAdminControls: {
        type: Boolean,
        default: true
    }
});

const emit = defineEmits([
    'toggle-bracket', 'remove-bracket', 'set-view-mode', 'set-match-filter'
]);

const matchStatusFilterOptions = [
    { label: 'All', value: 'all' },
    { label: 'Pending', value: 'pending' },
    { label: 'Completed', value: 'completed' }
];

const handleToggleBracket = () => emit('toggle-bracket', props.bracketIndex);
const handleRemoveBracket = () => emit('remove-bracket', props.bracketIndex);
const handleSetViewMode = (mode) => emit('set-view-mode', { index: props.bracketIndex, mode });
const handleSetMatchFilter = (filter) => emit('set-match-filter', { index: props.bracketIndex, filter });

</script>

<template>
    <div class="bracket-section">
        <div class="bracket-wrapper">
            <div class="bracket-header">
                <div class="flex justify-between items-start">
                    <div class="flex items-center gap-4">
                        <h2>{{ bracket.name }}</h2>
                        <div class="info-tags">
                            <span :class="['bracket-tag', getBracketTypeClass(bracket.type)]">{{ bracket.type }}</span>
                            <span :class="['bracket-tag', getBracketStats(bracket).status.class]">{{ getBracketStats(bracket).status.text }}</span>
                        </div>
                    </div>
                    <div class="bracket-controls">
                        <Button v-if="showAdminControls && user?.role === 'Admin' && !isArchived" icon="pi pi-trash" @click="handleRemoveBracket" class="p-button-rounded p-button-text p-button-danger" v-tooltip.top="'Delete Bracket'" />
                        <Button :icon="isExpanded ? 'pi pi-chevron-up' : 'pi pi-chevron-down'" @click="handleToggleBracket" class="p-button-rounded p-button-text" v-tooltip.top="isExpanded ? 'Hide Bracket' : 'Show Bracket'" />
                    </div>
                </div>

                <Link v-if="showEventLink && bracket.event" :href="route('event.details', { id: bracket.event_id })" class="event-link-container group">
                    <img :src="bracket.event.image || '/placeholder-event.jpg'" :alt="bracket.event.title" class="event-link-icon" />
                    <div class="event-link-info">
                        <h4 class="event-link-title group-hover:text-blue-600">{{ bracket.event.title }}</h4>
                        <p class="related-event-date">{{ formatDisplayDate(bracket.event.startDate) }}</p>
                    </div>
                </Link>

                <div class="bracket-stats">
                    <div class="stat-item">
                        <i class="pi pi-users"></i>
                        <span>{{ getBracketStats(bracket).participants }} Participants</span>
                    </div>
                    <div class="stat-item">
                        <i class="pi pi-sitemap"></i>
                        <span>{{ getBracketStats(bracket).rounds }} Rounds</span>
                    </div>
                </div>
            </div>

            <div v-if="isExpanded" class="bracket-content-wrapper" :id="`bracket-content-${bracket.id}`">
                <div class="view-toggle-buttons">
                    <Button :label="'Bracket View'" :class="['p-button-sm', viewMode !== 'matches' ? 'p-button-primary' : 'p-button-outlined']" @click="handleSetViewMode('bracket')" />
                    <Button :label="'Matches View'" :class="['p-button-sm', viewMode === 'matches' ? 'p-button-primary' : 'p-button-outlined']" @click="handleSetViewMode('matches')" />
                </div>

                <div v-show="viewMode !== 'matches'">
                    <BracketView
                        :bracket="bracket"
                        :bracketIndex="bracketIndex"
                        :user="user"
                        :standingsRevision="standingsRevision"
                        :isFinalRound="isFinalRound"
                        :openMatchDialog="isArchived ? null : onOpenMatchDialog"
                        :getRoundRobinStandings="getRoundRobinStandings"
                        :isRoundRobinConcluded="isRoundRobinConcluded"
                        :openScoringConfigDialog="isArchived ? null : onOpenScoringConfigDialog"
                    />
                </div>

                <div v-if="viewMode === 'matches'">
                    <div class="match-filters">
                        <SelectButton
                            :modelValue="matchFilter"
                            @update:modelValue="handleSetMatchFilter"
                            :options="matchStatusFilterOptions"
                            optionLabel="label"
                            optionValue="value"
                            aria-labelledby="match-status-filter"
                        />
                    </div>
                    <MatchesView
                        :bracket="bracket"
                        :bracketIndex="bracketIndex"
                        :user="user"
                        :filter="matchFilter"
                        :openMatchEditorFromCard="isArchived ? null : onOpenMatchEditorFromCard"
                        :isFinalRound="isFinalRound"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
