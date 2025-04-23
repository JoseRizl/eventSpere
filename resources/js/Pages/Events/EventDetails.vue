<script setup>
import { ref, computed } from 'vue';
import { parse, format, parseISO, isValid } from 'date-fns';
import { usePage, router } from '@inertiajs/vue3';

// Inertia props
const { props } = usePage();
const categories = ref(props.categories || []);
const tags = ref(props.tags || []);
const saving = ref(false);
const showSaveConfirmDialog = ref(false);
const showSuccessDialog = ref(false);
const removeTag = (tagToRemove) => {
  normalizedTags.value = normalizedTags.value.filter(tag => tag.id !== tagToRemove.id);
};


const eventDetails = ref({
    ...props.event,
  schedules: props.event.schedules || [],
  tags: props.event.tags?.map(tag => {
    // Handle both cases: when tag is an object or just an ID
    const tagObj = typeof tag === 'object' ? tag : props.tags.find(t => t.id === tag);
    return tagObj || { id: tag, name: 'Unknown Tag', color: '#cccccc' };
  }) || []
});

const normalizedTags = computed({
  get() {
    return eventDetails.value.tags;
  },
  set(newTags) {
    eventDetails.value.tags = newTags.map(tag => {
      if (typeof tag === 'object') return tag;
      return tags.value.find(t => t.id === tag) || { id: tag, name: 'Unknown Tag', color: '#cccccc' };
    });
  }
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

    // Prepare payload with consistent tag format
    const payload = {
    ...eventDetails.value,
    // Convert tags to just IDs for storage
    tags: eventDetails.value.tags.map(tag => tag.id),
    // Ensure other fields are properly formatted
    startDate: formattedStartDate.value,
    endDate: formattedEndDate.value,
    schedules: eventDetails.value.schedules.map(s => ({
      time: s.time.padStart(5, '0'),
      activity: s.activity
    }))
  };

  router.post(`/events/${eventDetails.value.id}/update`, payload, {
    preserveScroll: true,
    onFinish: () => {
      saving.value = false;
    },
    onSuccess: () => {
      showSuccessDialog.value = true;
      editMode.value = false;
      router.reload({ only: ['event'] });
    }
  });
};

const formatDisplayTime = (timeString) => {
  if (!timeString) return '';
  const parsed = parse(timeString, 'HH:mm', new Date());
  return format(parsed, 'hh:mm a'); // 04:00 PM
};


// Replace your existing date-related functions with these:

const formatDisplayDate = (dateString) => {
  if (!dateString) return '';
  try {
    // Try parsing as ISO format first (yyyy-MM-dd)
    let date = parseISO(dateString);
    if (!isValid(date)) {
      // If that fails, try parsing as MMM-dd-yyyy format
      date = parse(dateString, 'MMM-dd-yyyy', new Date());
    }
    return isValid(date) ? format(date, 'MMMM-dd-yyyy') : 'Invalid Date';
  } catch {
    return 'Invalid Date';
  }
};

const formatDateForInput = (dateString) => {
  if (!dateString) return '';
  try {
    // Try parsing as ISO format first (yyyy-MM-dd)
    let date = parseISO(dateString);
    if (!isValid(date)) {
      // If that fails, try parsing as MMM-dd-yyyy format
      date = parse(dateString, 'MMMM-dd-yyyy', new Date());
    }
    return isValid(date) ? format(date, 'yyyy-MM-dd') : '';
  } catch {
    return '';
  }
};

const formattedStartDate = computed({
  get() {
    return formatDateForInput(eventDetails.value.startDate);
  },
  set(value) {
    const date = parseISO(value);
    eventDetails.value.startDate = isValid(date)
      ? format(date, 'MMMM-dd-yyyy')
      : value;
  }
});

const formattedEndDate = computed({
  get() {
    return formatDateForInput(eventDetails.value.endDate);
  },
  set(value) {
    const date = parseISO(value);
    eventDetails.value.endDate = isValid(date)
      ? format(date, 'MMMM-dd-yyyy')
      : value;
  }
});


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

      <div v-if="eventDetails" class="bg-white shadow-md rounded-lg p-6 max-w-2xl w-full mt-6 flex flex-col space-y-4">
        <!-- Title -->
        <div class="flex justify-between items-center">
            <input
            v-if="editMode"
            v-model="eventDetails.title"
            class="text-xl font-bold border-b w-full"
            placeholder="Event Title"
            />
            <h1 v-else class="text-xl font-bold">{{ eventDetails.title }}</h1>

            <button
            @click="toggleEdit"
            class="px-4 py-2 rounded bg-gray-600 text-white hover:bg-gray-700 ml-4"
            >
            {{ editMode ? 'Cancel' : 'Edit Event' }}
            </button>
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
                v-model="normalizedTags"
                :options="tags"
                optionLabel="name"
                optionValue="id"
                display="chip"
                placeholder="Select tags"
                class="w-full"
                >

            <!-- Selected Chip Template -->
            <template #chip="slotProps">
                <div
                class="flex items-center gap-2 px-2 py-1 rounded text-white text-xs"
                :style="{ backgroundColor: slotProps.value.color }"
                >
                {{ slotProps.value.name }}
                <button
                    type="button"
                    class="text-white hover:text-gray-200"
                    @click.stop="removeTag(slotProps.value)"
                >
                    ✕
                </button>
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
            <input type="date" v-model="formattedStartDate" class="border p-2 rounded" />
            <input type="time" v-model="eventDetails.startTime" class="border p-2 rounded" />
            <input type="date" v-model="formattedEndDate" class="border p-2 rounded" />
            <input type="time" v-model="eventDetails.endTime" class="border p-2 rounded" />
          </template>
          <template v-else>
            <p><strong>Start:</strong> {{ formatDisplayDate(eventDetails.startDate) }}, {{ formatDisplayTime(eventDetails.startTime) }}</p>
            <p><strong>End:</strong> {{ formatDisplayDate(eventDetails.endDate) }}, {{ formatDisplayTime(eventDetails.endTime) }}</p>
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
            @click="showSaveConfirmDialog = true"
            class="mt-4 self-end bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded"
        >
            Save Changes
        </button>
      </div>
    </div>

    <!-- Confirm Save Changes Dialog -->
    <div v-if="showSaveConfirmDialog" class="fixed inset-0 bg-black bg-opacity-30 flex items-center justify-center z-50">
    <div class="bg-white p-6 rounded-lg shadow-lg max-w-sm w-full">
        <h2 class="text-lg font-semibold mb-2">Save Changes?</h2>
        <p class="text-sm text-gray-600 mb-4">Are you sure you want to save your changes?</p>
        <div class="flex justify-end gap-2">
        <button @click="showSaveConfirmDialog = false" class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">Cancel</button>
        <button
            @click="() => { showSaveConfirmDialog = false; saveChanges(); }"
            class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
        >
            Yes, Save
        </button>
        </div>
    </div>
    </div>

    <!-- Success Message Dialog -->
    <div v-if="showSuccessDialog" class="fixed inset-0 bg-black bg-opacity-30 flex items-center justify-center z-50">
    <div class="bg-white p-6 rounded-lg shadow-lg max-w-sm w-full">
        <h2 class="text-lg font-semibold text-green-700 mb-2">Success!</h2>
        <p class="text-sm text-gray-700 mb-4">The event was updated successfully.</p>
        <div class="flex justify-end">
        <button @click="showSuccessDialog = false" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">Close</button>
        </div>
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
