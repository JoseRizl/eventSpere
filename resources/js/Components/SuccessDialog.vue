<script setup>
defineProps({
  show: {
    type: Boolean,
    required: true,
  },
  title: {
    type: String,
    default: 'Success!',
  },
  message: {
    type: String,
    required: true,
  },
});

const emit = defineEmits(['update:show']);

const close = () => {
  emit('update:show', false);
};
</script>

<template>
  <div v-if="show" class="fixed inset-0 bg-black bg-opacity-30 flex items-center justify-center" style="z-index: 9998;">
    <div class="bg-white p-6 rounded-lg shadow-lg max-w-sm w-full">
      <h2 class="text-lg font-semibold text-green-700 mb-2">{{ title }}</h2>
      <p class="text-sm text-gray-700 mb-4">{{ message }}</p>
      <div class="flex justify-end">
        <button @click="close" class="modal-button-confirm">Close</button>
      </div>
    </div>
  </div>
</template>
