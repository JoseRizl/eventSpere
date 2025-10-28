<script setup>
import { computed, ref, watch } from 'vue';
import LoadingSpinner from '@/Components/LoadingSpinner.vue';
import ConfirmationDialog from '@/Components/ConfirmationDialog.vue';
import BracketView from '@/Components/Brackets/BracketView.vue';
import MatchesView from '@/Components/Brackets/MatchesView.vue';
import { formatDisplayDate } from '@/utils/dateUtils.js';
import { useToast } from '@/composables/useToast.js';

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
    },
    // Loading states from composable
    isDeletingBracket: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits([
    'toggle-bracket', 'remove-bracket', 'set-view-mode', 'set-match-filter'
]);

const matchStatusFilterOptions = [
    { label: 'All', value: 'all' },
    { label: 'Pending', value: 'pending' },
    { label: 'Ongoing', value: 'ongoing' },
    { label: 'Completed', value: 'completed' },
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

// Player name editing
const { showSuccess, showError } = useToast();
const showPlayerEditModal = ref(false);
const editablePlayers = ref([]);
const bracketViewRef = ref(null);
const playerColors = ref({});
const isSavingPlayers = ref(false);
const showDuplicatePlayerError = ref(false);
const showSaveConfirm = ref(false);

const openPlayerEditModal = () => {
    if (props.bracket.type === 'Double Elimination') {
        playerColors.value = bracketViewRef.value?.playerColors || {};
    } else {
        playerColors.value = {};
    }

    const players = new Set();

    const addPlayersFromMatches = (matches) => {
        matches.forEach(match => {
            if (match.players) {
                match.players.forEach(player => {
                    if (player && player.name && player.name !== 'TBD' && player.name !== 'BYE' &&
                        !player.name.includes('Winner') && !player.name.includes('Loser')) {
                        players.add(player.name);
                    }
                });
            }
        });
    };

    if (props.bracket.type === 'Double Elimination') {
        props.bracket.matches.winners.forEach(round => addPlayersFromMatches(round));
        props.bracket.matches.losers.forEach(round => addPlayersFromMatches(round));
        if (props.bracket.matches.grand_finals) {
            addPlayersFromMatches(props.bracket.matches.grand_finals[0]);
        }
    } else if (Array.isArray(props.bracket.matches)) {
        props.bracket.matches.forEach(round => addPlayersFromMatches(round));
    }

    editablePlayers.value = Array.from(players).map(name => ({
        oldName: name,
        newName: name
    }));

    showPlayerEditModal.value = true;
};

const savePlayerNames = async () => {
    const newNames = editablePlayers.value.map(p => p.newName.trim().toLowerCase());
    const hasDuplicates = newNames.length !== new Set(newNames).size;

    if (hasDuplicates) {
        showDuplicatePlayerError.value = true;
        return;
    }

    // Show confirmation dialog instead of saving directly
    showSaveConfirm.value = true;
};

const proceedWithSave = async () => {
    isSavingPlayers.value = true;

    // This is now called after confirmation
    const updates = editablePlayers.value
        .filter(p => p.oldName !== p.newName && p.newName.trim() !== '');

    const nameUpdates = editablePlayers.value
        .filter(p => p.oldName !== p.newName && p.newName.trim() !== '')
        .map(p => ({ oldName: p.oldName, newName: p.newName.trim() }));

    if (updates.length > 0) {
        try {
            await axios.put(route('api.brackets.updatePlayerNames', props.bracket.id), { updates });

            const updatePlayerInMatches = (matches, updates) => {
                matches.forEach(match => {
                    if (match.players) {
                        match.players.forEach(player => {
                            const update = updates.find(u => u.oldName === player.name);
                            if (update) {
                                player.name = update.newName;
                                if (playerColors.value[update.oldName]) {
                                    playerColors.value[update.newName] = playerColors.value[update.oldName];
                                    // Also update the child component's colors
                                    if (bracketViewRef.value?.playerColors) {
                                        bracketViewRef.value.playerColors[update.newName] = playerColors.value[update.oldName];
                                    }
                                }
                            }
                        });
                    }
                });
            };

            if (props.bracket.type === 'Double Elimination') {
                props.bracket.matches.winners.forEach(round => updatePlayerInMatches(round, updates));
                props.bracket.matches.losers.forEach(round => updatePlayerInMatches(round, updates));
                if (props.bracket.matches.grand_finals) {
                    updatePlayerInMatches(props.bracket.matches.grand_finals[0], updates);
                }
            } else if (Array.isArray(props.bracket.matches)) {
                props.bracket.matches.forEach(round => updatePlayerInMatches(round, updates));
            }

            showSuccess('Player names updated successfully!');
        } catch (error) {
            console.error('Error updating player names:', error);
            showError('Failed to update player names. Please try again.');
        }
    }

    // Only save colors for Double Elimination brackets
    if (props.bracket.type === 'Double Elimination') {
        const colorUpdates = editablePlayers.value
            .filter(p => bracketViewRef.value?.playerColors[p.oldName] !== playerColors.value[p.oldName]);

        try {
            await axios.put(route('api.brackets.updatePlayerColors', props.bracket.id), { colors: playerColors.value });
            if (colorUpdates.length > 0) showSuccess('Player colors updated successfully!');
        } catch (error) {
            console.error('Error updating player colors:', error);
            showError('Failed to update player colors. Please try again.');
        }
    }

    isSavingPlayers.value = false;
    showPlayerEditModal.value = false;
    showSaveConfirm.value = false; // Ensure confirm dialog is closed
};

</script>

<template>
    <div class="bracket-section">
        <div class="bracket-wrapper">
            <div class="bracket-header compact-v2">
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
                            v-if="showAdminControls && user?.role === 'Admin' && !isArchived"
                            icon="pi pi-pen-to-square"
                            @click="openPlayerEditModal"
                            class="p-button-rounded p-button-text action-btn-info"
                            v-tooltip.top="'Edit Player Names'"
                        />
                        <Button
                            v-if="showAdminControls && user?.role === 'Admin' && !isArchived && bracket.type === 'Single Elimination' && bracket.matches?.length >= 2"
                            :icon="hasConsolationMatch ? 'pi pi-minus-circle' : 'pi pi-plus-circle'"
                            @click="() => onToggleConsolationMatch(bracketIndex)"
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
                <!-- <div v-if="getBracketStats(bracket).status.text === 'Completed' && getBracketStats(bracket).winnerName" class="winner-banner">
                    <i class="pi pi-trophy"></i>
                    <span>Winner: <strong>{{ getBracketStats(bracket).winnerName }}</strong></span>
                </div> -->

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
                    ref="bracketViewRef"
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
                        v-if="bracket.type === 'Double Elimination'"
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
                <button @click="savePlayerNames" class="create-button" :loading="isSavingPlayers" :disabled="isSavingPlayers" label="Save Changes">Save Changes</button>
            </div>
        </div>
    </div>

    <!-- Duplicate Player Name Error Dialog -->
    <ConfirmationDialog
        v-model:show="showDuplicatePlayerError"
        title="Duplicate Player Names"
        message="Duplicate player names are not allowed. Please ensure all player names are unique."
        confirmText="OK"
        :showCancelButton="false"
        confirmButtonClass="modal-button-danger"
        @confirm="showDuplicatePlayerError = false"
    />

    <!-- Save Player Names Confirmation Dialog -->
    <ConfirmationDialog
        v-model:show="showSaveConfirm"
        title="Save Player Names"
        message="Are you sure you want to save these changes?"
        confirmText="Yes, Save"
        @confirm="proceedWithSave"
    />

    <LoadingSpinner :show="isSavingPlayers" message="Processing..."/>
</template>

<style scoped>
.bracket-header.compact-v2 {
    padding: 0.5rem 1rem; /* Further reduced vertical padding */
}
.bracket-header.compact-v2 h2 {
    font-size: 1.1rem; /* Further reduced font size */
    margin-bottom: 0.1rem;
}
.bracket-header.compact-v2 .winner-banner {
    margin-top: 0.25rem;
    padding: 0.25rem 0.75rem;
    font-size: 0.8rem;
}
.bracket-header.compact-v2 .event-link-container {
    margin-top: 0.25rem;
}
.bracket-header.compact-v2 .bracket-stats {
    margin-top: 0.25rem;
    gap: 0.75rem;
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
    z-index: 9997; /* Lower than ConfirmationDialog's 9998 to appear underneath */
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
</style>
