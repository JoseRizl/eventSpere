<template>
    <Dialog v-model:visible="props.tasksManager.isTaskModalVisible.value" modal header="Manage Tasks" :style="{ width: 'min(700px, 90vw)' }">
        <div class="p-fluid">
            <div class="p-field">
                <label>Event</label>
                <InputText v-if="props.tasksManager.selectedEventForTasks.value" v-model="props.tasksManager.selectedEventForTasks.value.title" disabled />
            </div>

            <!-- Task Entries -->
            <div v-for="(taskEntry, index) in props.tasksManager.taskAssignments.value" :key="index" class="p-field border-b pb-4 mb-4 last:border-b-0">
                <div class="flex justify-between items-center mb-2">
                    <h3 class="font-semibold text-lg">Task {{ index + 1 }}</h3>
                </div>


                <!-- Committee Selection -->
                <div class="p-field">
                    <label for="committee">Committee <span style="color: #6c757d; font-weight: normal;">(Optional)</span></label>
                    <div class="flex items-center gap-2">
                        <Select
                            v-model="taskEntry.committee"
                            :options="localCommittees"
                            optionLabel="name"
                            placeholder="Select Committee"
                            filter
                            class="w-full"
                        >
                            <template #option="slotProps">
                                <div class="flex justify-between items-center w-full">
                                    <span>{{ slotProps.option.name }}</span>
                                    <i v-if="!isCommitteeInUse(slotProps.option.id)"
                                        class="pi pi-times text-red-500 hover:text-red-700 cursor-pointer"
                                        @click.stop="promptDeleteCommittee(slotProps.option)" v-tooltip.top="'Delete Committee'"></i>
                                    <i v-else
                                        class="pi pi-info-circle text-blue-500 hover:text-blue-700 cursor-pointer"
                                        @click.stop="showUsageDetails(slotProps.option)"
                                        v-tooltip.top="'Committee is in use. Click for details.'"></i>
                                </div>
                            </template>
                            <template #value="slotProps">
                                <div v-if="slotProps.value">{{ slotProps.value.name }}</div>
                                <span v-else>{{ slotProps.placeholder }}</span>
                            </template>
                        </Select>
                        <Button icon="pi pi-plus" class="p-button-secondary p-button-rounded" @click="openCreateCommitteeModal" v-tooltip.top="'Create New Committee'" />
                    </div>
                </div>

                <!-- Employee Selection -->
                <div class="p-field">
                    <label>Employees <span style="color: red;">*</span></label>
                    <MultiSelect
                        v-model="taskEntry.employees"
                        :options="localEmployees"
                        optionLabel="name"
                        placeholder="Select Employees"
                        display="chip"
                        class="w-full"
                        filter
                        :showToggleAll="false"
                    >
                        <template #chip="slotProps">
                            <div class="flex items-center gap-2 px-2 py-1 rounded bg-blue-100 text-blue-800 text-xs">
                                {{ slotProps.value.name }}
                                <button
                                    type="button"
                                    class="text-blue-600 hover:text-blue-800"
                                    @click.stop="taskEntry.employees = taskEntry.employees.filter(e => e.id !== slotProps.value.id)"
                                    v-tooltip.top="'Remove Employee'"
                                >
                                    ✕
                                </button>
                            </div>
                        </template>
                    </MultiSelect>
                </div>

                <!-- Bracket Assignment -->
                <div v-if="taskHasManager(taskEntry)" class="p-field">
                    <label>Assign Manager(s) to Bracket <span style="color: red;">*</span></label>
                    <small class="p-text-secondary d-block mb-2">This assignment only applies to personnel with the 'Tournament Manager' role.</small>
                    <MultiSelect
                        v-model="taskEntry.assignedBrackets"
                        :options="localBrackets"
                        optionLabel="name"
                        placeholder="Select brackets for manager"
                        display="chip"
                        class="w-full"
                        filter
                        :showToggleAll="false"
                    />
                </div>

                <!-- Task Description -->
                <div class="p-field">
                    <label>Task <span style="color: red;">*</span></label>
                    <Textarea v-model="taskEntry.task" rows="2" placeholder="Enter task details" />
                    <div class="mt-5">
                        <button @click="promptDeleteTask(index)" class="text-red-500 hover:text-red-700 text-sm flex items-center">
                            <i class="pi pi-times mr-1"></i>
                            Clear Task
                        </button>
                    </div>
                </div>
            </div>

            <!-- Add Task Button -->
            <div class="flex justify-between items-center mt-2">
                <button @click="props.tasksManager.addTask()" class="text-blue-500 hover:text-blue-700 text-sm flex items-center">
                    <i class="pi pi-plus mr-1"></i> Add Task
                </button>
                <!-- <button
                    v-if="props.tasksManager.taskAssignments.value.length > 0"
                    @click="promptClearAllTasks" class="text-red-500 hover:text-red-700 text-sm flex items-center">
                    <i class="pi pi-trash mr-1"></i> Clear All Tasks
                </button> -->
            </div>
        </div>

        <template #footer>
            <div class="flex justify-between items-center flex-wrap gap-2">
                <div v-if="taskErrorMessage" class="text-red-500 text-sm text-left">
                    {{ taskErrorMessage }}
                </div>
                <div class="flex gap-2 ml-auto">
                    <button class="modal-button-secondary sm:p-button-sm" @click="closeModal" :disabled="isSaving">Cancel</button>
                    <button class="modal-button-primary sm:p-button-sm" @click="promptSave" :disabled="isSaving">
                        <i v-if="isSaving" class="pi pi-spin pi-spinner mr-2"></i>
                        {{ isSaving ? 'Saving...' : 'Save Tasks' }}
                    </button>
                </div>
            </div>
        </template>
    </Dialog>

    <!-- Save Confirmation -->
    <ConfirmationDialog
        v-model:show="showSaveConfirm"
        title="Save Task Assignments?"
        message="Are you sure you want to save these changes? This will overwrite any existing tasks for this event."
        confirmText="Yes, Save"
        @confirm="handleSave"
    />

    <!-- Delete Confirmation -->
    <ConfirmationDialog
        v-model:show="showDeleteConfirm"
        title="Clear Task?"
        message="Are you sure you want to clear this task? This will remove it from the list."
        confirmText="Yes, Clear"
        confirmButtonClass="modal-button-danger"
        @confirm="confirmDeleteTask"
    />

    <!-- Clear All Confirmation -->
    <ConfirmationDialog
        v-model:show="showClearAllConfirm"
        title="Clear All Tasks?"
        message="Are you sure you want to clear all tasks? This action cannot be undone."
        confirmText="Yes, Clear All"
        @confirm="confirmClearAllTasks"
    />

    <!-- Create Committee Modal -->
    <Dialog v-model:visible="isCreateCommitteeModalVisible" modal header="Create New Committee" :style="{ width: '30vw' }">
        <div class="p-field">
            <label for="committeeName">Committee Name</label>
            <InputText id="committeeName" v-model="newCommittee.name" placeholder="Enter committee name" />
        </div>
        <template #footer>
            <button class="modal-button-secondary" @click="isCreateCommitteeModalVisible = false">Cancel</button>
            <button class="modal-button-primary" @click="createCommittee" :disabled="isSaving">Create</button>
        </template>
    </Dialog>

    <!-- Delete Committee Confirmation -->
    <ConfirmationDialog
        v-model:show="showDeleteCommitteeConfirm"
        title="Delete Committee?"
        :message="committeeToDelete ? `Are you sure you want to delete the committee '${committeeToDelete.name}'? This cannot be undone.` : ''"
        confirmText="Yes, Delete"
        confirmButtonClass="modal-button-danger"
        @confirm="confirmDeleteCommittee"
    />

    <!-- Committee In Use Dialog -->
    <Dialog v-model:visible="committeeUsageDialog.visible" modal header="Committee In Use" :style="{ width: '35vw' }">
        <div class="p-fluid">
            <p class="mb-4">
                The committee <strong>'{{ committeeInUseDetails.name }}'</strong> cannot be deleted because it is currently in use by the following:
            </p>

            <div v-if="committeeInUseDetails.tasks?.length > 0" class="mb-4">
                <h4 class="font-semibold mb-2">Tasks:</h4>
                <ul class="list-disc pl-5 space-y-1 text-sm">
                    <li v-for="(task, index) in committeeInUseDetails.tasks" :key="`task-${index}`">{{ task }}</li>
                </ul>
            </div>

            <div v-if="committeeInUseDetails.employees?.length > 0">
                <h4 class="font-semibold mb-2">Employees:</h4>
                <ul class="list-disc pl-5 space-y-1 text-sm">
                    <li v-for="(employee, index) in committeeInUseDetails.employees" :key="`employee-${index}`">{{ employee }}</li>
                </ul>
            </div>
        </div>
    </Dialog>
</template>

<script setup>
import { ref, watch, computed } from 'vue';
import ConfirmationDialog from '@/Components/ConfirmationDialog.vue';
import axios from 'axios';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    tasksManager: {
        type: Object,
        required: true,
    },
    committees: Array,
    employees: Array,
    brackets: Array,
});

const emit = defineEmits(['save-success', 'save-error', 'committee-action-success']);
const isSaving = ref(false);
const showSaveConfirm = ref(false);
const showDeleteConfirm = ref(false);
const showClearAllConfirm = ref(false);
const taskToDeleteIndex = ref(null);
const taskErrorMessage = ref(null);
const isCreateCommitteeModalVisible = ref(false);
const newCommittee = ref({ name: '' });
const showDeleteCommitteeConfirm = ref(false);
const committeeToDelete = ref(null);
const committeeUsageDialog = ref({ visible: false });
const committeeInUseDetails = ref({
    name: '',
    tasks: [],
    employees: []
});

// Local mutable copies of props
const localCommittees = ref([]);
const localEmployees = ref([]);
const localBrackets = ref([]);

watch(() => props.committees, (newVal) => {
    localCommittees.value = [...(newVal || [])];
}, { immediate: true, deep: true });

watch(() => props.employees, (newVal) => {
    localEmployees.value = [...(newVal || [])];
}, { immediate: true, deep: true });

watch(() => props.brackets, (newVal) => {
    localBrackets.value = [...(newVal || [])];
}, { immediate: true, deep: true });

const taskHasManager = computed(() => {
    return (taskEntry) => {
        if (!taskEntry || !Array.isArray(taskEntry.employees)) {
            return false;
        }
        return taskEntry.employees.some(e => e.type === 'user');
    };
});

const isCommitteeInUse = computed(() => {
    return (committeeId) => {
        const usedInTasks = props.tasksManager.taskAssignments.value.some(t => t.committee?.id === committeeId);
        const usedByEmployees = localEmployees.value.some(e => e.committee_id === committeeId);
        return usedInTasks || usedByEmployees;
    };
});

watch(() => props.tasksManager.isTaskModalVisible.value, (newValue) => {
    if (newValue) {
        taskErrorMessage.value = null; // Clear error when modal opens
    }
});

const closeModal = () => {
    props.tasksManager.isTaskModalVisible.value = false;
};

const promptSave = () => {
    showSaveConfirm.value = true;
    taskErrorMessage.value = null;
};

const promptDeleteTask = (index) => {
    taskToDeleteIndex.value = index;
    showDeleteConfirm.value = true;
};

const confirmDeleteTask = () => {
    if (taskToDeleteIndex.value !== null) {
        props.tasksManager.deleteTask(taskToDeleteIndex.value);
        taskToDeleteIndex.value = null;
    }
};

const promptClearAllTasks = () => {
    showClearAllConfirm.value = true;
};

const confirmClearAllTasks = () => {
    props.tasksManager.clearAllTasks();
};

const openCreateCommitteeModal = () => {
    newCommittee.value.name = '';
    isCreateCommitteeModalVisible.value = true;
};

const createCommittee = async () => {
    if (!newCommittee.value.name.trim()) {
        emit('save-error', 'Committee name cannot be empty.');
        return;
    }
    isSaving.value = true;
    try {
        const response = await axios.post(route('committees.store'), { name: newCommittee.value.name });
        if (response.data.success) {
            localCommittees.value.push(response.data.committee);
            isCreateCommitteeModalVisible.value = false;
            emit('committee-action-success', 'Committee created successfully!');
        }
    } catch (error) {
        const message = error.response?.data?.message || 'Failed to create committee.';
        taskErrorMessage.value = message;
    } finally {
        isSaving.value = false;
    }
};

const promptDeleteCommittee = (committee) => {
    if (isCommitteeInUse.value(committee.id)) {
        showUsageDetails(committee);
    } else {
        committeeToDelete.value = committee;
        showDeleteCommitteeConfirm.value = true;
    }
};

const showUsageDetails = (committee) => {
    committeeInUseDetails.value.name = committee.name;
    committeeInUseDetails.value.tasks = props.tasksManager.taskAssignments.value
        .filter(t => t.committee?.id === committee.id)
        .map(t => t.task || `Task ${props.tasksManager.taskAssignments.value.indexOf(t) + 1}`);
    committeeInUseDetails.value.employees = localEmployees.value
        .filter(e => e.committee_id === committee.id)
        .map(e => e.name);
    committeeUsageDialog.value.visible = true;
};

const confirmDeleteCommittee = async () => {
    if (!committeeToDelete.value) return;
    isSaving.value = true;
    try {
        const response = await axios.delete(route('committees.destroy', { id: committeeToDelete.value.id }));
        localCommittees.value = localCommittees.value.filter(c => c.id !== committeeToDelete.value.id);
        emit('committee-action-success', 'Committee deleted successfully!');
    } catch (error) {
        if (error.response?.status === 422 && error.response?.data?.in_use) {
            committeeInUseDetails.value = { ...error.response.data.details, name: committeeToDelete.value.name };
            committeeUsageDialog.value.visible = true;
        } else {
            taskErrorMessage.value = error.response?.data?.message || 'Failed to delete committee.';
        }
    } finally {
        isSaving.value = false;
        showDeleteCommitteeConfirm.value = false;
    }
};

const handleSave = () => {
    isSaving.value = true;
    taskErrorMessage.value = null;

    try {
        const tasksToSave = props.tasksManager.taskAssignments.value.map(task => {
            const employees = task.employees?.filter(p => p.type === 'employee') || [];
            const managers = task.employees?.filter(p => p.type === 'user') || [];

            // --- Validation ---
            if (employees.length === 0 && managers.length === 0) {
                throw new Error('Please assign at least one employee or manager to all tasks.');
            }
            if (!task.task || task.task.trim() === '') {
                throw new Error('Please provide a description for all tasks.');
            }
            if (managers.length > 0 && (!task.assignedBrackets || task.assignedBrackets.length === 0)) {
                throw new Error('Please assign at least one bracket to every selected Tournament Manager.');
            }
            // --- End Validation ---

            return {
                description: task.task,
                committee_id: task.committee ? task.committee.id : null,
                employee_ids: employees.map(e => e.id),
                manager_assignments: managers.map(m => ({
                    user_id: m.id,
                    // Ensure assignedBrackets is an array before mapping
                    bracket_ids: Array.isArray(task.assignedBrackets) ? task.assignedBrackets.map(b => b.id) : [],
                })),
            };
        });

        console.log('Saving tasks with payload:', tasksToSave);
        const onSuccess = (page) => {
            const newTasks = page.props.flash?.tasks || [];
            emit('save-success', { message: 'Tasks updated successfully!', tasks: newTasks });
        };

        props.tasksManager.saveTaskAssignments(tasksToSave, onSuccess);

        // Reset saving state after a delay to give Inertia time to finish.
        setTimeout(() => { isSaving.value = false; }, 2000);

    } catch (err) {
        taskErrorMessage.value = err.message;
        isSaving.value = false;
    }
};
</script>
