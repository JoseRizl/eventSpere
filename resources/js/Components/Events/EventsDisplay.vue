<template>
    <div>
      <h2>Today's Events</h2>
      <ul>
        <li v-for="event in todayEvents" :key="event.id">
          <h3>{{ event.title }}</h3>
          <p>{{ event.subtitle }}</p>
          <p>{{ event.description }}</p>
        </li>
      </ul>

      <h2>Sports Events</h2>
      <ul>
        <li v-for="event in sportsEvents" :key="event.id">
          <h3>{{ event.title }}</h3>
          <p>{{ event.subtitle }}</p>
          <p>{{ event.description }}</p>
        </li>
      </ul>
    </div>
  </template>

  <script>
  import { useEventStore } from "@/stores/eventStore";
  import { computed, onMounted } from "vue";

  export default {
    setup() {
      const store = useEventStore();

      // Fetch events when the component is mounted
      onMounted(() => {
        store.fetchEvents();
      });

      // Use computed properties for reactivity
      const todayEvents = computed(() => store.todayEvents);
      const sportsEvents = computed(() => store.sportsEvents);

      return {
        todayEvents,
        sportsEvents,
      };
    },
  };
  </script>
