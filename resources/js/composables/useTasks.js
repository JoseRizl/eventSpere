import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';

export function useTasks() {
  // State for the modal
  const isTaskModalVisible = ref(false);
  const selectedEventForTasks = ref(null);
  const taskAssignments = ref([]);

  // Method to open the modal and prepare data
  const openTaskModal = (event, allCommittees = [], allPersonnel = [], eventBrackets = []) => {
    console.log('[useTasks] openTaskModal received event tasks:', event.tasks);
    console.log('[useTasks] openTaskModal received personnel list:', allPersonnel);
    console.log('[useTasks] openTaskModal received brackets:', eventBrackets);

    selectedEventForTasks.value = event;

    // Normalize inputs to arrays to prevent runtime errors
    const committeesArr = Array.isArray(allCommittees) ? allCommittees : [];
    const personnelArr = Array.isArray(allPersonnel) ? allPersonnel : [];
    const bracketsArr = Array.isArray(eventBrackets) ? eventBrackets : [];

    const committeesMap = committeesArr.reduce((map, committee) => {
      map[committee.id] = committee;
      return map;
    }, {});

    taskAssignments.value = (event.tasks || []).map(task => {
        console.log(`[useTasks] Processing task: "${task.task || task.description}"`);

        const assignedEmployees = (task.employees || []).map(emp => {
            const match = personnelArr.find(p => Number(p.id) === Number(emp.id) && p.type === 'employee');
            if (!match) console.warn(`[useTasks] Mismatch: Could not find employee ID ${emp.id} in personnel list.`);
            return match;
        }).filter(Boolean);

        const assignedManagers = (task.managers || []).map(mgr => {
            const match = personnelArr.find(p => Number(p.id) === Number(mgr.id) && p.type === 'user');
            if (!match) console.warn(`[useTasks] Mismatch: Could not find manager ID ${mgr.id} in personnel list.`);
            return match;
        }).filter(Boolean);

        const combinedPersonnel = [...assignedEmployees, ...assignedManagers];

        let assignedBrackets = [];
        if (assignedManagers.length > 0 && task.managers) {
            const managerBracketIds = new Set();
            task.managers.forEach(m => {
                // The relationship is loaded as managed_brackets
                (m.managed_brackets || []).forEach(b => managerBracketIds.add(b.id));
            });

            assignedBrackets = bracketsArr.filter(b => managerBracketIds.has(b.id));
        }

        return {
            ...task,
            committee: task.committee ? committeesMap[task.committee.id] || task.committee : null,
            employees: combinedPersonnel, // v-model for the personnel MultiSelect
            assignedBrackets: assignedBrackets, // v-model for the brackets MultiSelect
        };
    });

    isTaskModalVisible.value = true;
  };

  // Modal Actions
  const addTask = () => {
    taskAssignments.value.push({ committee: null, employees: [], task: '', assignedBrackets: [] });
  };

  const deleteTask = (index) => {
    taskAssignments.value.splice(index, 1);
  };

  const clearAllTasks = () => {
    taskAssignments.value = [];
  };

  // Save logic
  const saveTaskAssignments = (tasksToSave, onSuccessCallback) => {
    if (!selectedEventForTasks.value) {
      throw new Error('No event selected');
    }

    const payload = {
      tasks: tasksToSave,
    };

    router.put(route('tasks.updateForEvent', { id: selectedEventForTasks.value.id }), payload, {
      preserveScroll: true,
      onSuccess: (page) => {
        if (onSuccessCallback) {
          onSuccessCallback(page);
        }
      },
      onError: (errors) => {
        console.error("Inertia PUT error:", errors);
      },
    });
  };


  return { isTaskModalVisible, selectedEventForTasks, taskAssignments, openTaskModal, addTask, deleteTask, clearAllTasks, saveTaskAssignments };
}
