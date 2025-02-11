<script>
import { defineComponent, ref, onMounted, computed } from "vue";
import axios from "axios";
import { DataTable, Column, Button } from "primevue";
import { format } from "date-fns";

export default defineComponent({
  name: "EventList",
  components: {
    DataTable,
    Column,
    Button,
  },
  setup() {
    const events = ref([]);
    const sports = ref([]);
    const categories = ref([]);
    const combinedEvents = ref([]);

    const editingId = ref(null); // Track which row is being edited
    const editedEvent = ref({}); // Store event being edited

    //const route = window.route; // Route APi

    // Fetch data on component mount
    onMounted(async () => {
      try {
        const eventsResponse = await axios.get("http://localhost:3000/events");
        events.value = eventsResponse.data.map((event) => ({
          ...event,
          type: "event",
          category_id: event.category?.id || event.category_id,
          date: format(new Date(event.date), "yyyy-MM-dd"),
        }));

        const sportsResponse = await axios.get("http://localhost:3000/sports");
        sports.value = sportsResponse.data.map((sport) => ({
          ...sport,
          type: "sport",
          category_id: sport.category?.id || sport.category_id,
          date: format(new Date(sport.date), "yyyy-MM-dd"),
        }));

        const categoriesResponse = await axios.get("http://localhost:3000/category");
        categories.value = categoriesResponse.data;

        combinedEvents.value = [...events.value, ...sports.value];
        combinedEvents.value.sort((a, b) => new Date(a.date) - new Date(b.date));
      } catch (error) {
        console.error("Error fetching events, sports, or categories:", error);
      }
    });

    // Map category_id to category title
    const categoryMap = computed(() => {
      return categories.value.reduce((map, category) => {
        map[category.id] = category.title;
        return map;
      }, {});
    });

    // Start editing a row
    const startEditing = (event) => {
      editingId.value = event.id;
      editedEvent.value = { ...event };
    };

    // Save edited event
    const saveEdit = async () => {
      try {
        const endpoint = editedEvent.value.type === "sport"
          ? `http://localhost:3000/sports/${editedEvent.value.id}`
          : `http://localhost:3000/events/${editedEvent.value.id}`;

        await axios.put(endpoint, editedEvent.value);

        const index = combinedEvents.value.findIndex(e => e.id === editedEvent.value.id);
        if (index !== -1) {
          combinedEvents.value[index] = { ...editedEvent.value };
        }

        editingId.value = null;
        alert("Event updated successfully!");
      } catch (error) {
        console.error("Error updating event:", error);
      }
    };

    // Cancel editing
    const cancelEdit = () => {
      editingId.value = null;
    };

    // Confirm and delete an event
    const confirmDeleteEvent = (event) => {
      if (confirm("Are you sure you want to delete this event?")) {
        deleteEvent(event);
      }
    };

    const deleteEvent = async (item) => {
      try {
        const collection = item.type === "sport" ? "sports" : "events";
        await axios.delete(`http://localhost:3000/${collection}/${item.id}`);
        combinedEvents.value = combinedEvents.value.filter((e) => e.id !== item.id);
        alert("Item deleted successfully.");
      } catch (error) {
        console.error(`Error deleting item from ${item.type}:`, error);
        alert("Failed to delete the item.");
      }
    };

    return {
      combinedEvents,
      categoryMap,
      editingId,
      editedEvent,
      startEditing,
      saveEdit,
      cancelEdit,
      confirmDeleteEvent,
    };
  },
});
</script>

<template>
  <div class="event-list-container">
    <h1 class="title">Event List</h1>

    <DataTable :value="combinedEvents" class="p-datatable-striped">
      <!-- Event Name Column -->
      <Column field="title" header="Event Name" style="width:30%;" sortable>
        <template #body="{ data }">
          <div v-if="editingId === data.id">
            <input v-model="editedEvent.title" class="p-inputtext w-full" />
          </div>
          <div v-else>
            {{ data.title }}
          </div>
        </template>
      </Column>

      <!-- Category Column -->
      <Column header="Category" style="width:20%;">
        <template #body="{ data }">
          {{ categoryMap[data.category_id] || "Uncategorized" }}
        </template>
      </Column>

      <!-- Date Column -->
      <Column field="date" header="Date" style="width:15%;" sortable>
        <template #body="{ data }">
          <div v-if="editingId === data.id">
            <input type="date" v-model="editedEvent.date" class="p-inputtext w-full" />
          </div>
          <div v-else>
            {{ data.date }}
          </div>
        </template>
      </Column>

      <!-- Time Column -->
      <Column field="time" header="Time" style="width:15%;" sortable>
        <template #body="{ data }">
          <div v-if="editingId === data.id">
            <input type="time" v-model="editedEvent.time" class="p-inputtext w-full" />
          </div>
          <div v-else>
            {{ data.time }}
          </div>
        </template>
      </Column>

      <!-- Actions Column -->
      <Column header="Actions" style="width:10%;" body-class="text-center">
        <template #body="{ data }">
          <div class="action-buttons">
            <Button
              v-if="editingId !== data.id"
              icon="pi pi-pen-to-square"
              class="p-button-rounded p-button-info"
              @click="startEditing(data)"
            />

            <template v-if="editingId === data.id">
              <Button icon="pi pi-check" class="p-button-rounded p-button-success" @click="saveEdit" />
              <Button icon="pi pi-times" class="p-button-rounded p-button-secondary" @click="cancelEdit" />
            </template>

            <Button
              icon="pi pi-trash"
              class="p-button-rounded p-button-danger"
              @click="confirmDeleteEvent(data)"
            />
          </div>
        </template>
      </Column>
    </DataTable>
  </div>
</template>
