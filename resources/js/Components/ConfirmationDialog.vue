<script setup>
defineProps({
  show: {
    type: Boolean,
    required: true
  },
  title: {
    type: String,
    required: true
  },
  message: {
    type: String,
    required: true
  },
  confirmText: {
    type: String,
    default: 'Yes'
  },
  cancelText: {
    type: String,
    default: 'Cancel'
  },
  confirmButtonClass: {
    type: String,
    default: 'bg-red-600 hover:bg-red-700'
  },
  cancelButtonClass: {
    type: String,
    default: 'bg-gray-300 hover:bg-gray-400'
  }
});

const emit = defineEmits(['confirm', 'cancel', 'update:show']);

const handleConfirm = () => {
  emit('confirm');
  emit('update:show', false);
};

const handleCancel = () => {
  emit('cancel');
  emit('update:show', false);
};
</script>

<template>
  <div v-if="show" class="fixed inset-0 bg-black bg-opacity-30 flex items-center justify-center" style="z-index: 9998;">
    <div class="bg-white p-6 rounded-lg shadow-lg max-w-sm w-full">
      <h2 class="text-lg font-semibold mb-2">{{ title }}</h2>
      <p class="text-sm text-gray-600 mb-4">{{ message }}</p>
      <div class="flex justify-end gap-2">
        <button
          @click="handleCancel"
          :class="[cancelButtonClass, 'px-4 py-2 rounded text-gray-800']"
        >
          {{ cancelText }}
        </button>
        <button
          @click="handleConfirm"
          :class="[confirmButtonClass, 'px-4 py-2 rounded text-white']"
        >
          {{ confirmText }}
        </button>
      </div>
    </div>
  </div>
</template>
