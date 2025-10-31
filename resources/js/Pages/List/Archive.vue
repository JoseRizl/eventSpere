<template>
  <div class="archive-list-container">
    <LoadingSpinner :show="saving" />
    <h1 class="title">Archived {{ pageType === 'tags' ? 'Tags' : 'Events' }}</h1>

    <div class="search-container mb-4">
      <SearchFilterBar
        v-model:searchQuery="searchQuery"
        :placeholder="`Search archived ${pageType}...`"
        :show-date-filter="true"
        :is-date-filter-active="showDateFilter"
        :show-clear-button="false"
        @toggle-date-filter="toggleDateFilter"
      />
    </div>

    <!-- Date Filter Calendar -->
    <div v-if="showDateFilter" class="date-filter-container mb-4">
      <div class="date-range-wrapper">
        <div class="date-input-group">
          <label>From:</label>
          <DatePicker v-model="dateRange.from" dateFormat="MM-dd-yy" :showIcon="true" placeholder="Start date" class="date-filter-calendar" />
        </div>
        <div class="date-input-group">
          <label>To:</label>
          <DatePicker v-model="dateRange.to" dateFormat="MM-dd-yy" :showIcon="true" placeholder="End date" class="date-filter-calendar" />
        </div>
      </div>
      <Button icon="pi pi-times" class="p-button-text p-button-rounded clear-date-btn" @click="clearDateFilter" v-tooltip.top="'Clear date filter'" />
    </div>

    <!-- Skeleton for Events -->
    <DataTable v-if="initialLoading" :value="Array(5).fill({})" class="p-datatable-striped">
        <Column header="Event Name" style="width:20%;" :headerStyle="{ 'background-color': '#0077B3', 'color': 'white', 'font-weight': 'bold', 'text-transform': 'uppercase' }"><template #body><Skeleton /></template></Column>
        <Column header="Description" style="width:15%;" :headerStyle="{ 'background-color': '#0077B3', 'color': 'white', 'font-weight': 'bold', 'text-transform': 'uppercase' }"><template #body><Skeleton /></template></Column>
        <Column header="Venue" style="width:15%;" :headerStyle="{ 'background-color': '#0077B3', 'color': 'white', 'font-weight': 'bold', 'text-transform': 'uppercase' }"><template #body><Skeleton /></template></Column>
        <Column header="Start Date & Time" style="width:20%;" :headerStyle="{ 'background-color': '#0077B3', 'color': 'white', 'font-weight': 'bold', 'text-transform': 'uppercase' }"><template #body><Skeleton /></template></Column>
        <Column header="End Date & Time" style="width:20%;" :headerStyle="{ 'background-color': '#0077B3', 'color': 'white', 'font-weight': 'bold', 'text-transform': 'uppercase' }"><template #body><Skeleton /></template></Column>
        <Column header="Actions" style="width:10%;" body-class="text-center" :headerStyle="{ 'background-color': '#0077B3', 'color': 'white', 'font-weight': 'bold', 'text-transform': 'uppercase' }"><template #body><div class="flex justify-center gap-2"><Skeleton shape="circle" size="2rem" /><Skeleton shape="circle" size="2rem" /></div></template></Column>
    </DataTable>

    <div v-else-if="filteredItems.length === 0">
      <div v-if="searchQuery" class="no-results-message">
        <div class="icon-and-title">
          <i class="pi pi-search" style="font-size: 1.5rem; color: #007bff; margin-right: 10px;"></i>
          <h2 class="no-results-title">No Archived {{ pageType === 'tags' ? 'Tags' : 'Events' }} Found</h2>
        </div>
        <p class="no-results-text">No archived {{ pageType }} match your search criteria. Try adjusting your search terms.</p>
      </div>
      <div v-else class="no-results-message">
        <div class="icon-and-title">
          <i class="pi pi-inbox" style="font-size: 1.5rem; color: #6c757d; margin-right: 10px;"></i>
          <h2 class="no-results-title">No Archived {{ pageType === 'tags' ? 'Tags' : 'Events' }}</h2>
        </div>
        <p class="no-results-text">There are currently no {{ pageType }} in the archive.</p>
      </div>
    </div>

    <!-- Events Table -->
    <DataTable
      v-else-if="pageType === 'events'"
      :value="filteredItems"
      class="p-datatable-striped" showGridlines
      paginator :rows="10" :rowsPerPageOptions="[10, 20, 50]"
      paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
      currentPageReportTemplate="Showing {first} to {last} of {totalRecords} archived events"
      :headerStyle="{ 'background-color': '#004A99', 'color': 'white', 'font-weight': 'bold', 'text-transform': 'uppercase' }">

      <!-- Event Columns -->
      <Column field="title" header="Event Name" style="width:20%;" sortable :headerStyle="{ 'background-color': '#004A99', 'color': 'white', 'font-weight': 'bold', 'text-transform': 'uppercase' }">
        <template #body="{ data }">
          <div class="flex items-center gap-2">
            <img v-if="data.image" :src="data.image" alt="Event Image" class="event-icon" />
            <Link
              :href="route('event.details', { id: data.id })"
              class="text-base overflow-hidden line-clamp-2 text-gray-800 hover:text-blue-600 transition-colors duration-200 cursor-pointer"
              v-tooltip.top="data.title"
            >
              {{ data.title }}
            </Link>
          </div>

          <!-- Tags Display -->
          <!-- <div class="tags-container">
            <span v-for="tag in getEventTags(data.tags)" :key="tag.id" class="tag" :style="{ backgroundColor: tag.color || '#800080' }">
              {{ tag.name }}
            </span>
          </div> -->
        </template>
      </Column>

      <Column field="description" header="Description" style="width:15%;" :headerStyle="{ 'background-color': '#004A99', 'color': 'white', 'font-weight': 'bold', 'text-transform': 'uppercase' }">
        <template #body="{ data }">
          <div class="description line-clamp-3 whitespace-pre-line" v-html="formatDescription(data.description)" @click="handleDescriptionClick"></div>
        </template>
      </Column>

      <Column field="venue" header="Venue" style="width:15%;" sortable :headerStyle="{ 'background-color': '#004A99', 'color': 'white', 'font-weight': 'bold', 'text-transform': 'uppercase' }">
        <template #body="{ data }">
          <div class="datatable-content">
            {{ data.venue || "No venue specified" }}
          </div>
        </template>
      </Column>

      <Column header="Start Date & Time" style="width:20%;" sortable :headerStyle="{ 'background-color': '#004A99', 'color': 'white', 'font-weight': 'bold', 'text-transform': 'uppercase' }">
        <template #body="{ data }">
          <div class="date-time">
            <span class="date">{{ formatDateTime(data.startDate, data.startTime).date }}</span>
            <span class="time">{{ formatDateTime(data.startDate, data.startTime).time }}</span>
          </div>
        </template>
      </Column>

      <Column header="End Date & Time" style="width:20%;" sortable :headerStyle="{ 'background-color': '#004A99', 'color': 'white', 'font-weight': 'bold', 'text-transform': 'uppercase' }">
        <template #body="{ data }">
          <div class="date-time">
            <span class="date">{{ formatDateTime(data.endDate, data.endTime).date }}</span>
            <span class="time">{{ formatDateTime(data.endDate, data.endTime).time }}</span>
          </div>
        </template>
      </Column>

      <Column header="Actions" style="width:10%;" body-class="text-center" :headerStyle="{ 'background-color': '#004A99', 'color': 'white', 'font-weight': 'bold', 'text-transform': 'uppercase' }">
        <template #body="{ data }">
          <div class="action-buttons">
            <Button icon="pi pi-undo" class="p-button-rounded p-button-text action-btn-success" @click="restoreItem(data)" v-tooltip.top="'Restore Event'"/>
            <Button icon="pi pi-trash" class="p-button-rounded p-button-text action-btn-danger" @click="deleteItemPermanently(data)" v-tooltip.top="'Delete Event'"/>
          </div>
        </template>
      </Column>
    </DataTable>

    <!-- Tags Table -->
    <DataTable
      v-else-if="pageType === 'tags'"
      :value="filteredItems"
      class="p-datatable-striped" showGridlines
      paginator :rows="10" :rowsPerPageOptions="[10, 20, 50]"
      paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
      currentPageReportTemplate="Showing {first} to {last} of {totalRecords} archived tags"
      :headerStyle="{ 'background-color': '#004A99', 'color': 'white', 'font-weight': 'bold', 'text-transform': 'uppercase' }">

      <!-- Tag Columns -->
      <Column field="name" header="Tag Name" style="width:35%;" sortable :headerStyle="{ 'background-color': '#004A99', 'color': 'white', 'font-weight': 'bold', 'text-transform': 'uppercase' }">
        <template #body="{ data }">
          <div class="datatable-content">{{ data.name }}</div>
        </template>
      </Column>

      <Column field="category.title" header="Category" style="width:30%;" sortable :headerStyle="{ 'background-color': '#004A99', 'color': 'white', 'font-weight': 'bold', 'text-transform': 'uppercase' }">
        <template #body="{ data }">
          <div class="datatable-content">{{ data.category?.title || 'N/A' }}</div>
        </template>
      </Column>

      <Column header="Actions" style="width:10%;" body-class="text-center" :headerStyle="{ 'background-color': '#004A99', 'color': 'white', 'font-weight': 'bold', 'text-transform': 'uppercase' }">
        <template #body="{ data }">
          <div class="action-buttons">
            <Button icon="pi pi-undo" class="p-button-rounded p-button-text action-btn-success" @click="restoreItem(data)" v-tooltip.top="'Restore Tag'"/>
            <Button icon="pi pi-trash" class="p-button-rounded p-button-text action-btn-danger" @click="deleteItemPermanently(data)" v-tooltip.top="'Delete Tag'"/>
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
      :title="`Restore ${pageType === 'tags' ? 'Tag' : 'Event'}?`"
      :message="itemToProcess ? `Are you sure you want to restore '${itemToProcess.title || itemToProcess.name}'?` : ''"
      confirmText="Yes, Restore"
      @confirm="confirmRestore"
    />

    <!-- Delete Confirmation Dialog -->
    <ConfirmationDialog
      v-model:show="showDeleteConfirm"
      :title="`Delete ${pageType === 'tags' ? 'Tag' : 'Event'} Permanently?`"
      :message="itemToProcess ? `Are you sure you want to permanently delete '${itemToProcess.title || itemToProcess.name}'? This action cannot be undone.` : ''"
      confirmText="Yes, Delete"
      confirmButtonClass="modal-button-danger"
      @confirm="confirmDelete"
    />
  </div>
</template>

<script>
import { defineComponent, ref, onMounted, computed } from "vue";
import { router, usePage } from "@inertiajs/vue3";
import { format, parse, isWithinInterval } from 'date-fns';
import { useToast } from '@/composables/useToast';

export default defineComponent({
  name: "Archive",
  setup() {
    const { showSuccess, showError } = useToast();
    const { props } = usePage(); // props are now fully utilized
    const pageType = computed(() => props.type || 'events');
    const archivedItems = ref(props.archivedItems || []);

    const tags = ref(props.tags || []);
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
    const itemToProcess = ref(null);
    const showDateFilter = ref(false);
    const dateRange = ref({
      from: null,
      to: null,
    });

    const tagsMap = computed(() => {
      return tags.value.reduce((map, tag) => {
          map[tag.id] = tag;
          return map;
      }, {});
    });

    // Add computed property for filtered events
    const filteredItems = computed(() => {
      let items = archivedItems.value;

      if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase().trim();
        items = items.filter(item => {
            if (!item) return false;

            if (pageType.value === 'events') {
                const title = item.title?.toLowerCase() || '';
                const description = item.description?.toLowerCase() || '';
                const venue = item.venue?.toLowerCase() || '';
                const category = categoryMap.value[item.category_id]?.toLowerCase() || '';
                const tags = item.tags?.map(tag => tag?.name?.toLowerCase() || '').filter(Boolean) || [];

                return title.includes(query) || description.includes(query) || venue.includes(query) || category.includes(query) || tags.some(tag => tag.includes(query));
            } else if (pageType.value === 'tags') {
                const name = item.name?.toLowerCase() || '';
                const description = item.description?.toLowerCase() || '';
                const category = item.category?.title?.toLowerCase() || '';

                return name.includes(query) || description.includes(query) || category.includes(query);
            }
            return false;
        });
      }

      // Apply date range filter
      if (pageType.value === 'events' && (dateRange.value.from || dateRange.value.to)) {
        items = items.filter(event => {
          if (!event.startDate) return false;

          const eventDate = parse(event.startDate, 'MMM-dd-yyyy', new Date());

          // If only from date is set
          if (dateRange.value.from && !dateRange.value.to) {
            return eventDate >= dateRange.value.from;
          }

          // If only to date is set
          if (!dateRange.value.from && dateRange.value.to) {
            return eventDate <= dateRange.value.to;
          }

          // If both dates are set
          if (dateRange.value.from && dateRange.value.to) {
            return isWithinInterval(eventDate, { start: dateRange.value.from, end: dateRange.value.to });
          }

          return true;
        });
      }

      return items;
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

    const getEventTags = (eventTags) => {
      // Ensure eventTags is an array of tag objects, not just IDs
      return Array.isArray(eventTags) ? eventTags : [];
    };

    const formatDescription = (description) => {
      if (!description) return '';

      const escapeHtml = (unsafe) => {
        return unsafe
          .replace(/&/g, "&amp;")
          .replace(/</g, "&lt;")
          .replace(/>/g, "&gt;")
          .replace(/"/g, "&quot;")
          .replace(/'/g, "&#039;");
      };
      const escapedText = escapeHtml(description);

      const urlRegex = /(\b(https?|ftp|file):\/\/[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])|(\bwww\.[-A-Z0-9+&@#\/%?=~_|!:,.;]*[-A-Z0-9+&@#\/%=~_|])|(\b[A-Z0-9.-]+\.(com|org|net|gov|edu|io|co|us|ca|uk|de|fr|au|info|biz|me|tv|app|dev)\b([-A-Z0-9+&@#\/%?=~_|!:,.;]*))/gi;

      return escapedText.replace(urlRegex, (url) => {
        const unescapedUrlForHref = url.replace(/&amp;/g, '&');
        let href = unescapedUrlForHref;
        if (!href.match(/^(https?|ftp|file):\/\//i)) {
          href = 'http://' + href;
        }
        return `<a href="${href}" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline">${url}</a>`;
      });
    };

    const handleDescriptionClick = (event) => {
      if (event.target.tagName === 'A') {
        event.stopPropagation();
      }
    };

    // Restore Event
    const restoreItem = (item) => {
      itemToProcess.value = item;
      showRestoreConfirm.value = true;
    };

    const confirmRestore = async () => {
      if (!itemToProcess.value) return;
      saving.value = true;

      const url = pageType.value === 'events'
        ? `/events/${itemToProcess.value.id}/restore`
        : `/tags/${itemToProcess.value.id}/restore`;

      try {
        await router.put(url, {}, {
          onSuccess: () => {
            archivedItems.value = archivedItems.value.filter(e => e.id !== itemToProcess.value.id);
            successMessage.value = `${pageType.value === 'tags' ? 'Tag' : 'Event'} restored successfully!`;
            showSuccessDialog.value = true;
            showSuccess(successMessage.value);
            // The success toast is now handled by the MainLayout from the flashed session message.
          },
          onError: (errors) => {
            errorMessage.value = `Failed to restore the ${pageType.value === 'tags' ? 'tag' : 'event'}.`;
            showErrorDialog.value = true;
            showError(errorMessage.value);
          },
          onFinish: () => {
            saving.value = false;
            showRestoreConfirm.value = false;
            itemToProcess.value = null;
          }
        });
      } catch (error) {
        console.error(`Error restoring ${pageType.value}:`, error);
        errorMessage.value = `Failed to restore the ${pageType.value === 'tags' ? 'tag' : 'event'}.`;
        showErrorDialog.value = true;
        showError(errorMessage.value);
        saving.value = false;
        showRestoreConfirm.value = false;
        itemToProcess.value = null;
      }
    };

    // Delete Event Permanently
    const deleteItemPermanently = (item) => {
      itemToProcess.value = item;
      showDeleteConfirm.value = true;
    };

    const confirmDelete = async () => {
      if (!itemToProcess.value) return;
      saving.value = true;

      const url = pageType.value === 'events'
        ? `/events/${itemToProcess.value.id}/permanent`
        : `/tags/${itemToProcess.value.id}/permanent`;

      try {
        await router.delete(url, {
          onSuccess: () => {
            archivedItems.value = archivedItems.value.filter(e => e.id !== itemToProcess.value.id);
            successMessage.value = `${pageType.value === 'tags' ? 'Tag' : 'Event'} permanently deleted!`;
            showSuccessDialog.value = true;
            showSuccess(successMessage.value);
            // The success toast is now handled by the MainLayout from the flashed session message.
          },
          onError: (errors) => {
            errorMessage.value = `Failed to delete the ${pageType.value === 'tags' ? 'tag' : 'event'}.`;
            showErrorDialog.value = true;
            showError(errorMessage.value);
          },
          onFinish: () => {
            saving.value = false;
            showDeleteConfirm.value = false;
            itemToProcess.value = null;
          }
        });
      } catch (error) {
        console.error(`Error deleting ${pageType.value}:`, error);
        errorMessage.value = `An unexpected error occurred while deleting the ${pageType.value === 'tags' ? 'tag' : 'event'}.`;
        showErrorDialog.value = true;
        showError(errorMessage.value);
        saving.value = false;
        showDeleteConfirm.value = false;
        eventToProcess.value = null;
      }
    };

    const toggleDateFilter = () => {
      showDateFilter.value = !showDateFilter.value;
      if (!showDateFilter.value) {
        clearDateFilter();
      }
    };

    const clearDateFilter = () => {
      dateRange.value = { from: null, to: null };
      // No need to set showDateFilter to false here, as this is for clearing, not toggling off.
      // The toggle button will handle hiding it.
    };

    return {
      pageType,
      archivedItems,
      categoryMap,
      formatDateTime,
      restoreItem,
      confirmRestore,
      deleteItemPermanently,
      confirmDelete,
      searchQuery,
      initialLoading,
      filteredItems,
      saving,
      showSuccessDialog,
      successMessage,
      showErrorDialog,
      errorMessage,
      showRestoreConfirm,
      showDeleteConfirm,
      itemToProcess,
      showDateFilter,
      dateRange,
      tagsMap, // Expose tagsMap
      getEventTags, // Expose getEventTags
      formatDescription, // Expose formatDescription
      toggleDateFilter,
      clearDateFilter,
    };
  },
});
</script>

<style scoped>
.search-container {
  display: flex;
  justify-content: flex-start;
  width: 100%;
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
  margin-bottom: 1rem;
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
  background-color: #dc3545;
  color: white;
}

.event-icon {
  width: 40px;
  height: 40px;
  object-fit: cover;
  border-radius: 4px;
  margin-right: 8px;
}

.tags-container {
  display: flex;
  flex-wrap: wrap;
  gap: 5px;
  margin-top: 5px;
}

.tag {
  font-size: 0.75rem;
  padding: 3px 8px;
  border-radius: 12px;
  color: white;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  max-width: 100px; /* Adjust as needed */
}

.description a {
  color: #2563eb; /* Tailwind blue-600 */
  text-decoration: underline;
}

.description a:hover {
  color: #1d4ed8; /* Tailwind blue-700 */
}
</style>
