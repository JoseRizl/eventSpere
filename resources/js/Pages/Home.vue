<script setup>
import { ref, onMounted, computed, watch } from "vue";
import axios from "axios";
import { format } from "date-fns";
import { Link, router } from "@inertiajs/vue3";
import { useAnnouncementStore } from "../stores/announcementStore";
import { isSameMonth, isWithinInterval, parse } from "date-fns";

const allNews = ref([]);
const store = useAnnouncementStore();
const showAnnouncements = ref(false);
const newAnnouncement = ref("");
const showAddModal = ref(false);
const showDeleteModal = ref(false);
const selectedAnnouncementId = ref(null);
const now = new Date();
const showEventsThisMonth = ref(loadToggleState('showEventsThisMonth', true));
const showOngoingEvents = ref(loadToggleState('showOngoingEvents', true));
const showUpcomingEvents = ref(loadToggleState('showUpcomingEvents', true));
watch(showEventsThisMonth, (val) => saveToggleState('showEventsThisMonth', val));
watch(showOngoingEvents, (val) => saveToggleState('showOngoingEvents', val));
watch(showUpcomingEvents, (val) => saveToggleState('showUpcomingEvents', val));
const showLatestBanner = ref(true);

const latestAnnouncement = computed(() =>
  store.announcements.length ? store.announcements[store.announcements.length - 1] : null
);

const getEventStartDate = (news) => {
  return typeof news.startDate === 'string' && news.startDate.includes('-')
    ? parse(news.startDate, "MMM-dd-yyyy", new Date())
    : new Date(news.startDate);
};

const ongoingEvents = computed(() => {
  const events = allNews.value.filter((news) => {
    const start = getEventStartDate(news);
    const end = news.endDate
      ? (news.endDate.includes('-')
          ? parse(news.endDate, "MMM-dd-yyyy", new Date())
          : new Date(news.endDate))
      : start;

    return isWithinInterval(now, { start, end });
  });

  // Sort by start date (ascending)
  return events.sort((a, b) => getEventStartDate(a) - getEventStartDate(b));
});

const isNewEvent = (event) => {
  if (!event?.createdAt) return false; // Safely check if createdAt exists

  const oneWeekAgo = new Date();
  oneWeekAgo.setDate(oneWeekAgo.getDate() - 7); // 7 days ago

  const createdAtDate = new Date(event.createdAt);
  return createdAtDate > oneWeekAgo;
};

const getUpcomingTag = (startDate, endDate) => {
  if (!startDate) return 'Upcoming';

  const startDateObj = new Date(startDate);
  const endDateObj = endDate ? new Date(endDate) : startDateObj;

  if (isNaN(startDateObj.getTime())) return 'Upcoming';

  const today = new Date();
  today.setHours(0, 0, 0, 0); // Normalize to start of day for comparison

  // Check if event has ended (end date is in the past)
  if (endDateObj < today) return 'Ended';

  // Check if event is ongoing (today is between start and end dates)
  if (startDateObj <= today && today <= endDateObj) return 'Ongoing';

  // For future events, calculate days until start
  const daysDiff = Math.floor((startDateObj - today) / (1000 * 60 * 60 * 24));

  if (daysDiff === 0) return 'Today';
  if (daysDiff < 3) return 'Very Soon';
  if (daysDiff < 7) return 'This Week';
  if (daysDiff < 14) return 'Next Week';
  if (daysDiff < 30) return 'This Month';
  return 'Upcoming';
};

const getUpcomingSeverity = (startDate, endDate) => {
  const today = new Date();
  today.setHours(0, 0, 0, 0);

  const startDateObj = new Date(startDate);
  const endDateObj = endDate ? new Date(endDate) : startDateObj;

  if (endDateObj < today) return null; // No severity for ended events
  if (startDateObj <= today && today <= endDateObj) return 'success'; // Ongoing

  const daysDiff = Math.floor((startDateObj - today) / (1000 * 60 * 60 * 24));

  if (daysDiff < 3) return 'danger';
  if (daysDiff < 7) return 'warning';
  return 'info';
};

const eventsThisMonth = computed(() => {
  const events = allNews.value.filter((news) => {
    const start = getEventStartDate(news);
    const end = news.endDate
      ? (news.endDate.includes('-')
          ? parse(news.endDate, "MMM-dd-yyyy", new Date())
          : new Date(news.endDate))
      : start;

    return isSameMonth(start, now) && !(isWithinInterval(now, { start, end }));
  });

  // Sort by start date (ascending)
  return events.sort((a, b) => getEventStartDate(a) - getEventStartDate(b));
});

const upcomingEvents = computed(() => {
  const events = allNews.value.filter((news) => {
    const start = getEventStartDate(news);
    return start > now && !isSameMonth(start, now);
  });

  // Sort by start date (ascending)
  return events.sort((a, b) => getEventStartDate(a) - getEventStartDate(b));
});

// Fetch announcements and news
onMounted(async () => {
  try {
    const eventsResponse = await axios.get("http://localhost:3000/events");
    const sportsResponse = await axios.get("http://localhost:3000/sports");

    allNews.value = [...eventsResponse.data, ...sportsResponse.data]
      .filter((news) => !news.archived)
      .map((news) => ({
        ...news,
        formattedDate: news.startDate
          ? format(new Date(news.startDate), "MMMM dd, yyyy")
          : "No date",
      }));

    await store.fetchAnnouncements();
  } catch (error) {
    console.error("Error fetching news:", error);
  }
});

const toggleAnnouncements = () => {
  showAnnouncements.value = !showAnnouncements.value;
};

// Open Add Confirmation Modal
const confirmAdd = () => {
  if (newAnnouncement.value.trim()) {
    showAddModal.value = true;
  }
};

// Add Announcement After Confirmation
const addAnnouncement = async () => {
  await store.addAnnouncement(newAnnouncement.value);
  newAnnouncement.value = "";
  showAddModal.value = false;
};

// Open Delete Confirmation Modal
const confirmDelete = (id) => {
  selectedAnnouncementId.value = id;
  showDeleteModal.value = true;
};

// Delete Announcement After Confirmation
const deleteAnnouncement = async () => {
  await store.removeAnnouncement(selectedAnnouncementId.value);
  showDeleteModal.value = false;
};

function loadToggleState(key, defaultValue) {
  const saved = localStorage.getItem(key);
  return saved !== null ? JSON.parse(saved) : defaultValue;
}

function saveToggleState(key, value) {
  localStorage.setItem(key, JSON.stringify(value));
}

</script>

<template>
  <div class="min-h-screen flex flex-col items-center bg-gray-100 py-8 px-4">
    <!-- Bell Icon for Announcements -->
    <div class="fixed top-16 right-4 z-50">
      <button
        @click="toggleAnnouncements"
        class="p-3 rounded-full bg-blue-500 text-white hover:bg-blue-600 shadow-md"
        v-tooltip="'Announcements'"
      >
        🔔
      </button>

      <!-- Announcements Dropdown -->
      <div
        v-if="showAnnouncements"
        class="absolute top-12 right-0 w-72 bg-white border rounded shadow-md p-4"
      >
        <h3 class="font-bold text-center mb-2">Announcements</h3>

        <!-- Add Announcement Section -->
        <div class="flex items-center gap-2 mb-2">
          <InputText v-model="newAnnouncement" placeholder="wen, wer, wat" class="w-full"/>
          <Button icon="pi pi-plus" class="p-button-success" @click="confirmAdd" v-tooltip.top="'Create Announcement'"/>
        </div>

        <ul v-if="store.announcements.length">
            <li
            v-for="announcement in store.announcements"
            :key="announcement.id"
            class="p-2 border-b flex justify-between items-center"
            >
            <div class="flex-1 min-w-0">
                <p class="break-words text-sm w-full">{{ announcement.message }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ announcement.formattedTimestamp }}</p>
            </div>
            <Button icon="pi pi-trash" class="p-button-text p-button-danger shrink-0" @click="confirmDelete(announcement.id)"/>
            </li>
        </ul>

        <p v-else class="text-center text-sm text-gray-500">No announcements</p>
      </div>
    </div>

    <!-- Logo Section -->
    <div class="w-full max-w-5xl bg-white p-6 rounded-lg shadow-md flex justify-center">
      <img src="/resources/images/NCSlogo.png" alt="School Logo" class="w-32 md:w-48" />
    </div>

    <!-- News and Update Title -->
    <h1 class="text-2xl font-bold mt-6 text-center">News and Updates</h1>

    <div v-if="latestAnnouncement && showLatestBanner"
    class="mt-4 mb-6 w-full max-w-5xl bg-purple-100 border-l-4 border-purple-500 text-purple-700 p-4 relative rounded shadow"
    >
    <strong class="block font-semibold mb-1">📣 Latest Announcement</strong>
    <p>{{ latestAnnouncement.message }}</p>
    <button
     @click="showLatestBanner = false"
     class="absolute top-6 right-2 text-purple-700 hover:text-purple-900 text-2xl leading-none"
     aria-label="Close"
    >
     &times;
    </button>
    </div>

    <!-- Ongoing Events -->
    <div v-if="ongoingEvents.length" class="w-full max-w-5xl mt-12">
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-xl font-semibold">Ongoing Events</h2>
        <Button
        size="small"
        icon="pi pi-chevron-down"
        :label="showOngoingEvents ? 'Hide' : 'Show'"
        @click="showOngoingEvents = !showOngoingEvents"
        />
    </div>
    <div v-if="showOngoingEvents" class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div
        v-for="(event, index) in ongoingEvents"
        :key="'ongoing-' + index"
        class="group relative h-full"
        >
        <Link
            :href="route('event.details', { id: event.id })"
            preserve-scroll
            class="block h-full"
        >
        <Card class="h-full flex flex-col justify-between min-h-[280px]">
            <template #header>
                <div class="h-40 bg-gray-300 rounded-t-lg flex items-center justify-center overflow-hidden relative">
                    <div class="absolute inset-0 bg-gradient-to-b from-gray-900/20 to-transparent z-10"></div>
                    <img
                        v-if="event.image"
                        :src="event.image"
                        class="h-full w-full object-cover"
                        alt="Event image"
                    />
                    <span v-else class="text-gray-500">No image</span>
                    <Tag
                        v-if="isNewEvent(event)"
                        value="NEW"
                        severity="success"
                        class="absolute top-2 right-2 z-20"
                    />
                </div>
            </template>
            <template #title>
                <h3
                    class="text-lg font-medium overflow-hidden line-clamp-1 cursor-help"
                    v-tooltip.top="event.title"
                >
                    {{ event.title }}
                </h3>
            </template>

            <template #subtitle>
                <div class="flex items-center gap-2">
                    <span class="text-sm text-gray-500">{{ event.formattedDate }}</span>
                    <Tag
                        :value="getUpcomingTag(event.startDate, event.endDate)"
                        :severity="getUpcomingSeverity(event.startDate, event.endDate)"
                        class="text-xs"
                    />
                </div>
            </template>

            <template #content>
                <div class="flex-1 mb-2 overflow-hidden h-[calc(1.5rem)]">
                    <p class="text-sm text-gray-600 line-clamp-1">
                        {{ event.description }}
                    </p>
                </div>
            </template>

            <template #footer>
                <div class="flex justify-end mt-2 z-20">
                    <Button
                        label="View Details"
                        icon="pi pi-info-circle"
                        class="p-button-text p-button-sm"
                        @click.stop="$inertia.visit(route('event.details', { id: event.id }))"
                    />
                </div>
            </template>
            </Card>
        </Link>
        </div>
    </div>
    </div>

    <!-- Events This Month -->
    <div v-if="eventsThisMonth.length" class="w-full max-w-5xl mt-8">
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-xl font-semibold">Events This Month</h2>
        <Button
        size="small"
        icon="pi pi-chevron-down"
        :label="showEventsThisMonth ? 'Hide' : 'Show'"
        @click="showEventsThisMonth = !showEventsThisMonth"
        />
    </div>
    <div v-if="showEventsThisMonth" class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div
        v-for="(event, index) in eventsThisMonth"
        :key="'month-' + index"
        class="group relative h-full"
        >
        <Link
            :href="route('event.details', { id: event.id })"
            preserve-scroll
            class="block h-full"
        >
        <Card class="h-full flex flex-col justify-between min-h-[280px]">
            <template #header>
                <div class="h-40 bg-gray-300 rounded-t-lg flex items-center justify-center overflow-hidden relative">
                    <div class="absolute inset-0 bg-gradient-to-b from-gray-900/20 to-transparent z-10"></div>
                    <img
                        v-if="event.image"
                        :src="event.image"
                        class="h-full w-full object-cover"
                        alt="Event image"
                    />
                    <span v-else class="text-gray-500">No image</span>
                    <Tag
                        v-if="isNewEvent(event)"
                        value="NEW"
                        severity="success"
                        class="absolute top-2 right-2 z-20"
                    />
                </div>
            </template>
            <template #title>
                <h3
                    class="text-lg font-medium overflow-hidden line-clamp-1 cursor-help"
                    v-tooltip.top="event.title"
                >
                    {{ event.title }}
                </h3>
            </template>

            <template #subtitle>
                <div class="flex items-center gap-2">
                    <span class="text-sm text-gray-500">{{ event.formattedDate }}</span>
                    <Tag
                        :value="getUpcomingTag(event.startDate, event.endDate)"
                        :severity="getUpcomingSeverity(event.startDate, event.endDate)"
                        class="text-xs"
                    />
                </div>
            </template>

            <template #content>
                <div class="flex-1 mb-2 overflow-hidden h-[calc(1.5rem)]">
                    <p class="text-sm text-gray-600 line-clamp-1">
                        {{ event.description }}
                    </p>
                </div>
            </template>

            <template #footer>
                <div class="flex justify-end mt-2 z-20">
                    <Button
                        label="View Details"
                        icon="pi pi-info-circle"
                        class="p-button-text p-button-sm"
                        @click.stop="$inertia.visit(route('event.details', { id: event.id }))"
                    />
                </div>
            </template>
            </Card>
        </Link>
        </div>
    </div>
    </div>
    <p v-else class="text-center text-gray-500">No events this month.</p>

    <!-- Upcoming Events -->
    <div v-if="upcomingEvents.length" class="w-full max-w-5xl mt-12">
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-xl font-semibold">Upcoming Events</h2>
        <Button
        size="small"
        icon="pi pi-chevron-down"
        :label="showUpcomingEvents ? 'Hide' : 'Show'"
        @click="showUpcomingEvents = !showUpcomingEvents"
        />
    </div>

    <div v-if="showUpcomingEvents" class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div
        v-for="(event, index) in upcomingEvents"
        :key="'upcoming-' + index"
        class="group relative h-full"
        >
        <Link
            :href="route('event.details', { id: event.id })"
            preserve-scroll
            class="block h-full"
        >
            <Card class="h-full flex flex-col justify-between min-h-[280px]">
            <template #header>
                <div class="h-40 bg-gray-300 rounded-t-lg flex items-center justify-center overflow-hidden relative">
                    <div class="absolute inset-0 bg-gradient-to-b from-gray-900/20 to-transparent z-10"></div>
                    <img
                        v-if="event.image"
                        :src="event.image"
                        class="h-full w-full object-cover"
                        alt="Event image"
                    />
                    <span v-else class="text-gray-500">No image</span>
                    <Tag
                        v-if="isNewEvent(event)"
                        value="NEW"
                        severity="success"
                        class="absolute top-2 right-2 z-20"
                    />
                </div>
            </template>

            <template #title>
                <h3
                    class="text-lg font-medium overflow-hidden line-clamp-1 cursor-help"
                    v-tooltip.top="event.title"
                >
                    {{ event.title }}
                </h3>
            </template>

            <template #subtitle>
                <div class="flex items-center gap-2">
                    <span class="text-sm text-gray-500">{{ event.formattedDate }}</span>
                    <Tag
                        :value="getUpcomingTag(event.startDate, event.endDate)"
                        :severity="getUpcomingSeverity(event.startDate, event.endDate)"
                        class="text-xs"
                    />
                </div>
            </template>

            <template #content>
                <div class="flex-1 mb-2 overflow-hidden h-[calc(1.5rem)]">
                    <p class="text-sm text-gray-600 line-clamp-1">
                        {{ event.description }}
                    </p>
                </div>
            </template>

            <template #footer>
                <div class="flex justify-end mt-2 z-20">
                    <Button
                        label="View Details"
                        icon="pi pi-info-circle"
                        class="p-button-text p-button-sm"
                        @click.stop="$inertia.visit(route('event.details', { id: event.id }))"
                    />
                </div>
            </template>
            </Card>
        </Link>
        </div>
    </div>
    </div>
    <p v-else class="text-center text-gray-500">No upcoming events.</p>

    <!-- Add Announcement Confirmation Modal -->
    <Dialog v-model:visible="showAddModal" header="Confirm Add" modal>
      <p>Are you sure you want to add this announcement?</p>
      <p class="font-semibold">{{ newAnnouncement }}</p>
      <div class="flex justify-end gap-2 mt-4">
        <Button label="Cancel" class="p-button-secondary" @click="showAddModal = false"/>
        <Button label="Confirm" class="p-button-success" @click="addAnnouncement"/>
      </div>
    </Dialog>

    <!-- Delete Confirmation Modal -->
    <Dialog v-model:visible="showDeleteModal" header="Confirm Delete" modal>
      <p>Are you sure you want to delete this announcement?</p>
      <div class="flex justify-end gap-2 mt-4">
        <Button label="Cancel" class="p-button-secondary" @click="showDeleteModal = false"/>
        <Button label="Delete" class="p-button-danger" @click="deleteAnnouncement"/>
      </div>
    </Dialog>
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
</style>
