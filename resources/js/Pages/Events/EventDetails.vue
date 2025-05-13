<script setup>
import { ref, computed, onMounted } from 'vue';
import { parse, format, parseISO, isValid } from 'date-fns';
import { usePage, router } from '@inertiajs/vue3';

// Inertia props
const { props } = usePage();
//const categories = ref(props.categories || []);
const tags = ref(props.tags || []);
const saving = ref(false);
const showSaveConfirmDialog = ref(false);
const showSuccessDialog = ref(false);
const errorMessage = ref(null);
const showErrorDialog = ref(false);
const errorDialogMessage = ref('');
const committees = ref(props.committees || []);
const employees = ref(props.employees || []);
const filteredEmployees = ref([]);
const relatedEvents = ref(props.relatedEvents || []);
const showDeleteTaskConfirm = ref(false);
const taskToDelete = ref(null);
const showDeleteScheduleConfirm = ref(false);
const scheduleToDelete = ref(null);

// Create a map of tags for quick lookup
const tagsMap = computed(() => {
  return tags.value.reduce((map, tag) => {
    map[tag.id] = tag;
    return map;
  }, {});
});

const removeTag = (tagToRemove) => {
  normalizedTags.value = normalizedTags.value.filter(tag => tag.id !== tagToRemove.id);
};

const eventDetails = ref({
    ...props.event,
    venue: props.event.venue || '',
    schedules: props.event.schedules || [],
    tags: props.event.tags?.map(tag => {
      // Handle both cases: when tag is an object or just an ID
      if (typeof tag === 'object' && tag !== null) {
        return tag;
      }
      // If it's just an ID, find the corresponding tag object
      return tagsMap.value[tag] || { id: tag, name: 'Unknown Tag', color: '#cccccc' };
    }) || []
});

const normalizedTags = computed({
  get() {
    return eventDetails.value.tags;
  },
  set(newTags) {
    eventDetails.value.tags = newTags.map(tag => {
      if (typeof tag === 'object' && tag !== null) {
        return tag;
      }
      // If it's just an ID, find the corresponding tag object
      return tagsMap.value[tag] || { id: tag, name: 'Unknown Tag', color: '#cccccc' };
    });
  }
});

// Edit mode toggle
const editMode = ref(false);
const toggleEdit = () => {
  editMode.value = !editMode.value;
};

// Add this near your other initialization code
onMounted(() => {
  // Ensure tasks have proper committee/employee references
  if (eventDetails.value.tasks) {
    filteredEmployees.value = eventDetails.value.tasks.map(task => {
      // Find matching committee in committees list
      if (task.committee?.id) {
        task.committee = committees.value.find(c => c.id == task.committee.id) || task.committee;
      }

      // Find matching employee in employees list
      if (task.employee?.id) {
        task.employee = employees.value.find(e => e.id == task.employee.id) || task.employee;
      }

      // Return filtered employees for this task
      return task.committee?.id
        ? employees.value.filter(e => e.committeeId == task.committee.id)
        : [];
    });
  } else {
    eventDetails.value.tasks = [];
    filteredEmployees.value = [];
  }
});

if (!eventDetails.value.tasks) {
  eventDetails.value.tasks = [];
}

const updateFilteredEmployees = (index) => {
  const task = eventDetails.value.tasks[index];
  if (task.committee) {
    task.committee = committees.value.find(c => c.id == task.committee.id) || task.committee;
    filteredEmployees.value[index] = employees.value.filter(emp =>
      emp.committeeId == task.committee.id
    );
  } else {
    filteredEmployees.value[index] = [];
  }
  // Reset employee selection when committee changes
  task.employee = null;
};

// Methods
const addCommitteeTask = () => {
  eventDetails.value.tasks.push({
    committee: null,
    employee: null,
    task: ''
  });
  filteredEmployees.value.push([]);
};

const promptDeleteTask = (index) => {
  taskToDelete.value = index;
  showDeleteTaskConfirm.value = true;
};

const confirmDeleteTask = () => {
  eventDetails.value.tasks.splice(taskToDelete.value, 1);
  filteredEmployees.value.splice(taskToDelete.value, 1);
  showDeleteTaskConfirm.value = false;
  taskToDelete.value = null;
};

const addSchedule = () => {
  eventDetails.value.schedules.push({ time: '', activity: '' });
};
const promptDeleteSchedule = (index) => {
  scheduleToDelete.value = index;
  showDeleteScheduleConfirm.value = true;
};

const confirmDeleteSchedule = () => {
  eventDetails.value.schedules.splice(scheduleToDelete.value, 1);
  showDeleteScheduleConfirm.value = false;
  scheduleToDelete.value = null;
};

const saveChanges = () => {
  saving.value = true;
  errorMessage.value = null;
  showErrorDialog.value = false;

  // Validate dates and times
  if (!validateDates() || !validateTimes()) {
    saving.value = false;
    errorMessage.value = !validateDates()
      ? "End date cannot be earlier than start date"
      : "End time must be after start time";
    showErrorDialog.value = true;
    errorDialogMessage.value = errorMessage.value;
    return;
  }

  // Create clean payload without Vue internals
  const payload = {
    id: eventDetails.value.id,
    title: eventDetails.value.title,
    description: eventDetails.value.description,
    venue: eventDetails.value.venue,
    startDate: eventDetails.value.startDate,
    endDate: eventDetails.value.endDate,
    startTime: eventDetails.value.startTime,
    endTime: eventDetails.value.endTime,
    category_id: eventDetails.value.category?.id || null,
    // Only send tag IDs to the database
    tags: eventDetails.value.tags.map(tag => tag.id),
    schedules: eventDetails.value.schedules.map(s => ({
      time: s.time.padStart(5, '0'),
      activity: s.activity
    })),
    tasks: eventDetails.value.tasks.map(task => ({
      committee: task.committee ? { id: task.committee.id } : null,
      employee: task.employee ? { id: task.employee.id } : null,
      task: task.task
    }))
  };

  // Debug: log the payload before sending
  console.log('Sending payload:', payload);

  router.post(`/events/${eventDetails.value.id}/update`, payload, {
    preserveScroll: true,
    onFinish: () => saving.value = false,
    onError: (errors) => {
      saving.value = false;
      errorMessage.value = errors.message || 'Failed to save event';
      showErrorDialog.value = true;
      errorDialogMessage.value = JSON.stringify(errors, null, 2);
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


// Date-related functions

const validateDates = () => {
  try {
    const start = parse(eventDetails.value.startDate, 'MMM-dd-yyyy', new Date());
    const end = parse(eventDetails.value.endDate, 'MMM-dd-yyyy', new Date());
    return isValid(start) && isValid(end) && start <= end;
  } catch {
    return false;
  }
};

const validateTimes = () => {
  if (!eventDetails.value.startTime || !eventDetails.value.endTime) return true;
  return eventDetails.value.startTime < eventDetails.value.endTime;
};

const formatDisplayDate = (dateString) => {
  if (!dateString) return '';
  try {
    // Try parsing as ISO format first (yyyy-MM-dd)
    let date = parseISO(dateString);
    if (!isValid(date)) {
      // If that fails, try parsing as MMM-dd-yyyy format
      date = parse(dateString, 'MMM-dd-yyyy', new Date());
    }
    return isValid(date) ? format(date, 'MMM-dd-yyyy') : 'Invalid Date';
  } catch {
    return 'Invalid Date';
  }
};

const formatDateForPicker = (dateString) => {
  if (!dateString) return null;
  try {
    // Try parsing as ISO format first (yyyy-MM-dd)
    let date = parseISO(dateString);
    if (!isValid(date)) {
      // If that fails, try parsing as MMM-dd-yyyy format
      date = parse(dateString, 'MMM-dd-yyyy', new Date());
    }
    return isValid(date) ? date : null;
  } catch {
    return null;
  }
};

const startDateModel = computed({
  get() {
    const date = parse(eventDetails.value.startDate, 'MMM-dd-yyyy', new Date());
    return isValid(date) ? date : null;
  },
  set(value) {
    eventDetails.value.startDate = value ? format(value, 'MMM-dd-yyyy') : '';
  }
});

const endDateModel = computed({
  get() {
    const date = parse(eventDetails.value.endDate, 'MMM-dd-yyyy', new Date());
    return isValid(date) ? date : null;
  },
  set(value) {
    eventDetails.value.endDate = value ? format(value, 'MMM-dd-yyyy') : '';
  }
});

const formattedStartDate = computed(() => {
  return startDateModel.value ? format(startDateModel.value, 'MMM-dd-yyyy') : '';
});

const formattedEndDate = computed(() => {
  return endDateModel.value ? format(endDateModel.value, 'MMM-dd-yyyy') : '';
});

// Normalize related events tags
const normalizedRelatedEvents = computed(() => {
  return relatedEvents.value.map(event => ({
    ...event,
    tags: (event.tags || []).map(tag => {
      if (typeof tag === 'object' && tag !== null) {
        return tag;
      }
      return tagsMap.value[tag] || { id: tag, name: 'Unknown Tag', color: '#cccccc' };
    })
  }));
});
</script>

<template>
    <div class="min-h-screen bg-gray-200 py-8 px-4">
      <!-- Main container with flex layout -->
      <div class="max-w-6xl mx-auto flex flex-col md:flex-row gap-6">
        <!-- Main content (left side) - takes remaining space -->
        <div class="flex-1">
          <!-- Banner Image -->
          <div class="w-full bg-white rounded-lg shadow-md overflow-hidden">
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

          <!-- Event Details -->
          <div v-if="eventDetails" class="bg-white shadow-md rounded-lg p-6 w-full mt-6 flex flex-col space-y-4">
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

            <!-- Venue -->
            <div>
              <input
                v-if="editMode"
                v-model="eventDetails.venue"
                class="w-full border rounded p-2"
                placeholder="Enter venue (e.g., Conference Room A)"
              />
              <p v-else>
                <strong>Venue:</strong> {{ eventDetails.venue || 'Not specified' }}
              </p>
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
                        v-tooltip.top="'Remove Tag'"
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
                <!-- Start Date -->
                <div class="flex flex-col">
                  <label class="text-sm font-medium mb-1">Start Date</label>
                  <DatePicker
                    v-model="startDateModel"
                    dateFormat="MM-dd-yy"
                    showIcon
                    class="w-full"
                  />
                </div>

                <!-- Start Time -->
                <div class="flex flex-col">
                  <label class="text-sm font-medium mb-1">Start Time</label>
                  <input
                    type="time"
                    v-model="eventDetails.startTime"
                    class="border p-2 rounded"
                  />
                </div>

                <!-- End Date -->
                <div class="flex flex-col">
                  <label class="text-sm font-medium mb-1">End Date</label>
                  <DatePicker
                    v-model="endDateModel"
                    dateFormat="MM-dd-yy"
                    showIcon
                    class="w-full"
                  />
                </div>

                <!-- End Time -->
                <div class="flex flex-col">
                  <label class="text-sm font-medium mb-1">End Time</label>
                  <input
                    type="time"
                    v-model="eventDetails.endTime"
                    class="border p-2 rounded"
                  />
                </div>
              </template>
              <template v-else>
                <p><strong>Start Date:</strong> {{ formatDisplayDate(eventDetails.startDate) }}</p>
                <p><strong>Start Time:</strong> {{ formatDisplayTime(eventDetails.startTime) }}</p>
                <p><strong>End Date:</strong> {{ formatDisplayDate(eventDetails.endDate) }}</p>
                <p><strong>End Time:</strong> {{ formatDisplayTime(eventDetails.endTime) }}</p>
              </template>
            </div>

            <!-- Error Message -->
            <div v-if="errorMessage" class="text-red-500 text-sm mt-2">
              {{ errorMessage }}
            </div>

            <!-- Committee -->
  <div>
    <h2 class="font-semibold mb-1">Committee:</h2>
    <div v-if="editMode">
      <div v-for="(taskItem, index) in eventDetails.tasks" :key="index" class="space-y-2 mb-4 p-3 border rounded">
        <!-- Committee Selection -->
        <div class="p-field">
          <label class="block text-sm font-medium mb-1">Committee</label>
          <Select
  v-model="taskItem.committee"
  :options="committees"
  optionLabel="name"
  placeholder="Select Committee"
  class="w-full"
  @change="updateFilteredEmployees(index)"
>
  <template #option="slotProps">
    <div>{{ slotProps.option.name }}</div>
  </template>
  <template #value="slotProps">
    <div v-if="slotProps.value">{{ slotProps.value.name }}</div>
    <span v-else>{{ slotProps.placeholder }}</span>
  </template>
</Select>
        </div>

        <!-- Employee Selection (only shows if committee is selected) -->
        <div v-if="taskItem.committee" class="p-field">
          <label class="block text-sm font-medium mb-1">Employee</label>
          <Select
  v-model="taskItem.employee"
  :options="filteredEmployees[index]"
  optionLabel="name"
  placeholder="Select Employee"
  class="w-full"
  :disabled="!taskItem.committee"
>
  <template #option="slotProps">
    <div>{{ slotProps.option.name }}</div>
  </template>
  <template #value="slotProps">
    <div v-if="slotProps.value">{{ slotProps.value.name }}</div>
    <span v-else>{{ slotProps.placeholder }}</span>
  </template>
</Select>
        </div>

        <!-- Task Description -->
        <div class="p-field">
          <label class="block text-sm font-medium mb-1">Task</label>
          <input
            v-model="taskItem.task"
            type="text"
            placeholder="Enter task details"
            class="w-full border rounded p-2"
          />
        </div>

        <!-- Remove Task Button -->
        <button
          @click="promptDeleteTask(index)"
          class="text-red-500 hover:text-red-700 text-sm flex items-center"
        >
          <i class="pi pi-trash mr-1"></i> Remove Task
        </button>
      </div>

      <button
        @click="addCommitteeTask"
        class="text-blue-500 hover:text-blue-700 text-sm flex items-center mt-2"
      >
        <i class="pi pi-plus mr-1"></i> Add Committee Task
      </button>
    </div>
    <div v-else class="space-y-1 pl-4 text-sm">
      <p v-for="(taskItem, index) in eventDetails.tasks" :key="index">
        {{ taskItem.employee?.name || 'No employee selected' }}
        ({{ taskItem.committee?.name || 'No committee selected' }}):
        {{ taskItem.task || 'No task specified' }}
      </p>
    </div>
  </div>

            <!-- Schedules -->
            <div>
              <h2 class="font-semibold mb-1">Event Schedules</h2>
              <div v-if="editMode">
                <div v-for="(schedule, i) in eventDetails.schedules" :key="i" class="flex items-center gap-2 mb-2">
                  <input v-model="schedule.time" type="time" class="border p-1 rounded w-32" />
                  <input v-model="schedule.activity" type="text" placeholder="Activity" class="border p-1 rounded flex-1" />
                  <button @click="promptDeleteSchedule(i)" class="text-red-500">✕</button>
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

        <!-- Related events sidebar (right side) - fixed width -->
        <div class="md:w-80 flex-shrink-0">
          <div class="bg-white shadow-md rounded-lg p-4 sticky top-4">
            <h3 class="font-bold text-lg mb-4">Related Events</h3>

            <div v-if="normalizedRelatedEvents.length > 0" class="space-y-4">
              <div
                v-for="event in normalizedRelatedEvents"
                :key="event.id"
                class="cursor-pointer hover:bg-gray-50 rounded p-2 transition-colors"
                @click="router.visit(`/events/${event.id}`)"
              >
                <div class="flex gap-3">
                  <div class="w-24 h-16 flex-shrink-0">
                    <img
                      :src="event.image || '/placeholder-event.jpg'"
                      :alt="event.title"
                      class="w-full h-full object-cover rounded"
                    />
                  </div>
                  <div class="flex-1 min-w-0">
                    <h4 class="font-medium text-sm truncate">{{ event.title }}</h4>
                    <p class="text-xs text-gray-500 mt-1">
                      {{ formatDisplayDate(event.startDate) }}
                    </p>
                    <div class="flex flex-wrap gap-1 mt-1">
                      <span
                        v-for="tag in event.tags?.slice(0, 2)"
                        :key="tag.id"
                        class="text-xs px-1.5 py-0.5 rounded"
                        :style="{ backgroundColor: tag.color, color: '#fff' }"
                      >
                        {{ tag.name }}
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <p v-else class="text-gray-500 text-sm">
              No related events found
            </p>
          </div>
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

      <!-- Error Dialog -->
      <Dialog
        v-model:visible="showErrorDialog"
        modal
        header="Error"
        :style="{ width: '400px' }"
      >
        <div class="flex items-center gap-3">
          <i class="pi pi-exclamation-triangle text-red-500 text-2xl"></i>
          <span>{{ errorDialogMessage }}</span>
        </div>
        <template #footer>
          <Button
            label="OK"
            icon="pi pi-check"
            @click="showErrorDialog = false"
            class="p-button-text"
          />
        </template>
      </Dialog>
    </div>

    <!-- Delete Task Confirmation Dialog -->
<div v-if="showDeleteTaskConfirm" class="fixed inset-0 bg-black bg-opacity-30 flex items-center justify-center z-50">
  <div class="bg-white p-6 rounded-lg shadow-lg max-w-sm w-full">
    <h2 class="text-lg font-semibold mb-2">Delete Task?</h2>
    <p class="text-sm text-gray-600 mb-4">Are you sure you want to delete this task?</p>
    <div class="flex justify-end gap-2">
      <button @click="showDeleteTaskConfirm = false" class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">Cancel</button>
      <button
        @click="confirmDeleteTask"
        class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700"
      >
        Yes
      </button>
    </div>
  </div>
</div>

<!-- Delete Schedule Confirmation Dialog -->
<div v-if="showDeleteScheduleConfirm" class="fixed inset-0 bg-black bg-opacity-30 flex items-center justify-center z-50">
  <div class="bg-white p-6 rounded-lg shadow-lg max-w-sm w-full">
    <h2 class="text-lg font-semibold mb-2">Delete Schedule?</h2>
    <p class="text-sm text-gray-600 mb-4">Are you sure you want to delete this schedule item?</p>
    <div class="flex justify-end gap-2">
      <button @click="showDeleteScheduleConfirm = false" class="px-4 py-2 bg-gray-300 rounded hover:bg-gray-400">Cancel</button>
      <button
        @click="confirmDeleteSchedule"
        class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700"
      >
        Yes
      </button>
    </div>
  </div>
</div>
  </template>
