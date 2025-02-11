<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const allNews = ref([]);

onMounted(async () => {
  try {
    // Fetch both events and sports as one array
    const eventsResponse = await axios.get('http://localhost:3000/events');
    const sportsResponse = await axios.get('http://localhost:3000/sports');

    // Combine data into one array
    allNews.value = [...eventsResponse.data, ...sportsResponse.data];
    console.log(allNews.value)
  } catch (error) {
    console.error('Error fetching news:', error);
  }
});
</script>

<template>
  <div class="min-h-screen flex flex-col items-center bg-gray-100 py-8 px-4">

    <!-- Logo Section -->
    <div class="w-full max-w-4xl bg-white p-6 rounded-lg shadow-md flex justify-center">
      <img src="/resources/images/NCSlogo.png" alt="School Logo" class="w-32 md:w-48">
    </div>

    <!-- News and Update Title -->
    <h1 class="text-2xl font-bold mt-6 text-center">News and Updates</h1>

    <!-- News Grid (Merged Events & Sports) -->
    <div class="w-full max-w-5xl grid grid-cols-1 md:grid-cols-3 gap-6 mt-6">
      <div v-for="(news, index) in allNews" :key="index" class="p-4 border rounded-lg shadow-sm bg-white">
        <!-- Placeholder Image -->
        <div class="h-40 bg-gray-300 rounded mb-2 flex items-center justify-center">
          <img v-if="news.image" :src="news.image" :alt="news.title" class="h-full object-cover rounded">
        </div>
        <p class="font-semibold text-center">{{ news.title }}</p>
        <p class="text-sm text-gray-600 text-center">{{ news.subtitle }}</p>
        <div class="flex justify-center mt-2">
            <router-link
            :to="`/events/${news.id}`"
            class="bg-blue-500 text-white px-3 py-1 rounded text-sm hover:bg-blue-600 cursor-pointer"
          >
            Read more
          </router-link>
        </div>
        <p class="text-xs text-gray-500 text-center mt-1">{{ news.date }}</p>
      </div>
    </div>

  </div>
</template>
