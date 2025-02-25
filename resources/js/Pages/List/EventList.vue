<script>
import { defineComponent, ref, onMounted, computed } from "vue";
import axios from "axios";
import { DataTable, Column, Button, Dialog, InputText, Calendar, Dropdown, Textarea } from "primevue";
import { format } from "date-fns";

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

    // Fetch data on component mount
    onMounted(async () => {
      try {
        const eventsResponse = await axios.get("http://localhost:3000/events");
        events.value = eventsResponse.data.map((event) => ({
          ...event,
          type: "event",
          category_id: event.category?.id || event.category_id,
          startDate: event.startDate || "",
          endDate: event.endDate || "",
          startTime: event.startTime || "",
          endTime: event.endTime || "",
        }));

        const sportsResponse = await axios.get("http://localhost:3000/sports");
        sports.value = sportsResponse.data.map((sport) => ({
          ...sport,
          type: "sport",
          category_id: sport.category?.id || sport.category_id,
          startDate: sport.startDate || "",
          endDate: sport.endDate || "",
          startTime: sport.startTime || "",
          endTime: sport.endTime || "",
        }));

        const categoriesResponse = await axios.get("http://localhost:3000/category");
        categories.value = categoriesResponse.data;

        // Sort combined events by start date
        combinedEvents.value = [...events.value, ...sports.value].sort((a, b) => {
          return new Date(a.startDate || "1970-01-01") - new Date(b.startDate || "1970-01-01");
        });
      } catch (error) {
        console.error("Error fetching data:", error);
      }
    });

    // Map category_id to category title
    const categoryMap = computed(() =>
      categories.value.reduce((map, category) => {
        map[category.id] = category.title;
        return map;
      }, {})
    );

    // Open Edit Modal
    const openEditModal = (event) => {
      selectedEvent.value = {
        ...event,
        startDate: event.startDate ? new Date(event.startDate) : null,
        endDate: event.endDate ? new Date(event.endDate) : null,
      };
      isEditModalVisible.value = true;
    };

    // Save Edited Event
    const saveEditedEvent = async () => {
      if (!selectedEvent.value) return;

      try {
        const collection = selectedEvent.value.type === "sport" ? "sports" : "events";

        await axios.put(`http://localhost:3000/${collection}/${selectedEvent.value.id}`, {
          ...selectedEvent.value,
          startDate: selectedEvent.value.startDate ? format(new Date(selectedEvent.value.startDate), "yyyy-MM-dd") : null,
          endDate: selectedEvent.value.endDate ? format(new Date(selectedEvent.value.endDate), "yyyy-MM-dd") : null,
        });

        // Update the list without reloading
        const index = combinedEvents.value.findIndex((e) => e.id === selectedEvent.value.id);
        if (index !== -1) combinedEvents.value[index] = { ...selectedEvent.value };

        isEditModalVisible.value = false;
        alert("Event updated successfully!");
      } catch (error) {
        console.error("Error updating event:", error);
        alert("Failed to update the event.");
      }
    };

    // DELETE event function
    const confirmDeleteEvent = async (event) => {
      if (!confirm(`Are you sure you want to delete "${event.title}"?`)) return;

      try {
        const collection = event.type === "sport" ? "sports" : "events";
        await axios.delete(`http://localhost:3000/${collection}/${event.id}`);

        // Remove deleted event from the list
        combinedEvents.value = combinedEvents.value.filter((e) => e.id !== event.id);

        alert("Event deleted successfully!");
      } catch (error) {
        console.error("Error deleting event:", error);
        alert("Failed to delete the event.");
      }
    };

    return {
      combinedEvents,
      categoryMap,
      openEditModal,
      saveEditedEvent,
      confirmDeleteEvent,
      isEditModalVisible,
      selectedEvent,
      categories,
    };
  },
});
</script>

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

      <Column field="description" header="Description" style="width:20%;" />

      <Column header="Category" style="width:15%;">
        <template #body="{ data }">
          {{ categoryMap[data.category_id] || "Uncategorized" }}
        </template>
      </Column>

      <Column field="startDate" header="Start Date" style="width:10%;" sortable />
      <Column field="endDate" header="End Date" style="width:10%;" sortable />
      <Column field="startTime" header="Start Time" style="width:10%;"  />
      <Column field="endTime" header="End Time" style="width:10%;"  />

      <Column header="Actions" style="width:10%;" body-class="text-center">
        <template #body="{ data }">
          <div class="action-buttons">
            <Button icon="pi pi-pencil" class="p-button-rounded p-button-info" @click="openEditModal(data)" />
            <Button icon="pi pi-trash" class="p-button-rounded p-button-danger" @click="confirmDeleteEvent(data)" />
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

        <!-- Category -->
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
            <Calendar id="startDate" v-model="selectedEvent.startDate" dateFormat="yy-mm-dd" showIcon />
        </div>
        <div class="p-col-6">
            <label for="endDate">End Date</label>
            <Calendar id="endDate" v-model="selectedEvent.endDate" dateFormat="yy-mm-dd" showIcon />
        </div>
        </div>

        <!-- Start Time & End Time -->
        <div class="p-field p-grid">
        <div class="p-col-6">
            <label for="startTime">Start Time</label>
            <InputText id="startTime" v-model="selectedEvent.startTime" placeholder="HH:mm" />
        </div>
        <div class="p-col-6">
            <label for="endTime">End Time</label>
            <InputText id="endTime" v-model="selectedEvent.endTime" placeholder="HH:mm" />
        </div>
        </div>

        <!-- Description -->
        <div class="p-field">
        <label for="description">Description</label>
        <Textarea id="description" v-model="selectedEvent.description" rows="4" placeholder="Enter event description" autoResize />
        </div>
    </div>

    <template #footer>
        <Button label="Cancel" icon="pi pi-times" class="p-button-text" @click="isEditModalVisible = false" />
        <Button label="Save" icon="pi pi-check" class="p-button-primary" @click="saveEditedEvent" />
    </template>
    </Dialog>

  </div>
</template>
