<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

const todaysEvents = ref([]);
const sportsEvents = ref([]);

onMounted(async () => {
  try {
    // Fetch today's events
    const todayResponse = await axios.get('http://localhost:3000/events');
    todaysEvents.value = todayResponse.data;

    // Fetch sports events
    const sportsResponse = await axios.get('http://localhost:3000/sports');
    sportsEvents.value = sportsResponse.data;
  } catch (error) {
    console.error('Error fetching events:', error);
  }
});
</script>

<template>
  <div class="flex-1 p-4">
    <h3 class="title">News and Updates</h3>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <!-- Today's Events Section -->
      <div>
        <h2 class="text-lg font-bold">Events</h2>
        <div v-if="todaysEvents.length > 0" class="space-y-4">
          <div
            v-for="(event, index) in todaysEvents"
            :key="index"
            class="p-4 border rounded"
          >
            <img v-if="event.image" :src="event.image" :alt="event.title" class="mb-2" />
            <p class="font-semibold">{{ event.title }}</p>
            <p class="text-sm text-gray-600">{{ event.description }}</p>
            <!-- Temporary front end-->
            <Link href="/foundation-day" class="text-blue-500 hover:underline">
                Read more...
            </Link>

          </div>
        </div>
        <p v-else class="text-gray-500">No events available for today.</p>
      </div>

      <!-- Sports Section -->
      <div>
        <h2 class="text-lg font-bold">Sports</h2>
        <div v-if="sportsEvents.length > 0" class="space-y-4">
          <div
            v-for="(event, index) in sportsEvents"
            :key="index"
            class="p-4 border rounded"
          >
            <img v-if="event.image" :src="event.image" :alt="event.title" class="mb-2" />
            <p class="font-semibold">{{ event.title }}</p>
            <p class="text-sm text-gray-600">{{ event.description }}</p>
            <a :href="event.link" class="text-blue-500 hover:underline">Read more...</a>
          </div>
        </div>
        <p v-else class="text-gray-500">No sports updates available.</p>
      </div>
    </div>
  </div>
</template>
