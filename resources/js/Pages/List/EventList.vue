<template>
    <div class="event-list-container">
        <LoadingSpinner :show="saving" />
        <h1 class="title">Event List</h1>

      <div class="search-container mb-4">
        <div class="search-wrapper">
          <div class="p-input-icon-left">
            <i class="pi pi-search" />
            <InputText v-model="searchQuery" placeholder="Search events..." class="w-full" />
          </div>
          <Button
            icon="pi pi-calendar"
            class="p-button-outlined date-filter-btn"
            @click="toggleDateFilter"
            :class="{ 'p-button-primary': showDateFilter }"
            v-tooltip.top="'Filter by date'"
          />
        </div>
        <button v-if="user?.name === 'Admin' || user?.name === 'Principal'" class="create-button" @click="openCreateModal">Create Event</button>
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
        <Column v-if="user?.name === 'Admin' || user?.name === 'Principal'" header="Actions" style="width:10%;" body-class="text-center"><template #body><div class="flex justify-center gap-2"><Skeleton shape="circle" size="2rem" /><Skeleton shape="circle" size="2rem" /></div></template></Column>
        <Column v-if="user?.name === 'Admin' || user?.name === 'Principal'" header="Tasks" style="width:15%;" body-class="text-center"><template #body><div class="flex justify-center gap-2"><Skeleton shape="circle" size="2rem" /></div></template></Column>
      </DataTable>

      <DataTable v-else :value="filteredEvents" class="p-datatable-striped">
        <Column field="title" header="Event Name" style="width:20%;" sortable>
          <template #body="{ data }">
            <div class="flex items-center gap-2">
              <img v-if="data.image" :src="data.image" alt="Event Image" class="event-icon" />
              <Link
                :href="route('event.details', { id: data.id })"
                class="text-lg font-medium overflow-hidden line-clamp-2 hover:text-blue-600 transition-colors duration-200 cursor-pointer"
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
                :style="{ backgroundColor: tag.color || '#800080' }"
            >
                {{ tag.name }}
            </span>
            </div>
          </template>
        </Column>

        <Column field="description" header="Description" style="width:15%;" sortable>
          <template #body="{ data }">
            <div class="description line-clamp-3 whitespace-pre-line" v-html="formatDescription(data.description)" @click="handleDescriptionClick"></div>
          </template>
        </Column>

        <Column field="venue" header="Venue" style="width:15%;" sortable>
        <template #body="{ data }">
            <div class="venue">
            {{ data.venue || "No venue specified" }}
            </div>
        </template>
        </Column>

        <Column field="category_id" header="Category" style="width:15%;" sortable>
          <template #body="{ data }">
            {{ categoryMap[data.category_id] || "Uncategorized" }}
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

        <Column v-if="user?.name === 'Admin' || user?.name === 'Principal'" header="Actions" style="width:10%;" body-class="text-center">
          <template #body="{ data }">
            <div class="action-buttons">
              <Button icon="pi pi-pen-to-square" class="p-button-rounded p-button-info" @click="editEvent(data)" v-tooltip.top="'Edit Event'"/>
              <Button icon="pi pi-folder" class="p-button-rounded p-button-danger" @click="archiveEvent(data)" v-tooltip.top="'Archive Event'"/>
            </div>
          </template>
        </Column>

        <Column v-if="user?.name === 'Admin' || user?.name === 'Principal'" header="Tasks" style="width:15%;" body-class="text-center">
        <template #body="{ data }">
            <Button icon="pi pi-list" class="p-button-rounded p-button-warning" @click="openTaskModal(data)" v-tooltip.top="'Manage Tasks'"/>
        </template>
        </Column>

      </DataTable>

      <Dialog v-model:visible="isTaskModalVisible" modal header="Assign Tasks" :style="{ width: '50vw' }">
  <div class="p-fluid">
    <div class="p-field">
      <label>Event</label>
      <InputText v-model="selectedEvent.title" disabled />
    </div>

    <!-- Task Entries -->
    <div v-for="(taskEntry, index) in taskAssignments" :key="index" class="p-field">
      <h3>Task {{ index + 1 }}</h3>

      <!-- Committee Selection -->
      <div class="p-field">
        <label>Committee</label>
        <Select
          v-model="taskEntry.committee"
          :options="committees"
          optionLabel="name"
          placeholder="Select Committee"
          filter
          @change="updateEmployees(index)"
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

      <!-- Employee Selection -->
      <div v-if="taskEntry.committee" class="p-field">
        <label>Employees</label>
        <MultiSelect
          v-model="taskEntry.employees"
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
        <label>Task</label>
        <Textarea v-model="taskEntry.task" rows="2" placeholder="Enter task details" />
      </div>

      <!-- Remove Task Button -->
      <Button icon="pi pi-trash" class="p-button-danger p-button-text" @click="deleteTask(index)" v-tooltip.top="'Delete Task'"/>
    </div>

    <!-- Add Task Button -->
    <Button label="Add Task" icon="pi pi-plus" class="p-button-secondary" @click="addTask" />
  </div>

  <template #footer>
    <Button label="Cancel" icon="pi pi-times" class="p-button-text" @click="isTaskModalVisible = false" />
    <Button label="Save Tasks" icon="pi pi-check" class="p-button-primary" @click="saveTaskAssignments" />
  </template>
</Dialog>

    <!-- Create Event Modal-->
    <Dialog v-model:visible="isCreateModalVisible" modal header="Create Event" :style="{ width: '50vw' }">
        <div class="p-fluid">
          <!-- Event Title -->
          <div class="p-field">
            <label for="title">Event Title</label>
            <InputText id="title" v-model="newEvent.title" placeholder="Enter event title" />
          </div>

          <!-- Tags Selection -->
          <div class="p-field">
            <label for="tags">Tags</label>
            <div class="flex items-center gap-2">
                <MultiSelect
                  id="tags"
                  v-model="newEvent.tags"
                  :options="tags"
                  optionLabel="name"
                  placeholder="Select tags"
                  display="chip"
                  class="w-full"
                />
                <Button icon="pi pi-plus" class="p-button-secondary p-button-rounded" @click="openTagModal" v-tooltip.top="'Create New Tag'" />
            </div>
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
        </div>

        <div class="p-field">
          <label for="image">Event Image</label>
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
          <div v-if="newEvent.image" class="mt-2">
            <img :src="newEvent.image" alt="Preview" class="preview-image" />
          </div>
        </div>

        <template #footer>
          <Button label="Cancel" icon="pi pi-times" class="p-button-text" @click="isCreateModalVisible = false" />
          <Button label="Create Event" icon="pi pi-check" class="p-button-primary" @click="createEvent" />
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
                <label for="tagColor">Tag Color</label>
                <ColorPicker id="tagColor" v-model="newTag.color" />
            </div>
        </div>
        <template #footer>
            <Button label="Cancel" icon="pi pi-times" class="p-button-text" @click="isCreateTagModalVisible = false" />
            <Button label="Create" icon="pi pi-check" @click="createTag" :loading="saving" />
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

          <!-- Tags Selection -->
          <div class="p-field">
            <label for="tags">Tags</label>
            <MultiSelect
              id="tags"
              v-model="selectedEvent.tags"
              :options="tags"
              optionLabel="name"
              placeholder="Select tags"
              display="chip"
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
                <div
                  class="flex items-center gap-2 px-2 py-1 rounded text-white text-xs"
                  :style="{ backgroundColor: slotProps.value.color || '#800080' }"
                >
                  <div
                    class="w-2 h-2 rounded-full bg-white opacity-50"
                  ></div>
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
        </div>

        <div class="p-field">
          <label for="image">Event Image</label>
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
          <div v-if="selectedEvent.image" class="mt-2">
            <img :src="selectedEvent.image" alt="Preview" class="preview-image" />
          </div>
        </div>

        <template #footer>
          <Button label="Cancel" icon="pi pi-times" class="p-button-text" @click="isEditModalVisible = false" />
          <Button label="Save Changes" icon="pi pi-check" class="p-button-primary" @click="saveEditedEvent" />
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
      confirmButtonClass="bg-yellow-600 hover:bg-yellow-700"
      @confirm="confirmArchive"
    />

    <!-- Delete Task Confirmation Dialog -->
    <ConfirmationDialog
      v-model:show="showDeleteTaskConfirm"
      title="Delete Task?"
      :message="taskToDelete ? `Are you sure you want to delete this task?` : ''"
      confirmText="Yes, Delete"
      confirmButtonClass="bg-red-600 hover:bg-red-700"
      @confirm="confirmDeleteTask"
    />

    <!-- Save Changes Confirmation Dialog -->
    <ConfirmationDialog
      v-model:show="showSaveConfirm"
      title="Save Changes?"
      :message="selectedEvent ? `Are you sure you want to save changes to '${selectedEvent.title}'?` : ''"
      confirmText="Yes, Save"
      confirmButtonClass="bg-green-600 hover:bg-green-700"
      @confirm="confirmSaveChanges"
    />

    <!-- Create Confirmation Dialog -->
    <ConfirmationDialog
      v-model:show="showCreateConfirm"
      title="Create Event?"
      message="Are you sure you want to create this event?"
      confirmText="Yes, Create"
      confirmButtonClass="bg-green-600 hover:bg-green-700"
      @confirm="confirmCreateEvent"
    />

  </template>

  <script>
  import { defineComponent, ref, onMounted, computed, watch } from "vue";
  import { usePage, Link, router } from '@inertiajs/vue3';
  import axios from "axios";
  import { parse, format, isWithinInterval } from "date-fns";
  import LoadingSpinner from '@/Components/LoadingSpinner.vue';
  import ConfirmationDialog from '@/Components/ConfirmationDialog.vue';
  import SuccessDialog from '@/Components/SuccessDialog.vue';
  import Skeleton from 'primevue/skeleton';
  import ColorPicker from 'primevue/colorpicker';

  export default defineComponent({
    name: "EventList",
    components: {
      LoadingSpinner,
      ConfirmationDialog,
      SuccessDialog,
      Link,
      Skeleton,
    },
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
      const filteredEmployees = ref([]);
      const isTaskModalVisible = ref(false);
      const taskAssignments = ref([]);
      const employees = computed(() => page.props.employees_prop || []);
      const isConfirmModalVisible = ref(false);
      const confirmMessage = ref('');
      const confirmAction = ref(() => {});
      const cancelConfirmation = ref(() => {});
      const isCreateModalVisible = ref(false);
      const isCreateTagModalVisible = ref(false);
      const defaultImage = "https://primefaces.org/cdn/primeng/images/demo/product/bamboo-watch.jpg";
      const searchQuery = ref("");
      const showDateFilter = ref(false);
      const dateRange = ref({
        from: null,
        to: null
      });

      const newTag = ref({
        name: "",
        color: "#ff0000"
      });

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
        image: "https://primefaces.org/cdn/primeng/images/demo/product/bamboo-watch.jpg",
        archived: false,
        isAllDay: false
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
          tags: [],
          image: "https://primefaces.org/cdn/primeng/images/demo/product/bamboo-watch.jpg",
          archived: false,
          isAllDay: false
        };
        isCreateModalVisible.value = true;
        dateError.value = "";
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

     const openTagModal = () => {
       newTag.value = { name: '', color: '#ff0000' };
       isCreateTagModalVisible.value = true;
     };

     const createTag = async () => {
       if (!newTag.value.name.trim()) {
         errorMessage.value = "Tag name cannot be empty.";
         showErrorDialog.value = true;
         return;
       }
       const colorValue = newTag.value.color.startsWith('#') ? newTag.value.color : `#${newTag.value.color}`;

       saving.value = true;
       try {
         const response = await axios.post(route('category.store'), {
           name: newTag.value.name,
           color: colorValue
         });

         if (response.data.success) {
           const createdTag = response.data.tag;
           tags.value.push(createdTag);
           newEvent.value.tags.push(createdTag);
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

        // Validate title
        if (!newEvent.value.title.trim()) {
            errorMessage.value = "Please enter a valid event title";
            showErrorDialog.value = true;
            return;
        }

        // Validate dates
        if (newEvent.value.startDate && newEvent.value.endDate) {
            const start = new Date(newEvent.value.startDate);
            const end = new Date(newEvent.value.endDate);

            // Block if end date is BEFORE start date (allows same-day) and if end date is in the past
            if (end < start) {
                dateError.value = "End date cannot be before start date";
                return;
            }
            const now = new Date();
            const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
            if (end < today && start < today) {
                dateError.value = "Event cannot be entirely in the past";
                return;
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
            image: finalImage || defaultImage,
            tags: Array.isArray(newEvent.value.tags) ? newEvent.value.tags.map(tag => tag.id) : [],
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
        };

        await router.post(route('events.store'), payload, {
            onSuccess: () => {
                isCreateModalVisible.value = false;
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
                const MAX_SIZE = 800; // Back to 800px for better storage
                let width = img.width;
                let height = img.height;

                if (width > height && width > MAX_SIZE) {
                    height *= MAX_SIZE / width;
                    width = MAX_SIZE;
                } else if (height > MAX_SIZE) {
                    width *= MAX_SIZE / height;
                    height = MAX_SIZE;
                }

                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                canvas.width = width;
                canvas.height = height;

                // Enable image smoothing for better quality
                ctx.imageSmoothingEnabled = true;
                ctx.imageSmoothingQuality = 'high';

                ctx.drawImage(img, 0, 0, width, height);

                // Get compressed data with balanced quality
                const compressedData = canvas.toDataURL('image/jpeg', 0.8);

                // Create SVG wrapper for better scaling and storage
                const svgContent = `
                    <svg xmlns="http://www.w3.org/2000/svg"
                        width="${width}"
                        height="${height}">
                    <image href="${compressedData}"
                            width="100%"
                            height="100%"
                            preserveAspectRatio="xMidYMid meet"/>
                    </svg>`;

                // Create final SVG blob
                const svgBlob = new Blob([svgContent], { type: 'image/svg+xml' });
                const svgUrl = URL.createObjectURL(svgBlob);
                selectedEvent.value.image = svgUrl;
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
                // Compression setup
                const MAX_SIZE = 800; // Back to 800px for better storage
                let width = img.width;
                let height = img.height;

                // Calculate new dimensions while maintaining aspect ratio
                if (width > height && width > MAX_SIZE) {
                    height *= MAX_SIZE / width;
                    width = MAX_SIZE;
                } else if (height > MAX_SIZE) {
                    width *= MAX_SIZE / height;
                    height = MAX_SIZE;
                }

                // Create canvas for compression
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                canvas.width = width;
                canvas.height = height;

                // Enable image smoothing for better quality
                ctx.imageSmoothingEnabled = true;
                ctx.imageSmoothingQuality = 'high';

                ctx.drawImage(img, 0, 0, width, height);

                // Get compressed data with balanced quality
                const compressedData = canvas.toDataURL('image/jpeg', 0.8);

                // Create SVG wrapper for better scaling and storage
                const svgContent = `
                    <svg xmlns="http://www.w3.org/2000/svg"
                        width="${width}"
                        height="${height}">
                    <image href="${compressedData}"
                            width="100%"
                            height="100%"
                            preserveAspectRatio="xMidYMid meet"/>
                    </svg>`;

                // Create final SVG blob
                const svgBlob = new Blob([svgContent], { type: 'image/svg+xml' });
                const svgUrl = URL.createObjectURL(svgBlob);
                newEvent.value.image = svgUrl;
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
        target.image = defaultImage;
        };

      const normalizedTags = computed(() => {
        return tags.value.reduce((map, tag) => {
            map[tag.id] = tag;
            return map;
        }, {});
        });

    const getEventTags = (eventTags) => {
    if (!eventTags || !eventTags.length) return [];

    // If tags are already objects, return them directly
    if (typeof eventTags[0] === 'object') {
        return eventTags;
    }

    // If tags are IDs, map them to their full objects
    return eventTags
        .map(tagId => normalizedTags.value[tagId])
        .filter(tag => tag); // Filter out any undefined tags
    };

    onMounted(async () => {
        initialLoading.value = true;
        // Data is now passed as props on initial visit.
        // We just need to switch off the loading indicator after mount.
        initialLoading.value = false;
    });

       // Open Task Modal
    const openTaskModal = (event) => {
      selectedEvent.value = event;

      // Create maps for quick lookup
      const committeesMap = committees.value.reduce((map, committee) => {
        map[committee.id] = committee;
        return map;
      }, {});

      const employeesMap = employees.value.reduce((map, employee) => {
        map[employee.id] = employee;
        return map;
      }, {});

      // Normalize tasks with proper committee and employee objects
      taskAssignments.value = event.tasks ? event.tasks.map(task => {
        // Handle committee - could be full object, partial object, or just ID
        let committee = null;
        if (task.committee) {
          if (typeof task.committee === 'object') {
            if (task.committee.name) {
              committee = task.committee;
            } else {
              committee = committeesMap[task.committee.id] || { id: task.committee.id, name: 'Unknown Committee' };
            }
          } else {
            committee = committeesMap[task.committee] || { id: task.committee, name: 'Unknown Committee' };
          }
        }

        // Handle employees - convert single employee to array if needed
        let employees = [];
        if (task.employees) {
          // If it's already an array of employees
          employees = task.employees.map(emp => {
            if (typeof emp === 'object') {
              return emp.name ? emp : employeesMap[emp.id] || { id: emp.id, name: 'Unknown Employee' };
            }
            return employeesMap[emp] || { id: emp, name: 'Unknown Employee' };
          });
        } else if (task.employee) {
          // If it's a single employee, convert to array
          if (typeof task.employee === 'object') {
            employees = [task.employee.name ? task.employee : employeesMap[task.employee.id] || { id: task.employee.id, name: 'Unknown Employee' }];
          } else {
            employees = [employeesMap[task.employee] || { id: task.employee, name: 'Unknown Employee' }];
          }
        }

        return {
          ...task,
          committee,
          employees
        };
      }) : [{ committee: null, employees: [], task: "" }];

      // Populate filteredEmployees based on already selected committees
      filteredEmployees.value = taskAssignments.value.map(task =>
        task.committee ? employees.value.filter(emp => Number(emp.committeeId) === Number(task.committee.id)) : []
      );

      isTaskModalVisible.value = true;
    };

    // Add a new task entry
    const addTask = () => {
      taskAssignments.value.push({ committee: null, employees: [], task: "" });
      filteredEmployees.value.push([]);
    };

    const deleteTask = (index) => {
      taskToDelete.value = { index, taskEntry: taskAssignments.value[index] };
      showDeleteTaskConfirm.value = true;
    };

    const confirmDeleteTask = async () => {
      if (!taskToDelete.value) return;
      saving.value = true;

      try {
        taskAssignments.value.splice(taskToDelete.value.index, 1);

        const updatedEvent = {
          ...selectedEvent.value,
          tasks: [...taskAssignments.value]
        };

        await axios.put(`http://localhost:3000/events/${selectedEvent.value.id}`, updatedEvent);

        const eventIndex = combinedEvents.value.findIndex(event => event.id === selectedEvent.value.id);
        if (eventIndex !== -1) {
          combinedEvents.value[eventIndex].tasks = [...taskAssignments.value];
        }

        successMessage.value = 'Task deleted successfully!';
        showSuccessDialog.value = true;
      } catch (error) {
        console.error("Error deleting task:", error);
        errorMessage.value = 'Failed to delete task.';
        showErrorDialog.value = true;
      } finally {
        saving.value = false;
        showDeleteTaskConfirm.value = false;
        taskToDelete.value = null;
      }
    };

    // Update filtered employees when committee changes
    const updateEmployees = (index) => {
    const selectedCommittee = taskAssignments.value[index].committee;

    // Convert committeeId to the same type for comparison
    filteredEmployees.value[index] = employees.value.filter(emp =>
        Number(emp.committeeId) === Number(selectedCommittee?.id)
    );
    };

    const saveTaskAssignments = async () => {
        try {
        const updatedEvent = {
        ...selectedEvent.value,
        tasks: taskAssignments.value.map(task => ({
            committee: task.committee ? { id: task.committee.id } : null,
            employees: task.employees.map(emp => ({ id: emp.id })),
            task: task.task
        }))
        };
        await router.put(route('events.updateFromList', {id: selectedEvent.value.id}), updatedEvent, {
          onSuccess: () => {
            const index = combinedEvents.value.findIndex(event => event.id === selectedEvent.value.id);
            if (index !== -1) {
              combinedEvents.value[index].tasks = [...taskAssignments.value];
            }
            isTaskModalVisible.value = false;
            successMessage.value = 'Tasks assigned successfully!';
            showSuccessDialog.value = true;
          },
          onError: (errors) => {
            const firstErrorKey = Object.keys(errors)[0];
            const firstErrorMessage = firstErrorKey ? (Array.isArray(errors[firstErrorKey]) ? errors[firstErrorKey][0] : errors[firstErrorKey]) : 'Failed to save tasks.';
            errorMessage.value = firstErrorMessage;
            showErrorDialog.value = true;
          },
          preserveScroll: true
        });
      } catch (error) {
        errorMessage.value = 'Failed to save tasks.';
        showErrorDialog.value = true;
      }
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
        // Convert tag IDs to full tag objects if needed
        const normalizedTags = Array.isArray(event.tags)
            ? event.tags.map(tag => {
                // If tag is already an object, return it
                if (typeof tag === 'object' && tag !== null) {
                    return tag;
                }
                // If tag is an ID, find the corresponding tag object
                return tags.value.find(t => t.id === tag || t.id === tag.id);
            }).filter(Boolean) // Remove any undefined entries
            : [];

        selectedEvent.value = {
            ...event,
            venue: event.venue || "",
            tags: normalizedTags, // Use normalized tags
            startDate: event.startDate ? new Date(event.startDate) : null,
            endDate: event.endDate ? new Date(event.endDate) : null,
            image: event.image || defaultImage
        };
        isEditModalVisible.value = true;
    };

    const saveEditedEvent = () => {
      if (!selectedEvent.value) return;
      resetErrors();

      if (!selectedEvent.value.title.trim()) {
        errorMessage.value = 'Please enter a valid event title';
        showErrorDialog.value = true;
        return;
      }

      if (selectedEvent.value.startDate && selectedEvent.value.endDate) {
        const start = new Date(selectedEvent.value.startDate);
        const end = new Date(selectedEvent.value.endDate);

        if (end < start) {
          dateError.value = "End date cannot be before start date";
          return;
        }

        const now = new Date();
      }

      showSaveConfirm.value = true;
    };

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
              reader.onerror = reject;
              reader.onloadend = () => resolve(reader.result);
              reader.readAsDataURL(blob);
            });
            URL.revokeObjectURL(blobUrl); // Revoke the blob URL after it has been read
          } catch (error) {
            console.error("Failed to process blob image:", error);
            finalImage = defaultImage;
          }
        } else if (!selectedEvent.value.image) {
          finalImage = defaultImage;
        }
        const normalizedTags = Array.isArray(selectedEvent.value.tags)
          ? selectedEvent.value.tags.map(tag => tag?.id || tag)
          : [];
        const payload = {
          ...selectedEvent.value,
          image: finalImage,
          tags: normalizedTags,
          startDate: selectedEvent.value.startDate
            ? format(new Date(selectedEvent.value.startDate), "MMM-dd-yyyy")
            : null,
          endDate: selectedEvent.value.endDate
            ? format(new Date(selectedEvent.value.endDate), "MMM-dd-yyyy")
            : null,
          startTime: selectedEvent.value.startTime?.padStart(5, "0") || "00:00",
          endTime: selectedEvent.value.endTime?.padStart(5, "0") || "00:00",
        };
        await router.put(route('events.updateFromList', {id: selectedEvent.value.id}), payload, {
          onSuccess: () => {
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

    const removeEmployee = (taskIndex, employeeToRemove) => {
      taskAssignments.value[taskIndex].employees = taskAssignments.value[taskIndex].employees.filter(
        emp => emp.id !== employeeToRemove.id
      );
    };

    const removeTag = (tagToRemove) => {
      selectedEvent.value.tags = selectedEvent.value.tags.filter(tag => tag.id !== tagToRemove.id);
    };

    const handleDescriptionClick = (event) => {
      if (event.target.tagName === 'A') {
        event.stopPropagation();
      }
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
      const showCreateConfirm = ref(false);

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
    isTaskModalVisible,
    taskAssignments,
    committees,
    employees,
    filteredEmployees,
    openTaskModal,
    addTask,
    deleteTask,
    updateEmployees,
    saveTaskAssignments,
    isConfirmModalVisible,
    confirmMessage,
    confirmAction,
    cancelConfirmation,
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
    removeEmployee,
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
    confirmArchive,
    initialLoading,
    confirmDeleteTask,
    confirmSaveChanges,
    user,
    showCreateConfirm,
    confirmCreateEvent,
    formatDescription,
    handleDescriptionClick,
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

.search-wrapper {
  display: flex;
  gap: 10px;
  align-items: center;
  width: 100%;
  max-width: 400px;
}

.search-container .p-input-icon-left {
  position: relative;
  width: 100%;
  max-width: 400px;
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

.date-filter-btn {
  min-width: 40px;
  height: 40px;
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

.create-button {
  background: #7e0bc1;
  color: white;
  padding: 10px 15px;
  border: none;
  border-radius: 5px;
  cursor: pointer;
  font-size: 1rem;
  font-weight: bold;
  margin: 0;
  height: 40px;
}

.create-button:hover {
  background-color: #6800b3e9;
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
