<script setup>
defineProps({
  message: {
    type: String,
    default: 'Processing...'
  },
  show: {
    type: Boolean,
    default: false
  }
});
</script>

<template>
  <div v-if="show" class="fixed inset-0 bg-black bg-opacity-30 flex items-center justify-center" style="z-index: 9999;">
    <div class="bg-white rounded-lg p-6 shadow-lg flex items-center gap-3" style="z-index: 10000;">
      <svg class="animate-spin w-5 h-5 text-blue-600" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
      </svg>
      <span class="text-gray-700 font-medium">{{ message }}</span>
    </div>
  </div>
</template>
