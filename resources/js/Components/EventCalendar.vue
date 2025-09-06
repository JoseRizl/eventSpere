<script setup>
import { ref, computed } from 'vue';
import {
  format,
  startOfMonth,
  endOfMonth,
  startOfWeek,
  endOfWeek,
  eachDayOfInterval,
  isSameMonth,
  isToday,
  addMonths,
  subMonths,
  parse,
  isSameDay,
  areIntervalsOverlapping,
  endOfDay,
} from 'date-fns';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
  events: {
    type: Array,
    default: () => [],
  },
});

const currentDate = ref(new Date());

const previousMonth = () => {
  currentDate.value = subMonths(currentDate.value, 1);
};

const nextMonth = () => {
  currentDate.value = addMonths(currentDate.value, 1);
};

const getFullDateTime = (dateInput, timeStr) => {
  if (!dateInput) return null;

  let date;
  if (typeof dateInput === 'string' && /^[A-Za-z]{3}-\d{2}-\d{4}$/.test(dateInput)) {
    date = parse(dateInput, 'MMM-dd-yyyy', new Date());
  } else {
    date = new Date(dateInput);
  }

  if (isNaN(date.getTime())) return null;

  if (timeStr) {
    const [hours, minutes] = timeStr.split(':').map(Number);
    if (!isNaN(hours) && !isNaN(minutes)) {
      date.setHours(hours, minutes, 0, 0);
    }
  } else {
    // If no time is provided, treat it as start of the day to avoid timezone issues with date-only strings
    date.setHours(0, 0, 0, 0);
  }
  return date;
};

const eventColors = [
  'bg-purple-400 hover:bg-purple-600',
  'bg-blue-400 hover:bg-blue-600',
  'bg-green-400 hover:bg-green-600',
  'bg-yellow-500 hover:bg-yellow-600',
  'bg-orange-400 hover:bg-orange-600',
  'bg-red-400 hover:bg-red-600',
  'bg-pink-400 hover:bg-pink-600',
];

const eventsWithColor = computed(() =>
  props.events.map((event, index) => ({ ...event, colorClass: eventColors[index % eventColors.length] }))
);

const calendarGrid = computed(() => {
  const monthStart = startOfMonth(currentDate.value);
  const monthEnd = endOfMonth(currentDate.value);
  const startDate = startOfWeek(monthStart);
  const endDate = endOfWeek(monthEnd);

  const days = eachDayOfInterval({ start: startDate, end: endDate });

  return days.map(day => ({
    date: day,
    isCurrentMonth: isSameMonth(day, currentDate.value),
    isToday: isToday(day),
    events: eventsWithColor.value
      .filter((event) => {
        const start = getFullDateTime(event.startDate, event.startTime);
        const end = getFullDateTime(event.endDate || event.startDate, event.endTime || '23:59');
        if (!start || !end) return false;
        return areIntervalsOverlapping({ start: day, end: endOfDay(day) }, { start, end });
      })
      .map((event) => {
        const start = getFullDateTime(event.startDate, event.startTime);
        const end = getFullDateTime(event.endDate || event.startDate, event.endTime || '23:59');
        return { ...event, isStart: isSameDay(day, start), isEnd: isSameDay(day, end) };
      })
      .slice(0, 3), // Limit to 3 events per day for display
  }));
});

const weekDays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
</script>

<template>
  <div class="bg-white p-4 rounded-lg shadow-md">
    <div class="flex justify-between items-center mb-4">
      <Button icon="pi pi-chevron-left" @click="previousMonth" class="p-button-text" />
      <h2 class="text-xl font-bold">{{ format(currentDate, 'MMMM yyyy') }}</h2>
      <Button icon="pi pi-chevron-right" @click="nextMonth" class="p-button-text" />
    </div>
    <div class="grid grid-cols-7 gap-1 text-center font-semibold text-gray-600">
      <div v-for="day in weekDays" :key="day" class="py-2">{{ day }}</div>
    </div>
    <div class="grid grid-cols-7 gap-1 mt-1">
      <div
        v-for="(day, index) in calendarGrid"
        :key="index"
        class="border rounded-lg p-1 h-24 flex flex-col"
        :class="{
          'bg-gray-50 text-gray-400': !day.isCurrentMonth,
          'bg-white': day.isCurrentMonth,
          'border-blue-500 ring-2 ring-blue-200': day.isToday,
        }"
      >
        <span :class="{ 'font-bold text-blue-600': day.isToday }">
          {{ format(day.date, 'd') }}
        </span>
        <div class="mt-1 space-y-1 overflow-hidden flex-grow">
          <Link
            v-for="event in day.events"
            :key="event.id"
            :href="route('event.details', { id: event.id })"
            class="flex items-center h-5 text-white transition-colors text-xs"
            :class="[
              event.colorClass,
              {
                'rounded-l-md pl-1': event.isStart,
                'rounded-r-md': event.isEnd,
                'justify-start': event.isStart,
              }]"
            v-tooltip.top="event.title"
          >
            <img v-if="event.isStart && event.image" :src="event.image" class="w-4 h-4 rounded-full mr-1 object-cover flex-shrink-0" alt="Event Icon" />
            <span v-if="event.isStart" class="truncate font-semibold">{{ event.title }}</span>
          </Link>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.h-24 {
  height: 6rem;
}
</style>
