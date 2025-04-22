<script setup>
import { ref } from 'vue';
import { parse, format } from 'date-fns';
import { usePage, router } from '@inertiajs/vue3';

// Inertia props
const { props } = usePage();
const categories = ref(props.categories || []);
const tags = ref(props.tags || []);
const saving = ref(false);

const eventDetails = ref({
  ...props.event,
  schedules: props.event.schedules || [],
  tags: props.event.tags?.map(tag => {
  if (typeof tag === 'object') return tag;
  return props.tags.find(t => t.id === tag);
}) || []

});

// Edit mode toggle
const editMode = ref(false);
const toggleEdit = () => {
  editMode.value = !editMode.value;
};

// Methods
const addSchedule = () => {
  eventDetails.value.schedules.push({ time: '', activity: '' });
};

const removeSchedule = (index) => {
  eventDetails.value.schedules.splice(index, 1);
};

const saveChanges = () => {
  saving.value = true;
  router.post(`/events/${eventDetails.value.id}/update`, eventDetails.value, {
    preserveScroll: true,
    onFinish: () => {
      saving.value = false;
    },
    onSuccess: () => {
      alert('Event updated successfully!');
      editMode.value = false;
    }
  });
};

const formatDate = (dateString) => {
  if (!dateString) return '';
  return format(new Date(dateString), 'MMM-dd-yyyy');
};

const formatDisplayTime = (timeString) => {
  if (!timeString) return '';
  const parsed = parse(timeString, 'HH:mm', new Date());
  return format(parsed, 'hh:mm a'); // 04:00 PM
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

    <!-- Edit Toggle Button -->
    <div class="flex justify-end max-w-2xl w-full mt-4">
    <button @click="toggleEdit" class="px-4 py-2 rounded bg-gray-600 text-white hover:bg-gray-700">
        {{ editMode ? 'Cancel Edit' : 'Edit Event' }}
    </button>
    </div>


      <div v-if="eventDetails" class="bg-white shadow-md rounded-lg p-6 max-w-2xl w-full mt-6 flex flex-col space-y-4">
        <!-- Title -->
        <div>
          <input
            v-if="editMode"
            v-model="eventDetails.title"
            class="text-xl font-bold border-b w-full"
            placeholder="Event Title"
          />
          <h1 v-else class="text-xl font-bold">{{ eventDetails.title }}</h1>
        </div>

        <!-- Description -->
        <div>
          <textarea
            v-if="editMode"
            v-model="eventDetails.description"
            class="w-full border rounded p-2"
            placeholder="Event Description"
          />
          <p v-else class="text-gray-700 whitespace-pre-line">{{ eventDetails.description }}</p>
        </div>

        <!-- Tags -->
        <div class="flex gap-2 flex-wrap">
            <div v-if="editMode" class="w-full">
            <MultiSelect
                v-model="eventDetails.tags"
                :options="tags"
                optionLabel="name"
                optionValue="id"
                display="chip"
                placeholder="Select tags"
                class="w-full"
            >
                <template #option="slotProps">
                <div class="flex items-center gap-2">
                    <span
                    class="inline-block rounded px-2 py-1 text-xs text-white"
                    :style="{ backgroundColor: slotProps.option.color }"
                    >
                    {{ slotProps.option.name }}
                    </span>
                </div>
                </template>
                <template #chip="slotProps">
                <div
                    class="px-2 py-1 rounded text-white text-xs"
                    :style="{ backgroundColor: slotProps.value.color }"
                >
                    {{ slotProps.value.name }}
                </div>
                </template>
            </MultiSelect>
            </div>
            <div v-else class="flex gap-2 flex-wrap">
                <span
                v-for="tag in eventDetails.tags"
                :key="tag.id"
                :style="{ backgroundColor: tag.color, color: '#fff' }"
                class="text-xs py-1 px-2 rounded mr-2"
                >
                {{ tag.name }}
                </span>
            </div>
        </div>

        <!-- Dates and Times -->
        <div class="grid grid-cols-2 gap-4 text-sm">
          <template v-if="editMode">
            <input type="date" v-model="eventDetails.startDate" class="border p-2 rounded" />
            <input type="time" v-model="eventDetails.startTime" class="border p-2 rounded" />
            <input type="date" v-model="eventDetails.endDate" class="border p-2 rounded" />
            <input type="time" v-model="eventDetails.endTime" class="border p-2 rounded" />
          </template>
          <template v-else>
            <p><strong>Start:</strong> {{ formatDate(eventDetails.startDate) }}, {{ formatDisplayTime(eventDetails.startTime) }}</p>
            <p><strong>End:</strong> {{ formatDate(eventDetails.endDate) }}, {{ formatDisplayTime(eventDetails.endTime) }}</p>
          </template>
        </div>

        <!-- Category -->
        <div>
          <select v-if="editMode" v-model="eventDetails.category_id" class="border rounded p-2">
            <option v-for="cat in categories" :key="cat.id" :value="cat.id">
              {{ cat.title }}
            </option>
          </select>
          <p v-else>
            <strong>Category:</strong> {{ categories.find(c => c.id === eventDetails.category_id)?.title }}
          </p>
        </div>

        <!-- Committee -->
        <div>
          <h2 class="font-semibold mb-1">Committee:</h2>
          <div class="space-y-1 pl-4 text-sm">
            <p v-for="(taskItem, index) in eventDetails.tasks || []" :key="index">
              {{ taskItem.employee.name }} ({{ taskItem.committee.name }}): {{ taskItem.task }}
            </p>
          </div>
        </div>

        <!-- Schedules -->
        <div>
          <h2 class="font-semibold mb-1">Event Schedules:</h2>
          <div v-if="editMode">
            <div v-for="(schedule, i) in eventDetails.schedules" :key="i" class="flex items-center gap-2 mb-2">
              <input v-model="schedule.time" type="time" class="border p-1 rounded w-32" />
              <input v-model="schedule.activity" type="text" placeholder="Activity" class="border p-1 rounded flex-1" />
              <button @click="removeSchedule(i)" class="text-red-500">✕</button>
            </div>
            <button @click="addSchedule" class="text-blue-500 text-sm mt-2">+ Add Schedule</button>
          </div>
          <ul v-else class="list-disc pl-5 space-y-1 text-sm text-gray-700">
            <li v-for="(schedule, i) in eventDetails.schedules" :key="i">
              {{ schedule.time }} - {{ schedule.activity }}
            </li>
          </ul>
        </div>

        <!-- Save Button -->
        <button
          v-if="editMode"
          @click="saveChanges"
          class="mt-4 self-end bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded"
        >
          Save Changes
        </button>
      </div>
    </div>

    <!-- Loading Dialog -->
    <div v-if="saving" class="fixed inset-0 bg-black bg-opacity-30 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg p-6 shadow-lg flex items-center gap-3">
            <svg class="animate-spin w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
            </svg>
            <span class="text-gray-700 font-medium">Saving changes...</span>
        </div>
    </div>

  </template>
