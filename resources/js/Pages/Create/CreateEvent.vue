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

            <!-- Venue -->
            <div class="p-field">
            <label for="venue">Venue</label>
            <InputText
                id="venue"
                v-model="form.venue"
                placeholder="Enter venue (e.g., Conference Room A)"
            />
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
                <input type = "time"
                  id="startTime"
                  v-model="form.startTime"
                  placeholder="HH:mm"
                  @blur="form.startTime = formatTime(form.startTime)"
                  :class="{ 'p-invalid': !form.startTime && submitted }"
                />
              </div>
              <div class="p-col-6">
                <label for="endTime">End Time</label>
                <input type= "time"
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

    <Dialog v-model:visible="successModal" header="Success" modal :closable="false">
    <div class="p-d-flex p-ai-center p-jc-center">
        <i class="pi pi-check-circle" style="font-size: 2rem; color: green;"></i>
        <p class="p-ml-3">Event created successfully!</p>
    </div>

    <template #footer>
        <Button label="OK" icon="pi pi-times" class="p-button-text" @click="successModal = false" />
        <Button label="Go to Events" icon="pi pi-list" class="p-button-success" @click="router.get(route('event.list'))" />
    </template>
    </Dialog>

  </template>

  <script setup>
  import { useCategoryStore } from "@/stores/categoryStore";
  import { useEventStore } from "@/stores/eventStore";
  import { ref, reactive, onMounted } from "vue";
  import { router } from '@inertiajs/vue3';
  import { format } from 'date-fns';

  // Stores
  const categoryStore = useCategoryStore();
  const eventStore = useEventStore();

  // Categories & Tags
  const categories = ref([]);
  const tags = ref([]);
  const selectedTags = ref([]);
  const successModal = ref(false);

  // Form data
  const form = reactive({
    title: "",
    venue: "",
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

  // Create Event
  const createEvent = async () => {
    submitted.value = true;

    if (!form.title || !form.category || !form.startDate || !form.startTime) {
      alert("Please fill out the required fields.");
      return;
    }

      // Format dates before creating payload
    const formattedStartDate = form.startDate ? format(new Date(form.startDate), 'MMM-dd-yyyy') : null;
    const formattedEndDate = form.endDate ? format(new Date(form.endDate), 'MMM-dd-yyyy') : null;

    const payload = { ...form,
     tags: selectedTags.value,
     venue: form.venue,
     title: form.title,
     description: form.description,
     category_id: form.category,
     startDate: formatDate(form.startDate),
     endDate: formatDate(form.endDate),
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
    successModal.value = true;

  };

  function formatDate(date) {
  const d = new Date(date);
  const options = { year: 'numeric', month: 'short', day: '2-digit' };
  const parts = d.toLocaleDateString('en-US', options).replace(',', '').split(' ');
  return `${parts[0]}-${parts[1]}-${parts[2]}`;
}

  </script>

  <style scoped>
  .tag-creation {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 10px;
  }
  </style>
