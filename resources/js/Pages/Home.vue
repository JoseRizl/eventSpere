<script setup>
import { ref, onMounted, onUnmounted, computed, watch, nextTick } from "vue";
import axios from "axios";
import { format, isWithinInterval, isSameMonth, parse } from "date-fns";
import { Link, usePage, router } from "@inertiajs/vue3";
import EventCalendar from '@/Components/EventCalendar.vue';
import ConfirmationDialog from '@/Components/ConfirmationDialog.vue';
import SuccessDialog from '@/Components/SuccessDialog.vue';
import LoadingSpinner from '@/Components/LoadingSpinner.vue';
import Avatar from 'primevue/avatar';

const allNews = ref([]);
const showAnnouncements = ref(false);
const now = new Date();
const showEventsThisMonth = ref(loadToggleState('showEventsThisMonth', true));
const saving = ref(false);
const showSuccessDialog = ref(false);
const successMessage = ref('');
const showOngoingEvents = ref(loadToggleState('showOngoingEvents', true));
const showUpcomingEvents = ref(loadToggleState('showUpcomingEvents', true));
const eventAnnouncements = ref([]);
watch(showEventsThisMonth, (val) => saveToggleState('showEventsThisMonth', val));
watch(showOngoingEvents, (val) => saveToggleState('showOngoingEvents', val));
watch(showUpcomingEvents, (val) => saveToggleState('showUpcomingEvents', val));
const showLatestBanner = ref(true);
const currentAnnouncementIndex = ref(0);
const announcementDirection = ref('next'); // 'next' or 'prev'
const showErrorDialog = ref(false);
const errorMessage = ref('');
const hasNewAnnouncements = ref(false);
const showDeleteAnnouncementConfirm = ref(false);
const announcementToDelete = ref(null);
let announcementTimer = null;

const page = usePage();
const user = computed(() => page.props.auth.user);
const currentView = ref('events'); // 'events' or 'announcements'

const sortedAnnouncements = computed(() => {
  return eventAnnouncements.value;
});

const currentAnnouncement = computed(() => {
  if (sortedAnnouncements.value.length > 0) {
    return sortedAnnouncements.value[currentAnnouncementIndex.value];
  }
  return null;
});

const startAnnouncementCarousel = () => {
  if (announcementTimer) clearInterval(announcementTimer);
  if (sortedAnnouncements.value.length > 1) {
    announcementTimer = setInterval(() => {
      announcementDirection.value = 'next'; // Set direction for auto-advance
      currentAnnouncementIndex.value = (currentAnnouncementIndex.value + 1) % sortedAnnouncements.value.length;
    }, 15000); // 15 seconds
  }
};

const nextAnnouncement = () => {
  if (sortedAnnouncements.value.length > 1) {
    announcementDirection.value = 'next';
    currentAnnouncementIndex.value = (currentAnnouncementIndex.value + 1) % sortedAnnouncements.value.length;
    startAnnouncementCarousel(); // Reset timer on manual navigation
  }
};

const prevAnnouncement = () => {
  if (sortedAnnouncements.value.length > 1) {
    announcementDirection.value = 'prev';
    currentAnnouncementIndex.value = (currentAnnouncementIndex.value - 1 + sortedAnnouncements.value.length) % sortedAnnouncements.value.length;
    startAnnouncementCarousel(); // Reset timer on manual navigation
  }
};

const scrollToAnnouncement = (announcementId) => {
  currentView.value = 'announcements';
  nextTick(() => {
    const element = document.getElementById(`announcement-${announcementId}`);
    if (element) {
      element.scrollIntoView({ behavior: 'smooth', block: 'center' });
      element.classList.add('highlight');
      setTimeout(() => element.classList.remove('highlight'), 2000); // Highlight for 2 seconds
    }
  });
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

const ongoingEvents = computed(() => {
  const now = new Date();
  const events = allNews.value.filter((news) => {
    const start = getFullDateTime(news.startDate, news.startTime);
    // If no end date, it's a single-day event. If no end time, assume it lasts till end of day.
    const end = getFullDateTime(news.endDate || news.startDate, news.endTime || '23:59');
    return isWithinInterval(now, { start, end });
  });

  // Sort by start date (ascending)
  return events.sort((a, b) => getFullDateTime(a.startDate, a.startTime) - getFullDateTime(b.startDate, b.startTime));
});

const isNewEvent = (event) => {
  if (!event?.createdAt) return false; // Safely check if createdAt exists

  const oneWeekAgo = new Date();
  oneWeekAgo.setDate(oneWeekAgo.getDate() - 7); // 7 days ago

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
  if (startDateTime <= now && now <= endDateTime) return 'Ongoing';

  const diffMs = startDateTime - now;
  const diffHours = diffMs / (1000 * 60 * 60);
  const diffDays = diffHours / 24;

  if (diffHours < 1) return 'Starting Soon';
  if (diffHours < 24) return 'Today';
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
  if (startDateTime <= now && now <= endDateTime) return 'success'; // Ongoing

  const diffMs = startDateTime - now;
  const diffHours = diffMs / (1000 * 60 * 60);

  if (diffHours < 24 * 3) return 'danger'; // Less than 3 days
  if (diffHours < 24 * 7) return 'warning'; // Less than 7 days
  return 'info'; // Upcoming
};

const eventsThisMonth = computed(() => {
  const now = new Date();
  const events = allNews.value.filter((news) => {
    const start = getFullDateTime(news.startDate, news.startTime);
    const end = getFullDateTime(news.endDate || news.startDate, news.endTime || '23:59');

    if (!start || !end) return false;

    // Check if the event starts in the current month but is not currently ongoing.
    return isSameMonth(start, now) && !isWithinInterval(now, { start, end });
  });

  // Sort by start date (ascending)
  return events.sort((a, b) => getFullDateTime(a.startDate, a.startTime) - getFullDateTime(b.startDate, b.startTime));
});

const upcomingEvents = computed(() => {
  const now = new Date();
  const events = allNews.value.filter((news) => {
    const start = getFullDateTime(news.startDate, news.startTime);
    if (!start) return false;
    // Event is in the future and not in the current month.
    return start > now && !isSameMonth(start, now);
  });

  // Sort by start date (ascending)
  return events.sort((a, b) => getFullDateTime(a.startDate, a.startTime) - getFullDateTime(b.startDate, b.startTime));
});

// Fetch announcements and news
onMounted(async () => {
  try {
    const [eventsResponse, sportsResponse, eventAnnouncementsResponse] = await Promise.all([
        axios.get("http://localhost:3000/events"),
        axios.get("http://localhost:3000/sports"),
        axios.get("http://localhost:3000/event_announcements")
    ]);

    allNews.value = [...eventsResponse.data, ...sportsResponse.data]
      .filter((news) => !news.archived)
      .map((news) => ({
        ...news,
        formattedDate: news.startDate
          ? format(new Date(news.startDate), "MMMM dd, yyyy")
          : "No date",
      }))
      .sort((a, b) => getFullDateTime(a.startDate, a.startTime) - getFullDateTime(b.startDate, b.startTime));

    const eventMap = allNews.value.reduce((map, event) => {
        map[event.id] = event;
        return map;
    }, {});

    eventAnnouncements.value = eventAnnouncementsResponse.data.map(ann => ({
        ...ann,
        event: eventMap[ann.eventId],
        formattedTimestamp: format(new Date(ann.timestamp), "MMMM dd, yyyy HH:mm"),
    })).sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp));


    startAnnouncementCarousel();

    // Check for new announcements
    if (sortedAnnouncements.value.length > 0) {
      const latestTimestamp = new Date(sortedAnnouncements.value[0].timestamp).getTime();
      const lastSeenTimestamp = localStorage.getItem('lastSeenAnnouncementTimestamp');

      if (!lastSeenTimestamp || latestTimestamp > parseInt(lastSeenTimestamp, 10)) {
        hasNewAnnouncements.value = true;
      }
    }
  } catch (error) {
    console.error("Error fetching news:", error);
  }
});

onUnmounted(() => {
  if (announcementTimer) {
    clearInterval(announcementTimer);
  }
});

const toggleAnnouncements = () => {
  showAnnouncements.value = !showAnnouncements.value;

  // If opening the panel and there are new announcements, mark them as seen
  if (showAnnouncements.value && hasNewAnnouncements.value) {
    hasNewAnnouncements.value = false;
    if (sortedAnnouncements.value.length > 0) {
      const latestTimestamp = new Date(sortedAnnouncements.value[0].timestamp).getTime();
      localStorage.setItem('lastSeenAnnouncementTimestamp', latestTimestamp.toString());
    }
  }
};

function loadToggleState(key, defaultValue) {
  const saved = localStorage.getItem(key);
  return saved !== null ? JSON.parse(saved) : defaultValue;
}

function saveToggleState(key, value) {
  localStorage.setItem(key, JSON.stringify(value));
}

const promptDeleteAnnouncement = (announcement) => {
  announcementToDelete.value = announcement;
  showDeleteAnnouncementConfirm.value = true;
};

const confirmDeleteAnnouncement = async () => {
  if (!announcementToDelete.value) return;
  saving.value = true;
  try {
    await axios.delete(`http://localhost:3000/event_announcements/${announcementToDelete.value.id}`);
    eventAnnouncements.value = eventAnnouncements.value.filter(a => a.id !== announcementToDelete.value.id);
    successMessage.value = 'Announcement deleted successfully.';
    showSuccessDialog.value = true;
  } catch (error) {
    console.error('Error deleting announcement:', error);
    errorMessage.value = 'Failed to delete announcement.';
    showErrorDialog.value = true;
  } finally {
    saving.value = false;
    showDeleteAnnouncementConfirm.value = false;
    announcementToDelete.value = null;
  }
};

</script>

<template>
  <div class="min-h-screen flex flex-col items-center bg-gray-100 py-8 px-4">
    <!-- Bell Icon for Announcements -->
    <div class="fixed top-16 right-4 z-50">
      <button
        @click="toggleAnnouncements"
        class="relative p-3 rounded-full bg-blue-500 text-white hover:bg-blue-600 shadow-lg transition-transform transform hover:scale-110"
        v-tooltip="'Announcements'"
      >
        <span class="text-xl">🔔</span>
        <span v-if="hasNewAnnouncements" class="absolute top-1 right-1 block h-3 w-3 rounded-full bg-red-500 border-2 border-white"></span>
      </button>

      <!-- Announcements Dropdown -->
      <div
        v-if="showAnnouncements"
        class="absolute top-12 right-0 w-80 bg-white border rounded shadow-md p-4"
      >
        <h3 class="font-bold text-center mb-2">Announcements</h3>

        <ul v-if="sortedAnnouncements.length" class="max-h-96 overflow-y-auto">
            <li
            v-for="announcement in sortedAnnouncements"
            :key="announcement.id"
            @click="announcement.event && router.visit(route('event.details', { id: announcement.event.id, view: 'announcements' }))"
            :class="['group p-3 border-b flex justify-between items-start', announcement.event ? 'hover:bg-gray-50 cursor-pointer' : '']"
            >
            <div class="flex-1 min-w-0">
                <span v-if="announcement.event" class="text-xs font-semibold text-blue-600 group-hover:underline block truncate">
                    {{ announcement.event.title }}
                </span>
                <p class="break-words text-sm w-full">{{ announcement.message }}</p>
                <p class="text-xs text-gray-500 mt-2">{{ announcement.formattedTimestamp }}</p>
            </div>
            </li>
        </ul>

        <p v-else class="text-center text-sm text-gray-500">No announcements</p>
      </div>
    </div>

    <!-- News and Update Title -->
    <h1 class="text-2xl font-bold mt-6 text-center">News and Updates</h1>

    <!-- Announcement Banner -->
    <div v-if="currentAnnouncement && showLatestBanner"
      @click="scrollToAnnouncement(currentAnnouncement.id)"
      class="mt-4 mb-6 w-full max-w-5xl bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 relative rounded shadow flex items-center gap-2 overflow-hidden cursor-pointer"
    >
      <!-- Prev Button -->
      <Button
        v-if="sortedAnnouncements.length > 1"
        icon="pi pi-chevron-left"
        class="p-button-text p-button-rounded text-blue-700 hover:bg-purple-200 shrink-0"
        @click.stop="prevAnnouncement"
        aria-label="Previous Announcement"
      />

      <!-- Announcement Content -->
      <transition :name="announcementDirection === 'next' ? 'slide-next' : 'slide-prev'" mode="out-in">
        <div :key="currentAnnouncement.id" class="flex-grow text-center">
            <strong v-if="currentAnnouncement.event" class="block font-semibold mb-1 text-lg">
                📣 Announcement for <Link :href="route('event.details', { id: currentAnnouncement.event.id })" @click.stop class="text-blue-800 hover:underline">{{ currentAnnouncement.event.title }}</Link>
            </strong>
            <strong v-else class="block font-semibold mb-1 text-lg">📣 Announcement</strong>
            <p class="text-base">{{ currentAnnouncement.message }}</p>
            <p class="text-xs text-blue-500 mt-1">{{ currentAnnouncement.formattedTimestamp }}</p>
        </div>
      </transition>

      <!-- Next Button -->
      <Button
        v-if="sortedAnnouncements.length > 1"
        icon="pi pi-chevron-right"
        class="p-button-text p-button-rounded text-blue-700 hover:bg-purple-200 shrink-0"
        @click.stop="nextAnnouncement"
        aria-label="Next Announcement"
      />

      <!-- Close Button -->
      <button
        @click.stop="showLatestBanner = false"
        class="absolute top-2 right-2 text-blue-700 hover:text-blue-900 text-xl leading-none"
        aria-label="Close"
      >&times;</button>
    </div>

    <!-- View Toggle -->
    <div class="w-full max-w-5xl mt-8">
      <div class="flex border-b">
        <button
          @click="currentView = 'events'"
          :class="[
            'px-4 py-2 font-semibold transition-colors duration-200',
            currentView === 'events'
              ? 'border-b-2 border-purple-500 text-purple-600'
              : 'text-gray-500 hover:text-purple-600',
          ]"
        >
          Events
        </button>
        <button
          @click="currentView = 'announcements'"
          :class="[
            'px-4 py-2 font-semibold transition-colors duration-200',
            currentView === 'announcements'
              ? 'border-b-2 border-purple-500 text-purple-600'
              : 'text-gray-500 hover:text-purple-600',
          ]"
        >
          Announcements
        </button>
      </div>
    </div>

    <!-- Conditional Content -->
    <div v-if="currentView === 'events'">
      <!-- Ongoing Events -->
      <div v-if="ongoingEvents.length > 0" class="w-full max-w-5xl mt-8">
        <div class="flex justify-between items-center mb-4">
          <h2 class="text-xl font-semibold">Ongoing Events</h2>
          <Button
            size="small"
            :icon="showOngoingEvents ? 'pi pi-chevron-up' : 'pi pi-chevron-down'"
            :label="showOngoingEvents ? 'Hide' : 'Show'"
            @click="showOngoingEvents = !showOngoingEvents"
            class="p-button-text"
          />
        </div>
        <div v-if="showOngoingEvents">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div v-for="(event, index) in ongoingEvents" :key="'ongoing-' + index" class="group relative h-full">
              <Link :href="route('event.details', { id: event.id })" preserve-scroll class="block h-full">
                <Card class="h-full flex flex-col justify-between min-h-[280px]">
                  <template #header>
                    <div class="h-40 bg-gray-300 rounded-t-lg flex items-center justify-center overflow-hidden relative">
                      <div class="absolute inset-0 bg-gradient-to-b from-gray-900/20 to-transparent z-10"></div>
                      <img v-if="event.image" :src="event.image" class="h-full w-full object-cover" alt="Event image"/>
                      <img v-else src="/resources/images/NCSlogo.png" class="w-24 h-24 object-contain opacity-50" alt="Event Placeholder" />
                      <Tag v-if="isNewEvent(event)" value="NEW" severity="success" class="absolute top-2 right-2 z-20"/>
                    </div>
                  </template>
                  <template #title>
                    <h3 class="text-lg font-medium overflow-hidden line-clamp-1 cursor-help" v-tooltip.top="event.title">{{ event.title }}</h3>
                  </template>
                  <template #subtitle>
                    <div class="flex items-center gap-2">
                      <span class="text-sm text-gray-500">{{ event.formattedDate }}</span>
                      <Tag :value="getUpcomingTag(event)" :severity="getUpcomingSeverity(event)" class="text-xs"/>
                    </div>
                  </template>
                  <template #content>
                    <div class="flex-1 mb-2 overflow-hidden h-[calc(1.5rem)]">
                      <p class="text-sm text-gray-600 line-clamp-1">{{ event.description }}</p>
                    </div>
                  </template>
                  <template #footer>
                    <div class="flex justify-end mt-2 z-20">
                      <Button label="View Details" icon="pi pi-info-circle" class="p-button-text p-button-sm" @click.stop="$inertia.visit(route('event.details', { id: event.id }))"/>
                    </div>
                  </template>
                </Card>
              </Link>
            </div>
          </div>
        </div>
      </div>

      <!-- Events This Month -->
      <div v-if="eventsThisMonth.length > 0" class="w-full max-w-5xl mt-8">
        <div class="flex justify-between items-center mb-4">
          <h2 class="text-xl font-semibold">Events This Month</h2>
          <Button size="small" :icon="showEventsThisMonth ? 'pi pi-chevron-up' : 'pi pi-chevron-down'" :label="showEventsThisMonth ? 'Hide' : 'Show'" @click="showEventsThisMonth = !showEventsThisMonth" class="p-button-text"/>
        </div>
        <div v-if="showEventsThisMonth">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div v-for="(event, index) in eventsThisMonth" :key="'month-' + index" class="group relative h-full">
              <Link :href="route('event.details', { id: event.id })" preserve-scroll class="block h-full">
                <Card class="h-full flex flex-col justify-between min-h-[280px]">
                  <template #header>
                    <div class="h-40 bg-gray-300 rounded-t-lg flex items-center justify-center overflow-hidden relative">
                      <div class="absolute inset-0 bg-gradient-to-b from-gray-900/20 to-transparent z-10"></div>
                      <img v-if="event.image" :src="event.image" class="h-full w-full object-cover" alt="Event image"/>
                      <img v-else src="/resources/images/NCSlogo.png" class="w-24 h-24 object-contain opacity-50" alt="Event Placeholder" />
                      <Tag v-if="isNewEvent(event)" value="NEW" severity="success" class="absolute top-2 right-2 z-20"/>
                    </div>
                  </template>
                  <template #title>
                    <h3 class="text-lg font-medium overflow-hidden line-clamp-1 cursor-help" v-tooltip.top="event.title">{{ event.title }}</h3>
                  </template>
                  <template #subtitle>
                    <div class="flex items-center gap-2">
                      <span class="text-sm text-gray-500">{{ event.formattedDate }}</span>
                      <Tag :value="getUpcomingTag(event)" :severity="getUpcomingSeverity(event)" class="text-xs"/>
                    </div>
                  </template>
                  <template #content>
                    <div class="flex-1 mb-2 overflow-hidden h-[calc(1.5rem)]">
                      <p class="text-sm text-gray-600 line-clamp-1">{{ event.description }}</p>
                    </div>
                  </template>
                  <template #footer>
                    <div class="flex justify-end mt-2 z-20">
                      <Button label="View Details" icon="pi pi-info-circle" class="p-button-text p-button-sm" @click.stop="$inertia.visit(route('event.details', { id: event.id }))"/>
                    </div>
                  </template>
                </Card>
              </Link>
            </div>
          </div>
        </div>
      </div>

      <!-- Upcoming Events -->
      <div v-if="upcomingEvents.length > 0" class="w-full max-w-5xl mt-8">
        <div class="flex justify-between items-center mb-4">
          <h2 class="text-xl font-semibold">Upcoming Events</h2>
          <Button size="small" :icon="showUpcomingEvents ? 'pi pi-chevron-up' : 'pi pi-chevron-down'" :label="showUpcomingEvents ? 'Hide' : 'Show'" @click="showUpcomingEvents = !showUpcomingEvents" class="p-button-text"/>
        </div>
        <div v-if="showUpcomingEvents">
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div v-for="(event, index) in upcomingEvents" :key="'upcoming-' + index" class="group relative h-full">
              <Link :href="route('event.details', { id: event.id })" preserve-scroll class="block h-full">
                <Card class="h-full flex flex-col justify-between min-h-[280px]">
                  <template #header>
                    <div class="h-40 bg-gray-300 rounded-t-lg flex items-center justify-center overflow-hidden relative">
                      <div class="absolute inset-0 bg-gradient-to-b from-gray-900/20 to-transparent z-10"></div>
                      <img v-if="event.image" :src="event.image" class="h-full w-full object-cover" alt="Event image"/>
                      <img v-else src="/resources/images/NCSlogo.png" class="w-24 h-24 object-contain opacity-50" alt="Event Placeholder" />
                      <Tag v-if="isNewEvent(event)" value="NEW" severity="success" class="absolute top-2 right-2 z-20"/>
                    </div>
                  </template>
                  <template #title>
                    <h3 class="text-lg font-medium overflow-hidden line-clamp-1 cursor-help" v-tooltip.top="event.title">{{ event.title }}</h3>
                  </template>
                  <template #subtitle>
                    <div class="flex items-center gap-2">
                      <span class="text-sm text-gray-500">{{ event.formattedDate }}</span>
                      <Tag :value="getUpcomingTag(event)" :severity="getUpcomingSeverity(event)" class="text-xs"/>
                    </div>
                  </template>
                  <template #content>
                    <div class="flex-1 mb-2 overflow-hidden h-[calc(1.5rem)]">
                      <p class="text-sm text-gray-600 line-clamp-1">{{ event.description }}</p>
                    </div>
                  </template>
                  <template #footer>
                    <div class="flex justify-end mt-2 z-20">
                      <Button label="View Details" icon="pi pi-info-circle" class="p-button-text p-button-sm" @click.stop="$inertia.visit(route('event.details', { id: event.id }))"/>
                    </div>
                  </template>
                </Card>
              </Link>
            </div>
          </div>
        </div>
      </div>

      <!-- Event Calendar -->
    <div class="w-full max-w-5xl">
        <h2 class="text-2xl font-bold mb-6 mt-8 text-center">Event Calendar</h2>
      <EventCalendar :events="allNews" />
    </div>
    </div>

    <!-- Announcement Board -->
    <div v-if="currentView === 'announcements'" class="w-full max-w-5xl mt-8">
      <h2 class="text-xl font-semibold mb-5">Announcement Board</h2>
      <div v-if="sortedAnnouncements.length" class="space-y-6">
        <div
          v-for="announcement in sortedAnnouncements"
          :key="announcement.id"
          :id="`announcement-${announcement.id}`"
          @click="announcement.event && router.visit(route('event.details', { id: announcement.event.id, view: 'announcements' }))"
          :class="['relative p-6 bg-white rounded-lg shadow-lg border-l-4 border-blue-500', announcement.event ? 'cursor-pointer hover:bg-gray-50 transition-colors' : '']"
        >
          <!-- User Avatar and Name -->
          <div v-if="user?.name === 'Admin'" class="absolute top-2 right-2 z-10">
            <Button
                icon="pi pi-trash"
                class="p-button-text p-button-danger p-button-rounded"
                @click.stop="promptDeleteAnnouncement(announcement)"
                v-tooltip.top="'Delete Announcement'"
            />
          </div>
          <div class="flex items-center mb-3">
            <Avatar
              image="https://primefaces.org/cdn/primevue/images/avatar/amyelsner.png"
              class="mr-2"
              shape="circle"
              size="small"
            />
            <span class="text-gray-600 text-sm font-semibold">{{ user?.name }}</span>
          </div>
          <div v-if="announcement.event" class="mb-3">
            <span class="text-sm text-gray-600">For event:</span>
            <Link :href="route('event.details', { id: announcement.event.id, view: 'announcements' })" @click.stop class="font-semibold text-blue-700 hover:underline ml-1 text-base">
              {{ announcement.event.title }}
            </Link>
          </div>
          <p class="text-gray-800 text-lg leading-relaxed">{{ announcement.message }}</p>
          <img
            v-if="announcement.image"
            :src="announcement.image"
            alt="Announcement image"
            class="mt-4 rounded-lg max-w-md mx-auto h-auto shadow-md"
          />
          <p class="text-sm text-gray-500 mt-4 text-right">{{ announcement.formattedTimestamp }}</p>
        </div>
      </div>
      <p v-else class="flex flex-col items-center justify-center py-10 bg-gray-50 rounded-lg shadow-inner border border-dashed border-gray-300 text-center text-gray-500">
        <span class="text-4xl mb-2">📢</span>
        <span class="font-semibold text-lg">No announcements yet</span>
        <span class="text-sm">Check back later for updates.</span>
      </p>
    </div>

    <!-- Dialogs -->
    <LoadingSpinner :show="saving" />
    <SuccessDialog
      v-model:show="showSuccessDialog"
      :message="successMessage"
    />
    <div v-if="showErrorDialog" class="fixed inset-0 bg-black bg-opacity-30 flex items-center justify-center" style="z-index: 9998;">
      <div class="bg-white p-6 rounded-lg shadow-lg max-w-sm w-full">
        <h2 class="text-lg font-semibold text-red-700 mb-2">Error</h2>
        <p class="text-sm text-gray-700 mb-4">{{ errorMessage }}</p>
        <div class="flex justify-end">
          <button @click="showErrorDialog = false" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">Close</button>
        </div>
      </div>
    </div>
    <ConfirmationDialog
      v-model:show="showDeleteAnnouncementConfirm"
      title="Delete Announcement?"
      message="Are you sure you want to delete this announcement?"
      confirmText="Yes, Delete"
      confirmButtonClass="bg-red-600 hover:bg-red-700"
      @confirm="confirmDeleteAnnouncement"
    />
  </div>
</template>

<style scoped>
.z-20 {
  z-index: 20;
}

.h-40 {
  position: relative;
  background-color: #f3f4f6;
}

.h-40 img {
  transition: transform 0.3s ease;
}

.h-40:hover img {
  transform: scale(1.05);
}

.h-40::after {
  content: '';
  position: absolute;
  inset: 0;
  box-shadow: inset 0 0 10px rgba(0, 0, 0, 0.1);
  pointer-events: none;
}

/* Remove menu item background in collapsed mode except on hover */
:deep(.p-tieredmenu.menu-collapsed .p-menuitem-link) {
  background: transparent !important;
}

/* On hover, show background only behind the icon */
:deep(.p-tieredmenu.menu-collapsed .p-menuitem-link:hover) {
  background-color: rgba(0, 0, 0, 0.04) !important;
}

/* Card styles */
.min-h-\[280px\] {
  min-height: 280px;
}

.h-\[calc\(1\.5rem\)\] {
  height: 1.5rem;
}

/* Custom scrollbar for announcements list */
.max-h-96.overflow-y-auto::-webkit-scrollbar {
  width: 6px;
}

.max-h-96.overflow-y-auto::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 10px;
}

.max-h-96.overflow-y-auto::-webkit-scrollbar-thumb {
  background: #c1c1c1; /* Lighter gray */
  border-radius: 10px;
}

.max-h-96.overflow-y-auto::-webkit-scrollbar-thumb:hover {
  background: #a8a8a8; /* Darker gray on hover */
}

/* Announcement Carousel Transitions */
.slide-next-enter-active,
.slide-next-leave-active,
.slide-prev-enter-active,
.slide-prev-leave-active {
  transition: all 0.25s ease-in-out;
}

/* Slide Next */
.slide-next-enter-from {
  transform: translateX(100%);
  opacity: 0;
}
.slide-next-leave-to {
  transform: translateX(-100%);
  opacity: 0;
}

/* Slide Previous */
.slide-prev-enter-from {
  transform: translateX(-100%);
  opacity: 0;
}
.slide-prev-leave-to {
  transform: translateX(100%);
  opacity: 0;
}

.highlight {
  background-color: #e9d5ff; /* light purple */
  transition: background-color 0.5s ease-out;
}
</style>
