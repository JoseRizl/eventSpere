import { defineStore } from "pinia";
import axios from "axios";
import { format } from "date-fns";

export const useEventStore = defineStore("event", {
  state: () => ({
    events: [],
    sportsEvents: [],
    categories: [], // Add categories to state
  }),
  getters: {
    combinedEvents: (state) => [...state.events, ...state.sportsEvents],
  },
  actions: {
    async fetchEvents() {
        try {
            const [eventsResponse, sportsResponse] = await Promise.all([
              axios.get("http://localhost:3000/events"),
              axios.get("http://localhost:3000/sports"),
            ]);

            // Reformat dates for frontend display
            this.events = eventsResponse.data.map((event) => ({
              ...event,
              date: format(new Date(event.date), "yyyy-MM-dd"), // Reformat date
            }));

            this.sportsEvents = sportsResponse.data.map((event) => ({
              ...event,
              date: format(new Date(event.date), "yyyy-MM-dd"), // Reformat date
            }));
          } catch (error) {
            console.error("Error fetching events:", error);
          }
    },

    async fetchCategories() {
        try {
          const response = await axios.get("http://localhost:3000/category");
          this.categories = response.data;
        } catch (error) {
          console.error("Error fetching categories:", error);
        }
      },


      async createEvent(eventData) {
        try {
          // Extract category title for matching
          const categoryTitle =
            typeof eventData.category === "string"
              ? eventData.category
              : eventData.category.title;

          // Find category or use "Unknown"
          const categoryObj =
            this.categories.find((cat) => cat.title === categoryTitle) || {
              id: "0",
              title: "Unknown",
              description: "No description available",
            };

          // Prepare payload with consistent category format
          const payload = {
            ...eventData,
            category_id: categoryObj.id, // Use only category_id
            date: format(new Date(eventData.date), "yyyy-MM-dd"),
          };


          // Determine endpoint
          const endpoint =
            categoryObj.title === "Sports"
              ? "http://localhost:3000/sports"
              : "http://localhost:3000/events";

          // Send POST request
          const response = await axios.post(endpoint, payload);

          // Add the new event to the correct list
          const newEvent = { ...response.data, category: categoryObj };
          newEvent.date = format(new Date(newEvent.date), "yyyy-MM-dd"); // Reformat date

          if (categoryObj.title === "Sports") {
            this.sportsEvents.push(newEvent);
          } else {
            this.events.push(newEvent);
          }

          console.log("Event created successfully!", response.data);
        } catch (error) {
          console.error("Error creating event:", error);
          throw error;
        }
      },


    // Delete event with confirmation
    async deleteEvent(event) {
      if (confirm("Are you sure you want to delete this event?")) {
        try {
          const endpoint =
            event.category === "sports"
              ? `http://localhost:3000/sports/${event.id}`
              : `http://localhost:3000/events/${event.id}`;

          await axios.delete(endpoint);

          // Remove the event from the appropriate state
          if (event.category === "sports") {
            this.sportsEvents = this.sportsEvents.filter((e) => e.id !== event.id);
          } else {
            this.events = this.events.filter((e) => e.id !== event.id);
          }

          alert("Event deleted successfully!");
        } catch (error) {
          console.error("Error deleting event:", error);
        }
      }
    },
  },
});
