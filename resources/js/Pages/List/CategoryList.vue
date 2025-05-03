<script>
import { defineComponent, ref, onMounted, computed, watch } from "vue";
import axios from "axios";

export default defineComponent({
  name: "CategoryList",
  setup() {
    const categories = ref([]);
    const tags = ref([]);
    const events = ref([]);
    const isEditModalVisible = ref(false);
    const isCreateModalVisible = ref(false);
    const selectedItem = ref(null);
    const newItem = ref({ title: "", description: "", color: "#800080" });
    const showTags = ref(localStorage.getItem("showTags") === "true");

    const normalizedEvents = computed(() => {
  return events.value.map(event => ({
    ...event,
    tags: Array.isArray(event.tags)
      ? event.tags.map(tag => typeof tag === 'object' ? tag.id : tag)
      : []
  }));
});

    // Fetch data on mount
    onMounted(async () => {
      await fetchData();
    });

    const fetchData = async () => {
      try {
        const [categoriesResponse, tagsResponse, eventsResponse] = await Promise.all([
          axios.get("http://localhost:3000/category"),
          axios.get("http://localhost:3000/tags"),
          axios.get("http://localhost:3000/events"),
        ]);
        categories.value = categoriesResponse.data;
        tags.value = tagsResponse.data;
        events.value = eventsResponse.data;
      } catch (error) {
        console.error("Error fetching data:", error);
      }
    };

    // Check if an item is in use
    const isItemInUse = (id) => {
  if (showTags.value) {
    return normalizedEvents.value.some(event =>
      Array.isArray(event.tags) ? event.tags.includes(id) : false
    );
  } else {
    return normalizedEvents.value.some(event =>
      event.category_id === id && event.archived === false
    );
  }
};

    // Open Edit Modal
    const openEditModal = (item) => {
      selectedItem.value = { ...item };
      isEditModalVisible.value = true;
    };

    // Save Edited Item
    const saveEditedItem = async () => {
      if (!selectedItem.value) return;

      try {
        const endpoint = showTags.value ? 'tags' : 'category';
        await axios.put(
          `http://localhost:3000/${endpoint}/${selectedItem.value.id}`,
          selectedItem.value
        );

        // Update the list without reloading
        const list = showTags.value ? tags : categories;
        const index = list.value.findIndex(c => c.id === selectedItem.value.id);
        if (index !== -1) list.value[index] = { ...selectedItem.value };

        isEditModalVisible.value = false;
        alert(`${showTags.value ? 'Tag' : 'Category'} updated successfully!`);
      } catch (error) {
        console.error("Error updating item:", error);
        alert(`Failed to update the ${showTags.value ? 'tag' : 'category'}.`);
      }
    };

    // Open Create Modal
    const openCreateModal = () => {
      newItem.value = showTags.value
        ? { name: "", color: "#800080" }
        : { title: "", description: "" };
      isCreateModalVisible.value = true;
    };

    // Create New Item
    const createItem = async () => {
      const endpoint = showTags.value ? 'tags' : 'category';
      const requiredField = showTags.value ? 'name' : 'title';

      if (!newItem.value[requiredField]?.trim()) {
        alert(`${showTags.value ? 'Tag' : 'Category'} name is required.`);
        return;
      }

      try {
        const response = await axios.post(
          `http://localhost:3000/${endpoint}`,
          newItem.value
        );

        (showTags.value ? tags : categories).value.push(response.data);
        isCreateModalVisible.value = false;
        alert(`${showTags.value ? 'Tag' : 'Category'} created successfully!`);
      } catch (error) {
        console.error("Error creating item:", error);
        alert(`Failed to create the ${showTags.value ? 'tag' : 'category'}.`);
      }
    };

    // Delete Item
    const deleteItem = async (id) => {
      if (!confirm(`Are you sure you want to delete this ${showTags.value ? 'tag' : 'category'}?`)) return;

      try {
        const endpoint = showTags.value ? 'tags' : 'category';
        await axios.delete(`http://localhost:3000/${endpoint}/${id}`);

        const list = showTags.value ? tags : categories;
        list.value = list.value.filter(c => c.id !== id);
        alert(`${showTags.value ? 'Tag' : 'Category'} deleted successfully!`);
      } catch (error) {
        console.error("Error deleting item:", error);
        alert(`Failed to delete the ${showTags.value ? 'tag' : 'category'}.`);
      }
    };

    // Toggle between categories and tags
    const toggleView = () => {
      showTags.value = !showTags.value;
      localStorage.setItem("showTags", showTags.value);
    };

    watch(showTags, (newVal) => {
        localStorage.setItem("showTags", newVal);
    });

    return {
      categories,
      tags,
      showTags,
      openEditModal,
      saveEditedItem,
      deleteItem,
      isEditModalVisible,
      selectedItem,
      isCreateModalVisible,
      openCreateModal,
      createItem,
      newItem,
      isItemInUse,
      toggleView,
    };
  },
});
</script>

<template>
  <div class="category-list-container">
    <div class="category-header">
      <h1 class="title">{{ showTags ? 'Tag' : 'Category' }} List</h1>
      <div class="flex gap-2">
        <Button
          :label="showTags ? 'Show Categories' : 'Show Tags'"
          icon="pi pi-tags"
          class="p-button-secondary"
          @click="toggleView"
        />
        <button class="create-button" @click="openCreateModal">Create Bracket</button>
      </div>
    </div>

    <DataTable :value="showTags ? tags : categories" class="p-datatable-striped">
      <Column field="title" :header="showTags ? 'Tag Name' : 'Category Name'" style="width:30%;" sortable>
        <template #body="{ data }">
          <div v-if="showTags" class="flex items-center gap-2">
            <span
              class="inline-block w-4 h-4 rounded-full"
              :style="{ backgroundColor: data.color }"
            ></span>
            {{ data.name }}
          </div>
          <span v-else>{{ data.title }}</span>
        </template>
      </Column>

      <Column field="description" header="Description" style="width:40%;" v-if="!showTags">
        <template #body="{ data }">
          {{ data.description || "No description available" }}
        </template>
      </Column>

      <Column field="color" header="Color" style="width:20%;" v-if="showTags">
        <template #body="{ data }">
          {{ data.color }}
        </template>
      </Column>

      <Column header="Actions" style="width:10%;" body-class="text-center">
        <template #body="{ data }">
          <div class="action-buttons">
            <Button
              icon="pi pi-pen-to-square"
              class="p-button-rounded p-button-info"
              @click="openEditModal(data)"
              v-tooltip.top="`Edit ${showTags ? 'Tag' : 'Category'}`"
            />
            <Button
              icon="pi pi-trash"
              class="p-button-rounded p-button-danger"
              @click="deleteItem(data.id)"
              :disabled="isItemInUse(data.id)"
              v-tooltip.top="isItemInUse(data.id) ? `${showTags ? 'Tag' : 'Category'} is in use and cannot be deleted` : `Delete ${showTags ? 'Tag' : 'Category'}`"
            />
          </div>
        </template>
      </Column>
    </DataTable>

    <!-- Edit Modal -->
    <Dialog v-model:visible="isEditModalVisible" modal :header="`Edit ${showTags ? 'Tag' : 'Category'}`" :style="{ width: '50vw' }">
      <div class="p-fluid">
        <div class="p-field">
          <label for="title">{{ showTags ? 'Tag' : 'Category' }} Name</label>
          <InputText
            id="title"
            v-model="selectedItem[showTags ? 'name' : 'title']"
            :placeholder="`Enter ${showTags ? 'tag' : 'category'} name`"
          />
        </div>

        <div class="p-field" v-if="showTags">
          <label for="color">Color</label>
          <input
            id="color"
            v-model="selectedItem.color"
            type="color"
            placeholder="Enter color code"
          />
        </div>

        <div class="p-field" v-if="!showTags">
          <label for="description">Description</label>
          <Textarea
            id="description"
            v-model="selectedItem.description"
            rows="4"
            :placeholder="`Enter ${showTags ? 'tag' : 'category'} description`"
            autoResize
          />
        </div>
      </div>

      <template #footer>
        <Button label="Cancel" icon="pi pi-times" class="p-button-text" @click="isEditModalVisible = false" />
        <Button label="Save Changes" icon="pi pi-check" class="p-button-primary" @click="saveEditedItem" />
      </template>
    </Dialog>

    <!-- Create Modal -->
    <Dialog v-model:visible="isCreateModalVisible" modal :header="`Create ${showTags ? 'Tag' : 'Category'}`" :style="{ width: '50vw' }">
      <div class="p-fluid">
        <div class="p-field">
          <label for="newTitle">{{ showTags ? 'Tag' : 'Category' }} Name</label>
          <InputText
            id="newTitle"
            v-model="newItem[showTags ? 'name' : 'title']"
            :placeholder="`Enter ${showTags ? 'tag' : 'category'} name`"
          />
        </div>

        <div class="p-field" v-if="showTags">
          <label for="newColor">Color</label>
          <input
            id="newColor"
            v-model="newItem.color"
            type="color"
            placeholder="Enter color code"
          />
        </div>

        <div class="p-field" v-if="!showTags">
          <label for="newDescription">Description</label>
          <Textarea
            id="newDescription"
            v-model="newItem.description"
            rows="4"
            :placeholder="`Enter ${showTags ? 'tag' : 'category'} description`"
            autoResize
          />
        </div>
      </div>

      <template #footer>
        <Button label="Cancel" icon="pi pi-times" class="p-button-text" @click="isCreateModalVisible = false" />
        <Button :label="`Create ${showTags ? 'Tag' : 'Category'}`" icon="pi pi-check" class="p-button-primary" @click="createItem" />
      </template>
    </Dialog>
  </div>
</template>

<style scoped>
.category-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
}

.action-buttons {
  display: flex;
  gap: 0.5rem;
  justify-content: center;
}
</style>
