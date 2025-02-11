<script setup>
import { ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import axios from 'axios';

const route = useRoute();
const eventDetails = ref(null);
const relatedEvents = ref([]);
const selectedFiles = ref([]);

onMounted(async () => {
  try {
    const id = route.params.id;
    console.log('Route params:', route.params);

    // Fetch event details
    const response = await axios.get(`http://localhost:3000/events/${id}`);
    eventDetails.value = response.data;

    // Fetch all events and sports
    const [eventsResponse, sportsResponse] = await Promise.all([
      axios.get('http://localhost:3000/events'),
      axios.get('http://localhost:3000/sports')
    ]);

    // Merge both events and sports into related events (excluding current event)
    relatedEvents.value = [...eventsResponse.data, ...sportsResponse.data]
      .filter(event => event.id !== id);

  } catch (error) {
    console.error('Error fetching event details:', error);
  }
});

// Handle file upload
const handleFileUpload = (event) => {
  selectedFiles.value = [...selectedFiles.value, ...event.target.files];
};
</script>

<template>
  <div class="min-h-screen bg-gray-200 py-8 px-4 flex flex-col items-center">

    <!-- Banner Image -->
    <div class="w-full max-w-4xl bg-white p-6 rounded-lg shadow-md flex justify-center">
      <img src="/resources/images/NCSlogo.png" alt="Event Banner" class="w-32 md:w-48">
    </div>

    <!-- Event Card -->
    <div v-if="eventDetails" class="bg-white shadow-md rounded-lg p-6 max-w-2xl w-full mt-6 flex flex-col">

    <!-- Title & Description (Centered) -->
    <div class="text-center">
    <h1 class="text-2xl font-bold">{{ eventDetails.title }}</h1>
    <p class="text-gray-800 mt-1">{{ eventDetails.description }}</p>
    </div>

    <!-- Event Details & Image Upload -->
    <div class="flex justify-between mt-4">
    <!-- Left: Event Details -->
    <div class="text-left text-sm space-y-2 flex-1">
        <p><strong>Date:</strong> {{ eventDetails.date }}</p>
        <p><strong>Time:</strong> {{ eventDetails.time }}</p>
        <p><strong>Category:</strong> {{ eventDetails.category?.title }}</p>
        <p><strong>Participants:</strong> All</p>

        <!-- Committee Section -->
        <div class="border-t pt-4">
        <h2 class="font-semibold">Committee:</h2>
        <div class="pl-4">
            <p>Glin Mike: Speaker</p>
        </div>
        </div>
    </div>

    <!-- Right: Image Upload (Aligned Properly) -->
    <div class="flex flex-col items-center space-y-4 min-h-[150px] justify-end">
        <label class="cursor-pointer flex flex-col items-center p-4 bg-gray-100 rounded-lg w-32">
        <input type="file" class="hidden" @change="handleFileUpload" accept="image/*">
        <img src="/resources/images/Clip-icon.png" alt="Attach" class="h-10 w-10">
        <span class="text-sm text-gray-600">Add Image</span>
        </label>
        <label class="cursor-pointer flex flex-col items-center p-4 bg-gray-100 rounded-lg w-32">
        <input type="file" class="hidden" @change="handleFileUpload" accept="image/*">
        <img src="/resources/images/Clip-icon.png" alt="Attach" class="h-10 w-10">
        <span class="text-sm text-gray-600">Add Image</span>
        </label>
    </div>
    </div>
    </div>

    <!-- Related Events Section -->
    <div v-if="relatedEvents.length" class="bg-white shadow-md rounded-lg p-4 mt-6 max-w-3xl w-full">
      <h2 class="text-lg font-semibold mb-3">Related Events</h2>
      <div class="flex overflow-x-auto space-x-2">
        <router-link
          v-for="(event, index) in relatedEvents.slice(0, 5)"
          :key="index"
          :to="`/${event.category?.title === 'Sports' ? 'sports' : 'events'}/${event.id}`"
          class="min-w-[120px] bg-gray-300 p-2 rounded text-center hover:bg-gray-400 transition"
        >
          <div class="h-16 w-24 bg-gray-200 flex items-center justify-center rounded mb-2 overflow-hidden">
            <img v-if="event.image" :src="event.image" :alt="event.title" class="h-full w-full object-cover">
            <span v-else class="text-xs text-gray-500">No Image</span>
          </div>
          <p class="text-sm text-black font-semibold">{{ event.title }}</p>
        </router-link>
        <button class="text-blue-500 text-sm">More</button>
      </div>
    </div>

  </div>
</template>
