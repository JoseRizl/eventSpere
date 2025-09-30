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
import { Link, router } from '@inertiajs/vue3';

const props = defineProps({
  events: {
    type: Array,
    default: () => [],
  },
});

const currentDate = ref(new Date());
const showDayEventsModal = ref(false);
const selectedDay = ref(null);

const handleDayClick = (day) => {
  if (day.events.length === 0) return;

  const isMobile = window.innerWidth < 640; // Corresponds to Tailwind's 'sm' breakpoint

  // On mobile, always open the modal to show event details since they are just dots.
  // On desktop, only open the modal if there is more than one event.
  if (isMobile || day.events.length > 1) {
    selectedDay.value = day;
    showDayEventsModal.value = true;
  } else if (day.events.length === 1) {
    // This branch is now only for desktop screens with a single event.
    router.visit(route('event.details', { id: day.events[0].id }));
  }
};

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
</script>

<template>
  <div class="bg-white p-2 sm:p-4 rounded-lg shadow-md">
    <div class="flex justify-between items-center mb-4">
      <Button icon="pi pi-chevron-left" @click="previousMonth" class="p-button-text p-button-secondary" aria-label="Previous Month" />
      <h2 class="text-lg sm:text-xl font-bold text-center">{{ format(currentDate, 'MMMM yyyy') }}</h2>
      <Button icon="pi pi-chevron-right" @click="nextMonth" class="p-button-text p-button-secondary" aria-label="Next Month" />
    </div>
    <div class="grid grid-cols-7 gap-1 text-center font-semibold text-gray-600 text-xs sm:text-sm">
      <div class="py-2 hidden sm:block">Sun</div><div class="py-2 sm:hidden" aria-hidden="true">S</div>
      <div class="py-2 hidden sm:block">Mon</div><div class="py-2 sm:hidden" aria-hidden="true">M</div>
      <div class="py-2 hidden sm:block">Tue</div><div class="py-2 sm-hidden" aria-hidden="true">T</div>
      <div class="py-2 hidden sm:block">Wed</div><div class="py-2 sm-hidden" aria-hidden="true">W</div>
      <div class="py-2 hidden sm:block">Thu</div><div class="py-2 sm-hidden" aria-hidden="true">T</div>
      <div class="py-2 hidden sm:block">Fri</div><div class="py-2 sm-hidden" aria-hidden="true">F</div>
      <div class="py-2 hidden sm-block">Sat</div><div class="py-2 sm:hidden" aria-hidden="true">S</div>
    </div>
    <div class="grid grid-cols-7 gap-1">
      <div
        v-for="(day, index) in calendarGrid"
        :key="index"
        @click="handleDayClick(day)"
        class="border rounded-lg p-1 flex flex-col aspect-square sm:aspect-auto sm:h-24 transition-colors duration-150"
        :class="{
          'bg-gray-50 text-gray-400': !day.isCurrentMonth,
          'bg-white': day.isCurrentMonth,
          'border-blue-500 ring-2 ring-blue-200': day.isToday,
          'cursor-pointer hover:bg-blue-50/60': day.events.length > 0, // Make the whole cell clickable if there are events
        }"
      >
        <span class="text-xs sm:text-base" :class="{ 'font-bold text-blue-600': day.isToday }">
          {{ format(day.date, 'd') }}
        </span>
        <!-- Mobile view: dots -->
        <div class="sm:hidden mt-1 flex justify-center items-start flex-wrap gap-1 overflow-hidden">
            <div
                v-for="event in day.events"
                :key="event.id"
                class="w-1.5 h-1.5 rounded-full"
                :class="event.colorClass.replace('hover:', '')"
                v-tooltip.top="event.title"
                :aria-label="event.title"
            ></div>
        </div>
        <!-- Desktop view: event bars -->
        <div class="hidden sm:flex flex-col mt-1 space-y-1 overflow-hidden flex-grow">
          <div
            v-for="event in day.events"
            :key="event.id"
            class="flex items-center h-5 text-white transition-colors"
            :class="[
              event.colorClass,
              {
                'rounded-l-md pl-1.5': event.isStart,
                'rounded-r-md': event.isEnd,
                'justify-start': event.isStart,
              }]"
            v-tooltip.top="event.title"
          >
            <img v-if="event.isStart && event.image" :src="event.image" class="w-3.5 h-3.5 rounded-full mr-1 object-cover flex-shrink-0" alt="Event Icon" />
            <span v-if="event.isStart" class="truncate font-semibold text-xs">{{ event.title }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Day Events Modal -->
    <Dialog v-model:visible="showDayEventsModal" modal :dismissableMask="true" :header="selectedDay ? `Events for ${format(selectedDay.date, 'MMMM d, yyyy')}` : 'Events'" :style="{ width: '90vw', maxWidth: '500px' }">
        <div v-if="selectedDay" class="space-y-3 max-h-[60vh] overflow-y-auto p-1">
            <Link
                v-for="event in selectedDay.events"
                :key="event.id"
                :href="route('event.details', { id: event.id })"
                class="block p-3 rounded-lg hover:bg-gray-100 transition-colors"
            >
                <div class="flex items-center gap-3">
                    <div class="w-4 h-4 rounded-full flex-shrink-0" :class="event.colorClass.replace('hover:', '')"></div>
                    <div class="flex-1">
                        <p class="font-semibold text-gray-800">{{ event.title }}</p>
                        <p class="text-sm text-gray-500">{{ event.formattedDate }}</p>
                    </div>
                </div>
            </Link>
        </div>
    </Dialog>
  </div>
</template>

<style scoped>
/* The aspect-square and sm:h-24 classes now handle the responsive height */
</style>
