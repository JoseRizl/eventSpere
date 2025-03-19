<script setup>
import { ref, onMounted } from "vue";
import axios from "axios";
import { format } from "date-fns";
import { Link, router } from '@inertiajs/vue3';

const allNews = ref([]);

onMounted(async () => {
  try {
    const eventsResponse = await axios.get("http://localhost:3000/events");
    const sportsResponse = await axios.get("http://localhost:3000/sports");

    allNews.value = [...eventsResponse.data, ...sportsResponse.data]
      .filter(news => !news.archived)
      .map((news) => ({
        ...news,
        formattedDate: news.startDate ? format(new Date(news.startDate), "MMMM dd, yyyy") : "No date",
      }));

    console.log(allNews.value);
  } catch (error) {
    console.error("Error fetching news:", error);
  }
});

const showAnnouncements = ref(false);
const announcements = ref([
  { message: "🚨 Important Update: System Maintenance on March 30." },
  { message: "📢 Event registration closes this Friday!" },
]);

const toggleAnnouncements = () => {
  showAnnouncements.value = !showAnnouncements.value;
};

const newAnnouncement = ref("");

// Add a new announcement
const addAnnouncement = () => {
  if (newAnnouncement.value.trim()) {
    announcements.value.push({ message: newAnnouncement.value.trim() });
    newAnnouncement.value = ""; // Clear input after adding
  }
};

// Remove an announcement
const removeAnnouncement = (idx) => {
  announcements.value.splice(idx, 1);
};
</script>

<template>
  <div class="min-h-screen flex flex-col items-center bg-gray-100 py-8 px-4">

    <!-- Bell Icon for Announcements -->
    <div class="fixed top-16 right-4 z-50">
      <button
        @click="toggleAnnouncements"
        class="p-3 rounded-full bg-blue-500 text-white hover:bg-blue-600 shadow-md"
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
          <input
            v-model="newAnnouncement"
            type="text"
            class="p-2 border rounded w-full"
            placeholder="Add a new announcement"
          />
          <button
            @click="addAnnouncement"
            class="p-2 bg-green-500 text-white rounded hover:bg-green-600"
          >
            ➕
          </button>
        </div>

        <ul v-if="announcements.length">
          <li v-for="(announcement, idx) in announcements" :key="idx" class="p-2 border-b flex justify-between items-center">
            {{ announcement.message }}
            <button
              @click="removeAnnouncement(idx)"
              class="text-red-500 hover:text-red-700"
            >
              ❌
            </button>
          </li>
        </ul>

        <p v-else class="text-center text-sm text-gray-500">No announcements</p>
      </div>
    </div>

    <!-- Logo Section -->
    <div class="w-full max-w-4xl bg-white p-6 rounded-lg shadow-md flex justify-center">
      <img src="/resources/images/NCSlogo.png" alt="School Logo" class="w-32 md:w-48">
    </div>

    <!-- News and Update Title -->
    <h1 class="text-2xl font-bold mt-6 text-center">News and Updates</h1>

    <!-- News Grid (Merged Events & Sports) -->
    <div class="w-full max-w-5xl grid grid-cols-1 md:grid-cols-3 gap-6 mt-6">
      <Link
        v-for="(news, index) in allNews"
        :key="index"
        :href="route('event.details', { id: news.id })"
        :preserve-state="false"
        class="p-4 border rounded-lg shadow-sm bg-white block hover:shadow-lg hover:scale-105 transform transition duration-200 ease-in-out"
      >
        <div class="h-40 bg-gray-300 rounded mb-2 flex items-center justify-center overflow-hidden">
          <img v-if="news.image" :src="news.image" :alt="news.title" class="h-full w-full object-cover rounded">
        </div>
        <p class="font-semibold text-center">{{ news.title }}</p>
        <p class="description text-sm text-gray-600 text-center">
        {{ news.description }}
        </p>
        <p class="text-xs text-gray-500 text-center mt-1">{{ news.formattedDate }}</p>

        <div class="flex justify-center mt-2">
          <span
            class="bg-blue-500 text-white px-3 py-1 rounded text-sm hover:bg-blue-600 cursor-pointer"
          >
            Read more
          </span>
        </div>
      </Link>
    </div>
  </div>
</template>
