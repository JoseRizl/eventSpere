<script setup>
import { ref, computed, onMounted, nextTick, watch } from 'vue';
import { parse, format, parseISO, isValid, addDays } from 'date-fns';
import { usePage, router, Link } from '@inertiajs/vue3';
import axios from 'axios';
import LoadingSpinner from '@/Components/LoadingSpinner.vue';
import ConfirmationDialog from '@/Components/ConfirmationDialog.vue';
import SuccessDialog from '@/Components/SuccessDialog.vue';
import { useBracketState } from '@/composables/useBracketState.js';
import { useBracketActions } from '@/composables/useBracketActions.js';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import InputSwitch from 'primevue/inputswitch';
import Button from 'primevue/button';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';

// Inertia props
const { props } = usePage();
const user = computed(() => props.auth.user);
const currentView = ref('details'); // 'details' or 'announcements'
const bannerImageInput = ref(null);
const tags = ref(props.tags || []);
const saving = ref(false);
const showSaveConfirmDialog = ref(false);
const successMessage = ref('');
const showSuccessDialog = ref(false);
const errorMessage = ref(null);
const showErrorDialog = ref(false);
const errorDialogMessage = ref('');
const categories = ref(props.categories || []);
const committees = ref(props.committees || []);
const employees = ref(props.employees || []);
const filteredEmployees = ref([]);
const relatedEvents = ref(props.relatedEvents || []);
const showDeleteTaskConfirm = ref(false);
const taskToDelete = ref(null);
const showDeleteScheduleConfirm = ref(false);
const scheduleToDelete = ref(null);

// Event-specific announcements
const eventAnnouncements = ref([]);
const showAddAnnouncementModal = ref(false);
const newAnnouncement = ref({
  message: '',
  image: null,
  imagePreview: null
});
const showDeleteAnnouncementConfirm = ref(false);
const announcementToDelete = ref(null);

// Bracket logic
const bracketState = useBracketState();
const {
  brackets,
  expandedBrackets,
  showWinnerDialog,
  winnerMessage,
  showRoundRobinMatchDialog,
  selectedRoundRobinMatchData,
  showMatchUpdateConfirmDialog,
  roundRobinScoring,
  showScoringConfigDialog,
  standingsRevision,
} = bracketState;

const {
  fetchBrackets,
  handleByeRounds,
  updateLines,
  isFinalRound,
  isSemifinalRound,
  isQuarterfinalRound,
  getRoundRobinStandings,
  isRoundRobinConcluded,
  openMatchDialog,
  openRoundRobinMatchDialog,
  closeRoundRobinMatchDialog,
  confirmMatchUpdate,
  cancelMatchUpdate,
  proceedWithMatchUpdate,
  openScoringConfigDialog,
  closeScoringConfigDialog,
  saveScoringConfig,
} = useBracketActions(bracketState);

const relatedBrackets = computed(() => {
  if (!brackets.value || !props.event) {
    return [];
  }
  return brackets.value.filter(bracket => bracket.event_id === props.event.id);
});

const truncateNameElimination = (name) => {
  if (!name) return 'TBD';
  return name.length > 13 ? name.substring(0, 13) + '...' : name;
};

const truncateNameRoundRobin = (name) => {
  if (!name) return 'TBD';
  return name.length > 15 ? name.substring(0, 15) + '...' : name;
};

// Create a map of tags for quick lookup
const tagsMap = computed(() => {
  return tags.value.reduce((map, tag) => {
    map[tag.id] = tag;
    return map;
  }, {});
});

const categoryMap = computed(() => {
  return categories.value.reduce((map, category) => {
    map[category.id] = category.title;
    return map;
  }, {});
});

const removeTag = (tagToRemove) => {
  normalizedTags.value = normalizedTags.value.filter(tag => tag.id !== tagToRemove.id);
};

const eventDetails = ref({
    ...props.event,
    venue: props.event.venue || '',
    category_id: props.event.category?.id || props.event.category_id,
    scheduleLists: props.event.scheduleLists || [{
      day: 1,
      date: props.event.startDate,
      schedules: props.event.schedules || []
    }],
    tags: props.event.tags?.map(tag => {
      // Handle both cases: when tag is an object or just an ID
      if (typeof tag === 'object' && tag !== null) {
        return tag;
      }
      // If it's just an ID, find the corresponding tag object
      return tagsMap.value[tag] || { id: tag, name: 'Unknown Tag', color: '#cccccc' };
    }) || [],
    tasks: props.event.tasks?.map(task => {
      // Handle committee
      let committee = null;
      if (task.committee) {
        if (typeof task.committee === 'object') {
          if (task.committee.name) {
            committee = task.committee;
          } else {
            const foundCommittee = committees.value.find(c => c.id == task.committee.id);
            committee = foundCommittee || { id: task.committee.id, name: 'Unknown Committee' };
          }
        } else {
          const foundCommittee = committees.value.find(c => c.id == task.committee);
          committee = foundCommittee || { id: task.committee, name: 'Unknown Committee' };
        }
      }

      // Handle employees array
      let employees = [];
      if (task.employees) {
        employees = task.employees.map(emp => {
          if (typeof emp === 'object') {
            if (emp.name) {
              return emp;
            }
            const foundEmployee = employees.value.find(e => e.id == emp.id);
            return foundEmployee || { id: emp.id, name: 'Unknown Employee' };
          }
          const foundEmployee = employees.value.find(e => e.id == emp);
          return foundEmployee || { id: emp, name: 'Unknown Employee' };
        });
      } else if (task.employee) {
        // Handle legacy single employee format
        if (typeof task.employee === 'object') {
          if (task.employee.name) {
            employees = [task.employee];
          } else {
            const foundEmployee = employees.value.find(e => e.id == task.employee.id);
            employees = [foundEmployee || { id: task.employee.id, name: 'Unknown Employee' }];
          }
        } else {
          const foundEmployee = employees.value.find(e => e.id == task.employee);
          employees = [foundEmployee || { id: task.employee, name: 'Unknown Employee' }];
        }
      }

      return {
        ...task,
        committee,
        employees
      };
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
  const urlParams = new URLSearchParams(window.location.search);
  const viewParam = urlParams.get('view');
  if (viewParam === 'announcements') {
    currentView.value = 'announcements';
  }

  // Ensure tasks have proper committee/employee references
  fetchEventAnnouncements();
  fetchBrackets();
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

watch(relatedBrackets, (newBrackets) => {
  newBrackets.forEach(bracket => {
    const originalIndex = brackets.value.findIndex(b => b.id === bracket.id);
    if (originalIndex !== -1) {
      expandedBrackets.value[originalIndex] = true; // Always show
      handleByeRounds(originalIndex);
      nextTick(() => {
        updateLines(originalIndex);
      });
    }
  });
});

const goBack = () => {
    window.history.back();
};

const fetchEventAnnouncements = async () => {
  if (!props.event?.id) return;
  try {
    const response = await axios.get(`http://localhost:3000/event_announcements?eventId=${props.event.id}`);
    eventAnnouncements.value = response.data.sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp));
  } catch (error) {
    console.error('Error fetching event announcements:', error);
  }
};

const openAddAnnouncementModal = () => {
  newAnnouncement.value = { message: '', image: null, imagePreview: null };
  showAddAnnouncementModal.value = true;
};

const handleAnnouncementImageUpload = (event) => {
  const file = event.target.files[0];
  if (file) {
    newAnnouncement.value.image = file;
    newAnnouncement.value.imagePreview = URL.createObjectURL(file);
  }
};

const toBase64 = file => new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.readAsDataURL(file);
    reader.onload = () => resolve(reader.result);
    reader.onerror = error => reject(error);
});

const handleBannerImageUpload = async (event) => {
  const file = event.target.files[0];
  if (file) {
    eventDetails.value.image = await toBase64(file);
  }
};

const addAnnouncement = async () => {
  if (!newAnnouncement.value.message.trim()) {
    return;
  }

  saving.value = true;

  let imageUrl = null;
  if (newAnnouncement.value.image) {
    imageUrl = await toBase64(newAnnouncement.value.image);
  }

  const payload = {
    eventId: props.event.id,
    message: newAnnouncement.value.message,
    timestamp: new Date().toISOString(),
    image: imageUrl,
  };

  try {
    const response = await axios.post('http://localhost:3000/event_announcements', payload);
    eventAnnouncements.value.unshift(response.data);
    showAddAnnouncementModal.value = false;
    successMessage.value = 'Announcement posted successfully!';
    showSuccessDialog.value = true;
  } catch (error) {
    console.error('Error posting announcement:', error);
    errorMessage.value = 'Failed to post announcement.';
    showErrorDialog.value = true;
  } finally {
    saving.value = false;
  }
};

const promptDeleteAnnouncement = (announcement) => {
  announcementToDelete.value = announcement;
  showDeleteAnnouncementConfirm.value = true;
};

const confirmDeleteAnnouncement = async () => {
  if (!announcementToDelete.value) return;
  saving.value = true;
  try {
    await axios.delete(`http://localhost:3000/event_announcements/${announcementToDelete.value.id}`);
    eventAnnouncements.value = eventAnnouncements.value.filter(a => a.id !== announcementToDelete.value.id);
    successMessage.value = 'Announcement deleted.';
    showSuccessDialog.value = true;
  } catch (error) {
    console.error('Error deleting announcement:', error);
    errorMessage.value = 'Failed to delete announcement.';
    showErrorDialog.value = true;
  } finally {
    saving.value = false;
    showDeleteAnnouncementConfirm.value = false;
    announcementToDelete.value = null;
  }
};

const formatAnnouncementTimestamp = (timestamp) => {
  if (!timestamp) return '';
  return format(parseISO(timestamp), 'MMMM dd, yyyy HH:mm');
};

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
  // Reset employees when committee changes
  task.employees = [];
};

const removeEmployee = (taskIndex, employeeToRemove) => {
  eventDetails.value.tasks[taskIndex].employees = eventDetails.value.tasks[taskIndex].employees.filter(
    emp => emp.id !== employeeToRemove.id
  );
};

// Methods
const addCommitteeTask = () => {
  eventDetails.value.tasks.push({
    committee: null,
    employees: [],
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

const totalDays = computed(() => {
  if (!eventDetails.value.startDate || !eventDetails.value.endDate) return 1;
  const start = parse(eventDetails.value.startDate, 'MMM-dd-yyyy', new Date());
  const end = parse(eventDetails.value.endDate, 'MMM-dd-yyyy', new Date());
  const diffTime = Math.abs(end - start);
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
  return diffDays + 1; // Include both start and end dates
});

const currentScheduleList = ref(0);

const addScheduleList = () => {
  if (eventDetails.value.scheduleLists.length >= totalDays.value) return;

  const nextDay = eventDetails.value.scheduleLists.length + 1;
  const startDate = parse(eventDetails.value.startDate, 'MMM-dd-yyyy', new Date());
  const nextDate = addDays(startDate, nextDay - 1);

  eventDetails.value.scheduleLists.push({
    day: nextDay,
    date: format(nextDate, 'MMM-dd-yyyy'),
    schedules: []
  });
};

const removeScheduleList = (index) => {
  if (eventDetails.value.scheduleLists.length <= 1) return;
  eventDetails.value.scheduleLists.splice(index, 1);
  if (currentScheduleList.value >= eventDetails.value.scheduleLists.length) {
    currentScheduleList.value = eventDetails.value.scheduleLists.length - 1;
  }
};

const addSchedule = () => {
  eventDetails.value.scheduleLists[currentScheduleList.value].schedules.push({ time: '', activity: '' });
};

const promptDeleteSchedule = (index) => {
  scheduleToDelete.value = index;
  showDeleteScheduleConfirm.value = true;
};

const confirmDeleteSchedule = () => {
  eventDetails.value.scheduleLists[currentScheduleList.value].schedules.splice(scheduleToDelete.value, 1);
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
    image: eventDetails.value.image,
    venue: eventDetails.value.venue,
    category_id: eventDetails.value.category_id,
    startDate: eventDetails.value.startDate,
    endDate: eventDetails.value.endDate,
    startTime: eventDetails.value.startTime,
    endTime: eventDetails.value.endTime,
    tags: eventDetails.value.tags.map(tag => tag.id),
    scheduleLists: eventDetails.value.scheduleLists.map(list => ({
      day: list.day,
      date: list.date,
      schedules: list.schedules.map(s => ({
        time: s.time.padStart(5, '0'),
        activity: s.activity
      }))
    })),
    tasks: eventDetails.value.tasks.map(task => ({
      committee: task.committee ? { id: task.committee.id } : null,
      employees: task.employees.map(emp => ({ id: emp.id })),
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
      successMessage.value = 'The event was updated successfully.';
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

const getBracketIndex = (bracketId) => {
    return brackets.value.findIndex(b => b.id === bracketId);
}
</script>

<template>
    <div class="min-h-screen bg-gray-200 py-8 px-4">
        <div class="max-w-6xl mx-auto mt-4">
            <!-- Back Button for non-management -->
            <div v-if="!user || !['Admin', 'Principal', 'SportsManager'].includes(user.name)" class="mb-4">
                <Button icon="pi pi-arrow-left" label="Back" @click="goBack" class="p-button-text" />
            </div>

            <!-- Banner Image -->
            <div class="w-full bg-gray-700 rounded-lg shadow-md overflow-hidden relative">
                <template v-if="eventDetails?.image">
                    <!-- Blurred Background -->
                    <img
                        :src="eventDetails.image"
                        :alt="`${eventDetails.title} background`"
                        class="absolute inset-0 w-full h-64 object-cover filter blur-lg scale-110"
                    />
                    <!-- Foreground Image -->
                    <img
                        :src="eventDetails.image"
                        :alt="eventDetails.title"
                        class="relative w-full h-64 object-contain"
                    />
                </template>
                <div v-else class="w-full h-64 bg-gray-300 flex items-center justify-center">
                    <span class="text-gray-500 text-lg">No Image Available</span>
                </div>
                <div v-if="editMode" class="absolute inset-0 bg-black bg-opacity-50 flex items-center justify-center">
                    <input type="file" @change="handleBannerImageUpload" accept="image/*" class="hidden" ref="bannerImageInput" />
                    <Button label="Change Image" icon="pi pi-upload" @click="$refs.bannerImageInput.click()" />
                </div>
            </div>

        </div>

        <div class="max-w-6xl mx-auto mt-4">
            <div class="flex flex-col md:flex-row gap-6">
                <!-- Main content (left side) - takes remaining space -->
                <div class="flex-1">
                    <!-- View Toggle -->
                    <div class="w-full mb-4">
                        <div class="flex border-b">
                            <button @click="currentView = 'details'" :class="['px-4 py-2 font-semibold transition-colors duration-200 focus:outline-none', currentView === 'details' ? 'border-b-2 border-[#0077B3] text-[#0077B3]' : 'text-gray-500 hover:text-[#0077B3]']">
                                Event Details
                            </button>
                            <button @click="currentView = 'announcements'" :class="['px-4 py-2 font-semibold transition-colors duration-200 focus:outline-none', currentView === 'announcements' ? 'border-b-2 border-[#0077B3] text-[#0077B3]' : 'text-gray-500 hover:text-[#0077B3]']">
                                Announcements
                            </button>
                        </div>
                    </div>

                    <div v-if="currentView === 'details'">
                        <!-- Event Details -->
                        <div v-if="eventDetails" class="bg-white shadow-md rounded-lg p-6 w-full flex flex-col space-y-4">
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
                            v-if="user?.name === 'Admin'"
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

                    <!-- Venue and Category -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Venue -->
                        <div>
                            <template v-if="editMode">
                                <label class="text-sm font-medium mb-1">Venue</label>
                                <InputText
                                    v-model="eventDetails.venue"
                                    class="w-full"
                                    placeholder="Enter event venue (e.g., Conference Room A)"
                                />
                            </template>
                            <p v-else>
                                <strong>Venue:</strong> {{ eventDetails.venue || 'Not specified' }}
                            </p>
                        </div>
                        <!-- Category -->
                        <div>
                            <template v-if="editMode">
                                <label class="text-sm font-medium mb-1">Category</label>
                                <Select v-model="eventDetails.category_id"
                                    :options="categories"
                                    optionLabel="title"
                                    optionValue="id"
                                    placeholder="Select a category"
                                    class="w-full"
                                />
                            </template>
                            <p v-else><strong>Category:</strong> {{ categoryMap[eventDetails.category_id] || 'Uncategorized' }}</p>
                        </div>
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
                <label class="block text-sm font-medium mb-1">Employees</label>
                <MultiSelect
                    v-model="taskItem.employees"
                    :options="filteredEmployees[index]"
                    optionLabel="name"
                    placeholder="Select Employees"
                    display="chip"
                    filter
                >
                    <template #chip="slotProps">
                    <div class="flex items-center gap-2 px-2 py-1 rounded bg-blue-100 text-blue-800 text-xs">
                        {{ slotProps.value.name }}
                        <button
                        type="button"
                        class="text-blue-600 hover:text-blue-800"
                        @click.stop="removeEmployee(index, slotProps.value)"
                        v-tooltip.top="'Remove Employee'"
                        >
                        ✕
                        </button>
                    </div>
                    </template>
                </MultiSelect>
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
                {{ taskItem.employees?.map(e => e.name).join(', ') || 'No employees selected' }}
                ({{ taskItem.committee?.name || 'No committee selected' }}):
                {{ taskItem.task || 'No task specified' }}
            </p>
            </div>
        </div>

                    <!-- Schedules -->
                    <div>
                    <h2 class="font-semibold mb-1">Event Schedules</h2>
                    <div v-if="editMode">
                        <!-- Schedule List Navigation -->
                        <div class="flex items-center gap-4 mb-4">
                        <div class="flex-1 flex gap-2 overflow-x-auto pb-2">
                            <button
                            v-for="(list, index) in eventDetails.scheduleLists"
                            :key="index"
                            @click="currentScheduleList = index"
                            class="px-4 py-2 rounded whitespace-nowrap"
                            :class="currentScheduleList === index ? 'bg-blue-600 text-white' : 'bg-gray-200 hover:bg-gray-300'"
                            >
                            Day {{ list.day }} ({{ formatDisplayDate(list.date) }})
                            </button>
                        </div>
                        <div class="flex gap-2">
                            <button
                            @click="addScheduleList"
                            :disabled="eventDetails.scheduleLists.length >= totalDays"
                            class="px-3 py-2 rounded bg-green-600 text-white hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed"
                            :title="eventDetails.scheduleLists.length >= totalDays ? 'Maximum number of days reached' : 'Add new day'"
                            >
                            + Add Day
                            </button>
                            <button
                            v-if="eventDetails.scheduleLists.length > 1"
                            @click="removeScheduleList(currentScheduleList)"
                            class="px-3 py-2 rounded bg-red-600 text-white hover:bg-red-700"
                            >
                            Remove Day
                            </button>
                        </div>
                        </div>

                        <!-- Current Day's Schedules -->
                        <div class="border rounded-lg p-4">
                        <h3 class="font-medium mb-3">Day {{ eventDetails.scheduleLists[currentScheduleList].day }} Schedule</h3>
                        <div v-for="(schedule, i) in eventDetails.scheduleLists[currentScheduleList].schedules" :key="i" class="flex items-center gap-2 mb-2">
                            <input v-model="schedule.time" type="time" class="border p-1 rounded w-32" />
                            <input v-model="schedule.activity" type="text" placeholder="Activity" class="border p-1 rounded flex-1" />
                            <button @click="promptDeleteSchedule(i)" class="text-red-500">✕</button>
                        </div>
                        <button @click="addSchedule" class="text-blue-500 text-sm mt-2">+ Add Schedule</button>
                        </div>
                    </div>
                    <div v-else>
                        <!-- View Mode -->
                        <div class="space-y-4">
                        <div v-for="(list, listIndex) in eventDetails.scheduleLists" :key="listIndex" class="border rounded-lg p-4">
                            <h3 class="font-medium mb-2">Day {{ list.day }} ({{ formatDisplayDate(list.date) }})</h3>
                            <ul class="list-disc pl-5 space-y-1 text-sm text-gray-700">
                            <li v-for="(schedule, i) in list.schedules" :key="i">
                                {{ schedule.time }} - {{ schedule.activity }}
                            </li>
                            <li v-if="list.schedules.length === 0" class="text-gray-500 italic">
                                No schedules for this day
                            </li>
                            </ul>
                        </div>
                        </div>
                    </div>
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
                    <div v-if="currentView === 'announcements'">
                        <div class="w-full bg-white rounded-lg shadow-md p-6">
      <div class="flex justify-between items-center mb-4">
        <h2 class="text-xl font-bold">Event Announcements</h2>
        <Button
          v-if="user?.name === 'Admin'"
          label="Add Announcement"
          icon="pi pi-plus"
          class="p-button-sm"
          @click="openAddAnnouncementModal"
        />
      </div>

      <div v-if="eventAnnouncements.length > 0" class="space-y-6">
        <div
          v-for="announcement in eventAnnouncements"
          :key="announcement.id"
          class="p-4 bg-gray-50 rounded-lg shadow-sm border border-gray-200 relative"
        >
          <!-- wrapper guarantees reliable positioning despite internal Button padding -->
          <div class="absolute top-2 right-2 z-10">
            <Button
              v-if="user?.name === 'Admin'"
              icon="pi pi-trash"
              class="p-button-text p-button-danger p-button-icon-only !p-2"
              @click="promptDeleteAnnouncement(announcement)"
              aria-label="Delete announcement"
            />
          </div>

          <!-- User Avatar and Name -->
          <div class="flex items-center mb-2">
            <Avatar
              image="https://primefaces.org/cdn/primevue/images/avatar/amyelsner.png"
              class="mr-2"
              shape="circle"
              size="small"
            />
            <span class="text-gray-600 text-sm font-semibold">{{user?.name}}</span>
          </div>

          <p class="text-gray-800 text-base whitespace-pre-line">{{ announcement.message }}</p>

          <img
            v-if="announcement.image"
            :src="announcement.image"
            alt="Announcement image"
            class="mt-4 rounded-lg max-w-full h-auto"
          />

          <p class="text-xs text-gray-500 mt-2 text-right">
            {{ formatAnnouncementTimestamp(announcement.timestamp) }}
          </p>
        </div>
      </div>

      <div v-else class="text-center text-gray-500 py-8">
        <p>No announcements for this event yet.</p>
      </div>
    </div>
                    </div>
                </div>

                <!-- Related events sidebar (right side) - fixed width, shown only on details view -->
                <div v-if="currentView === 'details'" class="md:w-80 flex-shrink-0">
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
        </div>

      <!-- Brackets Section -->
      <div v-if="relatedBrackets.length > 0" class="max-w-6xl mx-auto mt-6">
        <h2 class="text-xl font-bold mb-4">Games</h2>
        <div v-for="bracket in relatedBrackets" :key="bracket.id" class="bracket-section">
            <div class="bracket-wrapper">
                <div class="bracket-header">
                    <h2>{{ bracket.name }} ({{ bracket.type }})</h2>
                </div>

                <div>
                    <!-- Single Elimination Display -->
                    <div v-if="bracket.type === 'Single Elimination'" class="bracket">
                        <svg class="connection-lines">
                        <line
                            v-for="(line, i) in bracket.lines"
                            :key="i"
                            :x1="line.x1"
                            :y1="line.y1"
                            :x2="line.x2"
                            :y2="line.y2"
                            stroke="black"
                            stroke-width="2"
                        />
                        </svg>

                        <div v-for="(round, roundIdx) in bracket.matches" :key="roundIdx"
                        :class="['round', `round-${roundIdx + 1}`]">
                        <h3>
                            {{ isFinalRound(getBracketIndex(bracket.id), roundIdx) ? 'Final Round' : isSemifinalRound(getBracketIndex(bracket.id), roundIdx) ? 'Semifinal' : isQuarterfinalRound(getBracketIndex(bracket.id), roundIdx) ? 'Quarterfinal' : `Round ${roundIdx + 1}` }}
                        </h3>

                        <!-- Matches Display -->
                        <div
                            v-for="(match, matchIdx) in round"
                            :key="matchIdx"
                            :id="`match-${roundIdx}-${matchIdx}`"
                            :class="['match']"
                            @click="user?.name === 'Admin' && openMatchDialog(getBracketIndex(bracket.id), roundIdx, matchIdx, match, 'single')"
                        >
                            <div class="player-box">
                                <span
                                :class="{
                                    winner: (match.players[0].name && match.players[0].name !== 'TBD') && match.players[0].completed && match.players[0].score >= match.players[1].score,
                                    loser: (match.players[0].name && match.players[0].name !== 'TBD') && match.players[0].completed && match.players[0].score < match.players[1].score,
                                    'bye-text': match.players[0].name === 'BYE',
                                    'facing-bye': match.players[1].name === 'BYE',
                                    'tbd-text': !match.players[0].name || match.players[0].name === 'TBD',
                                    'loser-name': match.loser_id === match.players[0].id,
                                    'winner-name': match.winner_id === match.players[0].id
                                }"
                                >
                                {{ truncateNameElimination(match.players[0].name) }} | {{ match.players[0].score }}
                                </span>
                                <hr />
                                <span
                                :class="{
                                    winner: (match.players[1].name && match.players[1].name !== 'TBD') && match.players[1].completed && match.players[1].score >= match.players[0].score,
                                    loser: (match.players[1].name && match.players[1].name !== 'TBD') && match.players[1].completed && match.players[1].score < match.players[0].score,
                                    'bye-text': match.players[1].name === 'BYE',
                                    'facing-bye': match.players[0].name === 'BYE',
                                    'tbd-text': !match.players[1].name || match.players[1].name === 'TBD',
                                    'loser-name': match.loser_id === match.players[1].id,
                                    'winner-name': match.winner_id === match.players[1].id
                                }"
                                >
                                {{ truncateNameElimination(match.players[1].name) }} | {{ match.players[1].score }}
                                </span>
                            </div>
                        </div>
                        </div>
                    </div>

                    <!-- Round Robin Display -->
                    <div v-else-if="bracket.type === 'Round Robin'" class="round-robin-bracket">
                        <div class="bracket">
                        <div v-for="(round, roundIdx) in bracket.matches" :key="`round-${roundIdx}`"
                            :class="['round', `round-${roundIdx + 1}`]">
                            <h3>Round {{ roundIdx + 1 }}</h3>

                            <div
                            v-for="(match, matchIdx) in round"
                            :key="`round-${roundIdx}-${matchIdx}`"
                            :id="`round-match-${roundIdx}-${matchIdx}`"
                            :class="['match']"
                            @click="user?.name === 'Admin' && openRoundRobinMatchDialog(getBracketIndex(bracket.id), roundIdx, matchIdx, match)"
                            >
                            <div class="player-box">
                                <span
                                :class="{
                                    winner: (match.players[0].name && match.players[0].name !== 'TBD') && match.players[0].completed && match.players[0].score > match.players[1].score,
                                    loser: (match.players[0].name && match.players[0].name !== 'TBD') && match.players[0].completed && match.players[0].score < match.players[1].score,
                                    tie: (match.players[0].name && match.players[0].name !== 'TBD') && match.players[0].completed && match.players[0].score === match.players[1].score && match.is_tie,
                                    'bye-text': match.players[0].name === 'BYE',
                                    'facing-bye': match.players[1].name === 'BYE',
                                    'tbd-text': !match.players[0].name || match.players[0].name === 'TBD',
                                    'loser-name': match.loser_id === match.players[0].id,
                                    'winner-name': match.winner_id === match.players[0].id
                                }"
                                >
                                {{ truncateNameRoundRobin(match.players[0].name) }} | {{ match.players[0].score }}
                                </span>
                                <hr />
                                <span
                                :class="{
                                    winner: (match.players[1].name && match.players[1].name !== 'TBD') && match.players[1].completed && match.players[1].score > match.players[0].score,
                                    loser: (match.players[1].name && match.players[1].name !== 'TBD') && match.players[1].completed && match.players[1].score < match.players[0].score,
                                    tie: (match.players[1].name && match.players[1].name !== 'TBD') && match.players[1].completed && match.players[1].score === match.players[0].score && match.is_tie,
                                    'bye-text': match.players[1].name === 'BYE',
                                    'facing-bye': match.players[0].name === 'BYE',
                                    'tbd-text': !match.players[1].name || match.players[1].name === 'TBD',
                                    'loser-name': match.loser_id === match.players[1].id,
                                    'winner-name': match.winner_id === match.players[1].id
                                }"
                                >
                                {{ truncateNameRoundRobin(match.players[1].name) }} | {{ match.players[1].score }}
                                </span>
                            </div>
                            </div>
                        </div>
                        </div>

                        <!-- Round Robin Standings -->
                        <div class="standings-section">
                        <div class="standings-header-row">
                            <h3>Standings</h3>
                            <button v-if="user?.name === 'Admin'"
                            @click="openScoringConfigDialog"
                            class="scoring-config-btn"
                            title="Configure scoring system"
                            >
                            <i class="pi pi-cog"></i>
                            </button>
                        </div>
                        <div class="standings-table">
                            <div class="standings-header">
                            <span class="rank">Rank</span>
                            <span class="player">Player</span>
                            <span class="wins">Wins</span>
                            <span class="draws">Draws</span>
                            <span class="losses">Losses</span>
                            <span class="points">Points</span>
                            </div>
                            <div
                            v-for="(player, index) in (standingsRevision, getRoundRobinStandings(getBracketIndex(bracket.id)))"
                            :key="player.id"
                            class="standings-row"
                            :class="{ 'winner': index === 0 && isRoundRobinConcluded(getBracketIndex(bracket.id)) }"
                            >
                            <span class="rank">{{ index + 1 }}</span>
                            <span class="player">
                                {{ truncateNameRoundRobin(player.name) }}
                                <i v-if="index === 0 && isRoundRobinConcluded(getBracketIndex(bracket.id))" class="pi pi-crown winner-crown"></i>
                            </span>
                            <span class="wins">{{ player.wins }}</span>
                            <span class="draws">{{ player.draws }}</span>
                            <span class="losses">{{ player.losses }}</span>
                            <span class="points">{{ player.points }}</span>
                            </div>
                        </div>
                        </div>
                    </div>

                    <!-- Double Elimination Display -->
                    <div v-else-if="bracket.type === 'Double Elimination'" class="double-elimination-bracket">
                        <!-- Winners Bracket -->
                        <div class="bracket-section winners">
                        <h3>Winners Bracket</h3>
                        <div class="bracket">
                            <svg class="connection-lines winners-lines">
                            <g v-for="(line, i) in bracket.lines?.winners" :key="`winners-${i}`">
                                <line
                                :x1="line.x1"
                                :y1="line.y1"
                                :x2="line.x2"
                                :y2="line.y2"
                                stroke="black"
                                stroke-width="2"
                                />
                            </g>
                            </svg>

                            <div v-for="(round, roundIdx) in bracket.matches.winners" :key="`winners-${roundIdx}`"
                            :class="['round', `round-${roundIdx + 1}`]">
                            <h4>Round {{ roundIdx + 1 }}</h4>

                            <div
                                v-for="(match, matchIdx) in round"
                                :key="`winners-${roundIdx}-${matchIdx}`"
                                :id="`winners-match-${roundIdx}-${matchIdx}`"
                                :class="['match']"
                                @click="user?.name === 'Admin' && openMatchDialog(getBracketIndex(bracket.id), roundIdx, matchIdx, match, 'winners')"
                            >
                                <div class="player-box">
                                <span
                                    :class="{
                                    winner: (match.players[0].name && match.players[0].name !== 'TBD') && match.players[0].completed && match.players[0].score >= match.players[1].score,
                                    loser: (match.players[0].name && match.players[0].name !== 'TBD') && match.players[0].completed && match.players[0].score < match.players[1].score,
                                    'bye-text': match.players[0].name === 'BYE',
                                    'facing-bye': match.players[1].name === 'BYE',
                                    'tbd-text': !match.players[0].name || match.players[0].name === 'TBD',
                                    'loser-name': match.loser_id === match.players[0].id,
                                    'winner-name': match.winner_id === match.players[0].id
                                    }"
                                >
                                    {{ truncateNameElimination(match.players[0].name) }} | {{ match.players[0].score }}
                                </span>
                                <hr />
                                <span
                                    :class="{
                                    winner: (match.players[1].name && match.players[1].name !== 'TBD') && match.players[1].completed && match.players[1].score >= match.players[0].score,
                                    loser: (match.players[1].name && match.players[1].name !== 'TBD') && match.players[1].completed && match.players[1].score < match.players[0].score,
                                    'bye-text': match.players[1].name === 'BYE',
                                    'facing-bye': match.players[0].name === 'BYE',
                                    'tbd-text': !match.players[1].name || match.players[1].name === 'TBD',
                                    'loser-name': match.loser_id === match.players[1].id,
                                    'winner-name': match.winner_id === match.players[1].id
                                    }"
                                >
                                    {{ truncateNameElimination(match.players[1].name) }} | {{ match.players[1].score }}
                                </span>
                                </div>
                            </div>
                            </div>
                        </div>
                        </div>

                        <!-- Losers Bracket -->
                        <div class="bracket-section losers">
                        <h3>Losers Bracket</h3>
                        <div class="bracket">
                            <svg class="connection-lines losers-lines">
                            <g v-for="(line, i) in bracket.lines?.losers" :key="`losers-${i}`">
                                <line
                                :x1="line.x1"
                                :y1="line.y1"
                                :x2="line.x2"
                                :y2="line.y2"
                                stroke="black"
                                stroke-width="2"
                                />
                            </g>
                            </svg>

                            <div v-for="(round, roundIdx) in bracket.matches.losers" :key="`losers-${roundIdx}`"
                            :class="['round', `round-${roundIdx + 1}`]">
                            <h4>Round {{ roundIdx + 1 }}</h4>

                            <div
                                v-for="(match, matchIdx) in round"
                                :key="`losers-${roundIdx}-${matchIdx}`"
                                :id="`losers-match-${roundIdx}-${matchIdx}`"
                                :class="['match']"
                                @click="user?.name === 'Admin' && openMatchDialog(getBracketIndex(bracket.id), roundIdx + bracket.matches.winners.length, matchIdx, match, 'losers')"
                            >
                                <div class="player-box">
                                <span
                                    :class="{
                                    winner: (match.players[0].name && match.players[0].name !== 'TBD') && match.players[0].completed && match.players[0].score >= match.players[1].score,
                                    loser: (match.players[0].name && match.players[0].name !== 'TBD') && match.players[0].completed && match.players[0].score < match.players[1].score,
                                    'bye-text': match.players[0].name === 'BYE',
                                    'facing-bye': match.players[1].name === 'BYE',
                                    'tbd-text': !match.players[0].name || match.players[0].name === 'TBD',
                                    'loser-name': match.loser_id === match.players[0].id,
                                    'winner-name': match.winner_id === match.players[0].id
                                    }"
                                >
                                    {{ truncateNameElimination(match.players[0].name) }} | {{ match.players[0].score }}
                                </span>
                                <hr />
                                <span
                                    :class="{
                                    winner: (match.players[1].name && match.players[1].name !== 'TBD') && match.players[1].completed && match.players[1].score >= match.players[0].score,
                                    loser: (match.players[1].name && match.players[1].name !== 'TBD') && match.players[1].completed && match.players[1].score < match.players[0].score,
                                    'bye-text': match.players[1].name === 'BYE',
                                    'facing-bye': match.players[0].name === 'BYE',
                                    'tbd-text': !match.players[1].name || match.players[1].name === 'TBD',
                                    'loser-name': match.loser_id === match.players[1].id,
                                    'winner-name': match.winner_id === match.players[1].id
                                    }"
                                >
                                    {{ truncateNameElimination(match.players[1].name) }} | {{ match.players[1].score }}
                                </span>
                                </div>
                            </div>
                            </div>
                        </div>
                        </div>

                    <!-- Grand Finals -->
                    <div class="bracket-section grand-finals">
                        <h3>Finals</h3>
                        <div class="bracket">
                        <svg class="connection-lines finals-lines">
                            <g v-for="(line, i) in bracket.lines?.finals" :key="`finals-${i}`">
                            <line
                                :x1="line.x1"
                                :y1="line.y1"
                                :x2="line.x2"
                                :y2="line.y2"
                                stroke="black"
                                stroke-width="2"
                            />
                            </g>
                        </svg>

                        <div v-for="(match, matchIdx) in bracket.matches.grand_finals" :key="`grand-finals-${matchIdx}`"
                            :id="`grand-finals-match-${matchIdx}`"
                            :class="['match']"
                            @click="user?.name === 'Admin' && openMatchDialog(getBracketIndex(bracket.id), bracket.matches.winners.length + bracket.matches.losers.length, matchIdx, match, 'grand_finals')"
                        >
                            <div class="player-box">
                            <span
                                :class="{
                                winner: (match.players[0].name && match.players[0].name !== 'TBD') && match.players[0].completed && match.players[0].score >= match.players[1].score,
                                loser: (match.players[0].name && match.players[0].name !== 'TBD') && match.players[0].completed && match.players[0].score < match.players[1].score,
                                'bye-text': match.players[0].name === 'BYE',
                                'facing-bye': match.players[1].name === 'BYE',
                                'tbd-text': !match.players[0].name || match.players[0].name === 'TBD',
                                'loser-name': match.loser_id === match.players[0].id,
                                'winner-name': match.winner_id === match.players[0].id
                                }"
                            >
                                {{ truncateNameElimination(match.players[0].name) }} | {{ match.players[0].score }}
                            </span>
                            <hr />
                            <span
                                :class="{
                                winner: (match.players[1].name && match.players[1].name !== 'TBD') && match.players[1].completed && match.players[1].score >= match.players[0].score,
                                loser: (match.players[1].name && match.players[1].name !== 'TBD') && match.players[1].completed && match.players[1].score < match.players[0].score,
                                'bye-text': match.players[1].name === 'BYE',
                                'facing-bye': match.players[0].name === 'BYE',
                                'tbd-text': !match.players[1].name || match.players[1].name === 'TBD',
                                'loser-name': match.loser_id === match.players[1].id,
                                'winner-name': match.winner_id === match.players[1].id
                                }"
                            >
                                {{ truncateNameElimination(match.players[1].name) }} | {{ match.players[1].score }}
                            </span>
                            </div>
                        </div>
                        </div>
                    </div>
                    </div>
                </div>
            </div>
        </div>
      </div>

      <!-- Loading Dialog -->
      <LoadingSpinner :show="saving" />

      <!-- Success Message Dialog -->
      <SuccessDialog
        v-model:show="showSuccessDialog"
        :message="successMessage"
      />
      <!-- Error Dialog -->
      <Dialog
        v-model:visible="showErrorDialog"
        modal
        header="Error"
        :style="{ width: '400px', zIndex: 9998 }"
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

      <!-- Delete Task Confirmation Dialog -->
      <ConfirmationDialog
        v-model:show="showDeleteTaskConfirm"
        title="Delete Task?"
        message="Are you sure you want to delete this task?"
        @confirm="confirmDeleteTask"
      />

      <!-- Delete Schedule Confirmation Dialog -->
      <ConfirmationDialog
        v-model:show="showDeleteScheduleConfirm"
        title="Delete Schedule?"
        message="Are you sure you want to delete this schedule item?"
        @confirm="confirmDeleteSchedule"
      />

      <!-- Save Changes Confirmation Dialog -->
      <ConfirmationDialog
        v-model:show="showSaveConfirmDialog"
        title="Save Changes?"
        message="Are you sure you want to save your changes?"
        confirmText="Yes, Save"
        confirmButtonClass="bg-blue-600 hover:bg-blue-700"
        @confirm="saveChanges"
      />
    </div>

    <!-- Add Announcement Modal -->
    <Dialog v-model:visible="showAddAnnouncementModal" modal header="Add Announcement" :style="{ width: '50vw' }">
        <div class="p-fluid">
            <div class="p-field">
                <label for="announcementMessage">Message</label>
                <Textarea id="announcementMessage" v-model="newAnnouncement.message" rows="5" placeholder="Enter your announcement..." autoResize />
            </div>
            <div class="p-field mt-4">
                <label for="announcementImage">Image (Optional)</label>
                <input type="file" id="announcementImage" @change="handleAnnouncementImageUpload" accept="image/*" class="p-inputtext" />
                <img v-if="newAnnouncement.imagePreview" :src="newAnnouncement.imagePreview" alt="Image preview" class="mt-4 rounded-lg max-w-xs h-auto" />
            </div>
        </div>
        <template #footer>
            <Button label="Cancel" icon="pi pi-times" class="p-button-text" @click="showAddAnnouncementModal = false" />
            <Button label="Post" icon="pi pi-check" @click="addAnnouncement" :loading="saving" />
        </template>
    </Dialog>

    <ConfirmationDialog
        v-model:show="showDeleteAnnouncementConfirm"
        title="Delete Announcement?"
        message="Are you sure you want to delete this announcement?"
        @confirm="confirmDeleteAnnouncement"
    />

    <!-- Dialogs from Bracket.vue -->
    <!-- Winner Dialog -->
    <Dialog v-model:visible="showWinnerDialog" header="Winner!" modal dismissableMask>
        <p>{{ winnerMessage }}</p>
    </Dialog>

    <!-- Match Update Confirmation Dialog -->
    <ConfirmationDialog
    v-model:show="showMatchUpdateConfirmDialog"
    title="Confirm Match Update"
    message="Are you sure you want to update this match? This action may trigger bracket progression and cannot be easily undone."
    confirmText="Yes, Update Match"
    cancelText="Cancel"
    confirmButtonClass="bg-green-600 hover:bg-green-700"
    @confirm="proceedWithMatchUpdate"
    @cancel="cancelMatchUpdate"
    />

    <!-- Scoring Configuration Dialog -->
    <Dialog v-model:visible="showScoringConfigDialog" header="Configure Scoring System" modal :style="{ width: '400px' }">
    <div class="scoring-config-dialog">
        <div class="scoring-option">
        <label>Win Points:</label>
        <InputText
            v-model="roundRobinScoring.win"
            type="number"
            step="0.5"
            min="0"
            placeholder="1"
        />
        </div>
        <div class="scoring-option">
        <label>Draw Points:</label>
        <InputText
            v-model="roundRobinScoring.draw"
            type="number"
            step="0.5"
            min="0"
            placeholder="0.5"
        />
        </div>
        <div class="scoring-option">
        <label>Loss Points:</label>
        <InputText
            v-model="roundRobinScoring.loss"
            type="number"
            step="0.5"
            min="0"
            placeholder="0"
        />
        </div>
        <div class="dialog-actions">
        <Button
            label="Cancel"
            @click="closeScoringConfigDialog"
            class="p-button-secondary"
        />
        <Button
            label="Save"
            @click="saveScoringConfig"
            class="p-button-success"
        />
        </div>
    </div>
    </Dialog>

    <!-- Round Robin Match Dialog -->
    <Dialog v-model:visible="showRoundRobinMatchDialog" header="Edit Match" modal :style="{ width: '500px' }">
    <div v-if="selectedRoundRobinMatchData" class="round-robin-match-dialog">
        <div class="match-info">
        <h3>Round Robin Match</h3>
        <p class="match-description">Edit player names, scores, and match status</p>
        </div>

        <div class="player-section">
        <div class="player-input">
            <label>Player 1 Name:</label>
            <InputText
            v-model="selectedRoundRobinMatchData.player1Name"
            placeholder="Enter player name"
            :disabled="selectedRoundRobinMatchData?.player1Name === 'BYE'"
            />
        </div>

        <div class="score-section">
            <label>Player 1 Score:</label>
            <div class="score-controls">
            <Button
                @click="selectedRoundRobinMatchData.player1Score--"
                :disabled="selectedRoundRobinMatchData.player1Score <= 0"
                icon="pi pi-minus"
                class="p-button-sm"
            />
            <span class="score-display">{{ selectedRoundRobinMatchData.player1Score }}</span>
            <Button
                @click="selectedRoundRobinMatchData.player1Score++"
                icon="pi pi-plus"
                class="p-button-sm"
            />
            </div>
        </div>
        </div>

        <div class="vs-divider">VS</div>

        <div class="player-section">
        <div class="player-input">
            <label>Player 2 Name:</label>
            <InputText
            v-model="selectedRoundRobinMatchData.player2Name"
            placeholder="Enter player name"
            :disabled="selectedRoundRobinMatchData?.player2Name === 'BYE'"
            />
        </div>

        <div class="score-section">
            <label>Player 2 Score:</label>
            <div class="score-controls">
            <Button
                @click="selectedRoundRobinMatchData.player2Score--"
                :disabled="selectedRoundRobinMatchData.player2Score <= 0"
                icon="pi pi-minus"
                class="p-button-sm"
            />
            <span class="score-display">{{ selectedRoundRobinMatchData.player2Score }}</span>
            <Button
                @click="selectedRoundRobinMatchData.player2Score++"
                icon="pi pi-plus"
                class="p-button-sm"
            />
            </div>
        </div>
        </div>

        <div class="match-status-section">
        <label>Match Status:</label>
        <div class="status-toggle">
            <span class="status-label" :class="{ 'active': selectedRoundRobinMatchData.status === 'pending' }">Pending</span>
            <InputSwitch
            :modelValue="selectedRoundRobinMatchData.status === 'completed'"
            @update:modelValue="(value) => selectedRoundRobinMatchData.status = value ? 'completed' : 'pending'"
            class="status-switch"
            />
            <span class="status-label" :class="{ 'active': selectedRoundRobinMatchData.status === 'completed' }">Completed</span>
        </div>
        </div>

        <div v-if="selectedRoundRobinMatchData.status === 'completed' && selectedRoundRobinMatchData.player1Score === selectedRoundRobinMatchData.player2Score"
            :class="['tie-indicator', selectedRoundRobinMatch?.bracketType !== 'round_robin' ? 'tie-warning-bg' : '']">
        <i class="pi pi-exclamation-triangle"></i>
        <span v-if="selectedRoundRobinMatch?.bracketType === 'round_robin'">This match is a tie!</span>
        <span v-else class="tie-warning">Ties are not allowed in elimination brackets. Please adjust scores to determine a winner.</span>
        </div>

        <div class="dialog-actions">
        <Button
            label="Cancel"
            @click="closeRoundRobinMatchDialog"
            class="p-button-secondary"
        />
        <Button
            label="Update Match"
            @click="confirmMatchUpdate"
            class="p-button-success"
        />
        </div>
    </div>
    </Dialog>
  </template>
