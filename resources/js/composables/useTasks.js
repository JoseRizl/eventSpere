import { ref } from 'vue';
import axios from 'axios';

export function useTasks() {
  const tasks = ref([]);

  const fetchTasks = async (eventId) => {
    const resp = await axios.get(route('api.events.tasks.indexForEvent', { eventId }));
    tasks.value = Array.isArray(resp.data) ? resp.data : [];
    return tasks.value;
  };

  const saveTasks = async (eventId, tasksPayload) => {
    return axios.put(route('tasks.updateForEvent', { id: eventId }), { tasks: tasksPayload });
  };

  return { tasks, fetchTasks, saveTasks };
}
