<script setup>
import InputText from 'primevue/inputtext';
import Button from 'primevue/button';
import IconField from 'primevue/iconfield';
import InputIcon from 'primevue/inputicon';

const props = defineProps({
  searchQuery: {
    type: String,
    required: true,
  },
  placeholder: {
    type: String,
    default: 'Search...',
  },
  showDateFilter: {
    type: Boolean,
    default: false,
  },
  isDateFilterActive: {
    type: Boolean,
    default: false,
  },
  showClearButton: {
    type: Boolean,
    default: false,
  }
});

const emit = defineEmits(['update:searchQuery', 'toggle-date-filter', 'clear-filters']);

const onInput = (event) => {
  emit('update:searchQuery', event.target.value);
};
</script>

<template>
  <div class="w-full max-w-lg">
    <IconField iconPosition="right">
      <InputIcon class="pi pi-search"></InputIcon>
      <InputText :value="searchQuery" @input="onInput" :placeholder="placeholder" class="w-full" />
      <div v-if="showDateFilter || showClearButton" class="absolute top-1/2 right-2 -translate-y-1/2 flex items-center gap-1">
        <Button v-if="showDateFilter" icon="pi pi-calendar" class="p-button-text text-gray-500 hover:bg-gray-200 !w-8 !h-8" @click="$emit('toggle-date-filter')" :class="{ 'text-purple-600': isDateFilterActive }" v-tooltip.top="'Filter by date'" />
        <Button v-if="showClearButton" icon="pi pi-times" class="p-button-text p-button-danger !w-8 !h-8" @click="$emit('clear-filters')" v-tooltip.top="'Clear All Filters'" />
      </div>
    </IconField>
  </div>
</template>
