import { defineStore } from "pinia";
import axios from "axios";
import { format } from "date-fns";

export const useEventStore = defineStore("event", {
  state: () => ({
    events: [],
    sportsEvents: [],
    categories: [],
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

        this.events = eventsResponse.data.map((event) => ({
          ...event,
          startDateTime: format(new Date(event.startDateTime), "yyyy-MM-dd HH:mm"),
          endDateTime: format(new Date(event.endDateTime), "yyyy-MM-dd HH:mm"),
        }));

        this.sportsEvents = sportsResponse.data.map((event) => ({
          ...event,
          startDateTime: format(new Date(event.startDateTime), "yyyy-MM-dd HH:mm"),
          endDateTime: format(new Date(event.endDateTime), "yyyy-MM-dd HH:mm"),
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
        // Ensure category_id is a string
        const category_id = typeof eventData.category === "string"
          ? eventData.category
          : eventData.category.id;

        // Format start and end datetime
        const startDateTime = format(new Date(`${eventData.startDate}T${eventData.startTime}:00`), "yyyy-MM-dd HH:mm");
        const endDateTime = format(new Date(`${eventData.endDate}T${eventData.endTime}:00`), "yyyy-MM-dd HH:mm");

        const payload = {
          title: eventData.title,
          subtitle: eventData.subtitle,
          description: eventData.description,
          category_id,
          startDateTime,
          endDateTime,
          image: eventData.image,
        };

        const endpoint = category_id === "3"
          ? "http://localhost:3000/sports"
          : "http://localhost:3000/events";

        const response = await axios.post(endpoint, payload);

        // Add the new event to the correct list
        const newEvent = {
          ...response.data,
          category: { id: category_id },
          startDateTime,
          endDateTime,
        };

        if (category_id === "3") {
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

    async deleteEvent(event) {
      if (confirm("Are you sure you want to delete this event?")) {
        try {
          const endpoint =
            event.category === "sports"
              ? `http://localhost:3000/sports/${event.id}`
              : `http://localhost:3000/events/${event.id}`;

          await axios.delete(endpoint);

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
