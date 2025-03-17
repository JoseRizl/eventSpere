<script setup>
import { ref, onMounted, watch, defineExpose } from 'vue';
import axios from 'axios';
import { format } from 'date-fns';
import { useRoute } from 'vue-router';

const vueRoute = useRoute();
const eventDetails = ref(null);
const relatedEvents = ref([]);
const selectedFiles = ref([]);
const categories = ref([]);
const tags = ref([]);
const isLoading = ref(true); // ✅ Loading state to manage when to display content
const key = ref(0);

const refreshComponent = () => {
    console.log(`Component refreshed at key: ${key.value}`);
    key.value++;
    console.log('[REFRESH] Key incremented to:', key.value);
};

defineExpose({ refreshComponent });

const fetchEventDetails = async (forceRefresh = false) => {
    console.log(`Fetching data... (Force Refresh: ${forceRefresh})`);

  isLoading.value = true; // ✅ Start loading

    // 🚨 Clear all reactive data before refilling it
  eventDetails.value = null;
  relatedEvents.value = [];
  categories.value = [];
  tags.value = [];

  try {
    const id = vueRoute.params.id;

     // 🚨 Add timestamp to prevent cached data
     const timestamp = new Date().getTime();

    const [eventsResponse, sportsResponse, categoriesResponse, tagsResponse] = await Promise.all([
      axios.get(`http://localhost:3000/events?ts=${timestamp}`, {
      headers: {
        'Cache-Control': 'no-cache',
        'Pragma': 'no-cache',
        'Expires': '0'
    }
     }),
      axios.get(`http://localhost:3000/sports?ts=${timestamp}`),
      axios.get(`http://localhost:3000/category?ts=${timestamp}`),
      axios.get(`http://localhost:3000/tags?ts=${timestamp}`)
    ]);

    categories.value = categoriesResponse.data;
    tags.value = tagsResponse.data;

    const combinedData = [...eventsResponse.data, ...sportsResponse.data];

    const foundEvent = combinedData.find(event => event.id === id);

    if (foundEvent) {
        console.log('[DATA FOUND]', foundEvent);
        eventDetails.value = { ...foundEvent };
      foundEvent.startDate = format(new Date(foundEvent.startDate), 'MMM-dd-yyyy');
      foundEvent.endDate = format(new Date(foundEvent.endDate), 'MMM-dd-yyyy');

      foundEvent.tags = foundEvent.tags.map(tagId =>
        tags.value.find(tag => tag.id.toString() === tagId.toString()) || { name: 'Unknown', color: '#e0e7ff' }
      );

      eventDetails.value = { ...foundEvent }; // 🚨 Force reactivity update
    }

    relatedEvents.value = combinedData.filter(event => event.id !== id);
    refreshComponent(); // ✅ Ensures UI refreshes immediately after data updates


  } catch (error) {
    console.error('Error fetching event details:', error);
  }finally {
    isLoading.value = false; // ✅ Ensure loading state ends after data fetch
  }
};

// ✅ Initial fetch on mount
onMounted( async () => {
    await fetchEventDetails(true); // Force fresh data

    setTimeout(() => {
  fetchEventDetails(true);  // ✅ Ensure fresh data
}, 1000); // Increased delay
});


// ✅ Watch for route changes to refresh data
watch(() => vueRoute.params.id, () => {
    fetchEventDetails();
}, { immediate: true });

// Handle file upload
const handleFileUpload = (event) => {
  selectedFiles.value = [...selectedFiles.value, ...event.target.files];
};

// Get Category Title
const getCategoryTitle = (categoryId) => {
  const category = categories.value.find(cat => cat.id === categoryId);
  return category ? category.title : 'Unknown Category';
};
</script>


<template>
<div :key="key">
  <div class="min-h-screen bg-gray-200 py-8 px-4 flex flex-col items-center">

    <!-- Loading Indicator -->
    <div v-if="isLoading" class="text-center text-xl font-semibold py-8">
      Loading event details...
    </div>

    <!-- Dynamic Banner Image -->
    <div class="w-full max-w-4xl bg-white rounded-lg shadow-md overflow-hidden">
      <img
        v-if="eventDetails?.image"
        :src="eventDetails.image"
        :alt="eventDetails.title"
        class="w-full h-64 object-cover"
      />
      <div v-else class="w-full h-64 bg-gray-300 flex items-center justify-center">
        <span class="text-gray-500 text-lg">No Image Available</span>
      </div>
    </div>

    <!-- Event Card -->
    <div v-if="eventDetails" class="bg-white shadow-md rounded-lg p-6 max-w-2xl w-full mt-6 flex flex-col">

      <!-- Title, Tags & Description (Centered) -->
      <div class="text-center">
        <h1 class="text-2xl font-bold">{{ eventDetails.title }}</h1>

        <!-- Tags Display -->
        <div class="flex justify-center gap-2 mt-2">
          <span
            v-for="(tag, index) in eventDetails.tags"
            :key="index"
            :style="{ backgroundColor: tag.color, color: '#fff' }"
            class="text-xs py-1 px-3 rounded-lg"
          >
            {{ tag.name }}
          </span>
        </div>

        <p class="text-gray-800 mt-3">{{ eventDetails.description }}</p>
      </div>

      <!-- Event Details & Image Upload -->
      <div class="flex justify-between mt-4">
        <!-- Left: Event Details -->
        <div class="text-left text-sm space-y-2 flex-1">
          <p><strong>Start Date:</strong> {{ eventDetails.startDate }}</p>
          <p><strong>End Date:</strong> {{ eventDetails.endDate }}</p>
          <p><strong>Start Time:</strong> {{ eventDetails.startTime }}</p>
          <p><strong>End Time:</strong> {{ eventDetails.endTime }}</p>
          <p><strong>Category:</strong> {{ getCategoryTitle(eventDetails.category_id) }}</p>
          <p><strong>Participants:</strong> All</p>

          <!-- Committee Section -->
          <div class="border-t pt-4">
            <h2 class="font-semibold">Committee:</h2>
            <div class="pl-4">
              <p>Glin Mike: Speaker</p>
            </div>
          </div>
        </div>

        <!-- Right: Image Upload -->
        <div class="flex flex-col items-center space-y-4 min-h-[150px] justify-end">
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
        <Link
        v-for="(event, index) in relatedEvents.slice(0, 5)"
        :key="index"
        :href="route(event.category_id === '3' ? 'sports.details' : 'event.details', { id: event.id })"
        class="min-w-[120px] bg-gray-300 p-2 rounded text-center hover:bg-gray-400 transition"
        >
        <div class="h-16 w-24 bg-gray-200 flex items-center justify-center rounded mb-2 overflow-hidden">
            <img v-if="event.image" :src="event.image" :alt="event.title" class="h-full w-full object-cover">
            <span v-else class="text-xs text-gray-500">No Image</span>
        </div>
        <p class="text-sm text-black font-semibold">{{ event.title }}</p>
        </Link>

        <button class="text-blue-500 text-sm">More</button>
      </div>
    </div>
  </div>
</div>
</template>

