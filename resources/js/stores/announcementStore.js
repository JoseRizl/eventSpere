import { defineStore } from "pinia";
import axios from "axios";
import { format } from "date-fns";

export const useAnnouncementStore = defineStore("announcementStore", {
  state: () => ({
    announcements: [],
  }),

  actions: {
    async fetchAnnouncements() {
      try {
        const response = await axios.get("http://localhost:3000/announcements");
        this.announcements = response.data.map((announcement) => ({
          ...announcement,
          formattedTimestamp: format(new Date(announcement.timestamp), "MMMM dd, yyyy HH:mm"),
        }));
      } catch (error) {
        console.error("Error fetching announcements:", error);
      }
    },

    async addAnnouncement(message) {
      if (!message.trim()) return;

      const newEntry = {
        message: message.trim(),
        timestamp: new Date().toISOString(),
      };

      try {
        const response = await axios.post("http://localhost:3000/announcements", newEntry);
        this.announcements.push({
          ...response.data,
          formattedTimestamp: format(new Date(newEntry.timestamp), "MMMM dd, yyyy HH:mm"),
        });
      } catch (error) {
        console.error("Error adding announcement:", error);
      }
    },

    async removeAnnouncement(id) {
      try {
        await axios.delete(`http://localhost:3000/announcements/${id}`);
        this.announcements = this.announcements.filter((a) => a.id !== id);
      } catch (error) {
        console.error("Error deleting announcement:", error);
      }
    },
  },
});
