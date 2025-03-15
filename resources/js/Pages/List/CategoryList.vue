<script>
import { defineComponent, ref, onMounted } from "vue";
import axios from "axios";
import { DataTable, Column, Button, Dialog, InputText, Textarea } from "primevue";

export default defineComponent({
  name: "CategoryList",
  components: {
    DataTable,
    Column,
    Button,
    Dialog,
    InputText,
    Textarea,
  },
  setup() {
    const categories = ref([]);
    const events = ref([]);
    const isEditModalVisible = ref(false);
    const isCreateModalVisible = ref(false);
    const selectedCategory = ref(null);
    const newCategory = ref({ title: "", description: "" });

    // Fetch categories and events on mount
    onMounted(async () => {
      await fetchCategories();
    });

    const fetchCategories = async () => {
      try {
        const [categoriesResponse, eventsResponse] = await Promise.all([
          axios.get("http://localhost:3000/category"),
          axios.get("http://localhost:3000/events"),
        ]);
        categories.value = categoriesResponse.data;
        events.value = eventsResponse.data;
      } catch (error) {
        console.error("Error fetching data:", error);
      }
    };

    // Check if a category is in use
    const isCategoryInUse = (categoryId) => {
      return events.value.some((event) => event.category_id === categoryId);
    };

    // Open Edit Modal
    const openEditModal = (category) => {
      selectedCategory.value = { ...category };
      isEditModalVisible.value = true;
    };

    // Save Edited Category
    const saveEditedCategory = async () => {
      if (!selectedCategory.value) return;

      try {
        await axios.put(
          `http://localhost:3000/category/${selectedCategory.value.id}`,
          selectedCategory.value
        );

        // Update the list without reloading
        const index = categories.value.findIndex((c) => c.id === selectedCategory.value.id);
        if (index !== -1) categories.value[index] = { ...selectedCategory.value };

        isEditModalVisible.value = false;
        alert("Category updated successfully!");
      } catch (error) {
        console.error("Error updating category:", error);
        alert("Failed to update the category.");
      }
    };

    // Open Create Modal
    const openCreateModal = () => {
      newCategory.value = { title: "", description: "" };
      isCreateModalVisible.value = true;
    };

    // Create New Category
    const createCategory = async () => {
      if (!newCategory.value.title.trim()) {
        alert("Category name is required.");
        return;
      }

      try {
        const response = await axios.post("http://localhost:3000/category", newCategory.value);
        categories.value.push(response.data); // Update list instantly

        isCreateModalVisible.value = false;
        alert("Category created successfully!");
      } catch (error) {
        console.error("Error creating category:", error);
        alert("Failed to create the category.");
      }
    };

    // Delete Category
    const deleteCategory = async (id) => {
      if (!confirm("Are you sure you want to delete this category?")) return;

      try {
        await axios.delete(`http://localhost:3000/category/${id}`);
        categories.value = categories.value.filter((c) => c.id !== id);
        alert("Category deleted successfully!");
      } catch (error) {
        console.error("Error deleting category:", error);
        alert("Failed to delete the category.");
      }
    };

    return {
      categories,
      openEditModal,
      saveEditedCategory,
      deleteCategory,
      isEditModalVisible,
      selectedCategory,
      isCreateModalVisible,
      openCreateModal,
      createCategory,
      newCategory,
      isCategoryInUse,
    };
  },
});
</script>

<template>
    <div class="category-list-container">
      <div class="category-header">
        <h1 class="title">Category List</h1>
        <Button label="Create Category" icon="pi pi-plus" class="p-button-success" @click="openCreateModal" />
      </div>

      <DataTable :value="categories" class="p-datatable-striped">
        <Column field="title" header="Category Name" style="width:30%;" sortable>
          <template #body="{ data }">
            {{ data.title }}
          </template>
        </Column>
        <Column field="description" header="Description" style="width:50%;">
          <template #body="{ data }">
            {{ data.description || "No description available" }}
          </template>
        </Column>

        <Column header="Actions" style="width:10%;" body-class="text-center">
          <template #body="{ data }">
            <div class="action-buttons">
              <Button
                icon="pi pi-pen-to-square"
                class="p-button-rounded p-button-info"
                @click="openEditModal(data)"
              />
              <Button
                icon="pi pi-trash"
                class="p-button-rounded p-button-danger"
                @click="deleteCategory(data.id)"
                :disabled="isCategoryInUse(data.id)"
                v-tooltip="isCategoryInUse(data.id) ? 'Category is in use and cannot be deleted' : ''"
              />
            </div>
          </template>
        </Column>
      </DataTable>

      <!-- Edit Category Modal -->
    <Dialog v-model:visible="isEditModalVisible" modal header="Edit Category" :style="{ width: '50vw' }">
      <div class="p-fluid">
        <div class="p-field">
          <label for="title">Category Name</label>
          <InputText id="title" v-model="selectedCategory.title" placeholder="Enter category name" />
        </div>
        <div class="p-field">
          <label for="description">Description</label>
          <Textarea id="description" v-model="selectedCategory.description" rows="4" placeholder="Enter category description" autoResize />
        </div>
      </div>

      <template #footer>
        <Button label="Cancel" icon="pi pi-times" class="p-button-text" @click="isEditModalVisible = false" />
        <Button label="Save Changes" icon="pi pi-check" class="p-button-primary" @click="saveEditedCategory" />
      </template>
    </Dialog>

    <!-- Create Category Modal -->
    <Dialog v-model:visible="isCreateModalVisible" modal header="Create Category" :style="{ width: '50vw' }">
      <div class="p-fluid">
        <div class="p-field">
          <label for="newTitle">Category Name</label>
          <InputText id="newTitle" v-model="newCategory.title" placeholder="Enter category name" />
        </div>
        <div class="p-field">
          <label for="newDescription">Description</label>
          <Textarea id="newDescription" v-model="newCategory.description" rows="4" placeholder="Enter category description" autoResize />
        </div>
      </div>

      <template #footer>
        <Button label="Cancel" icon="pi pi-times" class="p-button-text" @click="isCreateModalVisible = false" />
        <Button label="Create" icon="pi pi-check" class="p-button-primary" @click="createCategory" />
      </template>
    </Dialog>
    </div>
  </template>

