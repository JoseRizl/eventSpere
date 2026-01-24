<script setup>
import { ref, computed, onMounted, nextTick, watch } from 'vue';
import { parse, format, parseISO, isValid, addDays, endOfDay, isWithinInterval } from 'date-fns';
import { usePage, router, Link } from '@inertiajs/vue3';
import LoadingSpinner from '@/Components/LoadingSpinner.vue';
import ConfirmationDialog from '@/Components/ConfirmationDialog.vue';
import SuccessDialog from '@/Components/SuccessDialog.vue';
import { useBracketState } from '@/composables/Brackets/useBracketState.js';
import { useBracketActions } from '@/composables/Brackets/useBracketActions.js';
import SearchFilterBar from '@/Components/SearchFilterBar.vue';
import BracketCard from '@/Components/Brackets/BracketCard.vue';
import MatchEditorDialog from '@/Components/Brackets/MatchEditorDialog.vue';
import AnnouncementsBoard from '@/Components/AnnouncementsBoard.vue';
import TaskEditor from '@/Components/TaskEditor.vue';
import { useActivities } from '@/composables/useActivities.js';
import { useTasks } from '@/composables/useTasks.js';
import { useAnnouncements } from '@/composables/useAnnouncements.js';
import { useEventValidation } from '@/composables/useEventValidation.js';

const props = defineProps({
  event: Object,
  committees: Array,
  tags: Array,
  employees: Array,
  categories: Array,
  relatedEvents: Array,
  errors: Object,
  all_users: Array, // Assuming this comes from shared props
  auth: Object, // Assuming this comes from shared props
  preloadedActivities: Array,
  preloadedMemorandum: Object,
  preloadedTasks: Array,
  preloadedAnnouncements: Array,
});

const hasAnyRole = (roles) => {
  if (!user.value || !user.value.roles) return false;
  return user.value.roles.some(role => roles.includes(role));
};

const isUserManagerOfBracket = (bracketId) => {
    if (!user.value) return false;
    if (hasAnyRole(['Admin', 'Principal'])) {
        return true;
    }
    if (!hasAnyRole(['TournamentManager'])) {
        return false;
    }

    const tasks = props.preloadedTasks || [];
    // Check if the user is a manager of this specific bracket
    return tasks.some(task =>
        (task.managers || []).some(manager => {
            if (manager.id !== user.value.id) return false;
            // The manager object from tasks should have the managed_brackets relation loaded
            return (manager.managed_brackets || []).some(b => b.id === bracketId);
        })
    );
};

const assignablePersonnel = computed(() => {
  const employeePersonnel = (employees.value || []).map(e => ({
    ...e,
    type: 'employee',
    id: Number(e.id) // Ensure ID is a number for consistent comparison
  }));

  const tournamentManagers = (props.all_users || [])
    .filter(u => u.roles && u.roles.includes('TournamentManager'))
    .map(u => ({
      ...u,
      name: `${u.name} (Tournament Manager)`, // To distinguish them in the UI
      type: 'user',
      id: Number(u.id) // Ensure ID is a number for consistent comparison
    }));

  return [...employeePersonnel, ...tournamentManagers];
});

const isAssignedManager = computed(() => {
    if (!user.value) return false;
    if (hasAnyRole(['Admin', 'Principal'])) {
        return true;
    }

    if (hasAnyRole(['TournamentManager'])) {
        const tasks = props.preloadedTasks || [];
        return tasks.some(task =>
            (task.managers || []).some(manager => manager.id === user.value.id)
        );
    }

    return false;
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
const showMemoDocDialog = ref(false);
const memoDocUrl = ref('');
const memoDocTitle = ref('');

const employees = computed(() => props.employees || []);
const relatedEvents = ref(props.relatedEvents || []);
const showDeleteScheduleConfirm = ref(false);
const originalEventDetails = ref(null);
const searchQuery = ref('');
const scheduleToDelete = ref(null);
const isLoadingBrackets = ref(true);
const isTaskDataLoading = ref(false);

// Announcement search
const announcementSearchQuery = ref('');
const announcementStartDateFilter = ref(null);
const announcementEndDateFilter = ref(null);
const showAnnouncementDateFilter = ref(false);

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
const allNewsProxy = ref(props.relatedEvents || []); // placeholder for composable signature
const {
    eventAnnouncements,
    addAnnouncement,
    updateAnnouncement,
    deleteAnnouncementById,
    // We don't use filteredAnnouncements from the composable here, as the board does its own filtering.
    // But we pass the reactive filters to the composable so it can refetch if needed.
} = useAnnouncements({ searchQuery: announcementSearchQuery, startDateFilter: announcementStartDateFilter, endDateFilter: announcementEndDateFilter }, allNewsProxy);

// For viewing announcement images
const showImageDialog = ref(false);
const selectedImageUrl = ref('');


const { validateEvent, formatDateForPicker, formatDisplayDate } = useEventValidation();

const { activities: activitiesState, fetchActivities: fetchActivitiesApi, saveActivities } = useActivities();
const tasksManager = useTasks();

const handleTaskSaveSuccess = ({ message }) => {
        tasksManager.isTaskModalVisible.value = false; // Close the modal
        successMessage.value = message; // Show success message
        showSuccessDialog.value = true;

    };

const handleCommitteeActionSuccess = (message) => {
    // Only show success message, don't close the modal
    successMessage.value = message;
    showSuccessDialog.value = true;
};

const handleTaskSaveError = (message) => {
    errorMessage.value = message;
    showErrorDialog.value = true;
};
// Bracket logic - Data state only
const bracketState = useBracketState();
const {
  brackets,
} = bracketState;

const fetchActivities = async (eventId) => {
  try {
    const data = await fetchActivitiesApi(eventId);
    const arr = Array.isArray(data) ? data : [];
    eventDetails.value.activities = arr.map((a, idx) => ({
      ...a,
      __uid: a.__uid || `${Date.now()}-${idx}-${Math.random().toString(36).slice(2, 8)}`
    }));
  } catch (error) {
    console.error('Error fetching activities:', error);
  }
};

const {
  // Actions
  fetchBrackets,
  handleByeRounds,
  // updateLines, // We will call this from a modified toggleBracket
  isFinalRound,
  getRoundRobinStandings,
  isRoundRobinConcluded,
  openMatchDialog,
  proceedWithMatchUpdate,
  openScoringConfigDialog,
  closeScoringConfigDialog,
  // saveScoringConfig,
  toggleBracket,
  setBracketViewMode,
  setBracketMatchFilter,
  openMatchEditorFromCard,
  getBracketTypeClass,
  getBracketStats,
  // Added actions for admin toggles
  toggleConsolationMatch,
  toggleAllowDraws,
  confirmToggleDraws,
  cancelToggleDraws,
  confirmToggleConsolation,
  cancelToggleConsolation,

  // UI State (automatically included from useBracketActions)
  expandedBrackets,
  showMatchEditorDialog,
  selectedMatch,
  selectedMatchData,
  roundRobinScoring,
  bracketMatchFilters,
  bracketViewModes,
  showScoringConfigDialog,
  standingsRevision,
  // Added UI state for dialogs
  showToggleDrawsDialog,
  showToggleConsolationDialog,
  pendingBracketIdx,
} = useBracketActions(bracketState);

const selectedBracketForDialog = computed(() => {
  if (selectedMatch.value) {
    const bracket = brackets.value[selectedMatch.value.bracketIdx];
    if (bracket) {
        return bracket;
    }
  }
});

const filteredBrackets = computed(() => {
  if (!searchQuery.value) {
    return brackets.value;
  }
  const query = searchQuery.value.toLowerCase().trim();
  return brackets.value.filter(bracket => {
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
    memorandum: props.preloadedMemorandum || props.event.memorandum || null,
    category_id: props.event.category?.id || props.event.category_id,
    activities: (props.event.activities || []).map((a, idx) => ({
      ...a,
      __uid: a.__uid || `${Date.now()}-${idx}-${Math.random().toString(36).slice(2, 8)}`
    })),
    startTime: props.event.startTime ? props.event.startTime.substring(0, 5) : '',
    endTime: props.event.endTime ? props.event.endTime.substring(0, 5) : '',
    // event.tags is now an array of tag objects from backend, convert to IDs for editing
    tags: (props.event.tags || []).map(tag => typeof tag === 'object' ? tag.id : tag)
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
    console.log('[EventDetails] toggleEdit - eventDetails.value on entering edit mode:', JSON.parse(JSON.stringify(eventDetails.value)));
  }
  editMode.value = !editMode.value;
};

const ALLOWED_MIME_TYPES = [
  'application/pdf',
  'application/msword',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
];

const handleMemoUpload = (event) => {
  const file = event.target.files[0];
  if (!file) return;

  const isImage = file.type?.startsWith('image/');
  const isAllowedDoc = ALLOWED_MIME_TYPES.includes(file.type) || /\.(pdf|doc|docx)$/i.test(file.name || '');

  if (!isImage && !isAllowedDoc) {
    errorMessage.value = 'Unsupported file type. Only images, PDFs, and Word documents (.doc, .docx) are allowed.';
    errorDialogMessage.value = errorMessage.value;
    showErrorDialog.value = true;
    event.target.value = '';
    return;
  }

  const reader = new FileReader();
  reader.onload = (e) => {
    eventDetails.value.memorandum = {
      type: isImage ? 'image' : 'file',
      content: e.target.result,
      filename: file.name,
      isNew: true, // Flag to indicate it's a new upload
    };
  };
  reader.onerror = () => {
    errorMessage.value = 'Failed to read the memorandum file.';
    errorDialogMessage.value = errorMessage.value;
    showErrorDialog.value = true;
  };
  reader.readAsDataURL(file);
};

// Add this near your other initialization code
onMounted(async () => {
  const urlParams = new URLSearchParams(window.location.search);
  const viewParam = urlParams.get('view');
  const shouldOpenTasks = urlParams.get('openTasks') === '1';
  if (viewParam === 'announcements') {
    currentView.value = 'announcements';
  }

  // Preload tasks/activities/announcements from props to avoid initial GETs
  if (Array.isArray(props.preloadedActivities)) {
    eventDetails.value.activities = props.preloadedActivities.map((a, idx) => ({ ...a, __uid: `${Date.now()}-${idx}-${Math.random().toString(36).slice(2, 8)}` }));
  }
  if (Array.isArray(props.preloadedAnnouncements)) {
    eventAnnouncements.value = [...props.preloadedAnnouncements];
  }

  isLoadingBrackets.value = true;
  // Fetch and populate local brackets for this event
  try {
    await fetchBrackets(props.event.id);
  } finally {
    isLoadingBrackets.value = false;
  }

  // Auto-open TaskEditor when coming from EventList with openTasks=1
  if (shouldOpenTasks) {
    handleOpenTaskEditor();
  }
});

const goBack = () => {
    // A more robust way to go back.
    // It checks if there's a previous page in the session history.
    // If the user landed here directly, it provides a sensible fallback.
    if (window.history.length > 2) {
        window.history.back();
    } else {
        router.visit(route('home'));
    }
};

// Initial announcements are preloaded via props; no GET here to achieve zero client GETs.

const getAbsoluteContentUrl = (content) => {
    if (!content) return '';
    if (typeof content === 'string' && content.startsWith('data:')) return content;
    if (typeof content === 'string' && /^https?:\/\//i.test(content)) return content;
    return `${window.location.origin}${content && content.startsWith('/') ? '' : '/'}${content || ''}`;
};

const guessFileExt = (filename) => {
    if (!filename || typeof filename !== 'string') return '';
    const idx = filename.lastIndexOf('.');
    return idx !== -1 ? filename.slice(idx + 1).toLowerCase() : '';
};

const canInlinePreview = (memo) => {
    if (!memo) return false;
    if (memo.type === 'image') return true;
    const ext = guessFileExt(memo.filename);
    if (ext === 'pdf') return true;
    if (typeof memo.content === 'string' && memo.content.startsWith('data:application/pdf')) return true;
    return false;
};

const downloadMemorandum = () => {
    const memo = eventDetails.value.memorandum;
    if (!memo || !memo.content) return;
    const link = document.createElement('a');
    link.href = getAbsoluteContentUrl(memo.content);
    link.download = memo.filename || 'document';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};

const openInNewTab = (url) => {
    const href = typeof url === 'string' ? url : '';
    if (!href) return;
    window.open(href, '_blank', 'noopener');
};

const viewMemorandum = () => {
    const memo = eventDetails.value.memorandum;
    if (!memo || !memo.content) return;
    const url = getAbsoluteContentUrl(memo.content);
    if (memo.type === 'image') {
        memoImageUrl.value = url;
        showMemoImageDialog.value = true;
    } else {
        memoDocUrl.value = url;
        memoDocTitle.value = memo.filename || 'Document';
        showMemoDocDialog.value = true;
    }
};

const truncateFilename = (name, max = 40) => {
    if (!name || typeof name !== 'string') return '';
    if (name.length <= max) return name;
    const dotIdx = name.lastIndexOf('.');
    const ext = dotIdx > 0 ? name.slice(dotIdx) : '';
    const base = dotIdx > 0 ? name.slice(0, dotIdx) : name;
    const keep = Math.max(10, max - ext.length - 3);
    return base.slice(0, keep) + '…' + ext;
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


const addActivity = () => {
  if (!Array.isArray(eventDetails.value.activities)) {
    eventDetails.value.activities = [];
  }
  eventDetails.value.activities.push({
    __uid: `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
    title: '',
    date: eventDetails.value.startDate || '',
    startTime: '',
    endTime: '',
    location: ''
  });
};

const promptDeleteSchedule = (activity) => {
  scheduleToDelete.value = activity;
  showDeleteScheduleConfirm.value = true;
};

const confirmDeleteSchedule = () => {
  if (Array.isArray(eventDetails.value.activities) && scheduleToDelete.value) {
    const idx = eventDetails.value.activities.findIndex(a => (a.__uid && scheduleToDelete.value.__uid) ? a.__uid === scheduleToDelete.value.__uid : a === scheduleToDelete.value);
    if (idx !== -1) {
      eventDetails.value.activities.splice(idx, 1);
    }
  }
  showDeleteScheduleConfirm.value = false;
  scheduleToDelete.value = null;
};

const sortedActivities = computed(() => {
  const activities = Array.isArray(eventDetails.value.activities) ? [...eventDetails.value.activities] : [];
  return activities.sort((a, b) => {
    const ad = formatDateForPicker(a.date) || new Date(0);
    const bd = formatDateForPicker(b.date) || new Date(0);
    if (ad.getTime() !== bd.getTime()) return ad - bd;
    return (a.startTime || '').localeCompare(b.startTime || '');
  });
});

const saveChanges = () => {
  saving.value = true;
  errorMessage.value = null;
  showErrorDialog.value = false;

  const validationError = validateEvent(eventDetails.value, { isSubmitting: true, originalEvent: originalEventDetails.value });
  if (validationError) {
    saving.value = false;
    errorMessage.value = validationError;
    showErrorDialog.value = true;
    errorDialogMessage.value = errorMessage.value;
    return;
  }

  // Payload for the main event details
  const eventPayload = {
    id: eventDetails.value.id,
    title: eventDetails.value.title,
    description: eventDetails.value.description,
    image: eventDetails.value.image, // This will be handled on the backend
    venue: eventDetails.value.venue,
    category_id: eventDetails.value.category_id,
    startDate: (() => { const d = formatDateForPicker(eventDetails.value.startDate); return d ? format(d, 'MMM-dd-yyyy') : ''; })(),
    endDate: (() => { const d = formatDateForPicker(eventDetails.value.endDate); return d ? format(d, 'MMM-dd-yyyy') : ''; })(),
    startTime: eventDetails.value.startTime ? eventDetails.value.startTime.padStart(5, '0') : null,
    endTime: eventDetails.value.endTime ? eventDetails.value.endTime.padStart(5, '0') : null,
    tags: eventDetails.value.tags || [], // Only IDs
    memorandum: eventDetails.value.memorandum,
    activities: (eventDetails.value.activities || []).map(({ __uid, ...activity }) => activity),
  };

  console.log('[EventDetails] saveChanges - payload being sent to server:', eventPayload);

  // First, save the main event details
  router.put(route('event.update', { id: eventDetails.value.id }), eventPayload, {
    preserveScroll: true,
    onError: (errors) => {
      saving.value = false;
      // Use the first error message available, prioritizing 'title' for this specific validation.
      let message = errors.title || Object.values(errors)[0];
      if (Array.isArray(message)) {
        message = message[0];
      }
      message = message || 'An unknown validation error occurred.';
      errorMessage.value = message;
      errorDialogMessage.value = message;
      showErrorDialog.value = true;
    },
    onSuccess: async () => {
      try {
        const activitiesPayload = (eventDetails.value.activities || []).map(({ __uid, title, date, startTime, endTime, location }) => ({ title, date, startTime, endTime, location }));
        await saveActivities(eventDetails.value.id, activitiesPayload);

        showSuccessDialog.value = true;
        successMessage.value = 'The event was updated successfully.';
        editMode.value = false;
      } catch (err) {
        const msg = err?.response?.data?.message || err?.message || 'Failed to save related data.';
        errorMessage.value = `Event details saved, but failed to save activities/tasks: ${msg}`;
        errorDialogMessage.value = errorMessage.value;
        showErrorDialog.value = true;
      } finally {
        saving.value = false;
      }
    }
  });
};

const formatDisplayTime = (timeString) => {
  if (!timeString) return '';
  try {
    // The time string is expected to be in 'HH:mm' format.
    // We don't need a base date, just parse the time part.
    const [hours, minutes] = timeString.split(':').map(Number);
    if (isNaN(hours) || isNaN(minutes)) return timeString;
    const date = new Date();
    date.setHours(hours, minutes, 0);
    const parsed = date;
    return format(parsed, 'hh:mm a');
  } catch (e) {
    return timeString || '';
  }
};


// Date-related functions

const startDateModel = computed({
  get() {
    return formatDateForPicker(eventDetails.value.startDate);
  },
  set(value) {
    eventDetails.value.startDate = value ? format(value, 'MMM-dd-yyyy') : '';
  }
});

const endDateModel = computed({
  get() {
    return formatDateForPicker(eventDetails.value.endDate);
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

// Helpers for activity time input bounds
const dateOnlyStr = (d) => {
  const dt = formatDateForPicker(d);
  return dt ? format(dt, 'yyyy-MM-dd') : null;
};

const getActivityStartMin = (act) => {
  if (!eventDetails.value.startTime) return null;
  return dateOnlyStr(act.date) === dateOnlyStr(eventDetails.value.startDate) ? eventDetails.value.startTime : null;
};

const getActivityStartMax = (act) => {
  if (!eventDetails.value.endTime) return null;
  return dateOnlyStr(act.date) === dateOnlyStr(eventDetails.value.endDate) ? eventDetails.value.endTime : null;
};

const getActivityEndMin = (act) => {
  if (act.startTime) return act.startTime;
  if (!eventDetails.value.startTime) return null;
  return dateOnlyStr(act.date) === dateOnlyStr(eventDetails.value.startDate) ? eventDetails.value.startTime : null;
};

const getActivityEndMax = (act) => {
  if (!eventDetails.value.endTime) return null;
  return dateOnlyStr(act.date) === dateOnlyStr(eventDetails.value.endDate) ? eventDetails.value.endTime : null;
};

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
};

const handleOpenTaskEditor = async () => {
    console.log('[EventDetails] Opening Task Editor...');
    isTaskDataLoading.value = true;
    try {
        // Ensure brackets are loaded, as they are fetched client-side and might not be ready.
        await fetchBrackets(props.event.id);

        console.log('[EventDetails] Data for modal:', {
            tasks: props.preloadedTasks,
            committees: committees.value,
            personnel: assignablePersonnel.value,
            brackets: brackets.value
        });

        // Verify that all necessary data from props is available.
        if (!props.preloadedTasks || !committees.value || !assignablePersonnel.value) {
            console.error('Task editor opened with incomplete data props.');
            throw new Error('Essential event data is missing. Please refresh the page and try again.');
        }

        tasksManager.openTaskModal(
            { ...props.event, tasks: props.preloadedTasks },
            committees.value,
            assignablePersonnel.value,
            brackets.value
        );
    } catch (error) {
        console.error("Error preparing task editor:", error);
        // Display a user-friendly error message.
        errorMessage.value = error.message || "Failed to load task management data. Please try again.";
        showErrorDialog.value = true;
    } finally {
        isTaskDataLoading.value = false;
    }
};

</script>

<template>
<div>
    <div class="min-h-screen py-8 px-4 mx-auto">
        <!-- Back Button for non-management -->
        <div v-if="!user || !hasAnyRole(['Admin', 'Principal', 'TournamentManager'])" class="mb-4 -ml-2">
            <Button
                icon="pi pi-arrow-left"
                @click="goBack"
                class="p-button-secondary p-button-text p-button-rounded"
                v-tooltip.bottom="'Back to previous page'"
                aria-label="Back to previous page"
            />
        </div>

        <div class="mt-4">
            <div class="flex flex-col md:flex-row gap-6">
                <!-- Main content (left side) - takes remaining space -->
                <div class="flex-1">
                    <!-- Banner Image -->
                    <div class="w-full bg-gray-800 rounded-lg shadow-md overflow-hidden relative mb-6">
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

                    <div v-if="currentView === 'details'" class="space-y-6">
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
                            v-if="hasAnyRole(['Admin', 'Principal']) && !eventDetails.archived"
                            @click="toggleEdit"
                            :class="editMode ? 'modal-button-danger' : 'create-button'"
                            class="ml-4"
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
                    <p v-else class="text-gray-800 whitespace-pre-line text-sm" v-html="formattedDescription"></p>
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
                                <MultiSelect
                                    v-model="eventDetails.tags"
                                    :options="filteredTags"
                                    optionValue="id"
                                    optionLabel="name"
                                    placeholder="Select tags"
                                    class="w-full"
                                    :showToggleAll="false"
                                    display="chip"
                                >
                                    <template #chip="{ value }">
                                        <div
                                            v-if="tagsMap[value]"
                                            class="flex items-center gap-2 px-2 py-1 rounded text-white text-xs"
                                            style="background-color: #3B82F6;"
                                        >
                                            {{ tagsMap[value].name }}
                                            <button
                                                type="button"
                                                class="text-white hover:text-gray-200"
                                                @click.stop="removeTag(tagsMap[value])"
                                                v-tooltip.top="'Remove Tag'"
                                            >
                                                ✕
                                            </button>
                                        </div>
                                        <div v-else class="flex items-center gap-2 px-2 py-1 rounded bg-gray-500 text-white text-xs">
                                            {{ value }}
                                        </div>
                                    </template>
                                </MultiSelect>
                            </div>

                            <!-- Edit Mode: Dates and Times -->
                            <div class="grid grid-cols-2 gap-4">
                                <div class="flex flex-col">
                                    <label class="text-sm font-medium mb-1">Start Date</label>
                                    <DatePicker v-model="startDateModel" dateFormat="M-dd-yy" showIcon class="w-full" />
                                </div>
                                <div class="flex flex-col">
                                    <label class="text-sm font-medium mb-1">Start Time</label>
                                    <input type="time" v-model="eventDetails.startTime" class="border p-2 rounded" />
                                </div>
                                <div class="flex flex-col">
                                    <label class="text-sm font-medium mb-1">End Date</label>
                                    <DatePicker v-model="endDateModel" dateFormat="M-dd-yy" showIcon class="w-full" :minDate="startDateModel" />
                                </div>
                                <div class="flex flex-col">
                                    <label class="text-sm font-medium mb-1">End Time</label>
                                    <input type="time" v-model="eventDetails.endTime" class="border p-2 rounded" :min="dateOnlyStr(eventDetails.startDate) === dateOnlyStr(eventDetails.endDate) ? eventDetails.startTime : null" />
                                </div>
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
                                            <span v-for="tagId in eventDetails.tags" :key="tagId" style="background-color: #3B82F6; color: #fff;" class="text-xs font-semibold py-1 px-2 rounded">
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
                        <i class="pi pi-file flex-shrink-0"></i>
                        <span class="flex-1 cursor-pointer text-blue-600 hover:underline truncate min-w-0 max-w-full" @click="viewMemorandum" :title="eventDetails.memorandum.filename">
                            {{ truncateFilename(eventDetails.memorandum.filename) }}
                        </span>
                        <Button
                            icon="pi pi-download"
                            class="p-button-rounded p-button-text"
                            @click="downloadMemorandum"
                            v-tooltip.top="'Download Memorandum'"
                        />
                        <Button
                            icon="pi pi-times"
                            class="p-button-rounded p-button-danger p-button-text"
                            @click="eventDetails.memorandum = null"
                            v-tooltip.top="'Remove Memorandum'"
                        />
                        </div>

                        <!-- If no memorandum uploaded -->
                        <div v-else class="flex justify-center items-center border-2 border-dashed rounded-md p-4">
                        <input type="file" ref="memoFileInput" @change="handleMemoUpload($event)" accept="image/*,.pdf,.doc,.docx" class="hidden" />
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
                    <div class="flex flex-col sm:flex-row gap-2">
                    <Button @click="viewMemorandum" class="p-button-secondary w-full sm:w-auto" :title="eventDetails.memorandum.filename">
                    <div class="flex items-center gap-2 overflow-hidden">
                    <i class="pi pi-eye"></i>
                    <span class="truncate">View {{ truncateFilename(eventDetails.memorandum.filename) }}</span>
                    </div>
                    </Button>
                    <Button @click="downloadMemorandum" class="p-button-outlined w-full sm:w-auto">
                    <div class="flex items-center gap-2 overflow-hidden">
                    <i class="pi pi-download"></i>
                    <span class="truncate">Download</span>
                    </div>
                    </Button>
                    </div>
                    </div>
                    </div>
                    </div>

                    <!-- Activities -->
                    <div v-if="editMode || (eventDetails.activities && eventDetails.activities.length > 0)">
                    <h2 class="font-semibold mb-1">Activities</h2>
                    <div v-if="editMode">
                        <div v-for="(activity, i) in sortedActivities" :key="activity.__uid || i" class="flex flex-col md:flex-row items-center gap-2 mb-2">
                            <InputText v-model="activity.title" placeholder="Activity title" class="flex-1" />
                            <DatePicker
                              :modelValue="formatDateForPicker(activity.date)"
                              @update:modelValue="val => activity.date = val ? format(val, 'MMM-dd-yyyy') : ''"
                              :minDate="startDateModel"
                              :maxDate="endDateModel"
                              dateFormat="M-dd-yy"
                              showIcon
                              class="w-full md:w-44"
                            />
                            <input v-model="activity.startTime" type="time" class="border p-1 rounded w-28" :min="getActivityStartMin(activity)" :max="getActivityStartMax(activity)" />
                            <span>-</span>
                            <input v-model="activity.endTime" type="time" class="border p-1 rounded w-28" :min="getActivityEndMin(activity)" :max="getActivityEndMax(activity)" />
                            <InputText v-model="activity.location" placeholder="Location (optional)" class="flex-1" />
                            <button @click="promptDeleteSchedule(activity)" class="text-red-500">✕</button>
                        </div>
                        <button @click="addActivity" class="text-blue-500 text-sm mt-2">+ Add Activity</button>
                    </div>
                    <div v-else>
                        <div v-if="sortedActivities.length > 0" class="space-y-2">
                            <div v-for="(act, idx) in sortedActivities" :key="idx" class="flex items-center justify-between p-2 border rounded">
                                <div class="flex items-center gap-3">
                                    <i class="pi pi-clock text-gray-500"></i>
                                    <div>
                                        <div class="text-sm font-medium">{{ act.title || 'Untitled activity' }}</div>
                                        <div class="text-xs text-gray-600">
                                            {{ formatDisplayDate(act.date) }} • {{ formatDisplayTime(act.startTime) }} - {{ formatDisplayTime(act.endTime) }}
                                            <span v-if="act.location"> • {{ act.location }}</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-gray-500 italic p-4">No activities.</p>
                    </div>
                    </div>

                    <!-- Error Message -->
                    <div v-if="errorMessage" class="text-red-500 text-sm mt-2">
                    {{ errorMessage }}
                    </div>

                    <!-- Save Button -->
                    <button
                    v-if="editMode"
                    @click="showSaveConfirmDialog = true"
                    class="mt-4 self-end modal-button-primary"
                    :disabled="saving"
                    >
                    <i v-if="saving" class="pi pi-spin pi-spinner mr-2"></i>
                    {{ saving ? 'Saving...' : 'Save Changes' }}
                    </button>

                    <!-- Committee -->
                    <div v-if="props.preloadedTasks && props.preloadedTasks.length > 0 || hasAnyRole(['Admin', 'Principal', 'TournamentManager'])">
                        <div class="flex justify-between items-center mb-2">
                            <h2 class="font-semibold">Tasks & Committees:</h2>
                            <Button
                                v-if="hasAnyRole(['Admin', 'Principal'])"
                                :icon="isTaskDataLoading ? 'pi pi-spin pi-spinner' : 'pi pi-list'"
                                label="Manage Tasks"
                                class="p-button-sm manage-tasks-btn"
                                @click="handleOpenTaskEditor"
                                v-tooltip.top="'Manage Tasks'"
                                :disabled="isTaskDataLoading"
                            />
                        </div>
                        <div v-if="props.preloadedTasks && props.preloadedTasks.length > 0" class="space-y-3">
                            <div v-for="(taskItem, index) in props.preloadedTasks" :key="index" class="p-3 border rounded-lg bg-gray-50/50">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="font-semibold text-gray-800">{{ taskItem.task || 'No task specified' }}</p>
                                        <p class="text-xs text-gray-500">{{ taskItem.committee?.name || 'No committee' }}</p>
                                    </div>
                                </div>
                                <div class="mt-3 flex items-center gap-2 flex-wrap">
                                    <span v-if="(!taskItem.employees || taskItem.employees.length === 0) && (!taskItem.managers || taskItem.managers.length === 0)" class="text-gray-500 italic text-xs">No personnel assigned</span>

                                    <!-- Display employees -->
                                    <div v-for="employee in taskItem.employees" :key="`emp-${employee.id}`" class="flex items-center gap-2 bg-white rounded-full px-2 py-1 border shadow-sm" v-tooltip.top="employee.name">
                                        <Avatar :label="employee.name ? employee.name.split(' ').map(n => n[0]).join('').toUpperCase() : '?'" shape="circle" size="small" />
                                        <span class="text-xs font-medium text-gray-800">{{ employee.name }}</span>
                                    </div>

                                    <!-- Display managers -->
                                    <div v-for="manager in taskItem.managers" :key="`mgr-${manager.id}`" class="flex items-center gap-2 bg-blue-100 rounded-full px-2 py-1 border border-blue-200 shadow-sm" v-tooltip.top="`${manager.name} (Tournament Manager)`">
                                        <Avatar :label="manager.name ? manager.name.split(' ').map(n => n[0]).join('').toUpperCase() : '?'" shape="circle" size="small" class="bg-blue-500 text-white" />
                                        <span class="text-xs font-medium text-blue-800">{{ manager.name }} (Tournament Manager)</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center text-gray-500 py-4 border-2 border-dashed rounded-lg">
                            <p class="text-sm">No tasks or committees have been assigned to this event yet.</p>
                            <p v-if="hasAnyRole(['Admin', 'Principal'])" class="text-xs mt-1">Click the <strong>Manage Tasks</strong> button to start assigning tasks.</p>
                        </div>
                    </div>
                </div>
                    </div>

    <div v-if="currentView === 'announcements'">
    <div class="w-full bg-white rounded-lg shadow-md p-6">
        <div class="mb-4">
            <Link :href="route('home', { view: 'announcements' })" class="text-blue-600 hover:underline text-sm flex items-center gap-2">
                <i class="pi pi-arrow-left"></i>
                Back to Main Announcement Board
            </Link>
        </div>
        <!-- Filters for Announcements -->
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <SearchFilterBar
                v-model:searchQuery="announcementSearchQuery"
                placeholder="Search announcements..."
                :show-date-filter="true"
                :is-date-filter-active="showAnnouncementDateFilter"
                :show-clear-button="!!(announcementSearchQuery || announcementStartDateFilter || announcementEndDateFilter)"
                @toggle-date-filter="toggleAnnouncementDateFilter"
                @clear-filters="clearAnnouncementFilters"
            />
        </div>

        <!-- Date Filter Calendar -->
        <div v-if="showAnnouncementDateFilter" class="date-filter-container mb-4 max-w-md">
            <div class="flex flex-col sm:flex-row gap-2">
                <div class="flex-1">
                    <label>From:</label>
                    <DatePicker
                        v-model="announcementStartDateFilter"
                        dateFormat="M-dd-yy"
                        :showIcon="true"
                        placeholder="Start date"
                        class="w-full"
                    />
                </div>
                <div class="date-input-group">
                    <label>To:</label>
                    <DatePicker
                        v-model="announcementEndDateFilter"
                        dateFormat="M-dd-yy"
                        :showIcon="true"
                        placeholder="End date"
                        class="w-full"
                    />
                </div>
            </div>
            <Button v-if="announcementStartDateFilter || announcementEndDateFilter" icon="pi pi-times" class="p-button-text p-button-rounded clear-date-btn" @click="clearAnnouncementDateFilter" v-tooltip.top="'Clear date filter'" />
        </div>

        <AnnouncementsBoard
            :announcements="eventAnnouncements"
            :search-query="announcementSearchQuery"
            :start-date-filter="announcementStartDateFilter"
            :end-date-filter="announcementEndDateFilter"
            context="event"
            :event-id="props.event.id"
            @announcement-added="addAnnouncement"
            @announcement-updated="updateAnnouncement"
            @announcement-deleted="deleteAnnouncementById"
        />
    </div>
                    </div>
                </div>

                <!-- Related events sidebar (right side) -->
                <div v-if="currentView === 'details' && normalizedRelatedEvents.length > 0" class="md:w-80 flex-shrink-0">
                    <div class="bg-white shadow-md rounded-lg p-4 sticky top-4">
                        <h3 class="font-bold text-lg mb-4">Related Events</h3>
                        <div class="space-y-4">
                            <div
                                v-for="event in normalizedRelatedEvents"
                                :key="event.id"
                                class="cursor-pointer hover:bg-gray-50 rounded p-2 transition-colors"
                                @click="router.visit(`/events/${event.id}`)"
                            >
                                <div class="flex gap-3">
                                    <div class="w-24 h-16 flex-shrink-0">
                                        <img :src="event.image || '/placeholder-event.jpg'" :alt="event.title" class="w-full h-full object-cover rounded" />
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <h4 class="font-medium text-sm truncate">{{ event.title }}</h4>
                                        <p class="text-xs text-gray-500 mt-1">{{ formatDisplayDate(event.startDate) }}</p>
                                        <div class="flex flex-wrap gap-1 mt-1">
                                            <span v-for="tag in event.tags?.slice(0, 2)" :key="tag.id" class="text-xs px-1.5 py-0.5 rounded" style="background-color: #3B82F6; color: #fff;">
                                                {{ tag.name }}
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

      <!-- Brackets Section -->
      <div v-if="isLoadingBrackets" class="mx-auto mt-6">
          <h2 class="section-title mb-4">Games</h2>
          <div class="space-y-6">
              <div v-for="i in 3" :key="`skel-${i}`" class="bg-white rounded-lg shadow-md border-l-4 border-gray-200">
                  <!-- Card Header Skeleton -->
                  <div class="p-4">
                      <div class="flex justify-between items-start">
                          <!-- Left: Title and Tags Skeleton -->
                          <div class="flex-grow pr-4">
                              <Skeleton width="60%" height="1.75rem" class="mb-3" />
                              <div class="flex items-center gap-2 mt-1 mb-4">
                                  <Skeleton shape="circle" size="1rem" />
                                  <Skeleton width="40%" height="1rem" />
                              </div>
                              <div class="flex items-center gap-2">
                                  <Skeleton width="7rem" height="1.75rem" borderRadius="9999px" />
                                  <Skeleton width="6rem" height="1.75rem" borderRadius="9999px" />
                              </div>
                          </div>
                          <!-- Right: Action Buttons Skeleton -->
                          <div class="flex-shrink-0 flex items-center gap-1">
                              <Skeleton shape="circle" size="2.5rem" />
                          </div>
                      </div>
                  </div>

                  <!-- Progress Bar and Stats Skeleton -->
                  <div class="px-4 pb-4">
                      <!-- Progress section -->
                      <div class="progress-section mb-4">
                          <div class="flex justify-between text-sm mb-1">
                              <Skeleton width="5rem" height="1rem" />
                              <Skeleton width="8rem" height="1rem" />
                          </div>
                          <Skeleton height=".75rem" borderRadius="9999px" />
                      </div>

                      <!-- Stats section -->
                      <div class="grid grid-cols-3 gap-2 mt-4 pt-4 border-t border-gray-200">
                          <div class="stat-item flex items-center gap-2">
                              <Skeleton shape="circle" size="2rem" />
                              <div>
                                  <Skeleton width="4rem" height="1rem" class="mb-1" />
                                  <Skeleton width="6rem" height="0.75rem" />
                              </div>
                          </div>
                          <div class="stat-item flex items-center gap-2">
                              <Skeleton shape="circle" size="2rem" />
                              <div>
                                  <Skeleton width="3rem" height="1rem" class="mb-1" />
                                  <Skeleton width="4rem" height="0.75rem" />
                              </div>
                          </div>
                          <div class="stat-item flex items-center gap-2">
                              <Skeleton shape="circle" size="2rem" />
                              <div>
                                  <Skeleton width="6rem" height="1rem" class="mb-1" />
                                  <Skeleton width="3rem" height="0.75rem" />
                              </div>
                          </div>
                      </div>
                  </div>
              </div>
          </div>
      </div>
      <div v-else-if="brackets.length > 0" class="mx-auto mt-6">
        <h2 class="section-title mb-0">Games</h2>
        <div v-if="brackets.length > 1" class="my-4">
            <SearchFilterBar
                v-model:searchQuery="searchQuery"
                placeholder="Search games..."
                :show-date-filter="false"
                :show-clear-button="!!searchQuery"
                @clear-filters="searchQuery = ''" />
        </div>
        <div v-if="filteredBrackets.length === 0" class="no-brackets-message">
            <div class="icon-and-title">
                <i class="pi pi-search" style="font-size: 1.5rem; color: #007bff; margin-right: 10px;"></i>
                <h2 class="no-brackets-title">No Games Found</h2>
            </div>
            <p class="no-brackets-text">No games match your search criteria. Try adjusting your search terms.</p>
        </div>
        <div v-else>
            <BracketCard
                v-for="(bracket, index) in filteredBrackets"
                :key="bracket.id"
                :bracket="bracket"
                :bracketIndex="index"
                :user="user"
                :isExpanded="expandedBrackets[index] || false"
                :viewMode="bracketViewModes[index]"
                :matchFilter="bracketMatchFilters[index]"
                :standingsRevision="standingsRevision"
                :getBracketStats="getBracketStats"
                :getBracketTypeClass="getBracketTypeClass"
                :isFinalRound="isFinalRound"
                :getRoundRobinStandings="getRoundRobinStandings"
                :isArchived="eventDetails.archived"
                :isRoundRobinConcluded="isRoundRobinConcluded"
                :can-manage="isUserManagerOfBracket(bracket.id)"
                :onOpenMatchDialog="isUserManagerOfBracket(bracket.id) ? openMatchDialog : null"
                :onOpenScoringConfigDialog="isUserManagerOfBracket(bracket.id) ? openScoringConfigDialog : null"
                :onOpenMatchEditorFromCard="isUserManagerOfBracket(bracket.id) ? openMatchEditorFromCard : null"
                :onToggleConsolationMatch="toggleConsolationMatch"
                :onToggleAllowDraws="toggleAllowDraws"
                @toggle-bracket="() => toggleBracket(bracket, index)"
                @set-view-mode="({ index, mode }) => setBracketViewMode(index, mode)"
                @set-match-filter="({ index, filter }) => setBracketMatchFilter(index, filter)"
                :showEventLink="false"
                :showAdminControls="true"
            />
        </div>
      </div>

      <!-- Loading Dialog -->
      <LoadingSpinner :show="saving" />

      <!-- Success Message Dialog -->
      <SuccessDialog
        v-model:show="showSuccessDialog"
        :message="successMessage"
      />

      <!-- Delete Schedule Confirmation Dialog -->
      <ConfirmationDialog
        v-model:show="showDeleteScheduleConfirm"
        title="Clear Activity?"
        message="Are you sure you want to clear this Activity?"
        confirmButtonClass="modal-button-danger"
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

      <!-- Toggle Draws Confirmation Dialog -->
      <ConfirmationDialog
        v-model:show="showToggleDrawsDialog"
        title="Toggle Draws"
        :message="brackets[pendingBracketIdx]?.allow_draws ? 'Disable draws for this Round Robin tournament?' : 'Enable draws for this Round Robin tournament?'"
        :confirmText="brackets[pendingBracketIdx]?.allow_draws ? 'Disable Draws' : 'Enable Draws'"
        cancelText="Cancel"
        @confirm="confirmToggleDraws"
        @cancel="cancelToggleDraws"
      />

      <!-- Toggle Consolation Match Confirmation Dialog -->
      <ConfirmationDialog
        v-model:show="showToggleConsolationDialog"
        title="Toggle 3rd Place Match"
        :message="pendingBracketIdx !== null && brackets[pendingBracketIdx]?.matches.flat().some(m => m.bracket_type === 'consolation')
            ? 'Are you sure you want to remove the 3rd place match?'
            : 'Are you sure you want to add a 3rd place match?'"
        :confirmText="pendingBracketIdx !== null && brackets[pendingBracketIdx]?.matches.flat().some(m => m.bracket_type === 'consolation') ? 'Yes, Remove' : 'Yes, Add'"
        cancelText="Cancel"
        :confirmButtonClass="pendingBracketIdx !== null && brackets[pendingBracketIdx]?.matches.flat().some(m => m.bracket_type === 'consolation') ? 'modal-button-danger' : 'modal-button-primary'"
        @confirm="confirmToggleConsolation"
        @cancel="cancelToggleConsolation"
      />
    </div>

    <!-- Generic Error Dialog -->
    <div v-if="showErrorDialog" class="fixed inset-0 bg-black bg-opacity-30 flex items-center justify-center" style="z-index: 9998;">
      <div class="bg-white p-6 rounded-lg shadow-lg max-w-sm w-full">
        <h2 class="text-lg font-semibold text-red-700 mb-2">Error</h2>
        <p class="text-sm text-gray-700 mb-4">{{ errorDialogMessage || errorMessage || 'An unexpected error occurred.' }}</p>
        <div class="flex justify-end">
          <button @click="showErrorDialog = false" class="modal-button-danger">Close</button>
        </div>
      </div>
    </div>

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
        @confirm="proceedWithMatchUpdate"
    />

    <Dialog v-model:visible="showMemoImageDialog" modal :dismissableMask="true" :header="eventDetails.memorandum?.filename" :style="{ width: '90vw', maxWidth: '1200px' }">
        <img :src="memoImageUrl" alt="Memorandum Image" class="w-full h-auto max-h-[80vh] object-contain" />
    </Dialog>

    <Dialog v-model:visible="showMemoDocDialog" modal :dismissableMask="true" :header="memoDocTitle" :style="{ width: '90vw', maxWidth: '1200px' }">
        <template v-if="canInlinePreview(eventDetails.memorandum)">
            <object :data="memoDocUrl" type="application/pdf" style="width: 100%; height: 80vh;">
                <iframe :src="memoDocUrl" style="width: 100%; height: 80vh;" frameborder="0"></iframe>
            </object>
        </template>
        <template v-else>
            <p class="text-sm text-gray-600 mb-3">Preview not available. Only images and PDFs can be viewed inline.</p>
            <div class="flex gap-2">
                <Button label="Download" icon="pi pi-download" class="p-button-secondary" @click="downloadMemorandum" />
            </div>
        </template>
    </Dialog>

    <!-- Image Viewer Dialog -->
    <Dialog v-model:visible="showImageDialog" modal :dismissableMask="true" header="Image" :style="{ width: '90vw', maxWidth: '1200px' }">
        <img :src="selectedImageUrl" alt="Announcement Image" class="w-full h-auto max-h-[80vh] object-contain" />
    </Dialog>

    <!-- Task Editor Component -->
    <TaskEditor
        :tasks-manager="tasksManager"
        :committees="committees"
        :employees="assignablePersonnel"
        :brackets="brackets"
        :is-editable="isAssignedManager"
        @save-success="handleTaskSaveSuccess"
        @save-error="handleTaskSaveError"
        @committee-action-success="handleCommitteeActionSuccess"
    />
</div>
</template>

<style scoped>
.bracket-content-wrapper {
    margin-top: 1rem;
}

.manage-tasks-btn {
    background: linear-gradient(135deg, #0872a3 0%, #2121c8 100%) !important;
    border: none !important;
    color: white !important;
}

.manage-tasks-btn:hover {
    background: linear-gradient(135deg, #2121c8 0%, #073b53 100%) !important;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 119, 179, 0.3);
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
  gap: 10px;
}
.date-filter-container {
  background: white;
  padding: 15px;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  gap: 10px;
  align-items: flex-start;
}
.date-input-group {
  display: flex;
  flex-direction: column;
  gap: 5px;
  flex: 1;
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

@media (min-width: 640px) {
    .search-wrapper {
        max-width: 400px;
    }
}

@media (max-width: 420px) {
    .search-wrapper {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>
