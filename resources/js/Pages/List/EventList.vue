<template>
    <div class="event-list-container">
        <LoadingSpinner :show="saving" />
        <input type="file" ref="defaultImageInput" @change="handleDefaultImageUpload" accept="image/*" class="hidden" />
        <h1 class="title">Event List</h1>

      <div class="search-container mb-4">
        <SearchFilterBar
          v-model:searchQuery="searchQuery"
          placeholder="Search events..."
          :show-date-filter="true"
          :is-date-filter-active="showDateFilter"
          :show-clear-button="false"
          @toggle-date-filter="toggleDateFilter"
        />
        <div class="flex items-center gap-2">
          <Button icon="pi pi-print" class="p-button-secondary" @click="printTable" v-tooltip.top="'Print Table'"
            aria-label="Print Table"
          />
          <button v-if="user?.role === 'Admin' || user?.role === 'Principal'" class="create-button" @click="openCreateModal">
              Create<span class="hidden sm:inline"> Event</span>
          </button>
        </div>
      </div>

      <!-- Date Filter Calendar - Moved outside search-wrapper -->
      <div v-if="showDateFilter" class="date-filter-container">
        <div class="date-range-wrapper">
          <div class="date-input-group">
            <label>From:</label>
            <DatePicker
              v-model="dateRange.from"
              dateFormat="MM-dd-yy"
              :showIcon="true"
              placeholder="Start date"
              @date-select="filterByDate"
              class="date-filter-calendar"
            />
          </div>
          <div class="date-input-group">
            <label>To:</label>
            <DatePicker
              v-model="dateRange.to"
              dateFormat="MM-dd-yy"
              :showIcon="true"
              placeholder="End date"
              @date-select="filterByDate"
              class="date-filter-calendar"
            />
          </div>
        </div>
        <Button
          icon="pi pi-times"
          class="p-button-text p-button-rounded clear-date-btn"
          @click="clearDateFilter"
          v-tooltip.top="'Clear date filter'"
        />
      </div>

      <!-- No Results Message -->
      <div v-if="searchQuery && filteredEvents.length === 0" class="no-results-message">
        <div class="icon-and-title">
          <i class="pi pi-search" style="font-size: 1.5rem; color: #007bff; margin-right: 10px;"></i>
          <h2 class="no-results-title">No Events Found</h2>
        </div>
        <p class="no-results-text">No events match your search criteria. Try adjusting your search terms.</p>
      </div>

      <!-- No Events Message -->
      <div v-else-if="!initialLoading && filteredEvents.length === 0" class="no-results-message">
        <div class="icon-and-title">
          <i class="pi pi-calendar-times" style="font-size: 1.5rem; color: #6c757d; margin-right: 10px;"></i>
          <h2 class="no-results-title">No Events Available</h2>
        </div>
        <p class="no-results-text">There are currently no events to display. Check back later or create a new event.</p>
      </div>

      <DataTable v-else-if="initialLoading" :value="Array(5).fill({})" class="p-datatable-striped">
        <Column header="Event Name" style="width:20%;"><template #body><Skeleton /></template></Column>
        <Column header="Description" style="width:15%;"><template #body><Skeleton /></template></Column>
        <Column header="Venue" style="width:15%;"><template #body><Skeleton /></template></Column>
        <Column header="Category" style="width:15%;"><template #body><Skeleton /></template></Column>
        <Column header="Start Date & Time" style="width:20%;"><template #body><Skeleton /></template></Column>
        <Column header="End Date & Time" style="width:20%;"><template #body><Skeleton /></template></Column>
        <Column v-if="user?.role === 'Admin' || user?.role === 'Principal'" header="Actions" style="width:10%;" body-class="text-center"><template #body><div class="flex justify-center gap-2"><Skeleton shape="circle" size="2rem" /><Skeleton shape="circle" size="2rem" /></div></template></Column>
        <Column v-if="user?.role === 'Admin' || user?.role === 'Principal'" header="Tasks" style="width:15%;" body-class="text-center"><template #body><div class="flex justify-center gap-2"><Skeleton shape="circle" size="2rem" /></div></template></Column>
      </DataTable>

      <DataTable
        v-else
        :value="filteredEvents"
        id="events-table" class="p-datatable-striped" showGridlines
        paginator :rows="10" :rowsPerPageOptions="[10, 20, 50, 100]" responsiveLayout="scroll"
        paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
        currentPageReportTemplate="Showing {first} to {last} of {totalRecords} events">
        <Column field="title" header="Event Name" style="width:20%;" sortable>
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
            <div class="tags-container">
             <span
                v-for="tag in getEventTags(data.tags)"
                :key="tag.id"
                class="tag"
                :style="{ backgroundColor: getTagColor(tag) }"
            >
                {{ tag.name }}
            </span>
            </div>
          </template>
        </Column>

        <Column field="description" header="Description" style="width:15%;" sortable>
          <template #body="{ data }">
            <div class="description" v-html="formatDescription(data.description)" @click="handleDescriptionClick"></div>
          </template>
        </Column>

        <Column field="venue" header="Venue" style="width:15%;" sortable>
        <template #body="{ data }">
            <div class="datatable-content">
            {{ data.venue || "" }}
            </div>
        </template>
        </Column>

        <Column field="category_id" header="Category" style="width:15%;" sortable>
          <template #body="{ data }">
            <div class="datatable-content">
                {{ categoryMap[data.category_id] || "" }}
            </div>
          </template>
        </Column>

        <Column field="startDateTime" header="Start Date & Time" style="width:20%;" sortable>
          <template #body="{ data }">
            <div class="date-time">
              <span class="date">{{ formatDateTime(data.startDate, data.startTime).date }}</span>
              <span class="time">{{ formatDateTime(data.startDate, data.startTime).time }}</span>
            </div>
          </template>
        </Column>

        <Column field="endDateTime" header="End Date & Time" style="width:20%;" sortable>
          <template #body="{ data }">
            <div class="date-time">
              <span class="date">{{ formatDateTime(data.endDate, data.endTime).date }}</span>
              <span class="time">{{ formatDateTime(data.endDate, data.endTime).time }}</span>
            </div>
          </template>
        </Column>

        <Column v-if="user?.role === 'Admin' || user?.role === 'Principal'" header="Actions" style="width:10%;" body-class="text-center print-hide" header-class="print-hide" >
          <template #body="{ data }">
            <div class="action-buttons">
              <Button icon="pi pi-pen-to-square" class="p-button-rounded p-button-text action-btn-info" @click="editEvent(data)" v-tooltip.top="'Edit Event'"/>
              <Button icon="pi pi-folder" class="p-button-rounded p-button-text action-btn-danger" @click="archiveEvent(data)" v-tooltip.top="'Archive Event'"/>
            </div>
          </template>
        </Column>

        <Column v-if="user?.role === 'Admin' || user?.role === 'Principal'" header="Tasks" style="width:15%;" body-class="text-center print-hide" header-class="print-hide" >
        <template #body="{ data }">
            <Button icon="pi pi-list" class="p-button-rounded p-button-text action-btn-warning" @click="tasksManager.openTaskModal(data, committees, employees)" v-tooltip.top="'Manage Tasks'"/>
        </template>
        </Column>

      </DataTable>

      <TaskEditor
        :tasks-manager="tasksManager"
        :committees="committees"
        :employees="employees"
        @save-success="handleTaskSaveSuccess"
        @save-error="handleTaskSaveError"
      />

    <!-- Create Event Modal-->
    <Dialog v-model:visible="isCreateModalVisible" modal header="Create Event" :style="{ width: '50vw' }">
        <div class="p-fluid">
          <!-- Event Title -->
          <div class="p-field">
            <label for="title">Event Title</label>
            <InputText id="title" v-model="newEvent.title" placeholder="Enter event title" />
          </div>

          <div class="p-field grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Venue -->
            <div>
              <label for="venue">Venue</label>
              <InputText id="venue" v-model="newEvent.venue" placeholder="Enter event venue (e.g., Main Hall, Stadium)" class="w-full" />
            </div>

            <!-- Event Category Dropdown -->
            <div>
              <label for="category">Category</label>
              <Select
                id="category"
                v-model="newEvent.category_id"
                :options="categories"
                optionLabel="title"
                optionValue="id"
                placeholder="Select a category"
                class="w-full"
              />
            </div>
          </div>

          <!-- Tags Selection (Conditional) -->
          <div class="p-field" v-if="newEvent.category_id">
            <label for="tags">Tags</label>
            <div class="flex items-center gap-2">
                <MultiSelect
                  id="tags"
                  v-model="newEvent.tags"
                  :options="filteredNewEventTags"
                  optionValue="id"
                  optionLabel="name"
                  placeholder="Select tags"
                  display="chip"
                  :showToggleAll="false"
                  class="w-full"
                />
                <Button icon="pi pi-plus" class="p-button-secondary p-button-rounded" @click="openTagModal('create')" v-tooltip.top="'Create New Tag'" />
            </div>
          </div>
          <!-- Create Event Modal -->
          <div class="p-field grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Start -->
            <div class="flex flex-col">
              <label for="startDate" class="mb-1 font-semibold">Start</label>
              <div class="flex items-center gap-2">
                <DatePicker
                  id="startDate"
                  v-model="newEvent.startDate"
                  dateFormat="MM-dd-yy"
                  showIcon
                  class="w-full"
                />
                <input
                  v-if="!newEvent.isAllDay"
                  type="time"
                  id="startTime"
                  v-model="newEvent.startTime"
                  placeholder="HH:mm"
                  class="p-inputtext p-component w-36"
                  @blur="newEvent.startTime = newEvent.startTime.padStart(5, '0')"
                />
              </div>
            </div>

            <!-- End -->
            <div class="flex flex-col">
              <label for="endDate" class="mb-1 font-semibold">End</label>
              <div class="flex items-center gap-2">
                <DatePicker
                  id="endDate"
                  v-model="newEvent.endDate"
                  dateFormat="MM-dd-yy"
                  showIcon
                  class="w-full"
                />
                <input
                  v-if="!newEvent.isAllDay"
                  type="time"
                  id="endTime"
                  v-model="newEvent.endTime"
                  placeholder="HH:mm"
                  class="p-inputtext p-component w-36"
                  @blur="newEvent.endTime = newEvent.endTime.padStart(5, '0')"
                />
              </div>
            </div>

            <!-- All Day Checkbox - Moved below date/time fields -->
            <div class="col-span-2 mt-2">
              <div class="flex items-center gap-2 checkbox-container">
                <Checkbox v-model="newEvent.isAllDay" :binary="true" />
                <label for="allDay" class="checkbox-label">All Day Event</label>
              </div>
            </div>
          </div>

          <!-- Error Message -->
          <div v-if="dateError" class="text-red-500 text-sm mt-2 flex items-center">
            <i class="pi pi-exclamation-triangle mr-2"></i>
            {{ dateError }}
          </div>

          <!-- Event Description -->
          <div class="p-field">
            <label for="description">Description</label>
            <Textarea id="description" v-model="newEvent.description" rows="4" placeholder="Enter event description" autoResize />
          </div>

          <!-- Memorandum File Upload -->
          <div class="p-field">
            <label for="memorandum">Memorandum</label>
            <div v-if="newEvent.memorandum" class="flex items-center gap-2 p-2 border rounded-md bg-gray-100">
                <i class="pi pi-file"></i>
                <span class="flex-1">{{ newEvent.memorandum.filename }}</span>
                <Button icon="pi pi-times" class="p-button-rounded p-button-danger p-button-text" @click="newEvent.memorandum = null" v-tooltip.top="'Remove Memorandum'"/>
            </div>
            <div v-else class="flex justify-center items-center border-2 border-dashed rounded-md p-4">
                <input type="file" ref="memoFileInputCreate" @change="handleMemoUpload($event, false)" class="hidden" />
                <Button
                    label="Upload File"
                    icon="pi pi-upload"
                    class="p-button-sm p-button-outlined"
                    @click="$refs.memoFileInputCreate.click()" />
            </div>
          </div>
        </div>

        <div class="p-field">
          <label for="image">Event Image</label>
          <div v-if="newEvent.image" class="mt-2">
            <img :src="newEvent.image" alt="Preview" class="preview-image" />
          </div>
          <div class="flex align-items-center gap-2">
            <input
              type="file"
              id="image"
              accept="image/*"
              @change="handleImageUpload"
              class="p-inputtext"
            />
            <Button
              v-if="newEvent.image && newEvent.image !== defaultImage"
              icon="pi pi-times"
              class="p-button-rounded p-button-danger p-button-text"
              @click="removeImage(false)"
              v-tooltip.top="'Remove image'"
            />
          </div>
          <small class="p-text-secondary">Leave empty to use default image</small>
        </div>

        <template #footer>
            <div class="flex justify-between w-full">
                <Button v-if="user?.role === 'Admin' || user?.role === 'Principal'" icon="pi pi-image" class="p-button-secondary" @click="$refs.defaultImageInput.click()" v-tooltip.top="'Change Default Image'" />
                <div class="flex gap-2">
                    <button class="modal-button-secondary" @click="isCreateModalVisible = false">Cancel</button>
                    <button class="modal-button-primary" @click="createEvent">Create Event</button>
                </div>
            </div>
        </template>
      </Dialog>

      <!-- Create Tag Modal -->
      <Dialog v-model:visible="isCreateTagModalVisible" modal header="Create New Tag" :style="{ width: '30vw' }">
        <div class="p-fluid">
            <div class="p-field">
                <label for="tagName">Tag Name</label>
                <InputText id="tagName" v-model="newTag.name" />
            </div>
            <div class="p-field">
                <label for="tagCategory">Category</label>
                <Select id="tagCategory" v-model="newTag.category_id" :options="categories" optionLabel="title" optionValue="id" placeholder="Select a category" class="w-full" disabled />
            </div>
        </div>
        <template #footer>
            <button class="modal-button-secondary" @click="isCreateTagModalVisible = false">Cancel</button>
            <button class="modal-button-primary" @click="createTag" :disabled="saving">Create</button>
        </template>
      </Dialog>

      <!-- Edit Event Modal -->
      <Dialog v-model:visible="isEditModalVisible" modal header="Edit Event" :style="{ width: '50vw' }">
        <div class="p-fluid">
          <!-- Event Title -->
          <div class="p-field">
            <label for="title">Event Title</label>
            <InputText id="title" v-model="selectedEvent.title" placeholder="Enter event title" />
          </div>

          <div class="p-field grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Venue -->
            <div>
              <label for="venue">Venue</label>
              <InputText id="venue" v-model="selectedEvent.venue" placeholder="Enter event venue (e.g., Main Hall, Stadium)" class="w-full" />
            </div>

            <!-- Event Category Dropdown -->
            <div>
              <label for="category">Category</label>
              <Select
                id="category"
                v-model="selectedEvent.category_id"
                :options="categories"
                optionLabel="title"
                optionValue="id"
                placeholder="Select a category"
                class="w-full"
              />
            </div>
          </div>

          <!-- Tags Selection (Conditional) -->
          <div class="p-field" v-if="selectedEvent.category_id">
            <label for="edit-tags">Tags</label>
            <div class="flex items-center gap-2">
                <MultiSelect
                id="edit-tags"
                v-model="selectedEvent.tags"
                :options="filteredSelectedEventTags"
                optionValue="id"
                optionLabel="name"
                placeholder="Select tags"
                display="chip"
                :showToggleAll="false"
                class="w-full"
                >
                <!-- Option Template -->
                <template #option="slotProps">
                    <div class="flex items-center gap-2">
                    <div
                        class="w-3 h-3 rounded-full"
                        :style="{ backgroundColor: slotProps.option.color || '#800080' }"
                    ></div>
                    <span>{{ slotProps.option.name }}</span>
                    </div>
                </template>
                <!-- Selected Chip Template -->
                <template #chip="slotProps">
                    <div v-if="tagsMap[slotProps.value]"
                        class="flex items-center gap-2 px-2 py-1 rounded text-white text-xs"
                        :style="{ backgroundColor: tagsMap[slotProps.value].color || '#800080' }"
                    >
                        {{ tagsMap[slotProps.value].name }}
                        <button type="button" class="text-white hover:text-gray-200" @click.stop="removeTag(tagsMap[slotProps.value])" v-tooltip.top="'Remove Tag'">✕</button>
                    </div>
                    <div v-else class="flex items-center gap-2 px-2 py-1 rounded bg-gray-500 text-white text-xs">
                    {{ slotProps.value }}
                    </div>
                </template>
                </MultiSelect>
                <Button icon="pi pi-plus" class="p-button-secondary p-button-rounded" @click="openTagModal('edit')" v-tooltip.top="'Create New Tag'" />
            </div>
          </div>

          <!-- Edit Event Modal -->
          <div class="p-field grid grid-cols-1 md:grid-cols-2 gap-4">
            <!-- Start Date & Time -->
            <div class="flex flex-col">
              <label for="startDate" class="mb-1 font-semibold">Start</label>
              <div class="flex items-center gap-2">
                <DatePicker id="startDate" v-model="selectedEvent.startDate" dateFormat="MM-dd-yy" showIcon class="w-full" />
                <input
                  v-if="!selectedEvent.isAllDay"
                  type="time"
                  id="startTime"
                  v-model="selectedEvent.startTime"
                  placeholder="HH:mm"
                  class="p-inputtext p-component w-36"
                  @blur="selectedEvent.startTime = selectedEvent.startTime.padStart(5, '0')"
                />
              </div>
            </div>

            <!-- End Date & Time -->
            <div class="flex flex-col">
              <label for="endDate" class="mb-1 font-semibold">End</label>
              <div class="flex items-center gap-2">
                <DatePicker id="endDate" v-model="selectedEvent.endDate" dateFormat="MM-dd-yy" showIcon class="w-full" />
                <input
                  v-if="!selectedEvent.isAllDay"
                  type="time"
                  id="endTime"
                  v-model="selectedEvent.endTime"
                  placeholder="HH:mm"
                  class="p-inputtext p-component w-36"
                  @blur="selectedEvent.endTime = selectedEvent.endTime.padStart(5, '0')"
                />
              </div>
            </div>

            <!-- All Day Checkbox - Moved below date/time fields -->
            <div class="col-span-2 mt-2">
              <div class="flex items-center gap-2 checkbox-container">
                <Checkbox v-model="selectedEvent.isAllDay" :binary="true" />
                <label for="allDay" class="checkbox-label">All Day Event</label>
              </div>
            </div>
          </div>

          <!-- Error Display -->
          <div v-if="dateError" class="text-red-500 text-sm mt-2 flex items-center">
            <i class="pi pi-exclamation-triangle mr-2"></i>
            {{ dateError }}
          </div>

          <div class="p-field">
            <label for="description">Description</label>
            <Textarea id="description" v-model="selectedEvent.description" rows="4" placeholder="Enter event description" autoResize />
          </div>

          <!-- Memorandum File Upload -->
          <div class="p-field">
            <label for="edit-memorandum">Memorandum</label>
            <div v-if="selectedEvent.memorandum" class="flex items-center gap-2 p-2 border rounded-md bg-gray-100">
                <i class="pi pi-file"></i>
                <span class="flex-1">{{ selectedEvent.memorandum.filename }}</span>
                <Button icon="pi pi-times" class="p-button-rounded p-button-danger p-button-text" @click="selectedEvent.memorandum = null" v-tooltip.top="'Remove Memorandum'"/>
            </div>
            <div v-else class="flex justify-center items-center border-2 border-dashed rounded-md p-4">
                <input type="file" ref="memoFileInputEdit" @change="handleMemoUpload($event, true)" class="hidden" />
                <Button
                    label="Upload File"
                    icon="pi pi-upload"
                    class="p-button-sm p-button-outlined"
                    @click="$refs.memoFileInputEdit.click()" />
            </div>
          </div>
        </div>

        <div class="p-field">
          <label for="image">Event Image</label>
          <div v-if="selectedEvent.image" class="mt-2">
            <img :src="selectedEvent.image" alt="Preview" class="preview-image" />
          </div>
          <div class="flex align-items-center gap-2">
            <input
              type="file"
              id="image"
              accept="image/*"
              @change="handleEditImageUpload"
              class="p-inputtext"
            />
            <Button
              v-if="selectedEvent.image && selectedEvent.image !== defaultImage"
              icon="pi pi-times"
              class="p-button-rounded p-button-danger p-button-text"
              @click="removeImage(true)"
              v-tooltip.top="'Remove image'"
            />
          </div>
          <small class="p-text-secondary">Leave empty to keep current image</small>
        </div>

        <template #footer>
          <button class="modal-button-secondary" @click="isEditModalVisible = false">Cancel</button>
          <button class="modal-button-primary" @click="saveEditedEvent">Save Changes</button>
        </template>
      </Dialog>
    </div>

    <!-- Success Dialog -->
    <SuccessDialog
        v-model:show="showSuccessDialog"
        :message="successMessage"
    />
    <!-- Error Dialog -->
    <div v-if="showErrorDialog" class="fixed inset-0 bg-black bg-opacity-30 flex items-center justify-center" style="z-index: 9998;">
      <div class="bg-white p-6 rounded-lg shadow-lg max-w-sm w-full">
        <h2 class="text-lg font-semibold text-red-700 mb-2">Error</h2>
        <p class="text-sm text-gray-700 mb-4">{{ errorMessage }}</p>
        <div class="flex justify-end">
          <button @click="showErrorDialog = false" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">Close</button>
        </div>
      </div>
    </div>

    <!-- Archive Confirmation Dialog -->
    <ConfirmationDialog
      v-model:show="showArchiveConfirm"
      title="Archive Event?"
      :message="eventToProcess ? `Are you sure you want to archive '${eventToProcess.title}'?` : ''"
      confirmText="Yes, Archive"
      confirmButtonClass="modal-button-confirm bg-yellow-600 hover:bg-yellow-700"
      @confirm="confirmArchive"
    />

    <!-- Save Changes Confirmation Dialog -->
    <ConfirmationDialog
      v-model:show="showSaveConfirm"
      title="Save Changes?"
      :message="selectedEvent ? `Are you sure you want to save changes to '${selectedEvent.title}'?` : ''"
      confirmText="Yes, Save"
      @confirm="confirmSaveChanges"
    />

    <!-- Create Confirmation Dialog -->
    <ConfirmationDialog
      v-model:show="showCreateConfirm"
      title="Create Event?"
      message="Are you sure you want to create this event?"
      confirmText="Yes, Create"
      @confirm="confirmCreateEvent"
    />

  </template>

  <script>
  import { defineComponent, ref, onMounted, computed, watch, nextTick } from "vue";
  import { usePage, Link, router } from '@inertiajs/vue3';
  import axios from "axios";
  import { parse, format, isWithinInterval } from 'date-fns';
  import { useTasks } from '@/composables/useTasks.js';
  import { useMemorandum } from '@/composables/useMemorandum.js';
  import SearchFilterBar from '@/Components/SearchFilterBar.vue';
  import TaskEditor from '@/Components/TaskEditor.vue';

  export default defineComponent({
    name: "EventList",
    components: {
      TaskEditor,
      SearchFilterBar,
    },
    inheritAttrs: false,
    setup() {
      const dateError = ref("");
      const page = usePage();
      const user = computed(() => page.props.auth.user);
      const resetErrors = () => {
        dateError.value = "";
        };
      const events = computed(() => page.props.events_prop || []);
      const categories = computed(() => page.props.categories_prop || []);
      const initialLoading = ref(true);
      const isEditModalVisible = ref(false);
      const selectedEvent = ref(null);
      const tags = ref(page.props.tags_prop || []);
      const committees = computed(() => page.props.committees_prop || []);
      const employees = computed(() => page.props.employees_prop || []);
      const isCreateModalVisible = ref(false);
      const isCreateTagModalVisible = ref(false);
      const defaultImage = computed(() =>
        page.props.settings_prop?.defaultEventImage ||
        "\/images\/NCSlogo.png"
      );
      const searchQuery = ref("");
      const showDateFilter = ref(false);
      const dateRange = ref({
        from: null,
        to: null
      });
      const rowsPerPage = ref(10);

      // Centralized Task Management
      const tasksManager = useTasks();

      const newEvent = ref({
        title: "",
        description: "",
        venue: "",
        category_id: null,
        startDate: null,
        endDate: null,
        startTime: "",
        endTime: "",
        tags: [],
        image: defaultImage.value,
        archived: false,
        isAllDay: false,
      });

      // Memorandum composable
      const { saveMemorandum, clearMemorandum } = useMemorandum();

      const filteredNewEventTags = computed(() => {
        if (!newEvent.value.category_id) {
            return [];
        }
        return tags.value.filter(tag => tag.category_id == newEvent.value.category_id);
      });

      watch(() => newEvent.value.category_id, (newVal, oldVal) => {
        if (newVal !== oldVal) {
            newEvent.value.tags = [];
        }
      });

      const filteredSelectedEventTags = computed(() => {
        if (!selectedEvent.value?.category_id) {
            return [];
        }
        return tags.value.filter(tag => tag.category_id == selectedEvent.value.category_id);
      });

      const combinedEvents = computed(() => {
        const processItem = item => ({
            ...item,
            category_id: item.category?.id || item.category_id,
            startDateTime: item.startDate ? `${format(new Date(item.startDate), 'yyyy-MM-dd')}T${item.startTime || '00:00'}` : null,
            endDateTime: item.endDate ? `${format(new Date(item.endDate), 'yyyy-MM-dd')}T${item.endTime || '00:00'}` : null,
        });

        const allEvents = (events.value || []).map(processItem);

        return allEvents.sort((a, b) => {
            const dateA = a.startDateTime ? new Date(a.startDateTime) : new Date("1970-01-01");
            const dateB = b.startDateTime ? new Date(b.startDateTime) : new Date("1970-01-01");
            return dateB - dateA;
        });
      });

      const openCreateModal = () => {
        newEvent.value = {
          title: "",
          description: "",
          venue: "",
          category_id: null,
          startDate: null,
          endDate: null,
          startTime: "",
          endTime: "",
          memorandum: null,
          tags: [],
          image: defaultImage.value,
          archived: false,
          isAllDay: false
        };
        isCreateModalVisible.value = true;
        dateError.value = "";
      };

      const tagsMap = computed(() => {
        return tags.value.reduce((map, tag) => {
            map[tag.id] = tag;
            return map;
        }, {});
      });

      const newTag = ref({
        name: "",
        color: "#ff0000",
        category_id: null
      });

      watch(() => selectedEvent.value?.category_id, (newVal, oldVal) => {
        if (selectedEvent.value && newVal !== oldVal) {
            selectedEvent.value.tags = [];
        }
      });

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

     const openTagModal = (context = 'create') => {
        tagCreationContext.value = context;
        const categoryId = context === 'edit' ? selectedEvent.value.category_id : newEvent.value.category_id;
        newTag.value = { name: '', color: '#ff0000', category_id: categoryId };
        isCreateTagModalVisible.value = true;
     };

     const createTag = async () => {
       if (!newTag.value.name.trim()) {
         errorMessage.value = "Tag name cannot be empty.";
         showErrorDialog.value = true;
         return;
       }

       saving.value = true;
       try {
         const response = await axios.post(route('category.store'), {
           name: newTag.value.name,
           category_id: newTag.value.category_id
         });

         if (response.data.success) {
           const createdTag = response.data.tag;
           tags.value.push(createdTag);
           if (tagCreationContext.value === 'edit') {
               selectedEvent.value.tags.push(createdTag.id);
           } else {
               newEvent.value.tags.push(createdTag.id);
           }
           isCreateTagModalVisible.value = false;
           successMessage.value = 'Tag created successfully!';
           showSuccessDialog.value = true;
         }
       } catch (error) {
         errorMessage.value = `Failed to create tag: ${error.response?.data?.message || 'An unknown error occurred.'}`;
         showErrorDialog.value = true;
       } finally {
         saving.value = false;
       }
     };

     const createEvent = () => {
        resetErrors();
        let hasError = false;

        if (!newEvent.value.title.trim()) {
            errorMessage.value = "Please enter a valid event title";
            showErrorDialog.value = true;
            hasError = true;
        }

        if (!newEvent.value.startDate || !newEvent.value.endDate) {
            dateError.value = "Both start and end dates are required.";
            hasError = true;
        } else {
            const start = new Date(newEvent.value.startDate);
            const end = new Date(newEvent.value.endDate);

            if (end < start) {
                dateError.value = "End date cannot be before start date.";
                hasError = true;
            }
        }

        if (!newEvent.value.isAllDay) {
            if (!newEvent.value.startTime || !newEvent.value.endTime) {
                dateError.value = (dateError.value ? dateError.value + ' ' : '') + "Start and end times are required for non-all-day events.";
                hasError = true;
            } else if (newEvent.value.startDate && newEvent.value.endDate && newEvent.value.startTime && newEvent.value.endTime) {
                const startDateTime = new Date(`${format(new Date(newEvent.value.startDate), 'yyyy-MM-dd')}T${newEvent.value.startTime}`);
                const endDateTime = new Date(`${format(new Date(newEvent.value.endDate), 'yyyy-MM-dd')}T${newEvent.value.endTime}`);
                if (endDateTime <= startDateTime) {
                    dateError.value = (dateError.value ? dateError.value + ' ' : '') + "End date/time must be after start date/time.";
                    hasError = true;
                }
            }
        }

        if (hasError) {
            // If we already showed a specific error, don't show the generic one.
            if (!errorMessage.value) {
                errorMessage.value = "Please correct the errors before creating the event.";
                showErrorDialog.value = true;
            }
            if (end < start) {
                dateError.value = "End date cannot be before start date";
                return;
            }
        }

        showCreateConfirm.value = true;
    };

    const confirmCreateEvent = async () => {
        let finalImage = newEvent.value.image;
        if (newEvent.value.image.startsWith('blob:')) {
            const response = await fetch(newEvent.value.image);
            const blob = await response.blob();
            finalImage = await new Promise((resolve) => {
                const reader = new FileReader();
                reader.onloadend = () => resolve(reader.result);
                reader.readAsDataURL(blob);
            });
        }

        saving.value = true;
        showCreateConfirm.value = false;

        try {
          const payload = {
            ...newEvent.value,
            image: finalImage || defaultImage.value,
            tags: newEvent.value.tags || [],
            startDate: newEvent.value.startDate
                ? format(new Date(newEvent.value.startDate), "MMM-dd-yyyy")
                : null,
            endDate: newEvent.value.endDate
                ? format(new Date(newEvent.value.endDate), "MMM-dd-yyyy")
                : null,
            startTime: newEvent.value.startTime.padStart(5, "0"),
            endTime: newEvent.value.endTime.padStart(5, "0"),
            archived: false,
            createdAt: new Date().toISOString(),
            memorandum: newEvent.value.memorandum,
        };

        // The router.post call will handle the main event data.
        // We'll handle the memorandum in the onSuccess callback.
        router.post(route('events.store'), payload, {
            onSuccess: (page) => {
                const createdEventId = page.props.flash?.event_id;
                if (createdEventId && newEvent.value.memorandum) {
                    try {
                      saveMemorandum(createdEventId, newEvent.value.memorandum);
                    } catch (e) {
                      console.error('Failed to save memorandum:', e);
                    }
                }

                isCreateModalVisible.value = false;
                // Reset newEvent state after successful creation
                newEvent.value = {
                  title: "",
                  description: "",
                  venue: "",
                  category_id: null,
                  startDate: null,
                  endDate: null,
                  startTime: "",
                  endTime: "",
                  memorandum: null,
                  tags: [],
                  image: defaultImage.value,
                  archived: false,
                  isAllDay: false
                };
                successMessage.value = 'Event created successfully!';
                showSuccessDialog.value = true;
            },

            onError: (errors) => {
                const firstErrorKey = Object.keys(errors)[0];
                const firstErrorMessage = firstErrorKey ? (Array.isArray(errors[firstErrorKey]) ? errors[firstErrorKey][0] : errors[firstErrorKey]) : 'Failed to create the event.';
                errorMessage.value = firstErrorMessage;
                showErrorDialog.value = true;
            },
            preserveScroll: true
        });
    } catch (error) {
        errorMessage.value = 'Failed to create the event.';
        showErrorDialog.value = true;
    } finally {
        saving.value = false;
    }
      };

      const handleEditImageUpload = (event) => {
        const file = event.target.files[0];
        if (!file) return;

        // Clean up previous blob URL if exists
        if (selectedEvent.value.image && selectedEvent.value.image.startsWith('blob:')) {
            URL.revokeObjectURL(selectedEvent.value.image);
        }

        const reader = new FileReader();
        reader.onload = (e) => {
            const img = new Image();
            img.onload = () => {
                const MAX_WIDTH = 1920; // Increase max width for better banner quality
                let width = img.width;
                let height = img.height;

                if (width > MAX_WIDTH) {
                    height = (height / width) * MAX_WIDTH;
                    width = MAX_WIDTH;
                }

                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                canvas.width = width;
                canvas.height = height;

                // Enable image smoothing for better quality
                ctx.imageSmoothingEnabled = true;
                ctx.imageSmoothingQuality = 'high';

                ctx.drawImage(img, 0, 0, width, height);

                // Get compressed data in WebP format for better quality and smaller file size
                const compressedData = canvas.toDataURL('image/webp', 0.9);
                selectedEvent.value.image = compressedData;
            };
            img.onerror = () => {
                selectedEvent.value.image = defaultImage;
            };
            img.src = e.target.result;
        };
        reader.onerror = () => {
            selectedEvent.value.image = defaultImage;
        };
        reader.readAsDataURL(file);
    };

      const handleImageUpload = (event) => {
        const file = event.target.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = (e) => {
            const img = new Image();
            img.onload = () => {
                const MAX_WIDTH = 1920; // Increase max width for better banner quality
                let width = img.width;
                let height = img.height;

                if (width > MAX_WIDTH) {
                    height = (height / width) * MAX_WIDTH;
                    width = MAX_WIDTH;
                }

                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                canvas.width = width;
                canvas.height = height;

                ctx.imageSmoothingEnabled = true;
                ctx.imageSmoothingQuality = 'high';

                ctx.drawImage(img, 0, 0, width, height);

                // Get compressed data in WebP format for better quality and smaller file size
                const compressedData = canvas.toDataURL('image/webp', 0.9);
                newEvent.value.image = compressedData;
            };
            img.src = e.target.result;
        };
        reader.readAsDataURL(file);
    };

        const removeImage = (isEdit = false) => {
        const target = isEdit ? selectedEvent.value : newEvent.value;

        if (target.image && target.image.startsWith('blob:')) {
            URL.revokeObjectURL(target.image); // Clean up memory
        }
        target.image = defaultImage.value;
        };

      const normalizedTags = computed(() => {
        return tags.value.reduce((map, tag) => {
            map[tag.id] = tag;
            return map;
        }, {});
        });

    const getEventTags = (eventTags) => {
        return Array.isArray(eventTags) ? eventTags : [];
    };

    onMounted(async () => {
        initialLoading.value = true;
        // Data is now passed as props on initial visit.
        // We just need to switch off the loading indicator after mount.
        initialLoading.value = false;
    });

    const handleTaskSaveSuccess = ({ message }) => {
        tasksManager.isTaskModalVisible.value = false; // Close the modal
        successMessage.value = message; // Show success message
        showSuccessDialog.value = true;

    };

    const handleTaskSaveError = (message) => {
        errorMessage.value = message;
        showErrorDialog.value = true;
    };


      // Format date and time display
      const formatDateTime = (date, time) => {
        const formattedDate = date ? format(new Date(date), 'MMM-dd-yyyy') : 'No date';
        let formattedTime = '';

        if (time === "00:00" && time === "23:59") {
          formattedTime = 'All Day';
        } else if (time) {
          try {
            const paddedTime = time.padStart(5, '0');
            const parsedTime = parse(paddedTime, 'HH:mm', new Date());
            formattedTime = format(parsedTime, 'hh:mm a');
          } catch (e) {
            console.error('Error formatting time:', e);
            formattedTime = time.padStart(5, '0');
          }
        }

        return { date: formattedDate, time: formattedTime };
       };

      // Map category_id to category title
      const categoryMap = computed(() =>
        categories.value.reduce((map, category) => {
          map[category.id] = category.title;
          return map;
        }, {})
      );

      // Open Edit Modal
    const editEvent = (event) => {
        // Defensive: If tags are not loaded yet, wait and retry
        if (!tags.value || tags.value.length === 0) {
            // Wait for tags to be loaded, then retry
            const unwatch = watch(tags, (newTags) => {
                if (newTags && newTags.length > 0) {
                    unwatch();
                    editEvent(event); // Retry with tags loaded
                }
            });
            return;
        }

        selectedEvent.value = {
            ...event,
            venue: event.venue || "",
            tags: (event.tags || []).map(tag =>
                typeof tag === 'object' && tag !== null ? tag.id : tag
            ),
            startDate: event.startDate ? new Date(event.startDate) : null,
            endDate: event.endDate ? new Date(event.endDate) : null,
            image: event.image || defaultImage.value,
            memorandum: event.memorandum || null,
        };
        isEditModalVisible.value = true;
    };

    const saveEditedEvent = () => {
      if (!selectedEvent.value) return;
      resetErrors();
      let hasError = false;

      if (!selectedEvent.value.title.trim()) {
        errorMessage.value = 'Please enter a valid event title';
        showErrorDialog.value = true;
        hasError = true;
      }

      if (!selectedEvent.value.startDate || !selectedEvent.value.endDate) { // No changes here, just for context
        dateError.value = "Both start and end dates are required.";
        hasError = true;
      } else {
        const start = new Date(selectedEvent.value.startDate);
        const end = new Date(selectedEvent.value.endDate);

        if (end < start) {
          dateError.value = "End date cannot be before start date";
          hasError = true;
        }
      }

      if (!selectedEvent.value.isAllDay) {
        if (!selectedEvent.value.startTime || !selectedEvent.value.endTime) {
            dateError.value = (dateError.value ? dateError.value + ' ' : '') + "Start and end times are required for non-all-day events.";
            hasError = true;
        } else if (selectedEvent.value.startDate && selectedEvent.value.endDate && selectedEvent.value.startTime && selectedEvent.value.endTime) {
            const startDateTime = new Date(`${format(new Date(selectedEvent.value.startDate), 'yyyy-MM-dd')}T${selectedEvent.value.startTime}`);
            const endDateTime = new Date(`${format(new Date(selectedEvent.value.endDate), 'yyyy-MM-dd')}T${selectedEvent.value.endTime}`);
            if (endDateTime <= startDateTime) {
                dateError.value = (dateError.value ? dateError.value + ' ' : '') + "End date/time must be after start date/time.";
                hasError = true;
            }
        }
      }

      if (hasError) {
        return; // Stop execution if there are validation errors
      }

      showSaveConfirm.value = true;
    };

    let originalMemorandum = null;
    const confirmSaveChanges = async () => {
      saving.value = true;
      try {
        // If the image is a new upload (blob URL), convert it to a base64 data URL
        let finalImage = selectedEvent.value.image;
        if (selectedEvent.value.image && selectedEvent.value.image.startsWith('blob:')) {
          const blobUrl = selectedEvent.value.image;
          try {
            const response = await fetch(blobUrl);
            const blob = await response.blob();
            finalImage = await new Promise((resolve, reject) => {
              const reader = new FileReader();
              reader.onloadend = () => resolve(reader.result);
              reader.readAsDataURL(blob);
              reader.onerror = reject;
            });
            URL.revokeObjectURL(blobUrl); // Revoke the blob URL after it has been read
          } catch (error) {
            console.error("Failed to process blob image:", error);
            finalImage = defaultImage;
          }
        } else if (!selectedEvent.value.image) {
          finalImage = defaultImage.value;
        }
        const payload = {
          ...selectedEvent.value,
          image: finalImage,
          tags: selectedEvent.value.tags || [],
          startDate: selectedEvent.value.startDate
            ? format(new Date(selectedEvent.value.startDate), "MMM-dd-yyyy")
            : null,
          endDate: selectedEvent.value.endDate
            ? format(new Date(selectedEvent.value.endDate), "MMM-dd-yyyy")
            : null,
          startTime: selectedEvent.value.startTime?.padStart(5, "0") || "00:00",
          endTime: selectedEvent.value.endTime?.padStart(5, "0") || "00:00",
          memorandum: selectedEvent.value.memorandum,
        };

        router.put(route('events.updateFromList', {id: selectedEvent.value.id}), payload, {
          onSuccess: () => {
            const eventId = selectedEvent.value.id;
            const currentMemo = selectedEvent.value.memorandum;

            if (currentMemo && currentMemo.isNew) {
                saveMemorandum(eventId, currentMemo);
            } else if (!currentMemo && originalMemorandum) {
                clearMemorandum(eventId);
            }

            isEditModalVisible.value = false;
            successMessage.value = 'Event updated successfully!';
            showSuccessDialog.value = true;
          },
          onError: (errors) => {
            const firstErrorKey = Object.keys(errors)[0];
            const firstErrorMessage = firstErrorKey ? (Array.isArray(errors[firstErrorKey]) ? errors[firstErrorKey][0] : errors[firstErrorKey]) : 'Failed to update the event.';
            errorMessage.value = firstErrorMessage;
            showErrorDialog.value = true;
          },
          preserveScroll: true
        });
      } catch (error) {
        errorMessage.value = 'Failed to update the event.';
        showErrorDialog.value = true;
      } finally {
        saving.value = false;
        showSaveConfirm.value = false;
      }
    };

    const archiveEvent = (event) => {
      eventToProcess.value = event;
      showArchiveConfirm.value = true;
    };

    const confirmArchive = () => {
      if (!eventToProcess.value) return;
      saving.value = true;
      router.put(route('events.archive', {id: eventToProcess.value.id}), {}, {
        onSuccess: () => {
          successMessage.value = 'Event archived successfully!';
          showSuccessDialog.value = true;
        },
        onError: (errors) => {
          errorMessage.value = 'Failed to archive the event.';
          showErrorDialog.value = true;
        },
        onFinish: () => {
          saving.value = false;
          showArchiveConfirm.value = false;
          eventToProcess.value = null;
        },
        preserveScroll: true
      });
    };

    const removeTag = (tagToRemove) => {
      selectedEvent.value.tags = selectedEvent.value.tags.filter(tagId => tagId !== tagToRemove.id);
    };

    const handleDescriptionClick = (event) => {
      if (event.target.tagName === 'A') {
        event.stopPropagation();
      }
    };

    const handleMemoUpload = (event, isEdit) => {
        // This function now only needs to handle the file reading part.
        // The save/clear logic is moved to the main save functions.
        const file = event.target.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = (e) => {
            const target = isEdit ? selectedEvent.value : newEvent.value;
            target.memorandum = {
                type: file.type.startsWith('image/') ? 'image' : 'file',
                content: e.target.result,
                filename: file.name,
                isNew: true, // Flag to indicate it's a new upload
            };
        };
        reader.onerror = (err) => {
            console.error("File reading error:", err);
            errorMessage.value = "Failed to read the memorandum file.";
            showErrorDialog.value = true;
        };
        reader.readAsDataURL(file);
    };

    // Store the original memorandum when opening the edit modal
    watch(isEditModalVisible, (isVisible) => {
        if (isVisible && selectedEvent.value) originalMemorandum = selectedEvent.value.memorandum;
    });

    const handleDefaultImageUpload = async (event) => {
        const file = event.target.files[0];
        if (!file) return;

        const reader = new FileReader();
        reader.onload = async (e) => {
            const newDefaultImage = e.target.result;
            saving.value = true;
            try {
                router.post(route('events.updateDefaultImage'), { image: newDefaultImage }, {
                    preserveState: true,
                    onSuccess: (updatedPage) => {
                        // Manually update the prop to trigger computed properties
                        page.props.settings_prop = updatedPage.props.settings_prop;

                        // Explicitly update newEvent.value.image to reflect the new default
                        // This ensures the preview in the create modal updates immediately.
                        newEvent.value.image = updatedPage.props.settings_prop.defaultEventImage;

                        successMessage.value = 'Default image updated successfully!';
                        showSuccessDialog.value = true;
                    },
                    onError: (errors) => {
                        errorMessage.value = errors.image || 'Failed to update default image.';
                        showErrorDialog.value = true;
                    }
                });
            } catch (error) {
                console.error('Failed to update default image:', error);
                errorMessage.value = 'An unexpected error occurred.';
                showErrorDialog.value = true;
            } finally { // This finally block is correctly placed
                saving.value = false;
                if (event.target) event.target.value = ''; // Reset file input
            }
        };
        reader.readAsDataURL(file);
    };

// Add date filtering functionality
    const toggleDateFilter = () => {
      showDateFilter.value = !showDateFilter.value;
      if (!showDateFilter.value) {
        clearDateFilter();
      }
    };

    const clearDateFilter = () => {
      dateRange.value = {
        from: null,
        to: null
      };
      showDateFilter.value = false;
    };

    const filterByDate = () => {
      if (!dateRange.value.from && !dateRange.value.to) return;
      // The filtering is handled in the filteredEvents computed property
    };

    // Modify the filteredEvents computed property to include date range filtering
    const filteredEvents = computed(() => {
      let events = combinedEvents.value;

      // Apply search query filter
      if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase().trim();
        events = events.filter(event => {
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
      }

      // Apply date range filter
      if (dateRange.value.from || dateRange.value.to) {
        events = events.filter(event => {
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
            return isWithinInterval(eventDate, {
              start: dateRange.value.from,
              end: dateRange.value.to
            });
          }

          return true;
        });
      }

      return events;
    });

    const getTagColor = (tag) => {
        if (!tag || !tag.category_id) return '#808080'; // Default gray
        const category = categories.value.find(c => c.id == tag.category_id);
        return category?.color || '#808080';
    };


    const saving = ref(false);
    const showSuccessDialog = ref(false);
    const successMessage = ref('');
    const showErrorDialog = ref(false);
    const errorMessage = ref('');
    const showArchiveConfirm = ref(false);
    const showDeleteTaskConfirm = ref(false);
    const showSaveConfirm = ref(false);
    const eventToProcess = ref(null);
    const taskToDelete = ref(null);
    const tagCreationContext = ref('create');
    const showCreateConfirm = ref(false);

    const printTable = async () => {
    const originalRows = rowsPerPage.value;
    rowsPerPage.value = filteredEvents.value.length;

    await nextTick();

    const table = document.querySelector('#events-table');
    if (!table) {
        rowsPerPage.value = originalRows;
        return;
    }

    // Correct logo path (don’t use /public/)
    const logoUrl = new URL('/images/NCSlogo.png', window.location.origin).href;

    const printWindow = window.open('', '', 'width=1200,height=800');
    const tableClone = table.cloneNode(true);

    // Remove paginator and print-hidden columns
    tableClone.querySelectorAll('.p-paginator, .print-hide').forEach(el => el.remove());

    // Remove all event images (but keep logo)
    tableClone.querySelectorAll('img').forEach(img => {
        if (!img.classList.contains('school-logo')) img.remove();
    });

    // --- STRIP HEADERS: convert each <th> into plain text and remove attributes ---
    tableClone.querySelectorAll('th').forEach(th => {
        // Get the most likely title node (PrimeVue places title in .p-column-title or inside text)
        const titleEl = th.querySelector('.p-column-title') || th.querySelector('.p-sortable-column-icon') || null;
        const titleText = titleEl ? titleEl.textContent.trim() : th.textContent.trim();

        // Remove all attributes from the th
        const attrs = Array.from(th.attributes || []);
        attrs.forEach(a => th.removeAttribute(a.name));

        // Replace the inner HTML with the plain text title
        th.innerHTML = '';
        th.textContent = titleText;
        // Ensure it has default styling (no interactive cursor)
        th.style.cursor = 'default';
    });

    // Safety: also remove any leftover sortable icon elements anywhere
    tableClone.querySelectorAll('.p-sortable-column-icon, .pi-sort-alt, .pi-sort-amount-up, .pi-sort-amount-down')
        .forEach(icon => icon.remove());

    printWindow.document.write(`
        <html>
        <head>
            <title>Event List</title>
            <style>
            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            }
            .print-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 1.5rem;
                padding-bottom: 1rem;
                border-bottom: 2px solid #dee2e6;
            }
            .header-left { display: flex; align-items: center; gap: 1rem; }
            .header-text { display: flex; flex-direction: column; }
            .school-logo { width: 80px; height: 80px; display: block; object-fit: contain; }
            .school-name { font-size: 1.5rem; font-weight: bold; color: #334155; }
            .report-title { font-size: 1.2rem; color: #475569; }
            .print-date { font-size: 0.9rem; color: #64748b; text-align: right; }

            table { width: 100%; border-collapse: collapse; font-size: 12px; }
            th, td { border: 1px solid #dee2e6; padding: 0.5rem; text-align: left; }
            th { font-weight: 600; background-color: #f8f9fa; }
            .p-datatable-striped tr:nth-child(even) { background-color: #f8f9fa; }

            a { color: inherit; text-decoration: none; }

            /* Hide any remaining images in table (safety net) */
            td img { display: none !important; }

            /* Hide sort icons (extra safety for PrimeVue) */
            .p-sortable-column-icon,
            .pi-sort-alt,
            .pi-sort-amount-up,
            .pi-sort-amount-down {
                display: none !important;
            }

            img { -webkit-print-color-adjust: exact; print-color-adjust: exact; }

            @page {
                size: landscape;
                margin: 1in;
            }
                /* Hide event tags completely in print */
                .tags-container,
                .tag {
                display: none !important;
                visibility: hidden !important;
                }

            </style>
        </head>
        <body>
            <div class="print-header">
            <div class="header-left">
                <img src="${logoUrl}" alt="School Logo" class="school-logo" />
                <div class="header-text">
                <div class="school-name">Naawan Central School</div>
                <div class="report-title">Event List</div>
                </div>
            </div>
            <div class="print-date">
                ${new Date().toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
                })}
            </div>
            </div>
            ${tableClone.outerHTML}
        </body>
        </html>
    `);
    printWindow.document.close();

    // Wait for logo load before printing
    const waitForImageLoad = (win, selector = '.school-logo', timeout = 5000) => {
        return new Promise((resolve) => {
        try {
            const img = win.document.querySelector(selector);
            if (!img) return resolve();

            if (img.complete && img.naturalWidth !== 0) return resolve();

            let done = false;
            const finish = () => { if (!done) { done = true; resolve(); } };
            img.addEventListener('load', finish);
            img.addEventListener('error', finish);
            setTimeout(finish, timeout);
        } catch {
            resolve();
        }
        });
    };

    await waitForImageLoad(printWindow, '.school-logo', 6000);

    // Print and close
    printWindow.focus();
    printWindow.print();
    printWindow.close();

    // Restore rows
    rowsPerPage.value = originalRows;
    };

    // Add watch for isAllDay changes
    watch(() => newEvent.value.isAllDay, (newValue) => {
      if (newValue) {
        newEvent.value.startTime = "00:00";
        newEvent.value.endTime = "23:59";
      } else {
        newEvent.value.startTime = "";
        newEvent.value.endTime = "";
      }
    });

    watch(() => selectedEvent.value?.isAllDay, (newValue) => {
      if (newValue) {
        selectedEvent.value.startTime = "00:00";
        selectedEvent.value.endTime = "23:59";
      } else {
        selectedEvent.value.startTime = "";
        selectedEvent.value.endTime = "";
      }
    });

    return {
    combinedEvents,
    categoryMap,
    formatDateTime,
    editEvent,
    saveEditedEvent,
    isEditModalVisible,
    archiveEvent,
    selectedEvent,
    categories,
    tags,
    committees,
    dateError,
    isCreateModalVisible,
    isCreateTagModalVisible,
    newEvent,
    newTag,
    openCreateModal,
    openTagModal,
    createEvent,
    createTag,
    handleImageUpload,
    handleEditImageUpload,
    removeImage,
    defaultImage,
    searchQuery,
    filteredEvents,
    showDateFilter,
    dateRange,
    toggleDateFilter,
    clearDateFilter,
    filterByDate,
    getEventTags,
    removeTag,
    saving,
    showSuccessDialog,
    successMessage,
    showErrorDialog,
    errorMessage,
    showArchiveConfirm,
    showDeleteTaskConfirm,
    showSaveConfirm,
    eventToProcess,
    taskToDelete,
    tagCreationContext,
    confirmArchive,
    initialLoading,
    confirmSaveChanges,
    user,
    showCreateConfirm,
    confirmCreateEvent,
    formatDescription,
    filteredNewEventTags,
    tagsMap,
    filteredSelectedEventTags,
    handleDescriptionClick,
    employees,
    handleMemoUpload,
    handleDefaultImageUpload,
    getTagColor,
    rowsPerPage,
    printTable,
    tasksManager,
    handleTaskSaveSuccess,
    handleTaskSaveError,
    };

    },
 });
 </script>

<style scoped>
.search-container {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

@media (max-width: 768px) {
  .search-container .p-input-icon-left {
    max-width: 100%;
  }
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

@media (max-width: 768px) {
  .date-range-wrapper {
    flex-direction: column;
  }

  .date-filter-container {
    width: 100%;
  }

  .date-filter-calendar {
    width: 100%;
  }
}

.checkbox-container {
  display: flex;
  align-items: center;
}

.checkbox-label {
  line-height: 1;
  padding-top: 9px;
}

:deep(.p-checkbox) {
  display: flex;
  align-items: center;
}

</style>
