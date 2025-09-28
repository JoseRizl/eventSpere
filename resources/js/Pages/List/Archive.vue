<template>
  <div class="archive-list-container">
    <LoadingSpinner :show="saving" />
    <h1 class="title">Archived Events</h1>

    <div class="search-container mb-4">
      <div class="p-input-icon-left">
        <i class="pi pi-search" />
        <InputText
          v-model="searchQuery"
          placeholder="Search archived events..."
          class="w-full"
        />
      </div>
    </div>

    <DataTable v-if="initialLoading" :value="Array(5).fill({})" class="p-datatable-striped">
        <Column header="Event Name" style="width:20%;"><template #body><Skeleton /></template></Column>
        <Column header="Description" style="width:15%;"><template #body><Skeleton /></template></Column>
        <Column header="Venue" style="width:15%;"><template #body><Skeleton /></template></Column>
        <Column header="Start Date & Time" style="width:20%;"><template #body><Skeleton /></template></Column>
        <Column header="End Date & Time" style="width:20%;"><template #body><Skeleton /></template></Column>
        <Column header="Actions" style="width:10%;" body-class="text-center"><template #body><div class="flex justify-center gap-2"><Skeleton shape="circle" size="2rem" /><Skeleton shape="circle" size="2rem" /></div></template></Column>
    </DataTable>

    <div v-else-if="filteredEvents.length === 0">
      <div v-if="searchQuery" class="no-results-message">
        <div class="icon-and-title">
          <i class="pi pi-search" style="font-size: 1.5rem; color: #007bff; margin-right: 10px;"></i>
          <h2 class="no-results-title">No Archived Events Found</h2>
        </div>
        <p class="no-results-text">No archived events match your search criteria. Try adjusting your search terms.</p>
      </div>
      <div v-else class="no-results-message">
        <div class="icon-and-title">
          <i class="pi pi-inbox" style="font-size: 1.5rem; color: #6c757d; margin-right: 10px;"></i>
          <h2 class="no-results-title">No Archived Events</h2>
        </div>
        <p class="no-results-text">There are currently no events in the archive.</p>
      </div>
    </div>

    <DataTable
      v-else
      :value="filteredEvents"
      class="p-datatable-striped"
      paginator :rows="10" :rowsPerPageOptions="[10, 20, 50]"
      paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
      currentPageReportTemplate="Showing {first} to {last} of {totalRecords} archived events">
      <Column field="title" header="Event Name" style="width:20%;" sortable>
        <template #body="{ data }">
          <div class="flex items-center gap-2">
            <img v-if="data.image" :src="data.image" alt="Event Image" class="event-icon" />
            <span>{{ data.title }}</span>
          </div>
        </template>
      </Column>

      <Column field="description" header="Description" style="width:15%;">
        <template #body="{ data }">
          <div class="description">
            {{ data.description }}
          </div>
        </template>
      </Column>

      <Column field="venue" header="Venue" style="width:15%;" sortable>
        <template #body="{ data }">
          <div class="venue">
            {{ data.venue || "No venue specified" }}
          </div>
        </template>
      </Column>

      <Column header="Start Date & Time" style="width:20%;" sortable>
        <template #body="{ data }">
          <div class="date-time">
            <span class="date">{{ formatDateTime(data.startDate, data.startTime).date }}</span>
            <span class="time">{{ formatDateTime(data.startDate, data.startTime).time }}</span>
          </div>
        </template>
      </Column>

      <Column header="End Date & Time" style="width:20%;" sortable>
        <template #body="{ data }">
          <div class="date-time">
            <span class="date">{{ formatDateTime(data.endDate, data.endTime).date }}</span>
            <span class="time">{{ formatDateTime(data.endDate, data.endTime).time }}</span>
          </div>
        </template>
      </Column>

      <Column header="Actions" style="width:10%;" body-class="text-center">
        <template #body="{ data }">
          <div class="action-buttons">
            <Button icon="pi pi-undo" class="p-button-rounded p-button-text action-btn-success" @click="restoreEvent(data)" v-tooltip.top="'Restore Event'"/>
            <Button icon="pi pi-trash" class="p-button-rounded p-button-text action-btn-danger" @click="deleteEventPermanently(data)" v-tooltip.top="'Delete Event'"/>
          </div>
        </template>
      </Column>
    </DataTable>

    <!-- Success Dialog -->
    <SuccessDialog
      v-model:show="showSuccessDialog"
      :message="successMessage" />

    <ConfirmationDialog
      v-model:show="showErrorDialog"
      title="Error"
      :message="errorMessage"
      confirmText="Close"
      :showCancelButton="false" />

    <!-- Restore Confirmation Dialog -->
    <ConfirmationDialog
      v-model:show="showRestoreConfirm"
      title="Restore Event?"
      :message="eventToProcess ? `Are you sure you want to restore '${eventToProcess.title}'?` : ''"
      confirmText="Yes, Restore"
      @confirm="confirmRestore"
    />

    <!-- Delete Confirmation Dialog -->
    <ConfirmationDialog
      v-model:show="showDeleteConfirm"
      title="Delete Event Permanently?"
      :message="eventToProcess ? `Are you sure you want to permanently delete '${eventToProcess.title}'? This action cannot be undone.` : ''"
      confirmText="Yes, Delete"
      confirmButtonClass="modal-button-danger"
      @confirm="confirmDelete"
    />
  </div>
</template>

<script>
import { defineComponent, ref, onMounted, computed, nextTick } from "vue";
import { router, usePage } from '@inertiajs/vue3';
import { format } from "date-fns";
import LoadingSpinner from '@/Components/LoadingSpinner.vue';
import ConfirmationDialog from '@/Components/ConfirmationDialog.vue';
import SuccessDialog from '@/Components/SuccessDialog.vue';
import { useToast } from '@/composables/useToast';
import Skeleton from 'primevue/skeleton';

export default defineComponent({
  name: "Archive",
  components: {
    LoadingSpinner,
    ConfirmationDialog,
    SuccessDialog,
    Skeleton
  },
  setup() {
    const { showSuccess, showError } = useToast();
    const { props } = usePage(); // props are now fully utilized
    const archivedEvents = ref(props.archivedEvents || []);
    const categories = ref(props.categories || []);
    const searchQuery = ref("");
    const initialLoading = ref(true);
    const saving = ref(false);
    const showSuccessDialog = ref(false);
    const successMessage = ref('');
    const showErrorDialog = ref(false);
    const errorMessage = ref('');
    const showRestoreConfirm = ref(false);
    const showDeleteConfirm = ref(false);
    const eventToProcess = ref(null);

    // Add computed property for filtered events
    const filteredEvents = computed(() => {
      if (!searchQuery.value) return archivedEvents.value;

      const query = searchQuery.value.toLowerCase().trim();
      return archivedEvents.value.filter(event => {
        if (!event) return false;

        const title = event.title?.toLowerCase() || '';
        const description = event.description?.toLowerCase() || '';
        const venue = event.venue?.toLowerCase() || '';
        const category = categoryMap.value[event.category_id]?.toLowerCase() || '';
        const tags = event.tags?.map(tag => tag?.name?.toLowerCase() || '').filter(Boolean) || [];

        return (
          title.includes(query) ||
          description.includes(query) ||
          venue.includes(query) ||
          category.includes(query) ||
          tags.some(tag => tag.includes(query))
        );
      });
    });

    onMounted(() => {
      // Data is now passed as props on initial visit.
      // We just need to switch off the loading indicator after mount.
      initialLoading.value = false;
    });

    // Format date and time display
    const formatDateTime = (date, time) => {
      const formattedDate = date ? format(new Date(date), "MMM-dd-yyyy") : "No date";
      const formattedTime = time ? time.padStart(5, "0") : "00:00";
      return { date: formattedDate, time: formattedTime };
    };

    // Map category_id to category title
    const categoryMap = computed(() =>
      categories.value.reduce((map, category) => {
        map[category.id] = category.title;
        return map;
      }, {})
    );

    // Restore Event
    const restoreEvent = (event) => {
      eventToProcess.value = event;
      showRestoreConfirm.value = true;
    };

    const confirmRestore = async () => {
      if (!eventToProcess.value) return;
      saving.value = true;

      try {
        await router.put(`/events/${eventToProcess.value.id}/restore`, {}, {
          onSuccess: () => {
            archivedEvents.value = archivedEvents.value.filter(e => e.id !== eventToProcess.value.id);
            successMessage.value = 'Event restored successfully!';
            showSuccessDialog.value = true;
            showSuccess('Event restored successfully!');
          },
          onError: (errors) => {
            errorMessage.value = 'Failed to restore the event.';
            showErrorDialog.value = true;
            showError('Failed to restore the event.');
          },
          onFinish: () => {
            saving.value = false;
            showRestoreConfirm.value = false;
            eventToProcess.value = null;
          }
        });
      } catch (error) {
        console.error("Error restoring event:", error);
        errorMessage.value = 'Failed to restore the event.';
        showErrorDialog.value = true;
        showError('Failed to restore the event.');
        saving.value = false;
        showRestoreConfirm.value = false;
        eventToProcess.value = null;
      }
    };

    // Delete Event Permanently
    const deleteEventPermanently = (event) => {
      eventToProcess.value = event;
      showDeleteConfirm.value = true;
    };

    const confirmDelete = async () => {
      if (!eventToProcess.value) return;
      saving.value = true;

      try {
        await router.delete(`/events/${eventToProcess.value.id}/permanent`, {
          onSuccess: () => {
            archivedEvents.value = archivedEvents.value.filter(e => e.id !== eventToProcess.value.id);
            successMessage.value = 'Event permanently deleted!';
            showSuccessDialog.value = true;
            showSuccess('Event permanently deleted!');
          },
          onError: (errors) => {
            errorMessage.value = 'Failed to delete the event.';
            showErrorDialog.value = true;
            showError('Failed to delete the event.');
          },
          onFinish: () => {
            saving.value = false;
            showDeleteConfirm.value = false;
            eventToProcess.value = null;
          }
        });
      } catch (error) {
        console.error("Error deleting event:", error);
        errorMessage.value = 'Failed to delete the event.';
        showErrorDialog.value = true;
        showError('Failed to delete the event.');
        saving.value = false;
        showDeleteConfirm.value = false;
        eventToProcess.value = null;
      }
    };

    return {
      archivedEvents,
      categoryMap,
      formatDateTime,
      restoreEvent,
      confirmRestore,
      deleteEventPermanently,
      confirmDelete,
      searchQuery,
      initialLoading,
      filteredEvents,
      saving,
      showSuccessDialog,
      successMessage,
      showErrorDialog,
      errorMessage,
      showRestoreConfirm,
      showDeleteConfirm,
      eventToProcess
    };
  },
});
</script>

<style scoped>
.search-container {
  display: flex;
  justify-content: flex-start;
  width: 100%;
  max-width: 400px;
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

.no-results-message {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  margin: 20px 0;
  padding: 20px;
  background-color: #f9f9f9;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.icon-and-title {
  display: flex;
  align-items: center;
  margin-bottom: 10px;
}

.no-results-title {
  color: #333;
  margin: 0;
  font-size: 1.5rem;
  font-weight: bold;
}

.no-results-text {
  color: #555;
  margin: 5px 0 0 0;
}

@media (max-width: 768px) {
  .search-container .p-input-icon-left {
    max-width: 100%;
  }
}
</style>
