import { ref } from 'vue';
import axios from 'axios';
import { usePage } from '@inertiajs/vue3';

export function useMemorandum() {
    const saving = ref(false);
    const error = ref(null);
    const page = usePage();

    const fetchMemorandum = async (eventId) => {
        // This is a placeholder. In a real app, you might fetch this from an API endpoint.
        // For now, we assume it's preloaded or we find it in the page props.
        const memorandums = page.props.memorandums_prop || [];
        return memorandums.find(memo => memo.event_id === eventId) || null;
    };

    const saveMemorandum = async (eventId, payload) => {
        saving.value = true;
        error.value = null;
        return axios.post(route('api.memorandum.storeOrUpdate', { event: eventId }), payload)
            .finally(() => saving.value = false);
    };

    const clearMemorandum = async (eventId) => {
        return axios.delete(route('api.memorandum.destroy', { event: eventId }));
    };

    return { fetchMemorandum, saveMemorandum, clearMemorandum, saving, error };
}
