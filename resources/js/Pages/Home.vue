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
            <Link
            v-for="(news, index) in ongoingEvents"
            :key="'ongoing-' + index"
            :href="route('event.details', { id: news.id })"
            preserve-scroll
            class="p-4 border rounded-lg shadow-sm bg-white block hover:shadow-lg hover:scale-105 transition"
            >
            <div class="h-40 bg-gray-300 rounded mb-2 flex items-center justify-center overflow-hidden">
                <img v-if="news.image" :src="news.image" class="h-full w-full object-cover rounded" />
            </div>
            <p class="font-semibold text-center">{{ news.title }}</p>
            <p class="text-sm text-gray-600 text-center">{{ news.description }}</p>
            <p class="text-xs text-gray-500 text-center mt-1">{{ news.formattedDate }}</p>
            </Link>
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
            <Link
            v-for="(news, index) in eventsThisMonth"
            :key="'month-' + index"
            :href="route('event.details', { id: news.id })"
            preserve-scroll
            class="p-4 border rounded-lg shadow-sm bg-white block hover:shadow-lg hover:scale-105 transition"
            >
            <div class="h-40 bg-gray-300 rounded mb-2 flex items-center justify-center overflow-hidden">
                <img v-if="news.image" :src="news.image" class="h-full w-full object-cover rounded" />
            </div>
            <p class="font-semibold text-center">{{ news.title }}</p>
            <p class="text-sm text-gray-600 text-center">{{ news.description }}</p>
            <p class="text-xs text-gray-500 text-center mt-1">{{ news.formattedDate }}</p>
            </Link>
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
        <Link
        v-for="(news, index) in upcomingEvents"
        :key="'upcoming-' + index"
        :href="route('event.details', { id: news.id })"
        preserve-scroll
        class="p-4 border rounded-lg shadow-sm bg-white block hover:shadow-lg hover:scale-105 transition"
        >
        <div class="h-40 bg-gray-300 rounded mb-2 flex items-center justify-center overflow-hidden">
            <img v-if="news.image" :src="news.image" class="h-full w-full object-cover rounded" />
        </div>
        <p class="font-semibold text-center">{{ news.title }}</p>
        <p class="text-sm text-gray-600 text-center">{{ news.description }}</p>
        <p class="text-xs text-gray-500 text-center mt-1">{{ news.formattedDate }}</p>
        </Link>
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
