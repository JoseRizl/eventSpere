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
            const response = await axios.get("http://localhost:3000/event_announcements");

            const eventMap = allNews.value.reduce((map, event) => {
                map[event.id] = event;
                return map;
            }, {});

            const employeesMap = allUsers.value.reduce((map, emp) => {
                map[emp.id] = emp;
                return map;
            }, {});

            eventAnnouncements.value = response.data.map(ann => ({
                ...ann,
                event: eventMap[ann.eventId],
                employee: employeesMap[ann.userId] || { name: 'Admin' },
                formattedTimestamp: format(new Date(ann.timestamp), "MMMM dd, yyyy HH:mm"),
            })).sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp));
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

    const deleteAnnouncement = async (announcementId) => {
        await axios.delete(`http://localhost:3000/event_announcements/${announcementId}`);
        eventAnnouncements.value = eventAnnouncements.value.filter(a => a.id !== announcementId);
    };

    return { eventAnnouncements, fetchAnnouncements, filteredAnnouncements, deleteAnnouncement };
}
