<template>
    <div class="archive-container">
      <h1 class="title text-center mb-4">Archived Events</h1>

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

      <!-- No Results Message -->
      <div v-if="searchQuery && filteredEvents.length === 0" class="no-results-message">
        <div class="icon-and-title">
          <i class="pi pi-search" style="font-size: 1.5rem; color: #007bff; margin-right: 10px;"></i>
          <h2 class="no-results-title">No Archived Events Found</h2>
        </div>
        <p class="no-results-text">No archived events match your search criteria. Try adjusting your search terms.</p>
      </div>

      <DataTable v-else :value="filteredEvents" class="p-datatable-striped">
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
              <Button icon="pi pi-undo" class="p-button-rounded p-button-success" @click="restoreEvent(data)" v-tooltip.top="'Restore Event'"/>
              <Button icon="pi pi-trash" class="p-button-rounded p-button-danger" @click="deleteEventPermanently(data)" v-tooltip.top="'Delete Event'"/>
            </div>
          </template>
        </Column>
      </DataTable>
    </div>
</template>

  <script>
  import { defineComponent, ref, onMounted, computed } from "vue";
  import axios from "axios";
  import { format } from "date-fns";
  export default defineComponent({
    name: "Archive",
    setup() {
      const archivedEvents = ref([]);
      const categories = ref([]);
      const searchQuery = ref("");

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

    onMounted(async () => {
        try {
            const [eventsResponse, categoriesResponse] = await Promise.all([
            axios.get("http://localhost:3000/events?archived=true"),
            axios.get("http://localhost:3000/category"),
            ]);

            archivedEvents.value = eventsResponse.data.sort((a, b) => {
                return new Date(b.startDate || "1970-01-01") - new Date(a.startDate || "1970-01-01");
            });
            categories.value = categoriesResponse.data;
        } catch (error) {
            console.error("Error fetching archived events or categories:", error);
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


      // Restore Event
      const restoreEvent = async (event) => {
        if (!confirm(`Are you sure you want to restore "${event.title}"?`)) return;

        try {
          await axios.put(`http://localhost:3000/events/${event.id}`, {
            ...event,
            archived: false, // Set `archived` to false
          });

          archivedEvents.value = archivedEvents.value.filter(e => e.id !== event.id);
          alert("Event restored successfully!");
        } catch (error) {
          console.error("Error restoring event:", error);
          alert("Failed to restore the event.");
        }
      };

      // Delete Event Permanently
      const deleteEventPermanently = async (event) => {
        if (!confirm(`Are you sure you want to permanently delete "${event.title}"?`)) return;

        try {
          await axios.delete(`http://localhost:3000/events/${event.id}`);
          archivedEvents.value = archivedEvents.value.filter(e => e.id !== event.id);
          alert("Event permanently deleted!");
        } catch (error) {
          console.error("Error deleting event:", error);
          alert("Failed to delete the event.");
        }
      };

      return {
        archivedEvents,
        categoryMap,
        formatDateTime,
        restoreEvent,
        deleteEventPermanently,
        searchQuery,
        filteredEvents,
      };
    },
  });
  </script>

  <style scoped>
  .archive-container {
    padding: 20px;
  }

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

