<template>
    <Dialog v-model:visible="tasksManager.isTaskModalVisible.value" modal header="Assign Tasks" :style="{ width: '50vw' }">
        <div class="p-fluid">
            <div class="p-field">
                <label>Event</label>
                <InputText v-if="tasksManager.selectedEventForTasks.value" v-model="tasksManager.selectedEventForTasks.value.title" disabled />
            </div>

            <!-- Task Entries -->
            <div v-for="(taskEntry, index) in tasksManager.taskAssignments.value" :key="index" class="p-field border-b pb-4 mb-4 last:border-b-0">
                <div class="flex justify-between items-center mb-2">
                    <h3 class="font-semibold text-lg">Task {{ index + 1 }}</h3>
                    <button
                        @click="tasksManager.deleteTask(index)"
                        class="text-red-500 hover:text-red-700 text-sm flex items-center"
                        v-tooltip.top="'Clear Task'">
                        <i class="pi pi-times mr-1"></i> Clear
                    </button>
                </div>


                <!-- Committee Selection -->
                <div class="p-field">
                    <label>Committee</label>
                    <Select
                        v-model="taskEntry.committee"
                        :options="committees"
                        optionLabel="name"
                        placeholder="Select Committee"
                        filter
                        @change="tasksManager.updateEmployeesForTask(index, employees)"
                    >
                        <template #option="slotProps">
                            <div>{{ slotProps.option.name }}</div>
                        </template>
                        <template #value="slotProps">
                            <div v-if="slotProps.value">{{ slotProps.value.name }}</div>
                            <span v-else>{{ slotProps.placeholder }}</span>
                        </template>
                    </Select>
                </div>

                <!-- Employee Selection -->
                <div v-if="taskEntry.committee" class="p-field">
                    <label>Employees</label>
                    <MultiSelect
                        v-model="taskEntry.employees"
                        :options="tasksManager.filteredEmployees.value[index]"
                        optionLabel="name"
                        placeholder="Select Employees"
                        display="chip"
                        filter
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

                <!-- Task Description -->
                <div class="p-field">
                    <label>Task</label>
                    <Textarea v-model="taskEntry.task" rows="2" placeholder="Enter task details" />
                </div>
            </div>

            <!-- Add Task Button -->
            <button @click="tasksManager.addTask()" class="text-blue-500 hover:text-blue-700 text-sm flex items-center mt-2">
                <i class="pi pi-plus mr-1"></i> Add Task
            </button>
        </div>

        <template #footer>
            <button class="modal-button-secondary" @click="tasksManager.isTaskModalVisible.value = false" :disabled="isSaving">Cancel</button>
            <button class="modal-button-primary" @click="handleSave" :disabled="isSaving">
                <i v-if="isSaving" class="pi pi-spin pi-spinner mr-2"></i>
                {{ isSaving ? 'Saving...' : 'Save Tasks' }}
            </button>
        </template>
    </Dialog>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
    tasksManager: {
        type: Object,
        required: true,
    },
    committees: Array,
    employees: Array,
});

const tasksManager = props.tasksManager;
const committees = props.committees;
const employees = props.employees;

const emit = defineEmits(['save-success', 'save-error']);
const isSaving = ref(false);

const handleSave = async () => {
  isSaving.value = true;
  try {
    // use the local tasksManager reference instead of "props"
    await tasksManager.saveTaskAssignments();
    emit('save-success', 'Tasks updated successfully!');
  } catch (err) {
    console.error('[handleSave] raw:', err);
    let message = 'Failed to save tasks.';
    const errs = err?.errors || (err?.response?.data?.errors) || {};
    const errorMessages = Object.values(errs).flat().filter(Boolean);
    if (errorMessages.length) message = `Failed to save tasks: ${errorMessages.join(' ')}`;
    else if (err?.message) message = `Failed to save tasks: ${err.message}`;
    emit('save-error', message);
  } finally {
    isSaving.value = false;
  }
};
</script>

