import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import axios from 'axios';

export function useTasks() {
  // State for the modal
  const isTaskModalVisible = ref(false);
  const selectedEventForTasks = ref(null);
  const taskAssignments = ref([]);

  // Method to open the modal and prepare data
  const openTaskModal = (event, allCommittees, allEmployees) => {
    selectedEventForTasks.value = event;

    // Deep copy and normalize tasks to avoid modifying original data until save
    const committeesMap = allCommittees.reduce((map, committee) => {
      map[committee.id] = committee;
      return map;
    }, {});

    const employeesMap = allEmployees.reduce((map, employee) => {
      map[employee.id] = employee;
      return map;
    }, {});

    taskAssignments.value = (event.tasks || []).map(task => ({
      ...task,
      committee: task.committee ? committeesMap[task.committee.id] || task.committee : null,
      employees: (task.employees || []).map(emp => employeesMap[emp.id] || emp)
    }));

    isTaskModalVisible.value = true;
  };

  // Modal Actions
  const addTask = () => {
    taskAssignments.value.push({ committee: null, employees: [], task: '' });
  };

  const deleteTask = (index) => {
    taskAssignments.value.splice(index, 1);
  };

  const clearAllTasks = () => {
    taskAssignments.value = [];
  };

  // Save logic
  // in useTasks.js
  const saveTaskAssignments = (tasksToSave, onSuccessCallback) => {
    if (!selectedEventForTasks.value) {
      throw new Error('No event selected');
    }

    const payload = {
      tasks: tasksToSave,
    };

    // Use Inertia's router for PUT requests. It handles everything automatically.
    router.put(route('tasks.updateForEvent', { id: selectedEventForTasks.value.id }), payload, {
      preserveScroll: true,
      onSuccess: (page) => {
        if (onSuccessCallback) {
          onSuccessCallback(page);
        }
      },
      onError: (errors) => {
        // The component's catch block will not be hit, so we handle errors here if needed,
        // but Inertia's form helper automatically makes errors available to the page.
        console.error("Inertia PUT error:", errors);
      },
    });
  };


  return { isTaskModalVisible, selectedEventForTasks, taskAssignments, openTaskModal, addTask, deleteTask, clearAllTasks, saveTaskAssignments };
}
