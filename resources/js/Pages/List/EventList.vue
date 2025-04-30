<template>
    <div class="event-list-container">
        <div class="flex justify-content-between align-items-center mb-4">
        <h1 class="title">Event List</h1>
        <Button
          label="Create Event"
          icon="pi pi-plus"
          class="p-button-primary"
          @click="openCreateModal"
        />
      </div>

      <DataTable :value="combinedEvents" class="p-datatable-striped">
        <Column field="title" header="Event Name" style="width:20%;" sortable>
          <template #body="{ data }">
            <div class="flex items-center gap-2">
              <img v-if="data.image" :src="data.image" alt="Event Image" class="event-icon" />
              <span>{{ data.title }}</span>
            </div>

            <!-- Tags DIsplay-->
             <div class="tags-container">
                <span
                    v-for="tag in data.tags"
                    :key="tag.id"
                    class="tag"
                    :style="{ backgroundColor: tag.color || '#800080' }">
                    {{ tag.name }}
                </span>
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

        <Column header="Category" style="width:15%;">
          <template #body="{ data }">
            {{ categoryMap[data.category_id] || "Uncategorized" }}
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
              <Button icon="pi pi-pen-to-square" class="p-button-rounded p-button-info" @click="editEvent(data)" v-tooltip.top="'Edit Event'"/>
              <Button icon="pi pi-folder" class="p-button-rounded p-button-danger" @click="archiveEvent(data)" v-tooltip.top="'Archive Event'"/>
            </div>
          </template>
        </Column>

        <Column header="Tasks" style="width:15%;" body-class="text-center">
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
        <Dropdown v-model="taskEntry.committee" :options="committees" optionLabel="name" placeholder="Select Committee" filter @change="updateEmployees(index)" />
      </div>

      <!-- Employee Selection -->
      <div v-if="taskEntry.committee" class="p-field">
        <label>Employee</label>
        <Dropdown v-model="taskEntry.employee" :options="filteredEmployees[index]" optionLabel="name" placeholder="Select Employee" filter/>
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
            <MultiSelect
              id="tags"
              v-model="newEvent.tags"
              :options="tags"
              optionLabel="name"
              placeholder="Select tags"
              display="chip"
            />
          </div>

          <!-- Venue -->
          <div class="p-field">
            <label for="venue">Venue</label>
            <InputText id="venue" v-model="newEvent.venue" placeholder="Enter event venue (e.g., Main Hall, Stadium)" />
          </div>

          <!-- Event Category Dropdown -->
          <div class="p-field">
            <label for="category">Category</label>
            <Dropdown
              id="category"
              v-model="newEvent.category_id"
              :options="categories"
              optionLabel="title"
              optionValue="id"
              placeholder="Select a category"
            />
          </div>

          <!-- Start Date & End Date -->
          <div class="p-field p-grid">
            <div class="p-col-6">
              <label for="startDate">Start Date</label>
              <DatePicker id="startDate" v-model="newEvent.startDate" dateFormat="MM-dd-yy" showIcon />
            </div>
            <div class="p-col-6">
              <label for="endDate">End Date</label>
              <DatePicker id="endDate" v-model="newEvent.endDate" dateFormat="MM-dd-yy" showIcon />
            </div>
          </div>

          <div v-if="dateError" class="p-field text-red-500 text-sm mt-1">
            <i class="pi pi-exclamation-triangle mr-1"></i>
            {{ dateError }}
          </div>

          <!-- Start Time -->
          <div class="p-field">
            <label for="startTime">Start Time</label>
            <input type="time"
              id="startTime"
              v-model="newEvent.startTime"
              placeholder="HH:mm"
              :class="{ 'p-invalid': dateError && dateError.includes('end') }"
              @blur="newEvent.startTime = newEvent.startTime.padStart(5, '0')"
            />
          </div>

          <!-- End Time -->
          <div class="p-field">
            <label for="endTime">End Time</label>
            <input type="time"
              id="endTime"
              v-model="newEvent.endTime"
              placeholder="HH:mm"
              @blur="newEvent.endTime = newEvent.endTime.padStart(5, '0')"
            />
          </div>

          <!-- Event Description -->
          <div class="p-field">
            <label for="description">Description</label>
            <Textarea id="description" v-model="newEvent.description" rows="4" placeholder="Enter event description" autoResize />
          </div>
        </div>

        <template #footer>
          <Button label="Cancel" icon="pi pi-times" class="p-button-text" @click="isCreateModalVisible = false" />
          <Button label="Create Event" icon="pi pi-check" class="p-button-primary" @click="createEvent" />
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
            />
        </div>

        <!-- Venue -->
        <div class="p-field">
        <label for="venue">Venue</label>
        <InputText id="venue" v-model="selectedEvent.venue" placeholder="Enter event venue (e.g., Main Hall, Stadium)" />
        </div>

          <!-- Event Category Dropdown -->
          <div class="p-field">
            <label for="category">Category</label>
            <Dropdown
              id="category"
              v-model="selectedEvent.category_id"
              :options="categories"
              optionLabel="title"
              optionValue="id"
              placeholder="Select a category"
            />
          </div>

          <!-- Start Date & End Date -->
          <div class="p-field p-grid">
            <div class="p-col-6">
              <label for="startDate">Start Date</label>
              <DatePicker id="startDate" v-model="selectedEvent.startDate" dateFormat="MM-dd-yy" showIcon />
            </div>
            <div class="p-col-6">
              <label for="endDate">End Date</label>
              <DatePicker id="endDate" v-model="selectedEvent.endDate" dateFormat="MM-dd-yy" showIcon />
            </div>
          </div>

        <div v-if="dateError" class="p-field text-red-500 text-sm mt-1">
        <i class="pi pi-exclamation-triangle mr-1"></i>
        {{ dateError }}
        </div>

          <!-- Start Time -->
        <div class="p-field">
        <label for="startTime">Start Time</label>
        <input type="time"
            id="startTime"
            v-model="selectedEvent.startTime"
            placeholder="HH:mm"
            :class="{ 'p-invalid': dateError && dateError.includes('end') }"
            @blur="selectedEvent.startTime = selectedEvent.startTime.padStart(5, '0')"
        />
        </div>

        <!-- End Time -->
        <div class="p-field">
        <label for="endTime">End Time</label>
        <input type="time"
            id="endTime"
            v-model="selectedEvent.endTime"
            placeholder="HH:mm"
            @blur="selectedEvent.endTime = selectedEvent.endTime.padStart(5, '0')"
        />
        </div>

          <!-- Event Description -->
          <div class="p-field">
            <label for="description">Description</label>
            <Textarea id="description" v-model="selectedEvent.description" rows="4" placeholder="Enter event description" autoResize />
          </div>
        </div>

        <template #footer>
          <Button label="Cancel" icon="pi pi-times" class="p-button-text" @click="isEditModalVisible = false" />
          <Button label="Save Changes" icon="pi pi-check" class="p-button-primary" @click="saveEditedEvent" />
        </template>
      </Dialog>
    </div>

    <Dialog v-model:visible="isConfirmModalVisible" modal header="Confirmation" :style="{ width: '30vw' }">
    <p>{{ confirmMessage }}</p>
    <template #footer>
        <Button label="Cancel" icon="pi pi-times" class="p-button-text" @click="cancelConfirmation" />
        <Button label="Confirm" icon="pi pi-check" class="p-button-primary" @click="confirmAction" />
    </template>
    </Dialog>

  </template>

  <script>
  import { defineComponent, ref, onMounted, computed } from "vue";
  import axios from "axios";
  import { parse, format } from "date-fns";
  import DataTable from "primevue/datatable";
  import Column from "primevue/column";
  import Button from "primevue/button";
  import Dialog from "primevue/dialog";
  import InputText from "primevue/inputtext";
  import DatePicker from 'primevue/datepicker';
  import Dropdown from "primevue/dropdown";
  import Textarea from "primevue/textarea";

  export default defineComponent({
    name: "EventList",
    components: {
      DataTable,
      Column,
      Button,
      Dialog,
      InputText,
      DatePicker,
      Dropdown,
      Textarea,
    },
    setup() {
      const dateError = ref("");
      const events = ref([]);
      const sports = ref([]);
      const categories = ref([]);
      const combinedEvents = ref([]);
      const isEditModalVisible = ref(false);
      const selectedEvent = ref(null);
      const tags = ref([]);
      const committees = ref([]);
      const filteredEmployees = ref([]);
      const isTaskModalVisible = ref(false);
      const taskAssignments = ref([]);
      const employees = ref([]);
      const isConfirmModalVisible = ref(false);
      const confirmMessage = ref('');
      const confirmAction = ref(() => {});
      const cancelConfirmation = ref(() => {});
      const isCreateModalVisible = ref(false);
      const newEvent = ref({
        title: "",
        description: "",
        venue: "",
        category_id: null,
        startDate: null,
        endDate: null,
        startTime: "00:00",
        endTime: "00:00",
        tags: [],
        image: "https://primefaces.org/cdn/primeng/images/demo/product/bamboo-watch.jpg",
        archived: false
      });

      const openCreateModal = () => {
        newEvent.value = {
          title: "",
          description: "",
          venue: "",
          category_id: null,
          startDate: null,
          endDate: null,
          startTime: "00:00",
          endTime: "00:00",
          tags: [],
          image: "https://primefaces.org/cdn/primeng/images/demo/product/bamboo-watch.jpg",
          archived: false
        };
        isCreateModalVisible.value = true;
        dateError.value = "";
      };

      const createEvent = async () => {
        if (!newEvent.value.title) {
          alert("Please enter an event title");
          return;
        }

        if (newEvent.value.startDate && newEvent.value.endDate) {
          const start = new Date(newEvent.value.startDate);
          const end = new Date(newEvent.value.endDate);
          if (end < start) {
            dateError.value = "End date cannot be before start date.";
            return;
          }
        }

        try {
            const payload = {
            ...newEvent.value,
            tags: Array.isArray(newEvent.value.tags)
                ? newEvent.value.tags.map(tag => ({
                    id: tag.id,
                    name: tag.name,
                    color: tag.color
                    // include other tag properties as needed
                }))
                : [],
            startDate: newEvent.value.startDate
                ? format(new Date(newEvent.value.startDate), "MMM-dd-yyyy")
                : null,
            endDate: newEvent.value.endDate
                ? format(new Date(newEvent.value.endDate), "MMM-dd-yyyy")
                : null,
            startTime: newEvent.value.startTime.padStart(5, "0"),
            endTime: newEvent.value.endTime.padStart(5, "0"),
            archived: false
            };

            const response = await axios.post("http://localhost:3000/events", payload);

                // Add the new event to the local list
                const createdEvent = {
            ...response.data,
            type: "event"
            };

            combinedEvents.value = [createdEvent, ...combinedEvents.value];
            isCreateModalVisible.value = false;
            alert("Event created successfully!");
        } catch (error) {
            console.error("Error creating event:", error);
            alert("Failed to create the event.");
        }
      };

      const validateEventDates = () => {
        if (new Date(selectedEvent.value.startDate) > new Date(selectedEvent.value.endDate)) {
            dateError.value = "End date must be after start date";
            return false;
        }

        dateError.value = null;
        return true;
        };

      onMounted(async () => {
        try {
          const [eventsResponse, sportsResponse, categoriesResponse, tagsResponse, committeesResponse, employeesResponse] = await Promise.all([
            axios.get("http://localhost:3000/events"),
            axios.get("http://localhost:3000/sports"),
            axios.get("http://localhost:3000/category"),
            axios.get("http://localhost:3000/tags"),
            axios.get("http://localhost:3000/committees"),
            axios.get("http://localhost:3000/employees")
          ]);

          const tagsMap = tagsResponse.data.reduce((map, tag) => {
            map[tag.id] = tag;
            return map;
        }, {});

        events.value = eventsResponse.data
        .filter(event => !event.archived) // Exclude archived events
        .map(event => ({
            ...event,
            category_id: event.category?.id || event.category_id,
        }));

          categories.value = categoriesResponse.data;

          //Tags value for multiselect
          tags.value = tagsResponse.data;

          committees.value = committeesResponse.data;
          employees.value = employeesResponse.data;

          combinedEvents.value = [...events.value, ...sports.value].sort((a, b) => {
            return new Date(a.startDate || "1970-01-01") - new Date(b.startDate || "1970-01-01");
          });
        } catch (error) {
          console.error("Error fetching data:", error);
        }
      });

       // Open Task Modal
    const openTaskModal = (event) => {
      selectedEvent.value = event;
      taskAssignments.value = event.tasks ? [...event.tasks] : [{ committee: null, employee: null, task: "" }];
      // Populate filteredEmployees based on already selected committees
      filteredEmployees.value = taskAssignments.value.map(task =>
        employees.value.filter(emp => Number(emp.committeeId) === Number(task.committee?.id))
      );
      isTaskModalVisible.value = true;
    };

    // Add a new task entry
    const addTask = () => {
      taskAssignments.value.push({ committee: null, employee: null, task: "" });
      filteredEmployees.value.push([]);
    };

    const deleteTask = async (index, taskEntry) => {
  if (!confirm(`Are you sure you want to delete this task?`)) return;

  try {
    taskAssignments.value.splice(index, 1); // Remove from local UI

    const updatedEvent = {
      ...selectedEvent.value,
      tasks: [...taskAssignments.value]
    };

    await axios.put(`http://localhost:3000/events/${selectedEvent.value.id}`, updatedEvent);

    // Update local event data
    const eventIndex = combinedEvents.value.findIndex(event => event.id === selectedEvent.value.id);
    if (eventIndex !== -1) {
      combinedEvents.value[eventIndex].tasks = [...taskAssignments.value];
    }

    alert("Task deleted successfully!");
  } catch (error) {
    console.error("Error deleting task:", error);
    alert("Failed to delete task.");
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

    // Save Task Assignments
    const saveTaskAssignments = async () => {
        try {
        const updatedEvent = {
        ...selectedEvent.value,
        tasks: taskAssignments.value
        };

        await axios.put(`http://localhost:3000/events/${selectedEvent.value.id}`, updatedEvent);

        // Find the index of the event in combinedEvents and update it
        const index = combinedEvents.value.findIndex(event => event.id === selectedEvent.value.id);
        if (index !== -1) {
        combinedEvents.value[index].tasks = [...taskAssignments.value];
        }

        isTaskModalVisible.value = false;
        alert("Tasks assigned successfully!");
      } catch (error) {
        console.error("Error saving tasks:", error);
        alert("Failed to save tasks.");
      }
    };


      // Format date and time display
      const formatDateTime = (date, time) => {
        const formattedDate = date ? format(new Date(date), 'MMM-dd-yyyy') : 'No date';
        let formattedTime = '00:00';

        if (time) {
            try {
            // First pad the time to ensure HH:mm format
            const paddedTime = time.padStart(5, '0');
            // Then parse and format to 12-hour format with AM/PM
            const parsedTime = parse(paddedTime, 'HH:mm', new Date());
            formattedTime = format(parsedTime, 'hh:mm a'); // e.g. "04:00 PM"
            } catch (e) {
            console.error('Error formatting time:', e);
            formattedTime = time.padStart(5, '0'); // Fallback to original format if parsing fails
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
        selectedEvent.value = {
            ...event,
            venue: event.venue || "",
            tags: Array.isArray(event.tags)
            ? event.tags // Use the tag objects directly
            : [],
            startDate: event.startDate ? new Date(event.startDate) : null,
            endDate: event.endDate ? new Date(event.endDate) : null
        };
        isEditModalVisible.value = true;
        };


      // Save Edited Event
        const saveEditedEvent = async () => {
        if (!selectedEvent.value) return;
        dateError.value = "";

        // Validate that end date is not earlier than start date
        if (selectedEvent.value.startDate && selectedEvent.value.endDate) {
            const start = new Date(selectedEvent.value.startDate);
            const end = new Date(selectedEvent.value.endDate);
            if (end < start) {
                dateError.value = "End date cannot be before start date.";
            return;
            }
        }

        // Set up confirmation modal instead of using confirm()
        confirmMessage.value = `Are you sure you want to save changes to "${selectedEvent.value.title}"?`;
        isConfirmModalVisible.value = true;

        confirmAction.value = async () => {
            try {
            const collection = selectedEvent.value.type === "sport" ? "sports" : "events";

            // Ensure startTime and endTime are in HH:mm format
            const startTimeFormatted = selectedEvent.value.startTime
                ? selectedEvent.value.startTime.padStart(5, "0") // Ensure format HH:mm
                : "00:00";

            const endTimeFormatted = selectedEvent.value.endTime
                ? selectedEvent.value.endTime.padStart(5, "0")
                : "00:00";

            await axios.put(`http://localhost:3000/${collection}/${selectedEvent.value.id}`, {
                ...selectedEvent.value,
                venue: selectedEvent.value.venue,
                tags: selectedEvent.value.tags, // Send the full tag objects directly
                startDate: selectedEvent.value.startDate
                ? format(new Date(selectedEvent.value.startDate), "MMM-dd-yyyy")
                : null,
                endDate: selectedEvent.value.endDate
                ? format(new Date(selectedEvent.value.endDate), "MMM-dd-yyyy")
                : null,
                startTime: startTimeFormatted,
                endTime: endTimeFormatted,
            });

            // Update local state - NO mappedTags needed
            const index = combinedEvents.value.findIndex(e => e.id === selectedEvent.value.id);
            if (index !== -1) {
            combinedEvents.value[index] = selectedEvent.value;
            }

            isEditModalVisible.value = false;
            isConfirmModalVisible.value = false;
            alert("Event updated successfully!");
        } catch (error) {
            console.error("Error updating event:", error);
            alert("Failed to update the event.");
        }
        };

        cancelConfirmation.value = () => {
            isConfirmModalVisible.value = false;
        };
        };


const archiveEvent = async (event) => {
  if (!confirm(`Are you sure you want to archive "${event.title}"?`)) return;

  try {
    // Update the event's `archived` status on the server
    await axios.put(`http://localhost:3000/events/${event.id}`, {
      ...event,
      archived: true, // Ensure the `archived` flag is set to true
    });

    // Remove the archived event from the local list
    combinedEvents.value = combinedEvents.value.filter(e => e.id !== event.id);
    alert("Event archived successfully!");
  } catch (error) {
    console.error("Error archiving event:", error);
    alert("Failed to archive the event.");
  }
};
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
        newEvent,
        openCreateModal,
        createEvent
     };
    },
 });
 </script>

<style scoped>
.venue {
  font-weight: 500;
  color: var(--primary-color);
}

.flex.justify-content-between {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
</style>
