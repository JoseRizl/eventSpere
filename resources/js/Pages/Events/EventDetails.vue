<script setup>
import { ref, computed, onMounted, nextTick, watch } from 'vue';
import { parse, format, parseISO, isValid, addDays, endOfDay, isWithinInterval } from 'date-fns';
import { usePage, router, Link } from '@inertiajs/vue3';
import axios from 'axios';
import LoadingSpinner from '@/Components/LoadingSpinner.vue';
import ConfirmationDialog from '@/Components/ConfirmationDialog.vue';
import SuccessDialog from '@/Components/SuccessDialog.vue';
import { useBracketState } from '@/composables/useBracketState.js';
import { useBracketActions } from '@/composables/useBracketActions.js';
import Dialog from 'primevue/dialog';
import BracketView from '@/Components/BracketView.vue';
import MatchEditorDialog from '@/Components/MatchEditorDialog.vue';
import MatchesView from '@/Components/MatchesView.vue';
import InputText from 'primevue/inputtext';
import SelectButton from 'primevue/selectbutton';
import Button from 'primevue/button';
import Select from 'primevue/select';
import Avatar from 'primevue/avatar';
import Textarea from 'primevue/textarea';
import DatePicker from 'primevue/datepicker';

const props = defineProps({
  event: Object,
  tags: Array,
  committees: Array,
  employees: Array,
  categories: Array,
  relatedEvents: Array,
  errors: Object,
  all_users: Array, // Assuming this comes from shared props
  auth: Object, // Assuming this comes from shared props
});

// Inertia props (now using defineProps)
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

const showMemoImageDialog = ref(false);
const memoImageUrl = ref('');

const employees = ref(props.employees || []);
const filteredEmployees = ref([]);
const relatedEvents = ref(props.relatedEvents || []);
const showDeleteTaskConfirm = ref(false);
const taskToDelete = ref(null);
const showDeleteScheduleConfirm = ref(false);
const memoFileInput = ref(null);
const handlMemoUpload = ref(null);
const originalEventDetails = ref(null);
const searchQuery = ref('');
const scheduleToDelete = ref(null);

// Announcement search
const announcementSearchQuery = ref('');
const announcementStartDateFilter = ref(null);
const announcementEndDateFilter = ref(null);
const showAnnouncementDateFilter = ref(false);

const filteredEventAnnouncements = computed(() => {
  let announcements = eventAnnouncements.value;

  // Filter by search query
  const query = announcementSearchQuery.value.toLowerCase().trim();
  if (query) {
    announcements = announcements.filter(announcement => {
      const messageMatch = announcement.message?.toLowerCase().includes(query);
      return messageMatch;
    });
  }

  // Filter by date range
  if (announcementStartDateFilter.value || announcementEndDateFilter.value) {
    announcements = announcements.filter(ann => {
      const annDate = new Date(ann.timestamp);
      if (isNaN(annDate.getTime())) return false;

      const filterStart = announcementStartDateFilter.value ? new Date(announcementStartDateFilter.value) : null;
      const filterEnd = announcementEndDateFilter.value ? endOfDay(new Date(announcementEndDateFilter.value)) : null;

      if (filterStart && !filterEnd) return annDate >= filterStart;
      if (!filterStart && filterEnd) return annDate <= filterEnd;
      if (filterStart && filterEnd) return isWithinInterval(annDate, { start: filterStart, end: filterEnd });
      return true;
    });
  }

  return announcements;
});

const toggleAnnouncementDateFilter = () => {
  showAnnouncementDateFilter.value = !showAnnouncementDateFilter.value;
};

const clearAnnouncementDateFilter = () => {
  announcementStartDateFilter.value = null;
  announcementEndDateFilter.value = null;
};

const clearAnnouncementFilters = () => {
  announcementSearchQuery.value = '';
  announcementStartDateFilter.value = null;
  announcementEndDateFilter.value = null;
  showAnnouncementDateFilter.value = false;
};

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
  showMatchEditorDialog,
  selectedMatch,
  selectedMatchData,
  showMatchUpdateConfirmDialog,
  showGenericErrorDialog,
  genericErrorMessage,
  roundRobinScoring,
  bracketMatchFilters,
  showScoringConfigDialog,
  standingsRevision,
} = bracketState;
const { bracketViewModes } = bracketState;

const {
  fetchBrackets,
  handleByeRounds,
  updateLines,
  isFinalRound,
  getRoundRobinStandings,
  isRoundRobinConcluded,
  openMatchDialog,
  closeMatchEditorDialog,
  confirmMatchUpdate,
  cancelMatchUpdate,
  proceedWithMatchUpdate,
  openScoringConfigDialog,
  closeScoringConfigDialog,
  saveScoringConfig,
  toggleBracket,
  setBracketViewMode,
  getAllMatches,
  setBracketMatchFilter,
  openMatchEditorFromCard,
} = useBracketActions(bracketState);

const relatedBrackets = computed(() => {
  if (!brackets.value || !props.event) {
    return [];
  }
  return brackets.value.filter(bracket => bracket.event_id === props.event.id);
});

const matchStatusFilterOptions = ref([
    { label: 'All', value: 'all' },
    { label: 'Pending', value: 'pending' },
    { label: 'Completed', value: 'completed' }
]);

const selectedBracketForDialog = computed(() => {
  if (selectedMatch.value) {
    const bracket = brackets.value[selectedMatch.value.bracketIdx];
    if (bracket) {
        return bracket;
    }
  }
  return null;
});

const handleMemoUpload = (event) => {
    const file = event.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = (e) => {
        eventDetails.value.memorandum = {
            type: file.type.startsWith('image/') ? 'image' : 'file',
            content: e.target.result,
            filename: file.name,
        };
    };
    reader.readAsDataURL(file);
};


const isMatchDataInvalid = computed(() => {
    if (!selectedMatchData.value || !selectedBracketForDialog.value) return true;

    const { status, player1Score, player2Score, player1Name, player2Name } = selectedMatchData.value;
    const bracketType = selectedBracketForDialog.value.type;

    // Check for ties in elimination brackets
    if (status === 'completed' && player1Score === player2Score && (bracketType === 'Single Elimination' || bracketType === 'Double Elimination')) {
        return true;
    }

    // Check for empty player names
    if (player1Name.trim() === '' || player2Name.trim() === '') {
        return true;
    }

    return false;
});

const isMultiDayEvent = computed(() => {
  if (selectedBracketForDialog.value?.event) {
      return selectedBracketForDialog.value.event.startDate !== selectedBracketForDialog.value.event.endDate;
  }
  return false;
});

const eventMinDate = computed(() => {
    if (selectedBracketForDialog.value?.event?.startDate) {
        return parseISO(selectedBracketForDialog.value.event.startDate);
    }
    return null;
});

const eventMaxDate = computed(() => {
    if (selectedBracketForDialog.value?.event?.endDate) {
        return parseISO(selectedBracketForDialog.value.event.endDate);
    }
    return null;
});

const filteredRelatedBrackets = computed(() => {
  if (!searchQuery.value) {
    return relatedBrackets.value;
  }
  const query = searchQuery.value.toLowerCase().trim();
  return relatedBrackets.value.filter(bracket => {
    const bracketNameMatch = bracket.name?.toLowerCase().includes(query);
    const bracketTypeMatch = bracket.type?.toLowerCase().includes(query);
    return bracketNameMatch || bracketTypeMatch;
  });
});

// Create a map of tags for quick lookup
const tagsMap = computed(() =>
{
  return tags.value.reduce((map, tag) => {
    map[tag.id] = tag;
    return map;
  }, {});
});

const eventDetails = ref({
    ...props.event,
    venue: props.event.venue || '',
    category_id: props.event.category?.id || props.event.category_id,
    scheduleLists: props.event.scheduleLists || [{
      day: 1,
      date: props.event.startDate,
      schedules: props.event.schedules || []
    }],
    // event.tags is now an array of tag objects from backend, convert to IDs for editing
    tags: (props.event.tags || []).map(tag => tag.id),
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

const filteredTags = computed(() => {
  if (!eventDetails.value.category_id) {
    return [];
  }
  // Initialize memorandum if it's null
  if (editMode.value && !eventDetails.value.memorandum) {
      eventDetails.value.memorandum = null;
  }
  return tags.value.filter(tag => tag.category_id == eventDetails.value.category_id);
});

watch(() => eventDetails.value.category_id, (newCategoryId, oldCategoryId) => {
    if (newCategoryId !== oldCategoryId && editMode.value) {
        // When category changes, clear selected tags as they might not be valid for the new category.
        eventDetails.value.tags = [];
    }
});

const categoryMap = computed(() => {
  return categories.value.reduce((map, category) => {
    map[category.id] = category.title;
    return map;
  }, {});
});

const removeTag = (tagToRemove) => {
  eventDetails.value.tags = eventDetails.value.tags.filter(tagId => tagId !== tagToRemove.id);
};

const formattedDescription = computed(() => {
  const text = eventDetails.value.description;
  if (!text) return '';

  // 1. Escape the entire string to prevent XSS from user-inputted HTML.
  const escapeHtml = (unsafe) => {
    return unsafe
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#039;");
  };
  const escapedText = escapeHtml(text);

  // 2. Find URLs in the escaped text and wrap them in <a> tags.
  const urlRegex = /(\b(https?|ftp|file):\/\/[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])|(\bwww\.[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])|(\b[A-Z0-9.-]+\.(com|org|net|gov|edu|io|co|us|ca|uk|de|fr|au|info|biz|me|tv|app|dev)\b([-A-Z0-9+&@#\/%?=~_|!:,.;]*))/gi;

  return escapedText.replace(urlRegex, (url) => {
    const unescapedUrlForHref = url.replace(/&amp;/g, '&');
    let href = unescapedUrlForHref;
    if (!href.match(/^(https?|ftp|file):\/\//i)) {
      href = 'http://' + href;
    }
    return `<a href="${href}" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">${url}</a>`;
  });
});

// Edit mode toggle
const editMode = ref(false);
const toggleEdit = () => {
  if (editMode.value) {
    // Cancelling edit mode: restore original data
    if (originalEventDetails.value) {
      eventDetails.value = JSON.parse(JSON.stringify(originalEventDetails.value));
    }
    errorMessage.value = null; // Clear any validation errors
  } else {
    // Entering edit mode: store a copy of the current data
    originalEventDetails.value = JSON.parse(JSON.stringify(eventDetails.value));
  }
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
      if (expandedBrackets.value[originalIndex] === undefined) {
        expandedBrackets.value[originalIndex] = true; // Expand by default
      }
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

    const employeesMap = employees.value.reduce((map, emp) => {
        map[emp.id] = emp;
        return map;
    }, {});

    eventAnnouncements.value = response.data.map(ann => ({
        ...ann,
        employee: employeesMap[ann.userId] || { name: 'Admin' }
    })).sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp));
  } catch (error) {
    console.error('Error fetching event announcements:', error);
  }
};

const openAddAnnouncementModal = () => {
  newAnnouncement.value = { message: '', image: null, imagePreview: null };
  showAddAnnouncementModal.value = true;
};

const viewMemorandum = () => {
    if (eventDetails.value.memorandum && eventDetails.value.memorandum.content) {
    if (eventDetails.value.memorandum.type === 'image') {
        memoImageUrl.value = eventDetails.value.memorandum.content;
        showMemoImageDialog.value = true;
    } else {
        const link = document.createElement('a');
        link.href = eventDetails.value.memorandum.content;
        link.download = eventDetails.value.memorandum.filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
    }
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
    userId: user.value.id,
  };

  try {
    const response = await axios.post('http://localhost:3000/event_announcements', payload);
    const newAnn = {
        ...response.data,
        employee: { name: user.value.name }
    };
    eventAnnouncements.value.unshift(newAnn);
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
    tags: eventDetails.value.tags || [], // Only IDs
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
    })),
    memorandum: eventDetails.value.memorandum,
  };

  // Debug: log the payload before sending
  console.log('Sending payload:', payload);

  router.post(`/events/${eventDetails.value.id}/update`, payload, {
    preserveScroll: true,
    onFinish: () => saving.value = false,
    onError: (errors) => {
      saving.value = false;
      const firstErrorKey = Object.keys(errors)[0];
      let message = 'Failed to save event. Please check the form for errors.';
      if (firstErrorKey) {
          const errorValue = errors[firstErrorKey];
          message = Array.isArray(errorValue) ? errorValue[0] : errorValue;
      } else if (errors.message) {
          message = errors.message;
      }
      errorMessage.value = message;
      errorDialogMessage.value = message;
      showErrorDialog.value = true;
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
<div>
    <div class="min-h-screen py-8 px-4">
        <div class="mx-auto mt-4">
            <!-- Back Button for non-management -->
            <div v-if="!user || !['Admin', 'Principal', 'SportsManager'].includes(user.role)" class="mb-4">
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

        <div class="mx-auto mt-4">
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
                            v-if="user?.role === 'Admin' || user?.role === 'Principal'"
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
                    <p v-else class="text-gray-700 whitespace-pre-line text-sm" v-html="formattedDescription"></p>
                    </div>

                    <!-- Details Section -->
                    <div class="border-t border-gray-200 pt-4 mt-4">
                        <div v-if="editMode" class="space-y-4">
                            <!-- Edit Mode: Venue and Category -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="text-sm font-medium mb-1">Venue</label>
                                    <InputText v-model="eventDetails.venue" class="w-full" placeholder="Enter event venue" />
                                </div>
                                <div>
                                    <label class="text-sm font-medium mb-1">Category</label>
                                    <Select v-model="eventDetails.category_id" :options="categories" optionLabel="title" optionValue="id" placeholder="Select a category" class="w-full" />
                                </div>
                            </div>

                            <!-- Edit Mode: Tags -->
                            <div>
                                <label class="text-sm font-medium mb-1">Tags</label>
                                <MultiSelect v-model="eventDetails.tags" :options="filteredTags" optionValue="id" optionLabel="name" display="chip" placeholder="Select tags" class="w-full">
                                    <template #chip="slotProps">
                                        <div v-if="tagsMap[slotProps.value]" class="flex items-center gap-2 px-2 py-1 rounded text-white text-xs" :style="{ backgroundColor: tagsMap[slotProps.value].color }">
                                            {{ tagsMap[slotProps.value].name }}
                                            <button type="button" class="text-white hover:text-gray-200" @click.stop="removeTag(tagsMap[slotProps.value])" v-tooltip.top="'Remove Tag'">✕</button>
                                        </div>
                                        <div v-else class="flex items-center gap-2 px-2 py-1 rounded bg-gray-500 text-white text-xs">
                                            {{ slotProps.value }}
                                        </div>
                                    </template>
                                </MultiSelect>
                            </div>

                            <!-- Edit Mode: Dates and Times -->
                            <div class="grid grid-cols-2 gap-4">
                                <div class="flex flex-col"><label class="text-sm font-medium mb-1">Start Date</label><DatePicker v-model="startDateModel" dateFormat="M-dd-yy" showIcon class="w-full" /></div>
                                <div class="flex flex-col"><label class="text-sm font-medium mb-1">Start Time</label><input type="time" v-model="eventDetails.startTime" class="border p-2 rounded" /></div>
                                <div class="flex flex-col"><label class="text-sm font-medium mb-1">End Date</label><DatePicker v-model="endDateModel" dateFormat="M-dd-yy" showIcon class="w-full" /></div>
                                <div class="flex flex-col"><label class="text-sm font-medium mb-1">End Time</label><input type="time" v-model="eventDetails.endTime" class="border p-2 rounded" /></div>
                            </div>
                        </div>
                        <div v-else>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-y-6 gap-x-4 text-sm text-gray-700">
                                <!-- Date & Time -->
                                <div class="flex items-start gap-3">
                                    <i class="pi pi-calendar mt-1 text-gray-500 text-lg"></i>
                                    <div>
                                        <strong class="block text-gray-800 font-semibold">Date & Time</strong>
                                        <span v-if="eventDetails.startDate === eventDetails.endDate">
                                            {{ formatDisplayDate(eventDetails.startDate) }} from {{ formatDisplayTime(eventDetails.startTime) }} to {{ formatDisplayTime(eventDetails.endTime) }}
                                        </span>
                                        <span v-else>
                                            From {{ formatDisplayDate(eventDetails.startDate) }} at {{ formatDisplayTime(eventDetails.startTime) }}<br>
                                            to {{ formatDisplayDate(eventDetails.endDate) }} at {{ formatDisplayTime(eventDetails.endTime) }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Venue -->
                                <div class="flex items-start gap-3">
                                    <i class="pi pi-map-marker mt-1 text-gray-500 text-lg"></i>
                                    <div>
                                        <strong class="block text-gray-800 font-semibold">Venue</strong>
                                        <span>{{ eventDetails.venue || 'Not specified' }}</span>
                                    </div>
                                </div>

                                <!-- Category -->
                                <div class="flex items-start gap-3">
                                    <i class="pi pi-bookmark mt-1 text-gray-500 text-lg"></i>
                                    <div>
                                        <strong class="block text-gray-800 font-semibold">Category</strong>
                                        <span>{{ categoryMap[eventDetails.category_id] || 'Uncategorized' }}</span>
                                    </div>
                                </div>

                                <!-- Tags -->
                                <div v-if="eventDetails.tags && eventDetails.tags.length > 0" class="flex items-start gap-3">
                                    <i class="pi pi-tags mt-1 text-gray-500 text-lg"></i>
                                    <div>
                                        <strong class="block text-gray-800 font-semibold">Tags</strong>
                                        <div class="flex gap-2 flex-wrap mt-1">
                                            <span v-for="tagId in eventDetails.tags" :key="tagId" :style="{ backgroundColor: tagsMap[tagId]?.color || '#cccccc', color: '#fff' }" class="text-xs font-semibold py-1 px-2 rounded">
                                                {{ tagsMap[tagId]?.name || 'Unknown' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Memorandum -->
                    <div class="border-t border-gray-200 pt-4 mt-4">
                    <!-- Show title only if memorandum exists OR in edit mode -->
                    <h2 v-if="eventDetails.memorandum || editMode" class="font-semibold mb-2">Memorandum</h2>

                    <!-- Edit Mode -->
                    <div v-if="editMode" class="space-y-2">
                        <!-- If memorandum exists -->
                        <div v-if="eventDetails.memorandum" class="flex items-center gap-2 p-2 border rounded-md bg-gray-100">
                        <i class="pi pi-file"></i>
                        <span class="flex-1 cursor-pointer text-blue-600 hover:underline" @click="viewMemorandum">
                            {{ eventDetails.memorandum.filename }}
                        </span>
                        <Button
                            icon="pi pi-times"
                            class="p-button-rounded p-button-danger p-button-text"
                            @click="eventDetails.memorandum = null"
                            v-tooltip.top="'Remove Memorandum'"
                        />
                        </div>

                        <!-- If no memorandum uploaded -->
                        <div v-else class="flex justify-center items-center border-2 border-dashed rounded-md p-4">
                        <input type="file" ref="memoFileInput" @change="handleMemoUpload" class="hidden" />
                        <Button
                            label="Upload Memorandum"
                            icon="pi pi-upload"
                            class="p-button-sm p-button-outlined"
                            @click="$refs.memoFileInput.click()"
                        />
                        </div>
                    </div>

                    <!-- View Mode -->
                    <div v-else>
                        <div v-if="eventDetails.memorandum">
                        <Button
                            :label="`View ${eventDetails.memorandum.filename}`"
                            icon="pi pi-eye"
                            @click="viewMemorandum"
                            class="p-button-secondary"
                        />
                        </div>
                    </div>
                    </div>

                    <!-- Error Message -->
                    <div v-if="errorMessage" class="text-red-500 text-sm mt-2">
                    {{ errorMessage }}
                    </div>

                    <!-- Committee -->
                    <div v-if="editMode || (eventDetails.tasks && eventDetails.tasks.length > 0)">
                    <h2 class="font-semibold mb-2">Tasks & Committees:</h2>
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
            <div v-else class="space-y-3">
                <div v-for="(taskItem, index) in eventDetails.tasks" :key="index" class="p-3 border rounded-lg bg-gray-50/50">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="font-semibold text-gray-800">{{ taskItem.task || 'No task specified' }}</p>
                            <p class="text-xs text-gray-500">{{ taskItem.committee?.name || 'No committee' }}</p>
                        </div>
                    </div>
                    <div class="mt-3 flex items-center gap-2 flex-wrap">
                        <span v-if="!taskItem.employees || taskItem.employees.length === 0" class="text-gray-500 italic text-xs">No employees assigned</span>
                        <div v-else v-for="employee in taskItem.employees" :key="employee.id" class="flex items-center gap-2 bg-white rounded-full px-2 py-1 border shadow-sm" v-tooltip.top="employee.name">
                            <Avatar :label="employee.name ? employee.name.split(' ').map(n => n[0]).join('').toUpperCase() : '?'" shape="circle" size="small" />
                            <span class="text-xs font-medium text-gray-700">{{ employee.name }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

                    <!-- Schedules -->
                    <div v-if="editMode || (eventDetails.scheduleLists && eventDetails.scheduleLists.length > 0)">
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
                        <div v-if="eventDetails.scheduleLists && eventDetails.scheduleLists.length > 0" class="border rounded-lg p-4">
                            <h3 class="font-medium mb-3">Day {{ eventDetails.scheduleLists[currentScheduleList].day }} Schedule</h3>
                            <div v-for="(schedule, i) in eventDetails.scheduleLists[currentScheduleList].schedules" :key="i" class="flex items-center gap-2 mb-2">
                                <input v-model="schedule.time" type="time" class="border p-1 rounded w-32" />
                                <input v-model="schedule.activity" type="text" placeholder="Activity" class="border p-1 rounded flex-1" />
                                <button @click="promptDeleteSchedule(i)" class="text-red-500">✕</button>
                            </div>
                            <button @click="addSchedule" class="text-blue-500 text-sm mt-2">+ Add Schedule</button>
                        </div>
                        <p v-else class="text-gray-500 italic p-4">No schedules. Add a day to start.</p>
                    </div>
                    <div v-else>
                        <!-- View Mode for Schedules -->
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
          v-if="user?.role === 'Admin' || user?.role === 'Principal'"
          label="Add Announcement"
          icon="pi pi-plus"
          class="p-button-sm"
          @click="openAddAnnouncementModal"
        />
      </div>

      <!-- Filters for Announcements -->
      <div class="mb-4">
        <div class="search-wrapper">
            <div class="p-input-icon-left w-full">
                <i class="pi pi-search" />
                <InputText
                    v-model="announcementSearchQuery"
                    placeholder="Search by message..."
                    class="w-full"
                />
            </div>
            <Button
                icon="pi pi-calendar"
                class="p-button-outlined date-filter-btn"
                @click="toggleAnnouncementDateFilter"
                :class="{ 'p-button-primary': showAnnouncementDateFilter }"
                v-tooltip.top="'Filter by date'"
            />
            <Button v-if="announcementSearchQuery || announcementStartDateFilter || announcementEndDateFilter" icon="pi pi-times" class="p-button-rounded p-button-text p-button-danger" @click="clearAnnouncementFilters" v-tooltip.top="'Clear All Filters'" />
        </div>
      </div>

      <!-- Date Filter Calendar -->
      <div v-if="showAnnouncementDateFilter" class="date-filter-container mb-4">
          <div class="date-range-wrapper">
            <div class="date-input-group">
              <label>From:</label>
              <DatePicker
                v-model="announcementStartDateFilter"
                dateFormat="M-dd-yy"
                :showIcon="true"
                placeholder="Start date"
                class="date-filter-calendar"
              />
            </div>
            <div class="date-input-group">
              <label>To:</label>
              <DatePicker
                v-model="announcementEndDateFilter"
                dateFormat="M-dd-yy"
                :showIcon="true"
                placeholder="End date"
                class="date-filter-calendar"
              />
            </div>
          </div>
          <Button v-if="announcementStartDateFilter || announcementEndDateFilter" icon="pi pi-times" class="p-button-text p-button-rounded clear-date-btn" @click="clearAnnouncementDateFilter" v-tooltip.top="'Clear date filter'" />
      </div>

      <div v-if="filteredEventAnnouncements.length > 0" class="space-y-6">
        <div
          v-for="announcement in filteredEventAnnouncements"
          :key="announcement.id"
          class="p-4 bg-gray-50 rounded-lg shadow-sm border border-gray-200 relative"
        >
          <!-- wrapper guarantees reliable positioning despite internal Button padding -->
          <div v-if="user?.role === 'Admin' || user?.role === 'Principal'" class="absolute top-2 right-2 z-10">
            <Button
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
            <span class="text-gray-600 text-sm font-semibold">{{ announcement.employee?.name || 'Admin' }}</span>
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

      <div v-else-if="announcementSearchQuery || announcementStartDateFilter || announcementEndDateFilter" class="text-center text-gray-500 py-8">
        <p>No announcements found matching your search or filter.</p>
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
      <div v-if="relatedBrackets.length > 0" class="mx-auto mt-6">
        <h2 class="text-xl font-bold mb-4">Games</h2>
        <div v-if="relatedBrackets.length > 1" class="search-container mb-4" style="max-width: 300px;">
            <div class="p-input-icon-left w-full">
                <i class="pi pi-search" />
                <InputText
                    v-model="searchQuery"
                    placeholder="Search games..."
                    class="w-full"
                />
            </div>
        </div>
        <div v-if="filteredRelatedBrackets.length === 0" class="no-brackets-message">
            <div class="icon-and-title">
                <i class="pi pi-search" style="font-size: 1.5rem; color: #007bff; margin-right: 10px;"></i>
                <h2 class="no-brackets-title">No Games Found</h2>
            </div>
            <p class="no-brackets-text">No games match your search criteria. Try adjusting your search terms.</p>
        </div>
        <div v-else v-for="bracket in filteredRelatedBrackets" :key="bracket.id" class="bracket-section">
            <div class="bracket-wrapper">
                <div class="bracket-header" style="display: flex; flex-direction: row; justify-content: space-between; align-items: center;">
                    <h2>{{ bracket.name }} ({{ bracket.type }})</h2>
                    <Button
                        text
                        rounded
                        :icon="expandedBrackets[getBracketIndex(bracket.id)] ? 'pi pi-chevron-up' : 'pi pi-chevron-down'"
                        @click="toggleBracket(getBracketIndex(bracket.id))"
                        class="bracket-toggle-button"
                    />
                </div>

                <div v-if="expandedBrackets[getBracketIndex(bracket.id)]" class="bracket-content-wrapper" :id="`bracket-content-${bracket.id}`">
                    <div class="view-toggle-buttons">
                        <Button
                            :label="'Bracket View'"
                            :class="['p-button-sm', bracketViewModes[getBracketIndex(bracket.id)] !== 'matches' ? 'p-button-primary' : 'p-button-outlined']"
                            @click="setBracketViewMode(getBracketIndex(bracket.id), 'bracket')"
                        />
                        <Button
                            :label="'Matches View'"
                            :class="['p-button-sm', bracketViewModes[getBracketIndex(bracket.id)] === 'matches' ? 'p-button-primary' : 'p-button-outlined']"
                            @click="setBracketViewMode(getBracketIndex(bracket.id), 'matches')"
                        />
                    </div>
                    <!-- Bracket View -->
                    <div v-show="bracketViewModes[getBracketIndex(bracket.id)] !== 'matches'">
                        <BracketView
                            :bracket="bracket"
                            :bracketIndex="getBracketIndex(bracket.id)"
                            :user="user"
                            :standingsRevision="standingsRevision"
                            :isFinalRound="isFinalRound"
                            :openMatchDialog="openMatchDialog"
                            :getRoundRobinStandings="getRoundRobinStandings"
                            :isRoundRobinConcluded="isRoundRobinConcluded"
                            :openScoringConfigDialog="openScoringConfigDialog"
                        />
                    </div>

                    <!-- Matches Card View -->
                    <div v-if="bracketViewModes[getBracketIndex(bracket.id)] === 'matches'">
                        <div class="match-filters">
                            <SelectButton
                                :modelValue="bracketMatchFilters[getBracketIndex(bracket.id)]"
                                @update:modelValue="val => setBracketMatchFilter(getBracketIndex(bracket.id), val)"
                                :options="matchStatusFilterOptions"
                                optionLabel="label"
                                optionValue="value"
                                aria-labelledby="match-status-filter"
                            />
                        </div>
                        <MatchesView
                            :bracket="bracket"
                            :bracketIndex="getBracketIndex(bracket.id)"
                            :user="user"
                            :filter="bracketMatchFilters[getBracketIndex(bracket.id)]"
                            :openMatchEditorFromCard="openMatchEditorFromCard"
                            :isFinalRound="isFinalRound"
                        />
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
        @confirm="saveChanges"
      />
    </div>

    <!-- Generic Error Dialog -->
    <ConfirmationDialog
        v-model:show="showGenericErrorDialog"
        title="Error"
        :message="genericErrorMessage"
        confirmText="OK"
        :show-cancel-button="false"
        confirmButtonClass="bg-red-600 hover:bg-red-700"
        @confirm="showGenericErrorDialog = false"
    />

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
            <button class="modal-button-secondary" @click="showAddAnnouncementModal = false">Cancel</button>
            <button class="modal-button-primary" @click="addAnnouncement" :disabled="saving">Post</button>
        </template>
    </Dialog>

    <ConfirmationDialog
        v-model:show="showDeleteAnnouncementConfirm"
        title="Delete Announcement?"
        message="Are you sure you want to delete this announcement?"
        @confirm="confirmDeleteAnnouncement"
    />

    <!-- Dialogs from Bracket.vue -->

    <!-- Match Update Confirmation Dialog -->
    <ConfirmationDialog
    v-model:show="showMatchUpdateConfirmDialog"
    title="Confirm Match Update"
    message="Are you sure you want to update this match? This action may trigger bracket progression and cannot be easily undone."
    confirmText="Yes, Update Match"
    cancelText="Cancel"
    :style="{ zIndex: 1102 }"
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
            class="modal-button-secondary"
        />
        <Button
            label="Save"
            @click="saveScoringConfig"
            class="modal-button-primary"
        />
        </div>
    </div>
    </Dialog>

    <!-- Shared Match Editor Dialog -->
    <MatchEditorDialog
        v-model:show="showMatchEditorDialog"
        v-model:matchData="selectedMatchData"
        :bracket="selectedBracketForDialog"
        @confirm="confirmMatchUpdate"
    />

    <Dialog v-model:visible="showMemoImageDialog" modal :header="eventDetails.memorandum?.filename" :style="{ width: '90vw', maxWidth: '1200px' }">
        <img :src="memoImageUrl" alt="Memorandum Image" class="w-full h-auto max-h-[80vh] object-contain" />
    </Dialog>
</div>
</template>

<style scoped>
.bracket-content-wrapper {
    margin-top: 1rem;
}


.bracket-toggle-button {
    width: 2.5rem;
    height: 2.5rem;
}

.bracket-toggle-button :deep(.pi) {
    font-size: 1.2rem;
    font-weight: 600;
    color: #4B5563; /* gray-600 */
    transition: color 0.2s;
}

.bracket-toggle-button:hover :deep(.pi) {
    color: #1F2937; /* gray-800 */
}

.search-container {
  display: flex;
  justify-content: flex-start;
  width: 100%;
}

.search-container .p-input-icon-left {
  position: relative;
  width: 100%;
}

.search-container .p-input-icon-left i {
  position: absolute;
  left: 0.75rem;
  top: 50%;
  transform: translateY(-50%);
  color: #6c757d;
}
.search-container .p-input-icon-left .p-inputtext {
  width: 100%;
  padding-left: 2.5rem;
}

.search-wrapper {
  display: flex;
  gap: 10px;
  align-items: center;
  width: 100%;
  max-width: 400px;
}

.search-wrapper .p-input-icon-left {
  position: relative;
  width: 100%;
}

.search-wrapper .p-input-icon-left i {
  position: absolute;
  left: 0.75rem;
  top: 50%;
  transform: translateY(-50%);
  color: #6c757d;
}

.search-wrapper .p-input-icon-left .p-inputtext {
  width: 100%;
  padding-left: 2.5rem;
}

.date-filter-btn {
  min-width: 40px;
  height: 40px;
  flex-shrink: 0;
}

.date-filter-container {
  background: white;
  padding: 15px;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  display: flex;
  flex-direction: column;
  gap: 10px;
  width: 100%;
  max-width: 400px;
}

.date-range-wrapper {
  display: flex;
  flex-direction: row;
  gap: 10px;
  align-items: flex-start;
}

.date-input-group {
  display: flex;
  flex-direction: column;
  gap: 5px;
  flex: 1;
}

.date-input-group label {
  font-size: 0.9rem;
  color: #666;
  font-weight: 500;
}

.date-filter-calendar {
  width: 100%;
}

.clear-date-btn {
  align-self: flex-end;
  color: #dc3545;
}

.clear-date-btn:hover {
  background-color: rgba(220, 53, 69, 0.1);
}
</style>
