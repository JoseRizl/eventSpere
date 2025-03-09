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
              <InputText id="subtitle" v-model="form.subtitle" placeholder="Enter event subtitle" />
            </div>

            <!-- Event Category Dropdown -->
            <div class="p-field">
              <label for="category">Category</label>
              <Dropdown
                id="category"
                v-model="form.category"
                :options="categories"
                optionLabel="title"
                optionValue="id"
                placeholder="Select a category"
                :class="{ 'p-invalid': !form.category && submitted }"
              />
              <small v-if="!form.category && submitted" class="p-error">Category is required.</small>
            </div>

            <!-- Start Date & End Date -->
            <div class="p-field p-grid">
              <div class="p-col-6">
                <label for="startDate">Start Date</label>
                <Calendar id="startDate" v-model="form.startDate" dateFormat="yy-mm-dd" showIcon />
              </div>
              <div class="p-col-6">
                <label for="endDate">End Date</label>
                <Calendar id="endDate" v-model="form.endDate" dateFormat="yy-mm-dd" showIcon />
              </div>
            </div>

            <!-- Start Time & End Time -->
            <div class="p-field p-grid">
              <div class="p-col-6">
                <label for="startTime">Start Time</label>
                <InputText
                  id="startTime"
                  v-model="form.startTime"
                  placeholder="HH:mm"
                  @blur="form.startTime = formatTime(form.startTime)"
                  :class="{ 'p-invalid': !form.startTime && submitted }"
                />
                <small v-if="!form.startTime && submitted" class="p-error">Start time is required.</small>
              </div>
              <div class="p-col-6">
                <label for="endTime">End Time</label>
                <InputText
                  id="endTime"
                  v-model="form.endTime"
                  placeholder="HH:mm"
                  @blur="form.endTime = formatTime(form.endTime)"
                  :class="{ 'p-invalid': !form.endTime && submitted }"
                />
                <small v-if="!form.endTime && submitted" class="p-error">End time is required.</small>
              </div>
            </div>

            <!-- Event Description -->
            <div class="p-field">
              <label for="description">Description</label>
              <Textarea id="description" v-model="form.description" rows="4" placeholder="Enter event description" autoResize />
            </div>

            <!-- Submit Button -->
            <Button type="submit" label="Create Event" icon="pi pi-check" class="p-button-success mt-3" />
          </form>
        </template>
      </Card>
    </div>
  </template>

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
    category: null, // Stores category ID
    startDate: "",
    endDate: "",
    startTime: "",
    endTime: "",
    image: "https://primefaces.org/cdn/primeng/images/demo/product/bamboo-watch.jpg", // Default image
  });

  const submitted = ref(false);

  // Fetch categories on mount
  onMounted(async () => {
    await categoryStore.fetchCategories();
    categories.value = categoryStore.categories;
  });

  // Helper function to ensure valid HH:mm format
  const formatTime = (time) => {
    if (!time) return "00:00";
    const parts = time.split(":");
    const hours = parts[0].padStart(2, "0");
    const minutes = parts[1]?.padStart(2, "0") || "00";
    return `${hours}:${minutes}`;
  };

  // Form submission handler
  const createEvent = async () => {
    submitted.value = true;

    // Simple validation
    if (!form.title || !form.category || !form.startDate || !form.endDate || !form.startTime || !form.endTime) {
      alert("Please fill out the required fields.");
      return;
    }

    try {
      // Prepare payload
      const payload = {
        title: form.title,
        subtitle: form.subtitle,
        description: form.description,
        category_id: form.category, // Ensure correct category mapping
        startDate: form.startDate,
        endDate: form.endDate,
        startTime: formatTime(form.startTime),
        endTime: formatTime(form.endTime),
        image: form.image || "https://example.com/default-image.jpg",
      };

      await eventStore.createEvent(payload);

      // Reset form
      Object.assign(form, {
        title: "",
        subtitle: "",
        description: "",
        category: null,
        startDate: "",
        endDate: "",
        startTime: "",
        endTime: "",
        image: "https://primefaces.org/cdn/primeng/images/demo/product/bamboo-watch.jpg",
      });

      submitted.value = false;
      alert("Event created successfully!");
    } catch (error) {
      console.error("Error creating event:", error);
      alert("Failed to create the event.");
    }
  };
  </script>
