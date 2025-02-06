<script setup>
import { useCategoryStore } from "@/stores/categoryStore";
import { onMounted } from "vue";

const categoryStore = useCategoryStore();

// Fetch categories when the component mounts
onMounted(() => {
  categoryStore.fetchCategories();
});

// Actions
const deleteCategory = (id) => {
  categoryStore.deleteCategory(id);
};

const editCategory = (category) => {
  categoryStore.editCategory(category);
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
      <Column field="id" header="ID" style="width: 10%"></Column>
      <Column field="title" header="Category Name" style="width: 30%"></Column>
      <Column field="description" header="Description" style="width: 50%"></Column>

      <!-- Actions Column -->
      <Column header="Actions" body-class="text-center">
        <template #body="{ data }">
          <div class="action-buttons">
            <Button
              icon="pi pi-pen-to-square"
              class="p-button-rounded p-button-info"
              @click="editCategory(data)"
            />
            <Button
              icon="pi pi-trash"
              class="p-button-rounded p-button-danger"
              @click="deleteCategory(data.id)"
            />
          </div>
        </template>
      </Column>
    </DataTable>

  </div>
</template>

