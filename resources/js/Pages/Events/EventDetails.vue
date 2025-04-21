<script setup>
import { ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { format } from 'date-fns';

// Get data from Inertia page props
const { props } = usePage();
const eventDetails = ref(props.event);
const relatedEvents = ref(props.relatedEvents || []);
const categories = ref(props.categories || []);
const tags = ref(props.tags || []);
const selectedFiles = ref([]);

// Format dates and process tags when component mounts or props change
watch(() => props.event, (newEvent) => {
  if (newEvent) {
    eventDetails.value = {
      ...newEvent,
      startDate: format(new Date(newEvent.startDate), 'MMM-dd-yy'),
      endDate: format(new Date(newEvent.endDate), 'MMM-dd-yy'),
      tags: newEvent.tags?.map(tagId =>
        tags.value.find(tag => tag.id.toString() === tagId.toString()) ||
        { name: 'Unknown', color: '#e0e7ff' }
      ) || []
    };
  }
}, { immediate: true });

// Watch for related events updates
watch(() => props.relatedEvents, (newRelated) => {
  relatedEvents.value = newRelated || [];
}, { immediate: true });

// Category title display helper
const getCategoryTitle = (categoryId) => {
  const category = categories.value.find(cat => cat.id === categoryId);
  return category ? category.title : 'Unknown Category';
};

// Handle file upload
const handleFileUpload = (event) => {
  selectedFiles.value = [...selectedFiles.value, ...event.target.files];
};
</script>



<template>
  <div class="min-h-screen bg-gray-200 py-8 px-4 flex flex-col items-center">

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
          <p><strong>Start Date:</strong> {{ eventDetails.startDate }}, {{ eventDetails.startTime }}</p>
          <p><strong>End Date:</strong> {{ eventDetails.endDate }}, {{ eventDetails.endTime }}</p>
          <p><strong>Category:</strong> {{ getCategoryTitle(eventDetails.category_id) }}</p

          <!--  Committee section -->
          <div class="border-t pt-4">
            <h2 class="font-semibold">Committee:</h2>
            <div class="pl-4">
                <p
                v-for="(taskItem, index) in eventDetails.tasks || []"
                :key="index"
                >
                {{ taskItem.employee.name }} ({{ taskItem.committee.name }}): {{ taskItem.task }}
                </p>
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
        :href="route('event.details', { id: event.id })"
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
</template>

