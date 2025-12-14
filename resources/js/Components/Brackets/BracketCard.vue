<script setup>
import { computed, ref, watch, onMounted } from 'vue';
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

const menu = ref();

const adminMenuItems = computed(() => {
    const items = [];

    if (props.showAdminControls && (props.user?.role === 'Admin' || props.user.role == 'TournamentManager') && !props.isArchived) {
        items.push({
            label: 'Edit Player Names',
            icon: 'pi pi-pen-to-square',
            command: () => openPlayerEditModal()
        });

        if (props.bracket.type === 'Single Elimination' && props.bracket.matches?.length >= 2) {
            items.push({
                label: hasConsolationMatch.value ? 'Remove 3rd Place Match' : 'Add 3rd Place Match',
                icon: hasConsolationMatch.value ? 'pi pi-minus-circle' : 'pi pi-plus-circle',
                command: () => props.onToggleConsolationMatch(props.bracketIndex)
            });
        }

        if (props.bracket.type === 'Round Robin') {
             items.push({
                label: props.bracket.allow_draws ? 'Disable Draws' : 'Enable Draws',
                icon: props.bracket.allow_draws ? 'pi pi-check-circle' : 'pi pi-times-circle',
                command: () => props.onToggleAllowDraws(props.bracketIndex)
            });
        }
    }

    if (props.showAdminControls && props.user?.role === 'Admin' && !props.isArchived) {
        if (items.length > 0) {
            items.push({ separator: true });
        }
        items.push({
            label: 'Delete Bracket',
            icon: 'pi pi-trash',
            command: () => handleRemoveBracket(),
            class: 'p-menuitem-danger'
        });
    }

    return items;
});

const toggleAdminMenu = (event) => {
    menu.value.toggle(event);
};

// New Computed Properties for UI/UX enhancements
const statusInfo = computed(() => {
    const stats = props.getBracketStats(props.bracket);
    const statusText = stats.status.text;
    switch (statusText) {
        case 'Upcoming':
            return { text: 'Upcoming', icon: 'pi pi-clock', class: 'status-upcoming', borderClass: 'border-l-4 border-blue-500 hover:border-blue-400' };
        case 'Ongoing':
            return { text: 'Ongoing', icon: 'pi pi-play-circle', class: 'status-ongoing', borderClass: 'border-l-4 border-green-500 hover:border-green-400' };
        case 'Completed':
            return { text: 'Completed', icon: 'pi pi-check-circle', class: 'status-completed', borderClass: 'border-l-4 border-purple-500 hover:border-purple-400' };
        default:
            return { text: statusText, icon: 'pi pi-info-circle', class: 'status-default', borderClass: 'border-l-4 border-gray-500 hover:border-gray-400' };
    }
});

const typeInfo = computed(() => {
    const type = props.bracket.type;
    switch (type) {
        case 'Double Elimination':
            return { text: type, icon: 'pi pi-clone', class: 'type-double-elim' };
        case 'Round Robin':
            return { text: type, icon: 'pi pi-refresh', class: 'type-round-robin' };
        case 'Single Elimination':
            return { text: type, icon: 'pi pi-sitemap', class: 'type-single-elim' };
        default:
            return { text: type, icon: 'pi pi-question-circle', class: 'type-default' };
    }
});

const matchesPlayed = computed(() => {
    if (!props.bracket.matches) return 0;
    const allMatches = Array.isArray(props.bracket.matches)
        ? props.bracket.matches.flat()
        : [
            ...(props.bracket.matches.winners?.flat() || []),
            ...(props.bracket.matches.losers?.flat() || []),
            ...(props.bracket.matches.grand_finals?.flat() || [])
          ].flat();

    return allMatches.filter(m => m && m.status === 'completed').length;
});

const progressPercent = computed(() => {
    const stats = props.getBracketStats(props.bracket);
    if (!stats.totalGames || stats.totalGames === 0) return 0;
    // For "Completed" brackets, always show 100%
    if (statusInfo.value.text === 'Completed') return 100;
    return Math.round((matchesPlayed.value / stats.totalGames) * 100);
});

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
let originalPlayerColors = {};
const bracketViewKey = ref(0);

const generatePlayerColor = (playerName) => {
    if (!playerName || playerName === 'TBD' || playerName === 'BYE' || playerName.includes('Winner') || playerName.includes('Loser')) {
        return null;
    }
    if (playerColors.value[playerName]) {
        return playerColors.value[playerName];
    }
    const colors = [
        '#FF6B6B', '#4ECDC4', '#45B7D1', '#FFA07A', '#98D8C8',
        '#F7DC6F', '#BB8FCE', '#85C1E2', '#F8B739', '#52B788',
        '#E63946', '#457B9D', '#F77F00', '#06FFA5', '#B5179E'
    ];
    let hash = 0;
    for (let i = 0; i < playerName.length; i++) {
        hash = playerName.charCodeAt(i) + ((hash << 5) - hash);
    }
    const colorIndex = Math.abs(hash) % colors.length;
    playerColors.value[playerName] = colors[colorIndex];
    return playerColors.value[playerName];
};

const populateInitialColors = () => {
    // Clear existing colors to prevent stale data on bracket change
    playerColors.value = {};

    const processMatches = (matches) => {
        if (!matches) return;
        matches.flat().forEach(match => {
            if (match && match.players) {
                match.players.forEach(player => {
                    // If player has a color from the DB, use it
                    if (player && player.name && player.color && !playerColors.value[player.name]) {
                        playerColors.value[player.name] = player.color;
                    } else {
                        // Otherwise, generate a color for them
                        generatePlayerColor(player.name);
                    }
                });
            }
        });
    };

    // Process all parts of the bracket
    processMatches(props.bracket.matches?.winners);
    processMatches(props.bracket.matches?.losers);
    processMatches(props.bracket.matches?.grand_finals);
    // Also handle Single Elim and Round Robin
    if (Array.isArray(props.bracket.matches)) {
        processMatches(props.bracket.matches);
    }
};

const openPlayerEditModal = () => {
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

    // Store a snapshot of the colors when the modal opens
    originalPlayerColors = { ...playerColors.value };

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
        const colorUpdates = Object.keys(playerColors.value).filter(
            playerName => originalPlayerColors[playerName] !== playerColors.value[playerName]
        );

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

    // Force BracketView to re-render with updated colors
    bracketViewKey.value++;
};

onMounted(() => {
    populateInitialColors();
});

// Watch for bracket changes to re-populate colors
watch(() => props.bracket, populateInitialColors, { deep: true, immediate: true });

const handleRepopulateColors = () => {
    playerColors.value = {};
    populateInitialColors();
};

</script>

<template>
    <div class="bracket-section">
        <div
            class="bracket-card group/card bg-white rounded-lg shadow-md transition-all duration-300 ease-in-out hover:shadow-xl hover:-translate-y-1"
            :class="[statusInfo.borderClass]"
        >
            <!-- Card Header -->
            <div class="p-4">
                <div class="flex justify-between items-start">
                    <!-- Left: Title and Tags -->
                    <div class="flex-grow min-w-0">
                        <h2 class="text-lg font-semibold text-gray-800 break-words">{{ bracket.name }}</h2>
                        <Link v-if="showEventLink && bracket.event" :href="route('event.details', { id: bracket.event_id })" class="text-sm text-gray-500 hover:text-blue-600 transition-colors duration-200 flex items-center gap-2 mt-1">
                            <i class="pi pi-link"></i>
                            <span>{{ bracket.event.title }}</span>
                        </Link>

                        <div class="flex items-center gap-2 mt-3">
                            <span :class="['tag', typeInfo.class]">
                                <i :class="typeInfo.icon"></i>
                                {{ typeInfo.text }}
                            </span>
                            <span :class="['tag', statusInfo.class]">
                                <i :class="statusInfo.icon" ></i>
                                {{ statusInfo.text }}
                            </span>
                        </div>
                    </div>

                    <!-- Right: Action Buttons -->
                    <div class="flex-shrink-0 flex items-center flex-wrap justify-end gap-x-1 gap-y-0">
                        <!-- Desktop Admin controls: visible on hover on desktop -->
                        <div class="hidden lg:flex items-center gap-1 opacity-0 group-hover/card:opacity-100 transition-opacity duration-300">
                            <Button
                                v-if="showAdminControls && (user?.role === 'Admin' || user.role == 'TournamentManager') && !isArchived"
                                icon="pi pi-pen-to-square"
                                @click="openPlayerEditModal"
                                class="p-button-rounded p-button-text action-btn-info"
                                v-tooltip.top="'Edit Player Names'"
                            />
                            <Button
                                v-if="showAdminControls && (user?.role === 'Admin' || user.role == 'TournamentManager') && !isArchived && bracket.type === 'Single Elimination' && bracket.matches?.length >= 2"
                                :icon="hasConsolationMatch ? 'pi pi-minus-circle' : 'pi pi-plus-circle'"
                                @click="() => onToggleConsolationMatch(bracketIndex)"
                                class="p-button-rounded p-button-text"
                                :class="hasConsolationMatch ? 'p-button-warning' : 'p-button-success'"
                                v-tooltip.top="hasConsolationMatch ? 'Remove 3rd Place Match' : 'Add 3rd Place Match'"
                            />
                            <Button
                                v-if="showAdminControls && (user?.role === 'Admin' || user.role == 'TournamentManager') && !isArchived && bracket.type === 'Round Robin'"
                                :icon="bracket.allow_draws ? 'pi pi-check-circle' : 'pi pi-times-circle'"
                                @click="onToggleAllowDraws(bracketIndex)"
                                class="p-button-rounded p-button-text"
                                :class="bracket.allow_draws ? 'p-button-success' : 'p-button-secondary'"
                                v-tooltip.top="bracket.allow_draws ? 'Draws Enabled' : 'Draws Disabled'"
                            />
                            <Button
                                v-if="showAdminControls && user?.role === 'Admin' && !isArchived"
                                :icon="isDeletingBracket ? 'pi pi-spin pi-spinner' : 'pi pi-trash'"
                                @click="handleRemoveBracket"
                                :disabled="isDeletingBracket"
                                class="p-button-rounded p-button-text action-btn-delete"
                                v-tooltip.top="'Delete Bracket'"
                            />
                        </div>

                        <!-- Mobile Admin Menu -->
                        <div class="lg:hidden" v-if="adminMenuItems.length > 0">
                            <Button icon="pi pi-ellipsis-v" @click="toggleAdminMenu" class="p-button-rounded p-button-text action-btn-toggle" aria-haspopup="true" :aria-controls="`admin_menu_${bracket.id}`" />
                            <Menu ref="menu" :id="`admin_menu_${bracket.id}`" :model="adminMenuItems" :popup="true" />
                        </div>

                        <!-- Always-visible toggle -->
                        <Button :icon="isExpanded ? 'pi pi-chevron-up' : 'pi pi-chevron-down'" @click="handleToggleBracket" class="p-button-rounded p-button-text action-btn-toggle" v-tooltip.top="isExpanded ? 'Hide Details' : 'Show Details'" />
                    </div>
                </div>
            </div>

            <!-- Progress Bar and Stats -->
            <div class="px-4 pb-4">
                <div v-if="getBracketStats(bracket).totalGames > 0" class="progress-section">
                    <div class="flex justify-between text-sm text-gray-600 mb-1">
                        <span>Progress</span>
                        <span class="font-medium">{{ matchesPlayed }} / {{ getBracketStats(bracket).totalGames }} Matches</span>
                    </div>
                    <div class="progress-bar-bg">
                        <div class="progress-bar-fg" :style="{ width: progressPercent + '%' }" :class="statusInfo.class"></div>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-2 mt-4 pt-4 border-t border-gray-200">
                    <div class="stat-item flex items-center gap-2">
                        <i class="pi pi-users text-lg text-gray-400"></i>
                        <div>
                            <span class="label">Participants: </span>
                            <span class="value">{{ getBracketStats(bracket).participants }}</span>
                        </div>
                    </div>
                    <div class="stat-item flex items-center gap-2">
                        <i class="pi pi-sitemap text-lg text-gray-400"></i>
                        <div>
                            <span class="label">Rounds: </span>
                            <span class="value">{{ getBracketStats(bracket).rounds }}</span>
                        </div>
                    </div>
                    <div class="stat-item flex items-center gap-2">
                        <i class="pi pi-calendar text-lg text-gray-400"></i>
                        <div>
                            <span class="label">Date: </span>
                            <span class="value">{{ formatDisplayDate(bracket.event.startDate) }}</span>
                        </div>
                    </div>
                </div>
            </div>


            <!-- Collapsible Content -->
            <div v-if="isExpanded" class="bracket-content-wrapper border-t border-gray-200" :id="`bracket-content-${bracket.id}`">
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
                                class="responsive-select-button"
                            />
                        </div>
                    </div>

                <BracketView
                        ref="bracketViewRef"
                        :key="bracketViewKey"
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
                        :playerColors="playerColors"
                        :generatePlayerColor="generatePlayerColor"
                        @repopulate-colors="handleRepopulateColors"
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

        <!-- Player Name Edit Modal -->
        <div v-if="showPlayerEditModal" class="modal-overlay">
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
    </div>
</template>

<style scoped>
/* Card Enhancements */
.bracket-card {
    transition: all 0.3s ease-in-out;
}
.tag {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: capitalize;
}
.status-upcoming { background-color: #e0f2fe; color: #0c4a6e; }
.status-ongoing { background-color: #dcfce7; color: #166534; }
.status-completed { background-color: #f3e8ff; color: #581c87; }
.status-default { background-color: #f3f4f6; color: #4b5563; }

.type-double-elim { background-color: #ede9fe; color: #5b21b6; }
.type-round-robin { background-color: #fef9c3; color: #854d0e; }
.type-single-elim { background-color: #e5e7eb; color: #374151; }
.type-default { background-color: #f3f4f6; color: #4b5563; }
.progress-section .progress-bar-bg {
    background-color: #e5e7eb;
    border-radius: 9999px;
    height: 0.75rem;
    overflow: hidden;
}
.progress-section .progress-bar-fg {
    height: 100%;
    border-radius: 9999px;
    transition: width 0.5s ease-in-out;
}
.progress-bar-fg.status-upcoming { background-color: #3b82f6; }
.progress-bar-fg.status-ongoing { background-color: #22c55e; }
.progress-bar-fg.status-completed { background-color: #a855f7; }
.progress-bar-fg.status-default { background-color: #6b7280; }
.stat-item .value {
    font-size: 0.875rem;
    font-weight: 600;
    color: #1f2937;
}
.stat-item .label {
    font-size: 0.875rem;
    color: #6b7280;
}
.bracket-header.compact-v2 {
    padding: 0.5rem 1rem;
    /* Further reduced vertical padding */
}
.bracket-header.compact-v2 h2 {
    font-size: 1.1rem;
    /* Further reduced font size */
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
    z-index: 9997;
    /* Lower than ConfirmationDialog's 9998 to appear underneath */
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
.btn-cancel,
.btn-save {
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
.bracket-controls {
    display: flex;
    align-items: center;
}
@media (max-width: 640px) {
    /* sm breakpoint */
    :deep(.responsive-select-button) {
        display: flex;
        flex-direction: column;
        width: 100%;
    }
    :deep(.responsive-select-button .p-button) {
        width: 100%;
        justify-content: center;
        border-radius: 0 !important;
    }
    :deep(.responsive-select-button .p-button:first-child) {
        border-top-left-radius: 6px !important;
        border-top-right-radius: 6px !important;
    }
    :deep(.responsive-select-button .p-button:last-child) {
        border-bottom-left-radius: 6px !important;
        border-bottom-right-radius: 6px !important;
    }
}

.action-btn-delete,
.action-btn-toggle {
    color: #374151 !important;
}

.action-btn-delete:hover,
.action-btn-delete:focus {
    background-color: rgba(239, 68, 68, 0.1) !important;
    color: #ef4444 !important;
}

.action-btn-toggle:hover,
.action-btn-toggle:focus {
    background-color: rgba(229, 231, 235, 0.5) !important;
    color: #1f2937 !important;
}

:deep(.p-menuitem-danger > .p-menuitem-link) {
    color: #ef4444 !important;
}
:deep(.p-menuitem-danger > .p-menuitem-link:hover) {
    background-color: rgba(239, 68, 68, 0.1) !important;
}
</style>

