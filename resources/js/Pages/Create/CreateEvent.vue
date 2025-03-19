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

            <!-- Event Tags -->
            <div class="p-field">
              <label for="tags">Tags</label>
              <MultiSelect
                id="tags"
                v-model="selectedTags"
                :options="tags"
                optionLabel="name"
                optionValue="id"
                placeholder="Select tags"
              />
              <div class="tag-creation">
                <InputText
                  v-model="newTag.name"
                  placeholder="New tag name"
                  class="p-mr-2"
                />
                <ColorPicker v-model="newTag.color" />
                <Button
                  label="Add Tag"
                  icon="pi pi-plus"
                  class="p-button-success p-button-sm"
                  @click="addTag"
                />
              </div>
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

            <!-- Start & End Dates -->
            <div class="p-field p-grid">
              <div class="p-col-6">
                <label for="startDate">Start Date</label>
                <Calendar id="startDate" v-model="form.startDate" dateFormat="MM-dd-yy" showIcon />
              </div>
              <div class="p-col-6">
                <label for="endDate">End Date</label>
                <Calendar id="endDate" v-model="form.endDate" dateFormat="MM-dd-yy" showIcon />
              </div>
            </div>

            <!-- Start & End Times -->
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
              </div>
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

  <script setup>
  import { useCategoryStore } from "@/stores/categoryStore";
  import { useEventStore } from "@/stores/eventStore";
  import { ref, reactive, onMounted } from "vue";
  import { router } from '@inertiajs/vue3';

  // Stores
  const categoryStore = useCategoryStore();
  const eventStore = useEventStore();

  // Categories & Tags
  const categories = ref([]);
  const tags = ref([]);
  const selectedTags = ref([]);
  const newTag = reactive({ name: "", color: "#000000" });

  // Form data
  const form = reactive({
    title: "",
    description: "",
    category: null,
    startDate: "",
    endDate: "",
    startTime: "",
    endTime: "",
    image: "https://primefaces.org/cdn/primeng/images/demo/product/bamboo-watch.jpg",
    archived: false,
    tags: [],
  });

  const submitted = ref(false);

  // Fetch data on mount
  onMounted(async () => {
    await categoryStore.fetchCategories();
    await eventStore.fetchTags();

    // Restored original category handling logic
    categories.value = categoryStore.categories.map(category => ({
      id: category.id,
      title: category.title || "Uncategorized"
    }));

    tags.value = eventStore.tags;
  });

  // Format Time Helper
  const formatTime = (time) => {
    if (!time) return "00:00";
    const parts = time.split(":");
    const hours = parts[0].padStart(2, "0");
    const minutes = parts[1]?.padStart(2, "0") || "00";
    return `${hours}:${minutes}`;
  };

  // Add a new tag
  const addTag = async () => {
    if (!newTag.name.trim()) {
      alert("Tag name is required.");
      return;
    }

    const newTagData = { id: tags.value.length + 1, ...newTag };

    await eventStore.addTag(newTagData);
    newTag.name = "";
    newTag.color = "#000000";
  };

  // Create Event
  const createEvent = async () => {
    submitted.value = true;

    if (!form.title || !form.category || !form.startDate || !form.endDate || !form.startTime || !form.endTime) {
      alert("Please fill out the required fields.");
      return;
    }

    const payload = { ...form,
     tags: selectedTags.value,
     title: form.title,
     description: form.description,
     category_id: form.category, // Ensure correct category mapping
     startDate: form.startDate,
     endDate: form.endDate,
     startTime: formatTime(form.startTime),
     endTime: formatTime(form.endTime),
     image: form.image || "https://example.com/default-image.jpg",
     archived: false, };
    await eventStore.createEvent(payload);

    // Reset form
    Object.assign(form, {
      title: "",
      description: "",
      category: null,
      startDate: "",
      endDate: "",
      startTime: "",
      endTime: "",
      image: "https://primefaces.org/cdn/primeng/images/demo/product/bamboo-watch.jpg",
      archived: false,
      tags: [],
    });

    selectedTags.value = [];
    submitted.value = false;
    alert("Event created successfully!");

    // Navigate to Event List
    router.get(route('event.list')); // Redirect to EventList.vue
  };
  </script>

  <style scoped>
  .tag-creation {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 10px;
  }
  </style>
