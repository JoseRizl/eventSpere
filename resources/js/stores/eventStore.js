import { defineStore } from "pinia";
import axios from "axios";
import { format } from "date-fns";

export const useEventStore = defineStore("event", {
  state: () => ({
    events: [],
    archivedEvents: [],
    categories: [],
    tags: [],
  }),

  getters: {
    combinedEvents: (state) => state.events,
    archived: (state) => state.archivedEvents,
  },

  actions: {
    // Fetch Events
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

    // Fetch Categories
    async fetchCategories() {
      try {
        const response = await axios.get("http://localhost:3000/categories");
        this.categories = response.data;
      } catch (error) {
        console.error("Error fetching categories:", error);
      }
    },

    // Create Event
    async createEvent(eventData) {
      try {
        const response = await axios.post("http://localhost:3000/events", eventData);
        this.events.push(response.data);
      } catch (error) {
        console.error("Error creating event:", error);
        throw error;
      }
    },

    // Archive Event
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

    // Restore Event
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

    // Permanently Delete Event
    async deleteEventPermanently(event) {
      try {
        await axios.delete(`http://localhost:3000/archived/${event.id}`);
        this.archivedEvents = this.archivedEvents.filter(e => e.id !== event.id);
      } catch (error) {
        console.error("Error deleting event permanently:", error);
      }
    },

    // TAG SYSTEM
    async fetchTags() {
      try {
        const response = await axios.get("http://localhost:3000/tags");
        this.tags = response.data;
      } catch (error) {
        console.error("Error fetching tags:", error);
      }
    },

    async addTag(newTag) {
      try {
        const response = await axios.post("http://localhost:3000/tags", newTag);
        this.tags.push(response.data);
      } catch (error) {
        console.error("Error adding tag:", error);
      }
    },
  },
});
