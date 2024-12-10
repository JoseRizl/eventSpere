<script setup>
import { ref } from 'vue';
const visible = ref(false);
</script>

<template>
    <Head :title="`| ${$page.component}`"/>

    <!-- Main Content -->
    <main class="flex-1 ">
      <header class="mb-4 flex justify-between items-center">
        <h1 class="text-2xl font-bold">Dashboard</h1>
        <Button label="Add New" icon="pi pi-check" size="small" />
      </header>

      <section>
        <br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <!-- Card 1 -->
          <div class="bg-white shadow rounded p-6">
            <h2 class="text-lg font-semibold mb-2">Users</h2>
            <p class="text-gray-600">120 Active Users</p>
          </div>
          <!-- Card 2 -->
          <div class="bg-white shadow rounded p-6">
            <h2 class="text-lg font-semibold mb-2">Revenue</h2>
            <p class="text-gray-600">$15,000 This Month</p>
          </div>
          <!-- Card 3 -->
          <div class="bg-white shadow rounded p-6">
            <h2 class="text-lg font-semibold mb-2">Support Tickets</h2>
            <p class="text-gray-600">23 Open Tickets</p>
          </div>
        </div>
      </section>
    </main>

</template>
