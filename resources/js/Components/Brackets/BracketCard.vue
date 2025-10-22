<script setup>
import { computed } from 'vue';
import BracketView from '@/Components/Brackets/BracketView.vue';
import MatchesView from '@/Components/Brackets/MatchesView.vue';
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
    onToggleConsolationMatch: Function,
    onToggleAllowDraws: Function,
    onOpenTiebreakerDialog: Function,
    onDismissTiebreakerNotice: Function,
    dismissedTiebreakerNotices: Set,
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

const hasConsolationMatch = computed(() => {
    if (!props.bracket.matches || props.bracket.type !== 'Single Elimination') return false;
    const finalRound = props.bracket.matches[props.bracket.matches.length - 1];
    return finalRound?.some(m => m.bracket_type === 'consolation') || false;
});

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
                        <Button :icon="isExpanded ? 'pi pi-chevron-up' : 'pi pi-chevron-down'" @click="handleToggleBracket" class="p-button-rounded p-button-text" v-tooltip.top="isExpanded ? 'Hide Bracket' : 'Show Bracket'" />
                        <Button 
                            v-if="showAdminControls && user?.role === 'Admin' && !isArchived && bracket.type === 'Single Elimination' && bracket.matches?.length >= 2" 
                            :icon="hasConsolationMatch ? 'pi pi-minus-circle' : 'pi pi-plus-circle'" 
                            @click="onToggleConsolationMatch(bracketIndex)" 
                            class="p-button-rounded p-button-text" 
                            :class="hasConsolationMatch ? 'p-button-warning' : 'p-button-success'"
                            v-tooltip.top="hasConsolationMatch ? 'Remove 3rd Place Match' : 'Add 3rd Place Match'" 
                        />
                        <Button 
                            v-if="showAdminControls && user?.role === 'Admin' && !isArchived && bracket.type === 'Round Robin'" 
                            :icon="bracket.allow_draws ? 'pi pi-check-circle' : 'pi pi-times-circle'" 
                            @click="onToggleAllowDraws(bracketIndex)" 
                            class="p-button-rounded p-button-text" 
                            :class="bracket.allow_draws ? 'p-button-success' : 'p-button-secondary'"
                            v-tooltip.top="bracket.allow_draws ? 'Draws Enabled' : 'Draws Disabled (Click to Enable)'" 
                        />
                        <Button v-if="showAdminControls && user?.role === 'Admin' && !isArchived" icon="pi pi-trash" @click="handleRemoveBracket" class="p-button-rounded p-button-text p-button-danger" v-tooltip.top="'Delete Bracket'" />

                    </div>
                </div>

                <!-- Winner Banner -->
                <div v-if="getBracketStats(bracket).status.text === 'Completed' && getBracketStats(bracket).winnerName" class="winner-banner">
                    <i class="pi pi-trophy"></i>
                    <span>Winner: <strong>{{ getBracketStats(bracket).winnerName }}</strong></span>
                </div>

                <Link v-if="showEventLink && bracket.event" :href="route('event.details', { id: bracket.event_id })" class="event-link-container group">
                    <img :src="bracket.event.image || '/placeholder-event.jpg'" :alt="bracket.event.title" class="event-link-icon" />
                    <div class="event-link-info">
                        <h4 class="event-link-title group-hover:text-blue-600">{{ bracket.event.title }}</h4>
                        <p class="related-event-date">{{ formatDisplayDate(bracket.event.startDate) }}</p>
                    </div>
                </Link>

                <div class="bracket-stats">
                    <!-- <div class="stat-item">
                        <i class="pi pi-users"></i>
                        <span>{{ getBracketStats(bracket).participants }} Participants</span>
                    </div> -->
                    <div class="stat-item">
                        <i class="pi pi-sitemap"></i>
                        <span>{{ getBracketStats(bracket).rounds }} Rounds</span>
                    </div>
                </div>
            </div>

            <div v-if="isExpanded" class="bracket-content-wrapper" :id="`bracket-content-${bracket.id}`">
                <div class="filter-panel flex-col">
                    <div class="view-toggle-buttons">
                        <Button :label="'Bracket View'" :class="['p-button-sm', viewMode !== 'matches' ? 'p-button-primary' : 'p-button-outlined']" @click="handleSetViewMode('bracket')" />
                        <Button :label="'Matches View'" :class="['p-button-sm', viewMode === 'matches' ? 'p-button-primary' : 'p-button-outlined']" @click="handleSetViewMode('matches')" />
                    </div>

                    <div v-if="viewMode === 'matches'" class="match-filters flex justify-center w-full">
                        <SelectButton
                            :modelValue="matchFilter"
                            @update:modelValue="handleSetMatchFilter"
                            :options="matchStatusFilterOptions"
                            optionLabel="label"
                            optionValue="value"
                            aria-labelledby="match-status-filter"
                        />
                    </div>
                </div>

               <BracketView
                    v-show="viewMode !== 'matches'"
                    :bracket="bracket"
                    :bracketIndex="bracketIndex"
                    :user="user"
                    :standingsRevision="standingsRevision"
                    :isFinalRound="isFinalRound"
                    :openMatchDialog="isArchived || !user ? null : onOpenMatchDialog"
                    :getRoundRobinStandings="getRoundRobinStandings"
                    :isRoundRobinConcluded="isRoundRobinConcluded"
                    :openScoringConfigDialog="isArchived || !user ? null : onOpenScoringConfigDialog"
                    :openTiebreakerDialog="isArchived || !user ? null : onOpenTiebreakerDialog"
                    :dismissTiebreakerNotice="isArchived || !user ? null : onDismissTiebreakerNotice"
                    :dismissedTiebreakerNotices="dismissedTiebreakerNotices"
                />
                <MatchesView
                    v-if="viewMode === 'matches'"
                    :bracket="bracket"
                    :bracketIndex="bracketIndex"
                    :user="user"
                    :filter="matchFilter"
                    :openMatchEditorFromCard="isArchived || !user ? null : onOpenMatchEditorFromCard"
                    :isFinalRound="isFinalRound"
                />
            </div>
        </div>
    </div>
</template>
