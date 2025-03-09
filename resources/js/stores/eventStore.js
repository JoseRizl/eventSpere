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

        this.events = eventsResponse.data.map(event => ({
          ...event,
          startDate: event.startDate ? format(new Date(event.startDate), "yyyy-MM-dd") : "",
          endDate: event.endDate ? format(new Date(event.endDate), "yyyy-MM-dd") : "",
        }));

        this.sportsEvents = sportsResponse.data.map(event => ({
          ...event,
          startDate: event.startDate ? format(new Date(event.startDate), "yyyy-MM-dd") : "",
          endDate: event.endDate ? format(new Date(event.endDate), "yyyy-MM-dd") : "",
        }));
      } catch (error) {
        console.error("Error fetching events:", error);
      }
    },

    async fetchCategories() {
      try {
        const response = await axios.get("http://localhost:3000/categories");
        this.categories = response.data;
      } catch (error) {
        console.error("Error fetching categories:", error);
      }
    },

    async createEvent(eventData) {
      try {
        const endpoint = eventData.category === "3"
          ? "http://localhost:3000/sports"
          : "http://localhost:3000/events";

        const response = await axios.post(endpoint, eventData);

        if (eventData.category === "3") {
          this.sportsEvents.push(response.data);
        } else {
          this.events.push(response.data);
        }
      } catch (error) {
        console.error("Error creating event:", error);
        throw error;
      }
    },
  },
});
