<script setup>
import { ref, onMounted, computed, watch, nextTick } from "vue";
import axios from "axios";
import { format, isWithinInterval, isSameMonth, parse, areIntervalsOverlapping, endOfDay } from "date-fns";
import { Link, usePage, router } from "@inertiajs/vue3";
import EventCalendar from '@/Components/EventCalendar.vue';
import ConfirmationDialog from '@/Components/ConfirmationDialog.vue';
import SuccessDialog from '@/Components/SuccessDialog.vue';
import LoadingSpinner from '@/Components/LoadingSpinner.vue';
import Avatar from 'primevue/avatar';
import InputText from 'primevue/inputtext';
import DatePicker from 'primevue/datepicker';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import Card from 'primevue/card';

const allNews = ref([]);
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
const showErrorDialog = ref(false);
const errorMessage = ref('');
const showDeleteAnnouncementConfirm = ref(false);
const announcementToDelete = ref(null);

const page = usePage();
const user = computed(() => page.props.auth.user);
const allUsers = computed(() => page.props.all_users || []);
const currentView = ref('events'); // 'events' or 'announcements'

const searchQuery = ref('');
const startDateFilter = ref(null);
const endDateFilter = ref(null);
const showDateFilter = ref(false);

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

const filteredNews = computed(() => {
    let news = allNews.value;

    // Filter by search query
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase().trim();
        news = news.filter(event => {
            const title = event.title?.toLowerCase() || '';
            const description = event.description?.toLowerCase() || '';
            return title.includes(query) || description.includes(query);
        });
    }

    // Filter by date range
    if (startDateFilter.value || endDateFilter.value) {
        news = news.filter(event => {
            const eventStart = getFullDateTime(event.startDate, event.startTime);
            const eventEnd = getFullDateTime(event.endDate || event.startDate, event.endTime || '23:59');
            if (!eventStart || !eventEnd) return false;

            const filterStart = startDateFilter.value ? new Date(startDateFilter.value) : null;
            const filterEnd = endDateFilter.value ? endOfDay(new Date(endDateFilter.value)) : null;

            if (filterStart && filterEnd) {
                return areIntervalsOverlapping(
                    { start: eventStart, end: eventEnd },
                    { start: filterStart, end: filterEnd }
                );
            }
            if (filterStart) {
                return eventEnd >= filterStart;
            }
            if (filterEnd) {
                return eventStart <= filterEnd;
            }
            return true;
        });
    }

    return news;
});

const ongoingEvents = computed(() => {
  const now = new Date();
  const events = filteredNews.value.filter((news) => {
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
  const events = filteredNews.value.filter((news) => {
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
  const events = filteredNews.value.filter((news) => {
    const start = getFullDateTime(news.startDate, news.startTime);
    if (!start) return false;
    // Event is in the future and not in the current month.
    return start > now && !isSameMonth(start, now);
  });

  // Sort by start date (ascending)
  return events.sort((a, b) => getFullDateTime(a.startDate, a.startTime) - getFullDateTime(b.startDate, b.startTime));
});

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

// Fetch announcements and news
onMounted(async () => {
  try {
    const [eventsResponse, eventAnnouncementsResponse] = await Promise.all([
        axios.get("http://localhost:3000/events"),
        axios.get("http://localhost:3000/event_announcements"),
    ]);

    allNews.value = [...eventsResponse.data]
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

    const employeesMap = allUsers.value.reduce((map, emp) => {
        map[emp.id] = emp;
        return map;
    }, {});

    eventAnnouncements.value = eventAnnouncementsResponse.data.map(ann => ({
        ...ann,
        event: eventMap[ann.eventId],
        employee: employeesMap[ann.userId] || { name: 'Admin' },
        formattedTimestamp: format(new Date(ann.timestamp), "MMMM dd, yyyy HH:mm"),
    })).sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp));

  } catch (error) {
    console.error("Error fetching news:", error);
  }
});

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

</script>

<template>
  <div class="min-h-screen flex flex-col items-center bg-gray-100 py-8 px-4">
    <!-- News and Update Title -->
    <h1 class="text-2xl font-bold mt-6 text-center">News and Updates</h1>

    <!-- View Toggle -->
    <div class="w-full max-w-5xl mt-8">
      <div class="flex border-b">
        <div class="flex items-center">
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

      <!-- Filters -->
      <div class="mt-4">
        <div class="search-wrapper">
            <div class="p-input-icon-left">
                <i class="pi pi-search" />
                <InputText v-model="searchQuery" :placeholder="currentView === 'events' ? 'Search by title or description...' : 'Search by message or event...'" class="w-full" />
            </div>
            <Button
                icon="pi pi-calendar"
                class="p-button-outlined date-filter-btn"
                @click="toggleDateFilter"
                :class="{ 'p-button-primary': showDateFilter }"
                v-tooltip.top="'Filter by date'"
            />
            <Button v-if="searchQuery || startDateFilter || endDateFilter" icon="pi pi-times" class="p-button-rounded p-button-text p-button-danger" @click="clearFilters" v-tooltip.top="'Clear All Filters'" />
        </div>
      </div>

      <!-- Date Filter Calendar -->
      <div v-if="showDateFilter" class="date-filter-container mt-2">
          <div class="date-range-wrapper">
            <div class="date-input-group">
              <label>From:</label>
              <DatePicker
                v-model="startDateFilter"
                dateFormat="M-dd-yy"
                :showIcon="true"
                placeholder="Start date"
                class="date-filter-calendar"
              />
            </div>
            <div class="date-input-group">
              <label>To:</label>
              <DatePicker
                v-model="endDateFilter"
                dateFormat="M-dd-yy"
                :showIcon="true"
                placeholder="End date"
                class="date-filter-calendar"
              />
            </div>
          </div>
          <Button v-if="startDateFilter || endDateFilter" icon="pi pi-times" class="p-button-text p-button-rounded clear-date-btn" @click="clearDateFilter" v-tooltip.top="'Clear date filter'" />
      </div>
    </div>

    <!-- Conditional Content -->
    <div v-if="currentView === 'events'" class="w-full max-w-5xl">
      <div v-if="!filteredNews.length && (searchQuery || startDateFilter || endDateFilter)" class="w-full max-w-5xl mt-8 flex flex-col items-center justify-center py-10 bg-gray-50 rounded-lg shadow-inner border border-dashed border-gray-300 text-center text-gray-500">
        <span class="text-4xl mb-2">🧐</span>
        <span class="font-semibold text-lg">No Events Found</span>
        <span class="text-sm">Try adjusting your search or date filters.</span>
      </div>
      <div v-else>
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
          <EventCalendar :events="filteredNews" />
        </div>
      </div>
    </div>

    <!-- Announcement Board -->
    <div v-if="currentView === 'announcements'" class="w-full max-w-5xl mt-8">
      <h2 class="text-xl font-semibold mb-5">Announcement Board</h2>
      <div v-if="filteredAnnouncements.length" class="space-y-6">
        <div
          v-for="announcement in filteredAnnouncements"
          :key="announcement.id"
          :id="`announcement-${announcement.id}`"
          @click="announcement.event && router.visit(route('event.details', { id: announcement.event.id, view: 'announcements' }))"
          :class="['relative p-6 bg-white rounded-lg shadow-lg border-l-4 border-blue-500', announcement.event ? 'cursor-pointer hover:bg-gray-50 transition-colors' : '']"
        >
          <!-- User Avatar and Name -->
          <div v-if="user?.name === 'Admin' || user?.name === 'Principal'" class="absolute top-2 right-2 z-10">
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
            <span class="text-gray-600 text-sm font-semibold">{{ announcement.employee?.name || 'Admin' }}</span>
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
      <div v-else-if="searchQuery || startDateFilter || endDateFilter" class="flex flex-col items-center justify-center py-10 bg-gray-50 rounded-lg shadow-inner border border-dashed border-gray-300 text-center text-gray-500">
        <span class="text-4xl mb-2">🧐</span>
        <span class="font-semibold text-lg">No Announcements Found</span>
        <span class="text-sm">Try adjusting your search or date filters.</span>
      </div>
      <div v-else class="flex flex-col items-center justify-center py-10 bg-gray-50 rounded-lg shadow-inner border border-dashed border-gray-300 text-center text-gray-500">
        <span class="text-4xl mb-2">📢</span>
        <span class="font-semibold text-lg">No announcements yet</span>
        <span class="text-sm">Check back later for updates.</span>
      </div>
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

.highlight {
  background-color: #e9d5ff; /* light purple */
  transition: background-color 0.5s ease-out;
}

.search-wrapper {
  display: flex;
  gap: 10px;
  align-items: center;
  width: 100%;
  max-width: 400px;
}

.search-wrapper .p-input-icon-left {
  position: relative;
  width: 100%;
}

.search-wrapper .p-input-icon-left i {
  position: absolute;
  left: 0.75rem;
  top: 50%;
  transform: translateY(-50%);
  color: #6c757d;
}

.search-wrapper .p-input-icon-left .p-inputtext {
  width: 100%;
  padding-left: 2.5rem;
}

.date-filter-btn {
  min-width: 40px;
  height: 40px;
  flex-shrink: 0;
}

.date-filter-container {
  background: white;
  padding: 15px;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  display: flex;
  flex-direction: column;
  gap: 10px;
  width: 100%;
  max-width: 400px;
}

.date-range-wrapper {
  display: flex;
  flex-direction: row;
  gap: 10px;
  align-items: flex-start;
}

.date-input-group {
  display: flex;
  flex-direction: column;
  gap: 5px;
  flex: 1;
}

.date-input-group label {
  font-size: 0.9rem;
  color: #666;
  font-weight: 500;
}

.date-filter-calendar {
  width: 100%;
}

.clear-date-btn {
  align-self: flex-end;
  color: #dc3545;
}

.clear-date-btn:hover {
  background-color: rgba(220, 53, 69, 0.1);
}
</style>
