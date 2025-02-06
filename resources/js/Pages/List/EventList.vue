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

    const route = window.route; //laravel route helper

    // Fetch data on component mount
    onMounted(async () => {
  try {
    // Fetch events
    const eventsResponse = await axios.get("http://localhost:3000/events");
    events.value = eventsResponse.data.map((event) => ({
      ...event,
      type: "event", // Tag as event
      category_id: event.category?.id || event.category_id,
      date: format(new Date(event.date), "yyyy-MM-dd"),
    }));

    // Fetch sports
    const sportsResponse = await axios.get("http://localhost:3000/sports");
    sports.value = sportsResponse.data.map((sport) => ({
      ...sport,
      type: "sport", // Tag as sport
      category_id: sport.category?.id || sport.category_id,
      date: format(new Date(sport.date), "yyyy-MM-dd"),
    }));

    // Fetch categories
    const categoriesResponse = await axios.get("http://localhost:3000/category");
    categories.value = categoriesResponse.data;

    // Combine events and sports
    combinedEvents.value = [...events.value, ...sports.value];
  } catch (error) {
    console.error("Error fetching events, sports, or categories:", error);
  }
});


    // Map category_id to category title
    const categoryMap = computed(() => {
      return categories.value.reduce((map, category) => {
        map[category.id] = category.title; // Map category_id -> title
        return map;
      }, {});
    });

    // Edit and Delete Handlers
    const editEvent = (event) => {
      console.log("Edit event:", event);
      // Add edit logic here
    };

    const confirmDeleteEvent = (event) => {
      if (confirm("Are you sure you want to delete this event?")) {
        deleteEvent(event);
      }
    };

    const deleteEvent = async (item) => {
        try {
            const collection = item.type === "sport" ? "sports" : "events"; // Determine the collection
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
      editEvent,
      confirmDeleteEvent,
      route,
    };
  },
});

</script>


<template>
    <div class="event-list-container">
      <h1 class="title">Event List</h1>

      <DataTable :value="combinedEvents" class="p-datatable-striped">
        <!-- Event Name Column with Icon -->
        <Column field="title" header="Event Name" style="width:30%;">
          <template #body="{ data }">
            <div class="flex items-center gap-2">
              <img
                v-if="data.image"
                :src="data.image"
                alt="Event Image"
                class="event-icon"
              />
              <span>{{ data.title }}</span>
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
        <Column field="date" header="Date" style="width:15%;"/>

        <!-- Time Column -->
        <Column field="time" header="Time" style="width:15%;"/>

        <!-- Actions Column -->
        <Column header="Actions" style="width:10%;" body-class="text-center">
          <template #body="{ data }">
            <div class="action-buttons">
            <Button
                icon="pi pi-pen-to-square"
                class="p-button-rounded p-button-info"
                :href="route('event.edit')"
            />

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

