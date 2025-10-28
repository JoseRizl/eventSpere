<script>
import { defineComponent, ref, onMounted, computed } from "vue";
import { router, usePage } from "@inertiajs/vue3";
import { useToast } from "@/composables/useToast";

export default defineComponent({
  name: "CategoryList",
  setup() {
    const { toast, showSuccess, showError } = useToast();
    const { props } = usePage();
    const categories = ref(props.categories || []);
    const tags = ref(props.tags || []);
    const event_tags = ref(props.event_tags || []);
    const events = ref(props.events || []);
    const isEditModalVisible = ref(false);
    const isCreateModalVisible = ref(false);
    const selectedItem = ref(null);
    const newItem = ref({ title: "", description: "", name: "", category_id: null });
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
    const showArchiveConfirm = ref(false);
    const itemToArchive = ref(null);
    const showCreateConfirm = ref(false);
    const showEditConfirm = ref(false);
    const showUsageDialog = ref(false);
    const usageDetails = ref({ events: [], tags: [] });
    const isDetailsModalVisible = ref(false);
    const selectedTagForDetails = ref(null);
    const currentItemName = ref('');

    const currentPageReportTemplate = computed(() => `Showing {first} to {last} of {totalRecords} ${showTags.value ? 'tags' : 'categories'}`);

    const normalizedEvents = computed(() => {
      return events.value.map(event => ({
        ...event
      }));
    });

    const tagUsageCount = computed(() => event_tags.value.reduce((acc, item) => { acc[item.tag_id] = (acc[item.tag_id] || 0) + 1; return acc; }, {}));


    const categoryMap = computed(() => {
      return categories.value.reduce((map, category) => {
        map[category.id] = category.title;
        return map;
      }, {});
    });

    const categoryUsageCount = computed(() => {
      const usageMap = {};
      // Initialize counts for all categories
      categories.value.forEach(category => {
        usageMap[category.id] = 0;
      });
      // Count non-archived events for each category
      normalizedEvents.value.forEach(event => {
        if (event.category_id && !event.archived && usageMap.hasOwnProperty(event.category_id)) {
          usageMap[event.category_id]++;
        }
      });
      return usageMap;
    });

    // Add computed property for filtered items
    const filteredItems = computed(() => {
      const items = showTags.value
        ? tags.value.filter(tag => !tag.archived)
        : categories.value;
      if (!searchQuery.value) return items;

      const query = searchQuery.value.toLowerCase().trim();
      return items.filter(item => {
        if (showTags.value) {
          return item.name?.toLowerCase().includes(query);
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
        const currentView = showTags.value ? 'tags' : 'categories';
        await router.visit(route('category.list', { view: currentView }), {
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
        const eventUsingCategory = normalizedEvents.value.some(event =>
          event.category_id === id && event.archived === false
        );
        const tagUsingCategory = tags.value.some(tag => tag.category_id == id);

        return eventUsingCategory || tagUsingCategory;
      }
    };

    const getUsageDetails = (id) => {
      const usage = { events: [], tags: [] };
      if (showTags.value) {
        // A tag is in use if an event uses it.
        usage.events = normalizedEvents.value.filter(event =>
          Array.isArray(event.tags) ? event.tags.includes(id) : false
        );
      } else {
        // A category is in use if an event uses it OR a tag uses it.
        usage.events = normalizedEvents.value.filter(event =>
          event.category_id === id && event.archived === false
        );
        usage.tags = tags.value.filter(tag => tag.category_id == id);
      }
      return usage;
    };

    const openUsageDialog = (item) => {
      usageDetails.value = getUsageDetails(item.id);
      currentItemName.value = item.name || item.title;
      showUsageDialog.value = true;
    };

    const openDetailsModal = (tag) => {
      selectedTagForDetails.value = tag;
      usageDetails.value = getUsageDetails(tag.id);
      isDetailsModalVisible.value = true;
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
            showSuccess(successMessage.value);
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
        ? { name: "", description: "", category_id: null }
        : { title: "", description: "", name: "", category_id: null };
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
        const payload = showTags.value
          ? {
              name: newItem.value.name,
              description: newItem.value.description,
              category_id: newItem.value.category_id,
            }
          : { title: newItem.value.title, description: newItem.value.description };
        await router.post('/categories', payload, {
          onSuccess: () => {
            isCreateModalVisible.value = false;
            // Reset the form
            newItem.value = showTags.value
              ? { name: "", description: "", category_id: null }
              : { title: "", description: "", name: "", category_id: null };
            // Show success message
            successMessage.value = `${showTags.value ? 'Tag' : 'Category'} created successfully!`;
            showSuccessDialog.value = true;
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

    // Archive Tag
    const archiveItem = (item) => {
      itemToArchive.value = item;
      showArchiveConfirm.value = true;
    };

    const confirmArchive = async () => {
      if (!itemToArchive.value) return;
      saving.value = true;

      try {
        await router.put(route('tags.archive', { id: itemToArchive.value.id }), {}, {
          onSuccess: (page) => {
            // Manually update the tags list from props
            tags.value = page.props.tags;
            successMessage.value = 'Tag archived successfully!';
            showSuccessDialog.value = true;
            showSuccess('Tag archived successfully!');
          },
          onError: (errors) => {
            const message = errors.message || 'Failed to archive the tag.';
            showError(message);
            errorMessage.value = message;
            errorDialogMessage.value = JSON.stringify(errors, null, 2);
            showErrorDialog.value = true;
          },
          onFinish: () => {
            saving.value = false;
            showArchiveConfirm.value = false;
            itemToArchive.value = null;
          }
        });
      } catch (error) {
        showError('An unexpected error occurred while archiving the tag.');
        saving.value = false;
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
      archiveItem,
      confirmArchive,
      showArchiveConfirm,
      currentPageReportTemplate,
      openUsageDialog,
      showUsageDialog,
      usageDetails,
      categoryMap,
      categoryUsageCount,
      openDetailsModal,
      isDetailsModalVisible,
      selectedTagForDetails,
      tagUsageCount,
      currentItemName,
    };
  },
});
</script>

<template>
  <div class="category-list-container">
    <!-- <Toast /> -->
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
        <button class="create-button" @click="openCreateModal">{{ showTags ? 'Create Tag' : 'Create Category' }}</button>
      </div>
    </div>

    <DataTable v-if="initialLoading" :value="Array(5).fill({})" class="p-datatable-striped">
        <Column :header="showTags ? 'Tag Name' : 'Category Name'" style="width:30%;" :headerStyle="{ 'background-color': '#0077B3', 'color': 'white', 'font-weight': 'bold', 'text-transform': 'uppercase' }"><template #body><Skeleton /></template></Column>
        <Column v-if="!showTags" header="Description" style="width:40%;" :headerStyle="{ 'background-color': '#0077B3', 'color': 'white', 'font-weight': 'bold', 'text-transform': 'uppercase' }"><template #body><Skeleton /></template></Column>
        <Column v-if="showTags" header="Color" style="width:20%;" :headerStyle="{ 'background-color': '#0077B3', 'color': 'white', 'font-weight': 'bold', 'text-transform': 'uppercase' }"><template #body><Skeleton /></template></Column>
        <Column header="Actions" style="width:10%;" body-class="text-center" :headerStyle="{ 'background-color': '#0077B3', 'color': 'white', 'font-weight': 'bold', 'text-transform': 'uppercase' }"><template #body><div class="flex justify-center gap-2"><Skeleton shape="circle" size="2rem" /><Skeleton shape="circle" size="2rem" /></div></template></Column>
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

    <DataTable
      v-else
      :value="filteredItems"
      class="p-datatable-striped" showGridlines
      paginator :rows="10" :rowsPerPageOptions="[10, 20, 50]"
      paginatorTemplate="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink CurrentPageReport RowsPerPageDropdown"
      :currentPageReportTemplate="currentPageReportTemplate" :headerStyle="{ 'background-color': '#004A99', 'color': 'white', 'font-weight': 'bold', 'text-transform': 'uppercase' }">
      <Column :field="showTags ? 'name' : 'title'" :header="showTags ? 'Tag Name' : 'Category Name'" style="width:30%;" sortable :headerStyle="{ 'background-color': '#004A99', 'color': 'white', 'font-weight': 'bold', 'text-transform': 'uppercase' }">
        <template #body="{ data }">
          <div class="datatable-content">
            {{ showTags ? data.name : data.title }}
          </div>
        </template>
      </Column>

      <Column v-if="showTags" field="category_id" header="Category" style="width:20%;" sortable :headerStyle="{ 'background-color': '#004A99', 'color': 'white', 'font-weight': 'bold', 'text-transform': 'uppercase' }">
        <template #body="{ data }">
          <div class="datatable-content">
            <span>{{ categoryMap[data.category_id] || '' }}</span>
          </div>
        </template>
      </Column>

      <Column v-if="!showTags" field="description" header="Description" style="width:30%;" sortable :headerStyle="{ 'background-color': '#004A99', 'color': 'white', 'font-weight': 'bold', 'text-transform': 'uppercase' }">
        <template #body="{ data }">
          <div class="datatable-content">
            <span>{{ data.description || "" }}</span>
          </div>
        </template>
      </Column>

      <Column v-if="!showTags" header="Events Using" style="width:10%;" sortable :headerStyle="{ 'background-color': '#004A99', 'color': 'white', 'font-weight': 'bold', 'text-transform': 'uppercase' }">
        <template #body="{ data }">
          <div class="datatable-content text-center">
            <span class="inline-flex items-center justify-center px-2 py-1 text-xs font-bold leading-none text-indigo-100 bg-indigo-700 rounded-full">
              {{ categoryUsageCount[data.id] || 0 }}
            </span>
          </div>
        </template>
      </Column>

      <Column header="Actions" style="width:10%;" body-class="text-center" :headerStyle="{ 'background-color': '#004A99', 'color': 'white', 'font-weight': 'bold', 'text-transform': 'uppercase' }">
        <template #body="{ data }">
          <div class="action-buttons">
            <Button
              v-if="showTags"
              icon="pi pi-eye"
              class="p-button-rounded p-button-text action-btn-success"
              @click="openDetailsModal(data)"
              v-tooltip.top="`View Details`" />
            <Button
              icon="pi pi-pen-to-square"
              class="p-button-rounded p-button-text action-btn-info"
              @click="openEditModal(data)"
              v-tooltip.top="`Edit ${showTags ? 'Tag' : 'Category'}`"
            />
            <template v-if="!isItemInUse(data.id)">
                <Button
                    v-if="!showTags"
                    icon="pi pi-trash"
                    class="p-button-rounded p-button-text action-btn-danger"
                    @click="deleteItem(data.id)"
                    v-tooltip.top="`Delete Category`" />
                <Button v-if="showTags" icon="pi pi-folder" class="p-button-rounded p-button-text action-btn-warning" @click="archiveItem(data)" v-tooltip.top="'Archive Tag'"/>
            </template>
            <Button v-else
              icon="pi pi-info-circle"
              class="p-button-rounded p-button-text p-button-help"
              @click="openUsageDialog(data)"
              v-tooltip.top="`${showTags ? 'Tag' : 'Category'} is in use and cannot be archived. Click to see details.`"
            />
          </div>
        </template>
      </Column>
    </DataTable>

    <!-- Edit Modal -->
    <Dialog v-model:visible="isEditModalVisible" modal :header="`Edit ${showTags ? 'Tag' : 'Category'}`" :style="{ width: '50vw' }">
      <div class="p-fluid">
        <div class="p-field">
          <label for="title">{{ showTags ? 'Tag' : 'Category' }} Name <span style="color: red;">*</span></label>
          <InputText
            id="title"
            v-model="selectedItem[showTags ? 'name' : 'title']"
            :placeholder="`Enter ${showTags ? 'tag' : 'category'} name`"
          />
        </div>

        <div class="p-field" v-if="showTags">
          <label for="tagCategory">Category <span style="color: red;">*</span></label>
          <Select id="tagCategory" v-model="selectedItem.category_id" :options="categories" optionLabel="title" optionValue="id" placeholder="Select a category" class="w-full" />
        </div>

        <div class="p-field">
          <label for="description">Description <span style="color: #6c757d; font-weight: normal;">(Optional)</span></label>
          <Textarea
            id="description"
            v-model="selectedItem.description"
            rows="4"
            :placeholder="`Enter description`"
            autoResize
          />
        </div>
      </div>

      <template #footer>
        <button class="modal-button-secondary sm:p-button-sm" @click="isEditModalVisible = false" :disabled="saving">Cancel</button>
        <button class="modal-button-primary sm:p-button-sm" @click="saveEditedItem" :disabled="saving">
          <i v-if="saving" class="pi pi-spin pi-spinner mr-2"></i>
          {{ saving ? 'Saving...' : 'Save Changes' }}
        </button>
      </template>
    </Dialog>

    <!-- Create Modal -->
    <Dialog v-model:visible="isCreateModalVisible" modal :header="`Create ${showTags ? 'Tag' : 'Category'}`" :style="{ width: '50vw' }">
      <div class="p-fluid">
        <div class="p-field">
          <label for="newTitle">{{ showTags ? 'Tag' : 'Category' }} Name <span style="color: red;">*</span></label>
          <InputText
            id="newTitle"
            v-model="newItem[showTags ? 'name' : 'title']"
            :placeholder="`Enter ${showTags ? 'tag' : 'category'} name`"
          />
        </div>

        <div class="p-field" v-if="showTags">
          <label for="newTagCategory">Category <span style="color: red;">*</span></label>
          <Select id="newTagCategory" v-model="newItem.category_id" :options="categories" optionLabel="title" optionValue="id" placeholder="Select a category" class="w-full" />
        </div>

        <div class="p-field">
          <label for="newDescription">Description <span style="color: #6c757d; font-weight: normal;">(Optional)</span></label>
          <Textarea
            id="newDescription"
            v-model="newItem.description"
            rows="4"
            :placeholder="`Enter description`"
            autoResize
          />
        </div>
      </div>

      <template #footer>
        <button class="modal-button-secondary sm:p-button-sm" @click="isCreateModalVisible = false" :disabled="saving">Cancel</button>
        <button class="modal-button-primary sm:p-button-sm" @click="createItem" :disabled="saving">
          <i v-if="saving" class="pi pi-spin pi-spinner mr-2"></i>
          {{ saving ? 'Creating...' : `Create ${showTags ? 'Tag' : 'Category'}` }}
        </button>
      </template>
    </Dialog>

    <!-- Tag Details Modal -->
    <Dialog v-if="selectedTagForDetails" v-model:visible="isDetailsModalVisible" modal header="Tag Details" :style="{ width: '450px' }" :breakpoints="{ '960px': '75vw', '640px': '90vw' }">
        <div class="p-6">
            <div class="flex flex-col items-center text-center">
                <div class="text-2xl font-bold text-gray-800 mb-2">{{ selectedTagForDetails.name }}</div>
                <div class="text-sm text-gray-500 mb-4">{{ categoryMap[selectedTagForDetails.category_id] || 'Uncategorized' }}</div>
            </div>

            <div class="mt-4 border-t border-gray-200 pt-4">
                <div class="grid grid-cols-1 gap-4">
                    <div>
                        <h4 class="text-sm font-medium text-gray-500 mb-1">Description</h4>
                        <p class="text-base text-gray-700">{{ selectedTagForDetails.description || 'No description provided.' }}</p>
                    </div>

                    <div v-if="usageDetails.events.length > 0">
                        <h4 class="text-sm font-medium text-gray-500 mb-2">Used in {{ usageDetails.events.length }} Event(s):</h4>
                        <ul class="list-disc pl-5 space-y-1 text-sm max-h-40 overflow-y-auto">
                            <li v-for="event in usageDetails.events" :key="`event-detail-${event.id}`">
                                <a :href="route('event.details', { id: event.id })" target="_blank" class="text-blue-600 hover:underline">
                                    {{ event.title }}
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div v-else>
                        <h4 class="text-sm font-medium text-gray-500 mb-1">Usage</h4>
                        <p class="text-base text-gray-700">This tag is not currently used in any events.</p>
                    </div>
                </div>
            </div>
        </div>
        <template #footer>
            <Button
                label="Close"
                icon="pi pi-times"
                @click="isDetailsModalVisible = false"
                class="p-button-text" />
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
      confirmButtonClass="modal-button-danger"
      @confirm="confirmDelete"
    />

    <ConfirmationDialog v-model:show="showArchiveConfirm" title="Archive Tag?" :message="`Are you sure you want to archive this tag?`" confirmText="Yes, Archive" @confirm="confirmArchive"
    />

    <ConfirmationDialog v-model:show="showArchiveConfirm" title="Archive Tag?" :message="`Are you sure you want to archive this tag?`" confirmText="Yes, Archive" @confirm="confirmArchive"
    />

    <!-- Usage Details Dialog -->
    <Dialog v-model:visible="showUsageDialog" modal :header="`${showTags ? 'Tag' : 'Category'} In Use`" :style="{ width: '450px' }">
        <div class="p-fluid">
            <p class="mb-4">
                The {{ showTags ? 'tag' : 'category' }} <strong>'{{ currentItemName }}'</strong> cannot be deleted because it is currently in use by the following items:
            </p>

            <div v-if="usageDetails.events.length > 0" class="mb-4">
                <h4 class="font-semibold mb-2">Events:</h4>
                <ul class="list-disc pl-5 space-y-1 text-sm">
                    <li v-for="event in usageDetails.events" :key="`event-${event.id}`">
                        <a :href="route('event.details', { id: event.id })" target="_blank" class="text-blue-600 hover:underline">{{ event.title }}</a>
                    </li>
                </ul>
            </div>

            <div v-if="usageDetails.tags.length > 0">
                <h4 class="font-semibold mb-2">Tags:</h4>
                <ul class="list-disc pl-5 space-y-1 text-sm">
                    <li v-for="tag in usageDetails.tags" :key="`tag-${tag.id}`" class="flex items-center gap-2">
                        <span class="inline-block w-3 h-3 rounded-full" :style="{ backgroundColor: tag.color }"></span>
                        <span>{{ tag.name }}</span>
                    </li>
                </ul>
            </div>
        </div>
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
