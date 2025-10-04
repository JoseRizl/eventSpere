import { ref } from 'vue';
import axios from 'axios';

export function useActivities() {
  const activities = ref([]);

  const fetchActivities = async (eventId) => {
    const resp = await axios.get(route('api.events.activities.indexForEvent', { eventId }));
    activities.value = Array.isArray(resp.data) ? resp.data : [];
    return activities.value;
  };

  const saveActivities = async (eventId, activitiesPayload) => {
    return axios.put(route('events.activities.updateForEvent', { id: eventId }), { activities: activitiesPayload });
  };

  return { activities, fetchActivities, saveActivities };
}
