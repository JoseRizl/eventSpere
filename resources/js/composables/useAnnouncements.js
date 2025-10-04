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

            // 1) Try single aggregated endpoint if backend provides it
            try {
                const respAll = await axios.get(route('api.announcements.index'));
                eventAnnouncements.value = (respAll.data || [])
                    .map(ann => ({
                        ...ann,
                        event: events.find(e => e.id === ann.event_id) || null,
                        employee: employeesMap[ann.userId] || { name: 'Admin' },
                        formattedTimestamp: format(new Date(ann.timestamp), "MMMM dd, yyyy HH:mm"),
                    }))
                    .sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp));
                return;
            } catch (_) {
                // Fallback to per-event requests below
            }

            // 2) Fallback: fetch per event but stream results incrementally
            eventAnnouncements.value = [];
            await Promise.allSettled(
                events.map(async (ev) => {
                    try {
                        const resp = await axios.get(route('api.events.announcements.indexForEvent', { eventId: ev.id }));
                        const items = (resp.data || []).map(ann => ({
                            ...ann,
                            event: ev,
                            employee: employeesMap[ann.userId] || { name: 'Admin' },
                            formattedTimestamp: format(new Date(ann.timestamp), "MMMM dd, yyyy HH:mm"),
                        }));
                        // Push and keep sorted so UI updates progressively
                        eventAnnouncements.value = [...eventAnnouncements.value, ...items]
                            .sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp));
                    } catch (e) {
                        console.error('Error fetching announcements for event', ev.id, e);
                    }
                })
            );
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

    const deleteAnnouncement = async (announcementId, eventIdOverride = null) => {
        // Resolve event id from parameter or current list
        let eventId = eventIdOverride;
        if (!eventId) {
            const ann = eventAnnouncements.value.find(a => a.id === announcementId);
            eventId = ann?.event?.id;
        }
        if (!eventId) return;
        await axios.delete(route('events.announcements.destroyForEvent', { id: eventId, announcementId }));
        eventAnnouncements.value = eventAnnouncements.value.filter(a => a.id !== announcementId);
    };

    return { eventAnnouncements, fetchAnnouncements, filteredAnnouncements, deleteAnnouncement, setAnnouncements };
}
