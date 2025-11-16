import { ref, computed } from 'vue';
import axios from 'axios';
import { format, isWithinInterval, endOfDay } from "date-fns";
import { usePage } from '@inertiajs/vue3';

export function useAnnouncements({ searchQuery, startDateFilter, endDateFilter }, allNews) {
    const page = usePage();
    const allUsers = computed(() => page.props.all_users || []);
    const eventAnnouncements = ref([]);

    const fetchAnnouncements = async () => {
        try {
            const events = Array.isArray(allNews.value) ? allNews.value : [];
            const employeesMap = allUsers.value.reduce((map, emp) => {
                map[emp.id] = emp;
                return map;
            }, {});

            // Fetch all announcements from the single, consolidated endpoint.
            const response = await axios.get(route('api.announcements.index'));

            // The response from an API Resource collection already includes the data wrapper.
            const allAnnouncements = response.data.data || [];

            eventAnnouncements.value = allAnnouncements
                .map(ann => ({
                    ...ann,
                    // The 'event' and 'employee' data are now directly included by the API resource.
                    // We just need to format the timestamp for display.
                    formattedTimestamp: format(new Date(ann.timestamp), "MMMM dd, yyyy HH:mm"),
                }))
                .sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp));
        } catch (error) {
            console.error("Error fetching announcements:", error);
        }
    };

    const filteredAnnouncements = computed(() => {
        let announcements = eventAnnouncements.value;

        if (searchQuery.value) {
            const query = searchQuery.value.toLowerCase().trim();
            announcements = announcements.filter(ann => {
                const messageMatch = ann.message?.toLowerCase().includes(query);
                const eventTitleMatch = ann.event?.title?.toLowerCase().includes(query);
                return messageMatch || eventTitleMatch;
            });
        }

        if (startDateFilter.value || endDateFilter.value) {
            announcements = announcements.filter(ann => {
                const annDate = new Date(ann.timestamp);
                if (isNaN(annDate.getTime())) return false;

                const filterStart = startDateFilter.value ? new Date(startDateFilter.value) : null;
                const filterEnd = endDateFilter.value ? endOfDay(new Date(endDateFilter.value)) : null;

                if (filterStart && !filterEnd) return annDate >= filterStart;
                if (!filterStart && filterEnd) return annDate <= filterEnd;
                if (filterStart && filterEnd) return isWithinInterval(annDate, { start: filterStart, end: filterEnd });
                return true;
            });
        }
        return announcements;
    });

    const setAnnouncements = (anns) => {
        eventAnnouncements.value = Array.isArray(anns) ? anns : [];
    };

    const addAnnouncement = (newAnnouncement) => {
        const employeesMap = allUsers.value.reduce((map, emp) => { map[emp.id] = emp; return map; }, {});
        const events = Array.isArray(allNews.value) ? allNews.value : [];
        const augmentedAnn = {
            ...newAnnouncement,
            event: events.find(e => e.id === newAnnouncement.event_id) || null,
            employee: employeesMap[newAnnouncement.userId] || { name: 'Admin' },
            formattedTimestamp: format(new Date(newAnnouncement.timestamp), "MMMM dd, yyyy HH:mm"),
        };
        eventAnnouncements.value.unshift(augmentedAnn);
    };

    const updateAnnouncement = (updatedAnnouncement) => {
        const index = eventAnnouncements.value.findIndex(a => a.id === updatedAnnouncement.id);
        if (index !== -1) {
            // To keep reactivity, we need to merge properties into the existing object
            // or replace it entirely. Replacing is simpler if the object structure is consistent.
            eventAnnouncements.value.splice(index, 1, { ...eventAnnouncements.value[index], ...updatedAnnouncement });
        }
    };

    const deleteAnnouncementById = (announcementId) => {
        eventAnnouncements.value = eventAnnouncements.value.filter(a => a.id !== announcementId);
    };


    return { eventAnnouncements, fetchAnnouncements, filteredAnnouncements, setAnnouncements, addAnnouncement, updateAnnouncement, deleteAnnouncementById };
}
