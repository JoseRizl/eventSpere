<script>
import { defineComponent, ref, onMounted, computed } from "vue";
import { router, usePage } from '@inertiajs/vue3';
import Toast from 'primevue/toast';
import { useToast } from '@/composables/useToast';
import LoadingSpinner from '@/Components/LoadingSpinner.vue';
import ConfirmationDialog from '@/Components/ConfirmationDialog.vue';
import SuccessDialog from '@/Components/SuccessDialog.vue';
import Skeleton from 'primevue/skeleton';

export default defineComponent({
  name: "CategoryList",
  components: {
    LoadingSpinner,
    ConfirmationDialog,
    SuccessDialog,
    Skeleton
  },
  setup() {
    const { toast, showSuccess, showError } = useToast();
    const { props, flash } = usePage();
    const categories = ref(props.categories || []);
    const tags = ref(props.tags || []);
    const events = ref(props.events || []);
    const isEditModalVisible = ref(false);
    const isCreateModalVisible = ref(false);
    const selectedItem = ref(null);
    const newItem = ref({ title: "", description: "", color: "#800080" });
    const showTags = ref(false); // Default to categories, will be updated on mount
    const searchQuery = ref("");
    const initialLoading = ref(true);
    const saving = ref(false);
    const showSaveConfirmDialog = ref(false);
    const successMessage = ref('');
    const showSuccessDialog = ref(false);
    const errorMessage = ref(null);
    const showErrorDialog = ref(false);
    const errorDialogMessage = ref('');
    const showDeleteConfirm = ref(false);
    const itemToDelete = ref(null);
    const showCreateConfirm = ref(false);
    const showEditConfirm = ref(false);

    const normalizedEvents = computed(() => {
      return events.value.map(event => ({
        ...event,
        tags: Array.isArray(event.tags)
          ? event.tags.map(tag => typeof tag === 'object' ? tag.id : tag)
          : []
      }));
    });

    // Add computed property for filtered items
    const filteredItems = computed(() => {
      const items = showTags.value ? tags.value : categories.value;
      if (!searchQuery.value) return items;

      const query = searchQuery.value.toLowerCase().trim();
      return items.filter(item => {
        if (showTags.value) {
          return item.name?.toLowerCase().includes(query) ||
                 item.color?.toLowerCase().includes(query);
        } else {
          return item.title?.toLowerCase().includes(query) ||
                 item.description?.toLowerCase().includes(query);
        }
      });
    });

    // Fetch data on mount
    onMounted(async () => {
      const urlParams = new URLSearchParams(window.location.search);
      const view = urlParams.get('view');
      showTags.value = view === 'tags';

      initialLoading.value = true;
      await fetchData();
      initialLoading.value = false;
    });

    const fetchData = async () => {
      try {
        await router.visit('/category-list', {
          preserveState: true,
          onSuccess: (page) => {
            categories.value = page.props.categories;
            tags.value = page.props.tags;
            events.value = page.props.events;
          }
        });
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
      saving.value = true;
      errorMessage.value = null;
      showErrorDialog.value = false;

      try {
        await router.put(`/categories/${selectedItem.value.id}`, selectedItem.value, {
          onSuccess: () => {
            // Update the list without reloading
            const list = showTags.value ? tags : categories;
            const index = list.value.findIndex(c => c.id === selectedItem.value.id);
            if (index !== -1) list.value[index] = { ...selectedItem.value };

            isEditModalVisible.value = false;
            successMessage.value = `${showTags.value ? 'Tag' : 'Category'} updated successfully!`;
            showSuccessDialog.value = true;
            showSuccess(`${showTags.value ? 'Tag' : 'Category'} updated successfully!`);
          },
          onError: (errors) => {
            errorMessage.value = errors.message || 'Failed to update item';
            showErrorDialog.value = true;
            errorDialogMessage.value = JSON.stringify(errors, null, 2);
          },
          onFinish: () => {
            saving.value = false;
          }
        });
      } catch (error) {
        console.error("Error updating item:", error);
        errorMessage.value = `Failed to update the ${showTags.value ? 'tag' : 'category'}.`;
        showErrorDialog.value = true;
        errorDialogMessage.value = error.message;
        saving.value = false;
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
      const requiredField = showTags.value ? 'name' : 'title';

      if (!newItem.value[requiredField]?.trim()) {
        showError(`${showTags.value ? 'Tag' : 'Category'} name is required.`);
        return;
      }

      saving.value = true;
      errorMessage.value = null;
      showErrorDialog.value = false;

      try {
        await router.post('/categories', newItem.value, {
          onSuccess: () => {
            isCreateModalVisible.value = false;
            // Reset the form
            newItem.value = showTags.value
              ? { name: "", color: "#800080" }
              : { title: "", description: "" };
            // Show success message
            successMessage.value = `${showTags.value ? 'Tag' : 'Category'} created successfully!`;
            showSuccessDialog.value = true;
            showSuccess(`${showTags.value ? 'Tag' : 'Category'} created successfully!`);
            // Fetch updated list
            fetchData();
          },
          onError: (errors) => {
            errorMessage.value = errors.message || 'Failed to create item';
            showErrorDialog.value = true;
            errorDialogMessage.value = JSON.stringify(errors, null, 2);
          },
          onFinish: () => {
            saving.value = false;
          }
        });
      } catch (error) {
        console.error("Error creating item:", error);
        errorMessage.value = `Failed to create the ${showTags.value ? 'tag' : 'category'}.`;
        showErrorDialog.value = true;
        errorDialogMessage.value = error.message;
        saving.value = false;
      }
    };

    // Delete Item
    const deleteItem = async (id) => {
      itemToDelete.value = id;
      showDeleteConfirm.value = true;
    };

    const confirmDelete = async () => {
      if (!itemToDelete.value) return;
      saving.value = true;
      errorMessage.value = null;
      showErrorDialog.value = false;

      try {
        await router.delete(`/categories/${itemToDelete.value}`, {
          data: { type: showTags.value ? 'tag' : 'category' },
          onSuccess: async (page) => {
            // Update the data from the response
            categories.value = page.props.categories;
            tags.value = page.props.tags;
            events.value = page.props.events;

            // Show success message after data is updated
            successMessage.value = `${showTags.value ? 'Tag' : 'Category'} deleted successfully!`;
            showSuccessDialog.value = true;
            showSuccess(`${showTags.value ? 'Tag' : 'Category'} deleted successfully!`);
          },
          onError: (errors) => {
            errorMessage.value = errors.message || 'Failed to delete item';
            showErrorDialog.value = true;
            errorDialogMessage.value = JSON.stringify(errors, null, 2);
          },
          onFinish: () => {
            saving.value = false;
            showDeleteConfirm.value = false;
            itemToDelete.value = null;
          }
        });
      } catch (error) {
        console.error("Error deleting item:", error);
        errorMessage.value = `Failed to delete the ${showTags.value ? 'tag' : 'category'}.`;
        showErrorDialog.value = true;
        errorDialogMessage.value = error.message;
        saving.value = false;
        showDeleteConfirm.value = false;
        itemToDelete.value = null;
      }
    };

    return {
      categories,
      tags,
      showTags,
      searchQuery,
      filteredItems,
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
      toast,
      saving,
      initialLoading,
      showSaveConfirmDialog,
      successMessage,
      showSuccessDialog,
      errorMessage,
      showErrorDialog,
      errorDialogMessage,
      showDeleteConfirm,
      itemToDelete,
      showCreateConfirm,
      showEditConfirm,
      confirmDelete,
    };
  },
});
</script>

<template>
  <div class="category-list-container">
    <Toast />
    <LoadingSpinner :show="saving" />
    <h1 class="title">{{ showTags ? 'Tags' : 'Category' }} List</h1>

    <div class="category-header">
      <div class="search-container">
        <div class="p-input-icon-left">
          <i class="pi pi-search" />
          <InputText
            v-model="searchQuery"
            :placeholder="`Search ${showTags ? 'tags' : 'categories'}...`"
            class="w-full"
          />
        </div>
      </div>
      <div class="flex gap-2">
        <button class="create-button" @click="openCreateModal">Create</button>
      </div>
    </div>

    <DataTable v-if="initialLoading" :value="Array(5).fill({})" class="p-datatable-striped">
        <Column :header="showTags ? 'Tag Name' : 'Category Name'" style="width:30%;"><template #body><Skeleton /></template></Column>
        <Column v-if="!showTags" header="Description" style="width:40%;"><template #body><Skeleton /></template></Column>
        <Column v-if="showTags" header="Color" style="width:20%;"><template #body><Skeleton /></template></Column>
        <Column header="Actions" style="width:10%;" body-class="text-center"><template #body><div class="flex justify-center gap-2"><Skeleton shape="circle" size="2rem" /><Skeleton shape="circle" size="2rem" /></div></template></Column>
    </DataTable>

    <div v-else-if="filteredItems.length === 0" class="no-results-message">
      <div v-if="searchQuery">
        <div class="icon-and-title">
          <i class="pi pi-search" style="font-size: 1.5rem; color: #007bff; margin-right: 10px;"></i>
          <h2 class="no-results-title">No {{ showTags ? 'Tags' : 'Categories' }} Found</h2>
        </div>
        <p class="no-results-text">No {{ showTags ? 'tags' : 'categories' }} match your search criteria. Try adjusting your search terms.</p>
      </div>
      <div v-else>
        <div class="icon-and-title">
          <i class="pi pi-tags" style="font-size: 1.5rem; color: #6c757d; margin-right: 10px;"></i>
          <h2 class="no-results-title">No {{ showTags ? 'Tags' : 'Categories' }} Available</h2>
        </div>
        <p class="no-results-text">There are currently no items to display. Create a new one to get started.</p>
      </div>
    </div>

    <DataTable v-else :value="filteredItems" class="p-datatable-striped">
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

    <!-- Success Message Dialog -->
    <SuccessDialog
      v-model:show="showSuccessDialog"
      :message="successMessage"
    />
    <!-- Error Dialog -->
    <Dialog
      v-model:visible="showErrorDialog"
      modal
      header="Error"
      :style="{ width: '400px', zIndex: 9998 }"
    >
      <div class="flex items-center gap-3">
        <i class="pi pi-exclamation-triangle text-red-500 text-2xl"></i>
        <span>{{ errorDialogMessage }}</span>
      </div>
      <template #footer>
        <Button
          label="OK"
          icon="pi pi-check"
          @click="showErrorDialog = false"
          class="p-button-text"
        />
      </template>
    </Dialog>

    <!-- Delete Confirmation Dialog -->
    <ConfirmationDialog
      v-model:show="showDeleteConfirm"
      :title="`Delete ${showTags ? 'Tag' : 'Category'}?`"
      :message="`Are you sure you want to delete this ${showTags ? 'tag' : 'category'}?`"
      @confirm="confirmDelete"
    />
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

.search-container {
  display: flex;
  justify-content: flex-start;
  width: 100%;
  max-width: 400px;
}

.search-container .p-input-icon-left {
  position: relative;
  width: 100%;
}

.search-container .p-input-icon-left i {
  position: absolute;
  left: 0.75rem;
  top: 50%;
  transform: translateY(-50%);
  color: #6c757d;
}

.search-container .p-input-icon-left .p-inputtext {
  width: 100%;
  padding-left: 2.5rem;
}

.create-button {
  background: #7e0bc1;
  color: white;
  padding: 10px 15px;
  border: none;
  border-radius: 5px;
  cursor: pointer;
  font-size: 1rem;
  font-weight: bold;
  margin: 0;
  height: 40px;
}

.create-button:hover {
  background-color: #6800b3e9;
}

.no-results-message {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  margin: 20px 0;
  padding: 20px;
  background-color: #f9f9f9;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.icon-and-title {
  display: flex;
  align-items: center;
  margin-bottom: 10px;
}

.no-results-title {
  color: #333;
  margin: 0;
  font-size: 1.5rem;
  font-weight: bold;
}

.no-results-text {
  color: #555;
  margin: 5px 0 0 0;
}

@media (max-width: 768px) {
  .search-container .p-input-icon-left {
    max-width: 100%;
  }
}
</style>
