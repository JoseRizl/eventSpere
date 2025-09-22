import { ref, computed } from 'vue';
import axios from 'axios';
import { format, isWithinInterval, isSameMonth, areIntervalsOverlapping, endOfDay, differenceInHours, differenceInDays } from "date-fns";
import { getFullDateTime } from '@/utils/dateUtils.js';

export function useEvents({ searchQuery, startDateFilter, endDateFilter }) {
    const allNews = ref([]);

    const fetchEvents = async () => {
        try {
            const response = await axios.get("http://localhost:3000/events");
            allNews.value = [...response.data]
                .filter((news) => !news.archived)
                .map((news) => ({
                    ...news,
                    formattedDate: news.startDate
                        ? format(new Date(news.startDate), "MMMM dd, yyyy")
                        : "No date",
                }))
                .sort((a, b) => getFullDateTime(a.startDate, a.startTime) - getFullDateTime(b.startDate, b.startTime));
        } catch (error) {
            console.error("Error fetching events:", error);
        }
    };

    const isEventOngoing = (event) => {
        const now = new Date();
        const start = getFullDateTime(event.startDate, event.startTime);
        const end = getFullDateTime(event.endDate || event.startDate, event.endTime || '23:59');
        if (!start || !end) return false;
        return isWithinInterval(now, { start, end });
    };

    const filteredNews = computed(() => {
        let news = allNews.value;

        if (searchQuery.value) {
            const query = searchQuery.value.toLowerCase().trim();
            news = news.filter(event => {
                const title = event.title?.toLowerCase() || '';
                const description = event.description?.toLowerCase() || '';
                return title.includes(query) || description.includes(query);
            });
        }

        if (startDateFilter.value || endDateFilter.value) {
            news = news.filter(event => {
                const eventStart = getFullDateTime(event.startDate, event.startTime);
                const eventEnd = getFullDateTime(event.endDate || event.startDate, event.endTime || '23:59');
                if (!eventStart || !eventEnd) return false;

                const filterStart = startDateFilter.value ? new Date(startDateFilter.value) : null;
                const filterEnd = endDateFilter.value ? endOfDay(new Date(endDateFilter.value)) : null;

                if (filterStart && filterEnd) {
                    return areIntervalsOverlapping({ start: eventStart, end: eventEnd }, { start: filterStart, end: filterEnd });
                }
                if (filterStart) return eventEnd >= filterStart;
                if (filterEnd) return eventStart <= filterEnd;
                return true;
            });
        }
        return news;
    });

    const ongoingEvents = computed(() => {
        return filteredNews.value
            .filter(isEventOngoing)
            .sort((a, b) => getFullDateTime(a.startDate, a.startTime) - getFullDateTime(b.startDate, b.startTime));
    });

    const eventsThisMonth = computed(() => {
        const now = new Date();
        const events = filteredNews.value.filter((news) => {
            const start = getFullDateTime(news.startDate, news.startTime);
            if (!start) return false;
            // Exclude ongoing events, which are shown in their own section
            return isSameMonth(start, now) && !isEventOngoing(news);
        });
        return events.sort((a, b) => getFullDateTime(a.startDate, a.startTime) - getFullDateTime(b.startDate, b.startTime));
    });

    const upcomingEvents = computed(() => {
        const now = new Date();
        const events = filteredNews.value.filter((news) => {
            const start = getFullDateTime(news.startDate, news.startTime);
            if (!start) return false;
            return start > now && !isSameMonth(start, now);
        });
        return events.sort((a, b) => getFullDateTime(a.startDate, a.startTime) - getFullDateTime(b.startDate, b.startTime));
    });

    const carouselEvents = computed(() => {
        const getStatus = (event) => {
            const now = new Date();
            const start = getFullDateTime(event.startDate, event.startTime);
            const end = getFullDateTime(event.endDate || event.startDate, event.endTime || '23:59');
            if (!start || !end) return 3;
            if (isWithinInterval(now, { start, end })) return 1;
            if (start > now) return 2;
            return 3;
        };
        return [...filteredNews.value].sort((a, b) => {
            const statusA = getStatus(a);
            const statusB = getStatus(b);
            if (statusA !== statusB) return statusA - statusB;
            const startA = getFullDateTime(a.startDate, a.startTime);
            const startB = getFullDateTime(b.startDate, b.startTime);
            const endA = getFullDateTime(a.endDate || a.startDate, a.endTime || '23:59');
            const endB = getFullDateTime(b.endDate || b.startDate, b.endTime || '23:59');
            if (statusA === 1 || statusA === 2) return startA - startB;
            if (statusA === 3) return endB - endA;
            return 0;
        });
    });

    const isNewEvent = (event) => {
        if (!event?.createdAt) return false;
        const oneWeekAgo = new Date();
        oneWeekAgo.setDate(oneWeekAgo.getDate() - 7);
        const createdAtDate = new Date(event.createdAt);
        return createdAtDate > oneWeekAgo;
    };

    const getUpcomingTag = (event) => {
        const { startDate, endDate, startTime, endTime } = event;
        const now = new Date();

        const startDateTime = getFullDateTime(startDate, startTime);
        const endDateTime = getFullDateTime(endDate || startDate, endTime || startTime);

        if (!startDateTime || !endDateTime) return 'Upcoming';

        if (endDateTime < now) return 'Ended';
        if (isEventOngoing(event)) return 'Happening Now';

        const diffHours = differenceInHours(startDateTime, now);
        const diffDays = differenceInDays(startDateTime, now);

        if (diffHours < 24) return 'Starting Soon';
        if (diffDays < 3) return 'Very Soon';
        if (diffDays < 7) return 'This Week';
        if (diffDays < 14) return 'Next Week';
        if (diffDays < 30) return 'This Month';
        return 'Upcoming';
    };

    const getUpcomingSeverity = (event) => {
        const { startDate, endDate, startTime, endTime } = event;
        const now = new Date();

        const startDateTime = getFullDateTime(startDate, startTime);
        const endDateTime = getFullDateTime(endDate || startDate, endTime || startTime);

        if (!startDateTime || !endDateTime) return 'info'; // Default for invalid dates

        if (endDateTime < now) return null; // Ended events have no severity
        if (isEventOngoing(event)) return 'success'; // Ongoing

        const diffHours = differenceInHours(startDateTime, now);

        if (diffHours < 24 * 3) return 'danger'; // Less than 3 days
        if (diffHours < 24 * 7) return 'warning'; // Less than 7 days
        return 'info'; // Upcoming
    };

    return {
        allNews,
        fetchEvents,
        filteredNews,
        ongoingEvents,
        eventsThisMonth,
        upcomingEvents,
        carouselEvents,
        isNewEvent,
        getUpcomingTag,
        getUpcomingSeverity,
    };
}
