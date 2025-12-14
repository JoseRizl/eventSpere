<script setup>
import { ref, computed } from 'vue';
import { usePage, router, Link } from '@inertiajs/vue3';
import axios from 'axios';
import { format, parseISO } from 'date-fns';
import SearchFilterBar from '@/Components/SearchFilterBar.vue';
import ConfirmationDialog from '@/Components/ConfirmationDialog.vue';
import SuccessDialog from '@/Components/SuccessDialog.vue';
import LoadingSpinner from '@/Components/LoadingSpinner.vue';

const props = defineProps({
    announcements: {
        type: Array,
        required: true,
    },
    context: {
        type: String,
        default: 'event', // 'event' or 'home'
    },
    eventId: { // Only used in 'event' context
        type: String,
        default: null,
    },
    searchQuery: {
        type: String,
        default: '',
    },
    startDateFilter: {
        type: [Date, String],
        default: null,
    },
    endDateFilter: {
        type: [Date, String],
        default: null,
    },
    eventsForPicker: { // Only used in 'home' context
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['announcement-added', 'announcement-updated', 'announcement-deleted']);

const page = usePage();
const user = computed(() => page.props.auth.user);

// State Management
const saving = ref(false);
const showSuccessDialog = ref(false);
const successMessage = ref('');
const showErrorDialog = ref(false);
const errorMessage = ref('');

const filteredAnnouncements = computed(() => {
    let items = props.announcements;
    const query = props.searchQuery.toLowerCase().trim();
    if (query) {
        items = items.filter(ann => {
            const messageMatch = ann.message?.toLowerCase().includes(query);
            const eventTitleMatch = ann.event?.title?.toLowerCase().includes(query);
            return messageMatch || eventTitleMatch;
        });
    }

    if (props.startDateFilter || props.endDateFilter) {
        items = items.filter(ann => {
            const annDate = new Date(ann.timestamp);
            if (isNaN(annDate.getTime())) return false;

            const filterStart = props.startDateFilter ? new Date(props.startDateFilter) : null;
            const filterEnd = props.endDateFilter ? new Date(props.endDateFilter) : null;

            if (filterStart && !filterEnd) return annDate >= filterStart;
            if (!filterStart && filterEnd) return annDate <= filterEnd;
            if (filterStart && filterEnd) return isWithinInterval(annDate, { start: filterStart, end: filterEnd });
            return true;
        });
    }
    return items;
});

// Modals and Dialogs
const showAddModal = ref(false);
const showEditModal = ref(false);
const showDeleteConfirm = ref(false);
const showImageDialog = ref(false);
const selectedImageUrl = ref('');

// Data for Modals
const newAnnouncementData = ref({ message: '', image: null, imagePreview: null, event_id: null });
const announcementToEdit = ref(null);
const editAnnouncementData = ref({ message: '', image: null, imagePreview: null });
const announcementToDelete = ref(null);

// --- Functions ---

const openImageDialog = (imageUrl) => {
    selectedImageUrl.value = imageUrl;
    showImageDialog.value = true;
};

const openAddModal = () => {
    newAnnouncementData.value = { message: '', image: null, imagePreview: null, event_id: props.eventId };
    showAddModal.value = true;
};

const openEditModal = (announcement) => {
    announcementToEdit.value = { ...announcement };
    editAnnouncementData.value = { message: announcement.message, image: announcement.image, imagePreview: announcement.image };
    showEditModal.value = true;
};

const promptDelete = (announcement) => {
    announcementToDelete.value = { ...announcement };
    showDeleteConfirm.value = true;
};

const handleImageUpload = (event, dataObject) => { // event is now a FileUpload event
    const file = event.files[0];
    if (file) {
        const reader = new FileReader(); // FileReader remains the same
        reader.onload = (e) => {
            dataObject.image = e.target.result;
            dataObject.imagePreview = e.target.result;
        };
        reader.readAsDataURL(file);
    }
};

const removeImage = (dataObject) => {
    dataObject.image = null;
    dataObject.imagePreview = null;
};

const confirmAdd = async () => {
    if (!newAnnouncementData.value.message.trim()) {
        errorMessage.value = 'Announcement message cannot be empty.';
        showErrorDialog.value = true;
        return;
    }
    saving.value = true;
    try {
        const payload = {
            message: newAnnouncementData.value.message,
            image: newAnnouncementData.value.image,
        };
        let response;
        if (props.context === 'home') {
            payload.event_id = newAnnouncementData.value.event_id || null;
            response = await axios.post(route('announcements.store'), payload);
        } else {
            response = await axios.post(route('events.announcements.store', { event: props.eventId }), payload);
        }
        // Use the data from the server's response, which is wrapped in a 'data' key by the API Resource.
        emit('announcement-added', response.data.data);
        successMessage.value = 'Announcement posted successfully.';
        showSuccessDialog.value = true;
    } catch (error) {
        errorMessage.value = error.response?.data?.message || 'Failed to post announcement.';
        showErrorDialog.value = true;
    } finally {
        saving.value = false;
        showAddModal.value = false;
    }
};

const confirmUpdate = async () => {
    if (!announcementToEdit.value || !editAnnouncementData.value.message.trim()) {
        errorMessage.value = 'Announcement message cannot be empty.';
        showErrorDialog.value = true;
        return;
    }
    saving.value = true;
    try {
        const payload = {
            message: editAnnouncementData.value.message,
            image: editAnnouncementData.value.image,
        };
        const eventId = announcementToEdit.value.event?.id || announcementToEdit.value.event_id || null;
        let response;

        if (eventId) {
            response = await axios.put(route('events.announcements.update', { event: eventId, announcement: announcementToEdit.value.id }), payload);
        } else {
            response = await axios.put(route('announcements.update', { announcement: announcementToEdit.value.id }), payload);
        }

        // Use the data from the server's response.
        emit('announcement-updated', response.data.data);
        successMessage.value = 'Announcement updated successfully.';
        showSuccessDialog.value = true;
    } catch (error) {
        errorMessage.value = error.response?.data?.message || 'Failed to update announcement.';
        showErrorDialog.value = true;
    } finally {
        saving.value = false;
        showEditModal.value = false;
    }
};

const confirmDelete = async () => {
    if (!announcementToDelete.value) return;
    saving.value = true;
    try {
        const eventId = announcementToDelete.value.event?.id || announcementToDelete.value.event_id;
        if (eventId) {
            await axios.delete(route('events.announcements.destroy', { event: eventId, announcement: announcementToDelete.value.id }));
        } else {
            await axios.delete(route('announcements.destroy', { announcement: announcementToDelete.value.id }));
        }
        emit('announcement-deleted', announcementToDelete.value.id);
        successMessage.value = 'Announcement deleted successfully.';
        showSuccessDialog.value = true;
    } catch (error) {
        errorMessage.value = 'Failed to delete announcement.';
        showErrorDialog.value = true;
    } finally {
        saving.value = false;
        showDeleteConfirm.value = false;
    }
};

const formatTimestamp = (timestamp) => {
    if (!timestamp) return '';
    return format(parseISO(timestamp), 'MMMM dd, yyyy HH:mm');
};
</script>

<template>
    <div class="w-full bg-white rounded-lg shadow-md p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="section-title text-xl m-0">{{ context === 'home' ? 'Announcement Board' : 'Event Announcements' }}</h2>
            <button v-if="user?.role === 'Admin' || user?.role === 'Principal'" class="create-button flex items-center gap-2" @click="openAddModal">
                <i class="pi pi-plus"></i>
                <span>Add<span class="hidden sm:inline"> Announcement</span></span>
            </button>
        </div>

        <!-- Announcements List -->
        <div v-if="filteredAnnouncements.length > 0" class="space-y-6">
            <div v-for="announcement in filteredAnnouncements" :key="announcement.id" :id="`announcement-${announcement.id}`" @click="context === 'home' && announcement.event?.id && router.visit(route('event.details', { id: announcement.event.id, view: 'announcements' }))" :class="['relative p-6 bg-gray-50 rounded-lg shadow-sm border-l-4 border-blue-500', context === 'home' && announcement.event?.id ? 'cursor-pointer hover:bg-gray-100 transition-colors' : '']">
                <div v-if="user?.role === 'Admin' || user?.role === 'Principal'" class="absolute top-1 right-1 z-10 flex">
                    <Button icon="pi pi-pencil" class="p-button-rounded p-button-text action-btn-info" @click.stop="openEditModal(announcement)" v-tooltip.top="'Edit Announcement'" />
                    <Button icon="pi pi-trash" class="p-button-rounded p-button-text action-btn-danger" @click.stop="promptDelete(announcement)" v-tooltip.top="'Delete Announcement'" />
                </div>
                <div class="flex items-center mb-3">
                    <Avatar image="https://primefaces.org/cdn/primevue/images/avatar/amyelsner.png" class="mr-2" shape="circle" size="small" />
                    <span class="text-gray-600 text-sm font-semibold">{{ announcement.employee?.name || 'Admin' }}</span>
                </div>
                <div v-if="context === 'home'" class="mb-3">
                    <template v-if="announcement.event?.id">
                        <span class="text-sm text-gray-600">For event:</span>
                        <Link :href="route('event.details', { id: announcement.event.id, view: 'announcements' })" @click.stop class="font-semibold text-blue-700 hover:underline ml-1 text-base">{{ announcement.event.title }}</Link>
                    </template>
                    <template v-else>
                        <span class="text-sm font-semibold text-gray-500">General Announcement</span>
                    </template>
                </div>
                <p class="text-gray-800 text-base whitespace-pre-line">{{ announcement.message }}</p>
                <img v-if="announcement.image" :src="announcement.image" alt="Announcement image" class="mt-4 rounded-lg max-w-full w-full md:max-w-md mx-auto h-auto shadow-md cursor-pointer hover:opacity-90 transition-opacity" @click.stop="openImageDialog(announcement.image)" />
                <p class="text-sm text-gray-500 mt-4 text-right">{{ formatTimestamp(announcement.timestamp) }}</p>
            </div>
        </div>
        <div v-else class="text-center text-gray-500 py-8">
            <p v-if="props.searchQuery || props.startDateFilter || props.endDateFilter">No announcements found matching your filters.</p>
            <p v-else>No announcements yet.</p>
        </div>

        <!-- Dialogs and Modals -->
        <LoadingSpinner :show="saving" />
        <SuccessDialog v-model:show="showSuccessDialog" :message="successMessage" />
        <ConfirmationDialog v-model:show="showErrorDialog" title="Error" :message="errorMessage" confirmText="Close" :showCancelButton="false" @confirm="showErrorDialog = false" />
        <ConfirmationDialog v-model:show="showDeleteConfirm" title="Delete Announcement?" message="Are you sure you want to delete this announcement?" @confirm="confirmDelete" />

        <!-- Add Modal -->
        <Dialog v-model:visible="showAddModal" modal header="Add Announcement" :style="{ width: 'min(600px, 90vw)' }">
            <div class="p-fluid">
                <div v-if="context === 'home'" class="p-field mb-4">
                    <label for="newEventId">Event (Optional)</label>
                    <Select v-model="newAnnouncementData.event_id" :options="eventsForPicker" optionLabel="title" optionValue="id" placeholder="Select an event to link" filter showClear class="w-full" />
                </div>
                <div class="p-field">
                    <label for="newMessage">Message <span style="color: red;">*</span></label>
                    <Textarea id="newMessage" v-model="newAnnouncementData.message" rows="5" placeholder="Enter your announcement..." autoResize />
                </div>
                <div class="p-field mt-4">
                    <label for="newImage">Image (Optional)</label>
                    <div v-if="newAnnouncementData.imagePreview" class="mt-2 relative w-fit">
                        <img :src="newAnnouncementData.imagePreview" alt="Image preview" class="rounded-lg max-w-xs h-auto" />
                        <Button icon="pi pi-times" class="p-button-rounded p-button-danger p-button-text absolute top-1 right-1 bg-white/50" @click="removeImage(newAnnouncementData)" v-tooltip.top="'Remove Image'" />
                    </div>
                    <FileUpload
                        v-else
                        mode="basic"
                        name="newImage"
                        accept="image/*"
                        :maxFileSize="1000000"
                        @select="handleImageUpload($event, newAnnouncementData)"
                        chooseLabel="Choose Image"
                        class="mt-2"
                    />
                </div>
            </div>
            <template #footer>
                <div class="flex justify-end flex-wrap gap-2">
                    <button @click="showAddModal = false" class="modal-button-secondary sm:p-button-sm">Cancel</button>
                <button @click="confirmAdd" :disabled="saving" class="modal-button-primary sm:p-button-sm">
                    <i v-if="saving" class="pi pi-spin pi-spinner mr-2"></i>
                    Post Announcement
                </button>
                </div>
            </template>
        </Dialog>

        <!-- Edit Modal -->
        <Dialog v-model:visible="showEditModal" modal header="Edit Announcement" :style="{ width: 'min(600px, 90vw)' }">
            <div class="p-fluid">
                <div class="p-field">
                    <label for="editMessage">Message <span style="color: red;">*</span></label>
                    <Textarea id="editMessage" v-model="editAnnouncementData.message" rows="5" placeholder="Enter your announcement..." autoResize />
                </div>
                <div class="p-field mt-4">
                    <label for="editImage">Image (Optional)</label>
                    <div v-if="editAnnouncementData.imagePreview" class="mt-2 relative w-fit">
                        <img :src="editAnnouncementData.imagePreview" alt="Image preview" class="rounded-lg max-w-xs h-auto" />
                        <Button icon="pi pi-times" class="p-button-rounded p-button-danger p-button-text absolute top-1 right-1 bg-white/50" @click="removeImage(editAnnouncementData)" v-tooltip.top="'Remove Image'" />
                    </div>
                    <FileUpload
                        v-else
                        mode="basic"
                        name="editImage"
                        accept="image/*"
                        :maxFileSize="1000000"
                        @select="handleImageUpload($event, editAnnouncementData)"
                        chooseLabel="Choose Image"
                        class="mt-2"
                    />
                </div>
            </div>
            <template #footer>
                <div class="flex justify-end flex-wrap gap-2">
                    <button @click="showEditModal = false" class="modal-button-secondary sm:p-button-sm">Cancel</button>
                <button @click="confirmUpdate" :disabled="saving" class="modal-button-primary sm:p-button-sm">
                    <i v-if="saving" class="pi pi-spin pi-spinner mr-2"></i>
                    Save
                </button>
                </div>
            </template>
        </Dialog>

        <!-- Image Viewer Dialog -->
        <Dialog v-model:visible="showImageDialog" modal :dismissableMask="true" header="Image" :style="{ width: '90vw', maxWidth: '1200px' }">
            <img :src="selectedImageUrl" alt="Announcement Image" class="w-full h-auto max-h-[80vh] object-contain" />
        </Dialog>
    </div>
</template>

<style scoped>
.date-filter-container {
  background: white;
  padding: 15px;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  gap: 10px;
  align-items: flex-start;
}
.clear-date-btn {
  align-self: flex-end;
  color: #dc3545;
}
.clear-date-btn:hover {
  background-color: rgba(220, 53, 69, 0.1);
}
</style>
