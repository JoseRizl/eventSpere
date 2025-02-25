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
  startDate: "", // New field
  endDate: "",   // New field
  startTime: "", // New field
  endTime: "",   // New field
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
  if (
    !form.title ||
    !form.category ||
    !form.startDate ||
    !form.endDate ||
    !form.startTime ||
    !form.endTime
  ) {
    alert("Please fill out the required fields.");
    return;
  }

  try {
    // Combine date and time into start and end datetime strings
    const startDateTime = `${form.startDate}T${form.startTime}:00`;
    const endDateTime = `${form.endDate}T${form.endTime}:00`;

    // Prepare payload
    const payload = {
      ...form,
      startDateTime, // Add startDateTime
      endDateTime,   // Add endDateTime
    };

    await eventStore.createEvent(payload);

    // Reset form
    form.title = "";
    form.subtitle = "";
    form.description = "";
    form.category = null;
    form.startDate = "";
    form.endDate = "";
    form.startTime = "";
    form.endTime = "";
    form.image = "https://primefaces.org/cdn/primeng/images/demo/product/bamboo-watch.jpg"; // Reset image
    submitted.value = false;
    alert("Event created successfully!");
  } catch (error) {
    console.error("Error creating event:", error);
    alert("Failed to create the event.");
  }
};
</script>

<template>
  <div class="create-event-wrapper">
    <Card class="create-event-form">
      <template #title>Create Event</template>
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

          <!-- Start Date -->
          <div class="p-field">
            <label for="startDate">Start Date</label>
            <Calendar
              id="startDate"
              v-model="form.startDate"
              dateFormat="yy-mm-dd"
              showIcon
              placeholder="Select start date"
              :class="{ 'p-invalid': !form.startDate && submitted }"
            />
            <small v-if="!form.startDate && submitted" class="p-error">Start date is required.</small>
          </div>

          <!-- End Date -->
          <div class="p-field">
            <label for="endDate">End Date</label>
            <Calendar
              id="endDate"
              v-model="form.endDate"
              dateFormat="yy-mm-dd"
              showIcon
              placeholder="Select end date"
              :class="{ 'p-invalid': !form.endDate && submitted }"
            />
            <small v-if="!form.endDate && submitted" class="p-error">End date is required.</small>
          </div>

          <!-- Start Time -->
          <div class="p-field">
            <label for="startTime">Start Time</label>
            <InputText
              id="startTime"
              v-model="form.startTime"
              placeholder="HH:mm"
              :class="{ 'p-invalid': !form.startTime && submitted }"
            />
            <small v-if="!form.startTime && submitted" class="p-error">Start time is required.</small>
          </div>

          <!-- End Time -->
          <div class="p-field">
            <label for="endTime">End Time</label>
            <InputText
              id="endTime"
              v-model="form.endTime"
              placeholder="HH:mm"
              :class="{ 'p-invalid': !form.endTime && submitted }"
            />
            <small v-if="!form.endTime && submitted" class="p-error">End time is required.</small>
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
            label="Create Event"
            icon="pi pi-check"
            class="p-button-success mt-3"
          />
        </form>
      </template>
    </Card>
  </div>
</template>
