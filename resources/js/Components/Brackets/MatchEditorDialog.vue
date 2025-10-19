<script setup>
import { computed, ref, watch } from 'vue';
import { parseISO } from 'date-fns';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import ToggleSwitch from 'primevue/toggleswitch';
import DatePicker from 'primevue/datepicker';
import ConfirmationDialog from '@/Components/ConfirmationDialog.vue';

const props = defineProps({
    show: {
        type: Boolean,
        required: true,
    },
    matchData: {
        type: Object,
        default: null,
    },
    bracket: {
        type: Object,
        default: null,
    },
});

const emit = defineEmits(['update:show', 'confirm', 'update:matchData']);

const showUpdateConfirm = ref(false);

const localMatchData = computed({
    get: () => props.matchData,
    set: (value) => emit('update:matchData', value),
});

watch(() => props.show, (newVal) => {
    if (newVal && localMatchData.value) {
        // Pre-fill scores for BYE matches upon opening the dialog
        const isPlayer1Bye = localMatchData.value.player1Name === 'BYE';
        const isPlayer2Bye = localMatchData.value.player2Name === 'BYE';

        if (isPlayer1Bye || isPlayer2Bye) {
            if (isPlayer1Bye) {
                localMatchData.value.player1Score = 0;
                localMatchData.value.player2Score = 1;
            } else if (isPlayer2Bye) {
                localMatchData.value.player1Score = 1;
                localMatchData.value.player2Score = 0;
            }
            // Automatically set the status to completed for any BYE match
            // This simplifies the user flow, as a BYE match has a predetermined outcome.
            localMatchData.value.status = 'completed';
        }
    }
});

const isMatchDataInvalid = computed(() => {
    if (!localMatchData.value || !props.bracket) return true;

    const { status, player1Score, player2Score, player1Name, player2Name } = localMatchData.value;
    const bracketType = props.bracket.type;

    if (status === 'completed' && player1Score === player2Score && (bracketType === 'Single Elimination' || bracketType === 'Double Elimination')) {
        return true;
    }

    if (player1Name.trim() === '' || player2Name.trim() === '') {
        return true;
    }

    return false;
});

const isDrawNotAllowed = computed(() => {
    if (!localMatchData.value || !props.bracket) return false;
    
    const { status, player1Score, player2Score } = localMatchData.value;
    const bracketType = props.bracket.type;
    
    // Check if it's a draw and draws are not allowed
    if (status === 'completed' && player1Score === player2Score) {
        // Elimination brackets never allow draws
        if (bracketType === 'Single Elimination' || bracketType === 'Double Elimination') {
            return true;
        }
        // Round Robin only allows draws if allow_draws is true
        if (bracketType === 'Round Robin' && !props.bracket.allow_draws) {
            return true;
        }
    }
    
    return false;
});

const isMultiDayEvent = computed(() => {
  if (props.bracket?.event) {
      return props.bracket.event.startDate !== props.bracket.event.endDate;
  }
  return false;
});

const eventMinDate = computed(() => {
    if (props.bracket?.event?.startDate) {
        return parseISO(props.bracket.event.startDate);
    }
    return null;
});

const eventMaxDate = computed(() => {
    if (props.bracket?.event?.endDate) {
        return parseISO(props.bracket.event.endDate);
    }
    return null;
});

const closeModal = () => {
    emit('update:show', false);
};

const confirmUpdate = () => {
    showUpdateConfirm.value = true;
};

const proceedWithUpdate = () => {
    emit('confirm');
    // The parent component will close the main dialog upon successful update.
};
</script>

<template>
    <Dialog :visible="show" @update:visible="closeModal" header="Edit Match" modal :style="{ width: '500px' }">
        <div v-if="localMatchData" class="round-robin-match-dialog">
            <div class="match-info">
                <h3>Match Details</h3>
                <p class="match-description">Edit player names, scores, and match status</p>
            </div>

            <div class="player-section">
                <div class="player-input">
                    <label>Player 1 Name:</label>
                    <InputText v-model="localMatchData.player1Name" placeholder="Enter player name" :disabled="localMatchData?.player1Name === 'BYE'" />
                </div>
                <div class="score-section">
                    <label>Player 1 Score:</label>
                    <div class="score-controls">
                        <Button @click="localMatchData.player1Score--" :disabled="localMatchData.player1Score <= 0 || localMatchData.status === 'completed'" icon="pi pi-minus" class="p-button-sm" severity="danger" v-tooltip="{ value: 'Scores cannot be changed for a completed match.', disabled: localMatchData.status !== 'completed' }" />
                        <span class="score-display">{{ localMatchData.player1Score }}</span>
                        <Button @click="localMatchData.player1Score++" :disabled="localMatchData.status === 'completed'" icon="pi pi-plus" class="p-button-sm" severity="success" v-tooltip="{ value: 'Scores cannot be changed for a completed match.', disabled: localMatchData.status !== 'completed' }" />
                    </div>
                </div>
            </div>

            <div class="vs-divider">VS</div>

            <div class="player-section">
                <div class="player-input">
                    <label>Player 2 Name:</label>
                    <InputText v-model="localMatchData.player2Name" placeholder="Enter player name" :disabled="localMatchData?.player2Name === 'BYE'" />
                </div>
                <div class="score-section">
                    <label>Player 2 Score:</label>
                    <div class="score-controls">
                        <Button @click="localMatchData.player2Score--" :disabled="localMatchData.player2Score <= 0 || localMatchData.status === 'completed'" icon="pi pi-minus" class="p-button-sm" severity="danger" v-tooltip="{ value: 'Scores cannot be changed for a completed match.', disabled: localMatchData.status !== 'completed' }" />
                        <span class="score-display">{{ localMatchData.player2Score }}</span>
                        <Button @click="localMatchData.player2Score++" :disabled="localMatchData.status === 'completed'" icon="pi pi-plus" class="p-button-sm" severity="success" v-tooltip="{ value: 'Scores cannot be changed for a completed match.', disabled: localMatchData.status !== 'completed' }" />
                    </div>
                </div>
            </div>

            <div class="vs-divider"></div>

            <div class="grid grid-cols-2 gap-4">
                <div class="p-field">
                    <label>Date:</label>
                    <DatePicker v-model="localMatchData.date" dateFormat="yy-mm-dd" showIcon :minDate="eventMinDate" :maxDate="eventMaxDate" :disabled="!isMultiDayEvent" class="w-full" />
                </div>
                <div class="p-field">
                    <label>Time:</label>
                    <InputText type="time" v-model="localMatchData.time" class="w-full" />
                </div>
            </div>
            <div class="p-field">
                <label>Venue:</label>
                <InputText v-model="localMatchData.venue" class="w-full" />
            </div>

            <div class="match-status-section">
                <label>Match Status:</label>
                <div class="status-toggle">
                    <span class="status-label" :class="{ 'active': localMatchData.status === 'pending' }">Pending</span>
                    <ToggleSwitch :modelValue="localMatchData.status === 'completed'" @update:modelValue="(value) => localMatchData.status = value ? 'completed' : 'pending'" class="status-switch" />
                    <span class="status-label" :class="{ 'active': localMatchData.status === 'completed' }">Completed</span>
                </div>
            </div>

            <div v-if="localMatchData.status === 'completed' && localMatchData.player1Score === localMatchData.player2Score" :class="['tie-indicator', (bracket?.type !== 'Round Robin' || (bracket?.type === 'Round Robin' && !bracket?.allow_draws)) ? 'tie-warning-bg' : '']">
                <i class="pi pi-exclamation-triangle"></i>
                <span v-if="bracket?.type === 'Round Robin' && bracket?.allow_draws">This match is a tie!</span>
                <span v-else class="tie-warning">Draws are not allowed in this tournament. Please adjust scores to determine a winner.</span>
            </div>

            <div class="dialog-actions">
                <button @click="closeModal" class="modal-button-secondary">Cancel</button>
                <button @click="confirmUpdate" class="modal-button-primary" :disabled="isMatchDataInvalid || isDrawNotAllowed">Update Match</button>
            </div>
        </div>

        <ConfirmationDialog
            v-model:show="showUpdateConfirm"
            title="Confirm Match Update"
            message="Are you sure you want to update this match? This action may trigger bracket progression and cannot be easily undone."
            confirmText="Yes, Update Match"
            confirmButtonClass="modal-button-primary"
            @confirm="proceedWithUpdate"
            :style="{ zIndex: 1102 }"
        />
    </Dialog>
</template>

<style scoped>
  .round-robin-match-dialog {
    padding: 20px;
  }

  .match-info {
    text-align: center;
    margin-bottom: 20px;
  }

  .match-info h3 {
    margin: 0 0 5px 0;
    color: #333;
    font-size: 1.2rem;
  }

  .match-description {
    margin: 0;
    color: #666;
    font-size: 0.9rem;
  }

  .player-section {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
  }

  .player-input {
    margin-bottom: 15px;
  }

  .player-input label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
    color: #333;
  }
</style>
