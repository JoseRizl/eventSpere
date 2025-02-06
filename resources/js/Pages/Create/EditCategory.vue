<script setup>
  import { useCategoryStore } from "@/stores/categoryStore";
  import { reactive } from "vue";

  // Initialize the category store
  const categoryStore = useCategoryStore();

  // Reactive form data
  const form = reactive({
    title: "",
    description: "",
  });

  // Form submission handler
  const createCategory = async () => {
    if (!form.title || !form.description) {
      alert("Please fill out all fields.");
      return;
    }

    try {
      await categoryStore.createCategory({
        title: form.title,
        description: form.description,
      });

      // Reset form
      form.title = "";
      form.description = "";
    } catch (error) {
      console.error("Error creating category:", error);
    }
  };
  </script>

<template>
    <!-- Wrapper to center content -->
    <div class="create-category-wrapper">
      <!-- PrimeVue Card Wrapper -->
      <Card class="create-category-form">
        <template #title>
          Edit Category
        </template>

        <template #content>
          <!-- Form -->
          <form @submit.prevent="createCategory" class="p-fluid">
            <!-- Category Name Input -->
            <div class="p-field">
              <label for="title">Category Name</label>
              <InputText
                id="title"
                v-model="form.title"
                placeholder="Enter category name"
                required
              />
            </div>

            <!-- Category Description Input -->
            <div class="p-field">
              <label for="description">Category Description</label>
              <Textarea
                id="description"
                v-model="form.description"
                rows="4"
                placeholder="Enter category description"
                autoResize
              />
            </div>

            <!-- Submit Button -->
            <Button
              type="submit"
              label="Save"
              icon="pi pi-check"
              class="p-button-success"
            />
          </form>
        </template>
      </Card>
    </div>
  </template>

