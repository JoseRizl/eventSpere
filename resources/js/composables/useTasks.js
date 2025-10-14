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
  const saveTaskAssignments = async (onSuccessCallback) => {
    if (!selectedEventForTasks.value) {
      throw new Error('No event selected');
    }

    const payload = {
      tasks: taskAssignments.value.map(task => ({
        committee_id: task.committee ? task.committee.id : null,
        employees: (task.employees || []).map(emp => (emp && emp.id) ? emp.id : emp),
        description: task.task || ''
      })),
    };

    try {
      const response = await axios.put(route('tasks.updateForEvent', { id: selectedEventForTasks.value.id }), payload);
      // Manually trigger an Inertia visit to refresh props after a successful axios call
      router.reload({ onSuccess: onSuccessCallback });
      return response.data;
    } catch (error) {
      // Re-throw the error to be caught by the component
      throw error;
    }
  };


  return { isTaskModalVisible, selectedEventForTasks, taskAssignments, openTaskModal, addTask, deleteTask, clearAllTasks, saveTaskAssignments };
}
