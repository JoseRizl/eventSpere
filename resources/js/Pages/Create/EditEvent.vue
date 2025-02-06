<script setup>
  import { useCategoryStore } from "@/stores/categoryStore";
  import { useEventStore } from "@/stores/eventStore";
  import { ref, reactive, onMounted } from "vue";

  // Stores
  const categoryStore = useCategoryStore();
  const eventStore = useEventStore();

  // Categories
  const categories = ref([]);

  // Form data
  const form = reactive({
    title: "",
    subtitle: "",
    description: "",
    category: null,
    date: "",
    time: "",
    image: "https://primefaces.org/cdn/primeng/images/demo/product/bamboo-watch.jpg", // Default image
  });

  const submitted = ref(false);

  // Fetch categories on mount
  onMounted(async () => {
    await categoryStore.fetchCategories();
    categories.value = categoryStore.categories;
  });

  // Form submission handler
  const createEvent = async () => {
    submitted.value = true;

    // Simple validation
    if (!form.title || !form.category || !form.date || !form.time) {
      alert("Please fill out the required fields.");
      return;
    }

    try {
      await eventStore.createEvent({ ...form });

      // Reset form
      form.title = "";
      form.subtitle = "";
      form.description = "";
      form.category = null;
      form.date = "";
      form.time = "";
      form.image = "https://primefaces.org/cdn/primeng/images/demo/product/bamboo-watch.jpg"; // Reset image
      submitted.value = false;
      alert("Event edited successfully!");
    } catch (error) {
      console.error("Error creating event:", error);
      alert("Failed to edit the event.");
    }
  };
  </script>

<template>
    <div class="create-event-wrapper">
      <Card class="create-event-form">
        <template #title>Edit Event</template>
        <template #content>
          <form @submit.prevent="createEvent" class="p-fluid">
            <!-- Event Title -->
            <div class="p-field">
              <label for="title">Event Title</label>
              <InputText
                id="title"
                v-model="form.title"
                placeholder="Enter event title"
                :class="{ 'p-invalid': !form.title && submitted }"
              />
              <small v-if="!form.title && submitted" class="p-error">Title is required.</small>
            </div>

            <!-- Event Subtitle -->
            <div class="p-field">
              <label for="subtitle">Event Subtitle (Optional)</label>
              <InputText
                id="subtitle"
                v-model="form.subtitle"
                placeholder="Enter event subtitle"
              />
            </div>

            <!-- Event Category Dropdown -->
            <div class="p-field">
              <label for="category">Category</label>
              <Dropdown
                id="category"
                v-model="form.category"
                :options="categories"
                optionLabel="title"
                placeholder="Select a category"
                :class="{ 'p-invalid': !form.category && submitted }"
              />
              <small v-if="!form.category && submitted" class="p-error">Category is required.</small>
            </div>

            <!-- Event Date -->
            <div class="p-field">
              <label for="date">Event Date</label>
              <Calendar
                id="date"
                v-model="form.date"
                dateFormat="yy-mm-dd"
                showIcon
                placeholder="Select a date"
                :class="{ 'p-invalid': !form.date && submitted }"
              />
              <small v-if="!form.date && submitted" class="p-error">Date is required.</small>
            </div>

            <!-- Event Time -->
            <div class="p-field">
              <label for="time">Event Time</label>
              <InputText
                id="time"
                v-model="form.time"
                placeholder="HH:mm"
                :class="{ 'p-invalid': !form.time && submitted }"
              />
              <small v-if="!form.time && submitted" class="p-error">Time is required.</small>
            </div>

            <!-- Event Description -->
            <div class="p-field">
              <label for="description">Description</label>
              <Textarea
                id="description"
                v-model="form.description"
                rows="4"
                placeholder="Enter event description"
                autoResize
              />
            </div>

            <!-- Submit Button -->
            <Button
              type="submit"
              label="Edit Event"
              icon="pi pi-check"
              class="p-button-success mt-3"
            />
          </form>
        </template>
      </Card>
    </div>
  </template>

