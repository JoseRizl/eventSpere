<template>
    <div class="event-list-container">
      <h1 class="title">Event List</h1>

      <DataTable :value="combinedEvents" class="p-datatable-striped">
        <Column field="title" header="Event Name" style="width:20%;" sortable>
          <template #body="{ data }">
            <div class="flex items-center gap-2">
              <img v-if="data.image" :src="data.image" alt="Event Image" class="event-icon" />
              <span>{{ data.title }}</span>
            </div>
          </template>
        </Column>

        <Column field="subtitle" header="Subtitle" style="width:15%;" />

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
              <Button icon="pi pi-pen-to-square" class="p-button-rounded p-button-info" @click="editEvent(data)" />
              <Button icon="pi pi-folder" class="p-button-rounded p-button-danger" @click="archiveEvent(data)" />
            </div>
          </template>
        </Column>
      </DataTable>

      <!-- Edit Event Modal -->
      <Dialog v-model:visible="isEditModalVisible" modal header="Edit Event" :style="{ width: '50vw' }">
        <div class="p-fluid">
          <!-- Event Title -->
          <div class="p-field">
            <label for="title">Event Title</label>
            <InputText id="title" v-model="selectedEvent.title" placeholder="Enter event title" />
          </div>

          <!-- Event Subtitle -->
          <div class="p-field">
            <label for="subtitle">Event Subtitle (Optional)</label>
            <InputText id="subtitle" v-model="selectedEvent.subtitle" placeholder="Enter event subtitle" />
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
              <Calendar id="startDate" v-model="selectedEvent.startDate" dateFormat="MM-dd-yy" showIcon />
            </div>
            <div class="p-col-6">
              <label for="endDate">End Date</label>
              <Calendar id="endDate" v-model="selectedEvent.endDate" dateFormat="MM-dd-yy" showIcon />
            </div>
          </div>

          <!-- Start Time & End Time -->
          <!-- Start Time -->
        <div class="p-field">
        <label for="startTime">Start Time</label>
        <InputText
            id="startTime"
            v-model="selectedEvent.startTime"
            placeholder="HH:mm"
            @blur="selectedEvent.startTime = selectedEvent.startTime.padStart(5, '0')"
        />
        </div>

        <!-- End Time -->
        <div class="p-field">
        <label for="endTime">End Time</label>
        <InputText
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
  </template>

  <script>
  import { defineComponent, ref, onMounted, computed } from "vue";
  import axios from "axios";
  import { format } from "date-fns";
  import DataTable from "primevue/datatable";
  import Column from "primevue/column";
  import Button from "primevue/button";
  import Dialog from "primevue/dialog";
  import InputText from "primevue/inputtext";
  import Calendar from "primevue/calendar";
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
      Calendar,
      Dropdown,
      Textarea,
    },
    setup() {
      const events = ref([]);
      const sports = ref([]);
      const categories = ref([]);
      const combinedEvents = ref([]);
      const isEditModalVisible = ref(false);
      const selectedEvent = ref(null);

      onMounted(async () => {
        try {
          const [eventsResponse, sportsResponse, categoriesResponse] = await Promise.all([
            axios.get("http://localhost:3000/events"),
            axios.get("http://localhost:3000/sports"),
            axios.get("http://localhost:3000/category"),
          ]);

          events.value = eventsResponse.data
          .filter(event => !event.archived) // Exclude archived events
          .map(event => ({
            ...event,
            type: "event",
            category_id: event.category?.id || event.category_id,
          }));

          sports.value = sportsResponse.data.map(sport => ({
            ...sport,
            type: "sport",
            category_id: sport.category?.id || sport.category_id,
          }));

          categories.value = categoriesResponse.data;

          combinedEvents.value = [...events.value, ...sports.value].sort((a, b) => {
            return new Date(a.startDate || "1970-01-01") - new Date(b.startDate || "1970-01-01");
          });
        } catch (error) {
          console.error("Error fetching data:", error);
        }
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

      // Open Edit Modal
      const editEvent = (event) => {
        selectedEvent.value = {
            ...event,
            startDate: event.startDate ? new Date(event.startDate) : null,
            endDate: event.endDate ? new Date(event.endDate) : null
        };
        isEditModalVisible.value = true;
       };


      // Save Edited Event
      const saveEditedEvent = async () => {
  if (!selectedEvent.value) return;

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
      startDate: selectedEvent.value.startDate
        ? format(new Date(selectedEvent.value.startDate), "MMM-dd-yyyy")
        : null,
      endDate: selectedEvent.value.endDate
        ? format(new Date(selectedEvent.value.endDate), "MMM-dd-yyyy")
        : null,
      startTime: startTimeFormatted,
      endTime: endTimeFormatted,
    });

    // Update the local event list instantly
    const index = combinedEvents.value.findIndex(e => e.id === selectedEvent.value.id);
    if (index !== -1) {
      combinedEvents.value[index] = { ...selectedEvent.value };
    }

    isEditModalVisible.value = false;
    alert("Event updated successfully!");
  } catch (error) {
    console.error("Error updating event:", error);
    alert("Failed to update the event.");
  }
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
      };
    },
  });
  </script>
