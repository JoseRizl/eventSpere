import { ref } from 'vue';
import { router } from '@inertiajs/vue3';

export function useTasks() {
  // State for the modal
  const isTaskModalVisible = ref(false);
  const selectedEventForTasks = ref(null);
  const taskAssignments = ref([]);
  const filteredEmployees = ref([]);

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

    // Pre-populate filtered employees for each task
    filteredEmployees.value = taskAssignments.value.map(task =>
      task.committee ? allEmployees.filter(emp => Number(emp.committeeId) === Number(task.committee.id)) : []
    );

    isTaskModalVisible.value = true;
  };

  // Modal Actions
  const addTask = () => {
    taskAssignments.value.push({ committee: null, employees: [], task: '' });
    filteredEmployees.value.push([]);
  };

  const deleteTask = (index) => {
    taskAssignments.value.splice(index, 1);
    filteredEmployees.value.splice(index, 1);
  };

  const updateEmployeesForTask = (index, allEmployees) => {
    const selectedCommittee = taskAssignments.value[index].committee;
    filteredEmployees.value[index] = selectedCommittee
      ? allEmployees.filter(emp => Number(emp.committeeId) === Number(selectedCommittee?.id))
      : [];
    // Reset employees when committee changes
    taskAssignments.value[index].employees = [];
  };

  // Save logic
  // in useTasks.js
const saveTaskAssignments = async (onSuccessCallback) => {
  if (!selectedEventForTasks.value) {
    return Promise.reject({ message: 'No event selected', errors: { general: ['No event selected'] }});
  }

  const payload = {
    tasks: taskAssignments.value.map(task => ({
      committee_id: task.committee ? task.committee.id : null,
      employees: (task.employees || []).map(emp => (emp && emp.id) ? emp.id : emp),
      description: task.task || ''
    })),
  };

  console.debug('[saveTaskAssignments] payload', payload);

  const response = await router.put(route('tasks.updateForEvent', { id: selectedEventForTasks.value.id }), payload, {
    preserveScroll: true,
    onSuccess: (page) => {
      if (onSuccessCallback) onSuccessCallback(page);
    },
  });

  // Inertia's router.put doesn't return the page object directly in the promise resolution.
  // The page updates automatically, and we can access the new props via usePage().
  // However, since the controller flashes the tasks, they will be in the next page's props.
  // The calling component handles the onSuccess logic, which has access to the updated page.
  // For optimistic updates, we rely on the controller flashing back the data.
  return response; // The promise resolves when the visit is complete.
};


  return { isTaskModalVisible, selectedEventForTasks, taskAssignments, filteredEmployees, openTaskModal, addTask, deleteTask, updateEmployeesForTask, saveTaskAssignments };
}
