<script setup>
import { useCategoryStore } from "@/stores/categoryStore";
import { onMounted, ref } from "vue";

const categoryStore = useCategoryStore();
const editingRow = ref(null); // Track the row being edited
const editedCategory = ref({}); // Store edited data

// Fetch categories when the component mounts
onMounted(() => {
  categoryStore.fetchCategories();
});

// Actions
const deleteCategory = (id) => {
  categoryStore.deleteCategory(id);
};

const startEdit = (category) => {
  editingRow.value = category.id; // Track which row is being edited
  editedCategory.value = { ...category }; // Clone category data
};

const saveEdit = async () => {
  await categoryStore.editCategory(editedCategory.value); // Send update to store
  editingRow.value = null; // Exit edit mode
};

const cancelEdit = () => {
  editingRow.value = null; // Exit edit mode without saving
};
</script>

<template>
  <div class="category-list-container">
    <h1 class="title">Category List</h1>

    <!-- Table to display categories -->
    <DataTable
      :value="categoryStore.categories"
      class="p-datatable-striped"
      responsiveLayout="scroll"
    >
      <Column field="id" header="ID" style="width: 10%" sortable></Column>

      <!-- Editable Category Name Column -->
      <Column field="title" header="Category Name" style="width: 30%" sortable>
        <template #body="{ data }">
          <template v-if="editingRow === data.id">
            <InputText v-model="editedCategory.title" class="p-inputtext-sm" />
          </template>
          <template v-else>
            {{ data.title }}
          </template>
        </template>
      </Column>

      <!-- Editable Description Column -->
      <Column field="description" header="Description" style="width: 50%">
        <template #body="{ data }">
          <template v-if="editingRow === data.id">
            <InputText v-model="editedCategory.description" class="p-inputtext-sm" />
          </template>
          <template v-else>
            {{ data.description }}
          </template>
        </template>
      </Column>

      <!-- Actions Column -->
      <Column header="Actions" body-class="text-center">
        <template #body="{ data }">
          <div class="action-buttons">
            <!-- Show Save/Cancel if editing, otherwise show Edit/Delete -->
            <template v-if="editingRow === data.id">
              <Button icon="pi pi-check" class="p-button-rounded p-button-success" @click="saveEdit" />
              <Button icon="pi pi-times" class="p-button-rounded p-button-warning" @click="cancelEdit" />
            </template>
            <template v-else>
              <Button icon="pi pi-pen-to-square" class="p-button-rounded p-button-info" @click="startEdit(data)" />
              <Button icon="pi pi-trash" class="p-button-rounded p-button-danger" @click="deleteCategory(data.id)" />
            </template>
          </div>
        </template>
      </Column>
    </DataTable>
  </div>
</template>
