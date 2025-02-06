// stores/categoryStore.js
import { defineStore } from "pinia";
import axios from "axios";

export const useCategoryStore = defineStore("category", {
  state: () => ({
    categories: [],
  }),
  actions: {
    // Fetch all categories
    async fetchCategories() {
      try {
        const response = await axios.get("http://localhost:3000/category");
        this.categories = response.data;
      } catch (error) {
        console.error("Failed to fetch categories:", error);
      }
    },

    // Delete a category
    async deleteCategory(id) {
      if (confirm("Are you sure you want to delete this category?")) {
        try {
          await axios.delete(`http://localhost:3000/category/${id}`);
          this.categories = this.categories.filter((cat) => cat.id !== id);
          alert("Category deleted successfully!");
        } catch (error) {
          console.error("Failed to delete category:", error);
        }
      }
    },

    // Create a new category
    async createCategory(newCategory) {
        try {
          const response = await axios.post("http://localhost:3000/category", newCategory);
          this.categories.push(response.data);
          alert("Category created successfully!");
        } catch (error) {
          console.error("Failed to create category:", error);
        }
      },
  },
});
