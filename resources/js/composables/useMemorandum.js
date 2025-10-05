import { ref } from 'vue';
import axios from 'axios';

export function useMemorandum(eventId) {
  const memorandum = ref(null);
  const loading = ref(false);
  const error = ref(null);

  const fetchMemorandum = async () => {
    loading.value = true;
    error.value = null;
    try {
      const response = await axios.get(route('events.memorandum.show', { id: eventId }));
      memorandum.value = response.data;
    } catch (e) {
      console.error('Error fetching memorandum:', e);
      error.value = 'Failed to load memorandum.';
      memorandum.value = null;
    } finally {
      loading.value = false;
    }
  };

  const saveMemorandum = async (payload) => {
    try {
      const response = await axios.post(route('events.memorandum.store', { id: eventId }), payload);
      memorandum.value = response.data;
    } catch (e) {
      console.error('Error saving memorandum:', e);
      throw new Error('Failed to save memorandum.');
    }
  };

  // Initial fetch is now handled by preloading via props, but this is here if needed.
  // onMounted(fetchMemorandum);

  return { memorandum, loading, error, fetchMemorandum, saveMemorandum };
}
