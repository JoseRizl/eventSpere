<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { isWithinInterval, getYear } from 'date-fns';
import EventCalendar from '@/Components/EventCalendar.vue';
import Card from 'primevue/card';

const page = usePage();
// Assumes `events_prop` is passed from the controller, similar to EventList
const events = computed(() => page.props.events_prop || []);

const now = new Date();
const currentYear = getYear(now);

const getFullDateTime = (dateInput, timeStr) => {
    if (!dateInput) return null;
    // The date format from Laravel is 'YYYY-MM-DD HH:MM:SS', which new Date() can parse.
    // Or it could be the 'MMM-dd-yyyy' format from the form.
    // new Date() is flexible enough for both in most cases.
    const date = new Date(dateInput);
    if (isNaN(date.getTime())) return null;

    if (timeStr) {
        const [hours, minutes] = timeStr.split(':').map(Number);
        if (!isNaN(hours) && !isNaN(minutes)) {
            date.setHours(hours, minutes, 0, 0);
        }
    }
    return date;
};

const eventsThisYearCount = computed(() => {
    return events.value.filter(event => {
        const startDate = getFullDateTime(event.startDate, event.startTime);
        return startDate && getYear(startDate) === currentYear;
    }).length;
});

const ongoingEventsCount = computed(() => {
    return events.value.filter(event => {
        const start = getFullDateTime(event.startDate, event.startTime);
        const end = getFullDateTime(event.endDate || event.startDate, event.endTime || '23:59');
        return start && end && isWithinInterval(now, { start, end });
    }).length;
});

const upcomingEventsCount = computed(() => {
    return events.value.filter(event => {
        const start = getFullDateTime(event.startDate, event.startTime);
        return start && start > now;
    }).length;
});

const totalEventsCount = computed(() => events.value.length);

const stats = computed(() => [
    { title: 'Events This Year', value: eventsThisYearCount.value, icon: 'pi pi-calendar', color: 'bg-blue-500' },
    { title: 'Ongoing Events', value: ongoingEventsCount.value, icon: 'pi pi-spin pi-spinner', color: 'bg-green-500' },
    { title: 'Upcoming Events', value: upcomingEventsCount.value, icon: 'pi pi-calendar-plus', color: 'bg-orange-500' },
    { title: 'Total Active Events', value: totalEventsCount.value, icon: 'pi pi-globe', color: 'bg-purple-500' },
]);
</script>

<template>
    <div>
        <h1 class="text-2xl font-bold mb-6 text-slate-800">Welcome, Principal</h1>

        <!-- Stats Boxes -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <Card v-for="stat in stats" :key="stat.title" :class="[stat.color, 'text-white', 'rounded-lg', 'shadow-lg', 'overflow-hidden']">
                <template #content>
                    <div class="flex justify-between items-center p-4">
                        <div>
                            <div class="text-4xl font-bold">{{ stat.value }}</div>
                            <div class="text-lg">{{ stat.title }}</div>
                        </div>
                        <i :class="[stat.icon, 'text-5xl', 'opacity-50']"></i>
                    </div>
                </template>
            </Card>
        </div>

        <!-- Event Calendar -->
        <div class="bg-white p-6 rounded-lg shadow-lg">
            <h2 class="text-2xl font-bold mb-4 text-center text-slate-700">Event Calendar</h2>
            <EventCalendar :events="events" />
        </div>
    </div>
</template>
