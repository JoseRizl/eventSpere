import { ref } from 'vue';

export function useFilters() {
    const searchQuery = ref('');
    const startDateFilter = ref(null);
    const endDateFilter = ref(null);
    const showDateFilter = ref(false);

    const toggleDateFilter = () => {
        showDateFilter.value = !showDateFilter.value;
    };

    const clearDateFilter = () => {
        startDateFilter.value = null;
        endDateFilter.value = null;
    };

    const clearFilters = () => {
        searchQuery.value = '';
        startDateFilter.value = null;
        endDateFilter.value = null;
        showDateFilter.value = false;
    };

    return {
        searchQuery, startDateFilter, endDateFilter, showDateFilter,
        toggleDateFilter, clearDateFilter, clearFilters
    };
}
