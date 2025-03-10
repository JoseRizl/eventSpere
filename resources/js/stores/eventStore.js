import { defineStore } from "pinia";
import axios from "axios";
import { format } from "date-fns";

export const useEventStore = defineStore("event", {
  state: () => ({
    events: [],
    archivedEvents: [],
    categories: [],
  }),

  getters: {
    combinedEvents: (state) => state.events,
    archived: (state) => state.archivedEvents,
  },

  actions: {
    async fetchEvents() {
      try {
        const [eventsResponse, archivedResponse] = await Promise.all([
          axios.get("http://localhost:3000/events"),
          axios.get("http://localhost:3000/archived"),
        ]);

        this.events = eventsResponse.data.map(event => ({
          ...event,
          startDate: event.startDate ? format(new Date(event.startDate), "yyyy-MM-dd") : "",
          endDate: event.endDate ? format(new Date(event.endDate), "yyyy-MM-dd") : "",
        }));

        this.archivedEvents = archivedResponse.data.map(event => ({
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
        const response = await axios.post("http://localhost:3000/events", eventData);
        this.events.push(response.data);
      } catch (error) {
        console.error("Error creating event:", error);
        throw error;
      }
    },

    async archiveEvent(event) {
      try {
        await axios.delete(`http://localhost:3000/events/${event.id}`);
        await axios.post("http://localhost:3000/archived", event);

        this.events = this.events.filter(e => e.id !== event.id);
        this.archivedEvents.push(event);
      } catch (error) {
        console.error("Error archiving event:", error);
      }
    },

    async restoreEvent(event) {
      try {
        await axios.delete(`http://localhost:3000/archived/${event.id}`);
        await axios.post("http://localhost:3000/events", event);

        this.archivedEvents = this.archivedEvents.filter(e => e.id !== event.id);
        this.events.push(event);
      } catch (error) {
        console.error("Error restoring event:", error);
      }
    },

    async deleteEventPermanently(event) {
      try {
        await axios.delete(`http://localhost:3000/archived/${event.id}`);
        this.archivedEvents = this.archivedEvents.filter(e => e.id !== event.id);
      } catch (error) {
        console.error("Error deleting event permanently:", error);
      }
    },
  },
});
