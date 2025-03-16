import { defineStore } from 'pinia';
import axios from 'axios';

export const useBracketStore = defineStore('bracketStore', {
  state: () => ({
    brackets: [],
  }),

  actions: {
    // Fetch all brackets
    async fetchBrackets() {
      try {
        const response = await axios.get('http://localhost:3000/brackets');
        this.brackets = response.data;
      } catch (error) {
        console.error('Error fetching brackets:', error);
      }
    },

    // Create a new bracket
    async createBracket(newBracket) {
      try {
        await axios.post('http://localhost:3000/brackets', newBracket);
        await this.fetchBrackets(); // Refresh brackets
      } catch (error) {
        console.error('Error creating bracket:', error);
      }
    },

    // Delete a bracket
    async deleteBracket(id) {
      if (!confirm('Are you sure you want to delete this bracket?')) return;

      try {
        await axios.delete(`http://localhost:3000/brackets/${id}`);
        this.brackets = this.brackets.filter(bracket => bracket.id !== id);
      } catch (error) {
        console.error('Error deleting bracket:', error);
      }
    }
  }
});
