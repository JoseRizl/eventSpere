<script setup>
import { ref } from "vue";
import AutoComplete from "primevue/autocomplete";
import Card from "primevue/card";
import Button from "primevue/button";

// Reactive state for form fields
const selectedCommittee = ref(null);
const selectedTask = ref(null);
const selectedEvent = ref(null);

// Options
const committees = ref([
  { name: "Committee 1" },
  { name: "Committee 2" },
  { name: "Committee 3" }
]);

const tasks = ref([
  { name: "Event Planning", value: "event_planning" },
  { name: "Venue Setup", value: "venue_setup" },
  { name: "Budget Management", value: "budget_management" }
]);

const events = ref([
  { name: "4 A.M. Zumba" },
  { name: "Foundation Day" },
  { name: "bbal" }
]);

const submitted = ref(false);

// Filtered search results
const filteredEvents = ref([]);
const filteredCommittees = ref([]);
const filteredTasks = ref([]);

// Filtering logic for search
const searchEvent = (event) => {
  filteredEvents.value = events.value.filter((e) =>
    e.name.toLowerCase().includes(event.query.toLowerCase())
  );
};

// Show all suggestions on focus
const showAllEvents = () => {
  filteredEvents.value = [...events.value];
};

const searchCommittee = (event) => {
  filteredCommittees.value = committees.value.filter((c) =>
    c.name.toLowerCase().includes(event.query.toLowerCase())
  );
};

const showAllCommittees = () => {
  filteredCommittees.value = [...committees.value];
};

const searchTask = (event) => {
  filteredTasks.value = tasks.value.filter((t) =>
    t.name.toLowerCase().includes(event.query.toLowerCase())
  );
};

const showAllTasks = () => {
  filteredTasks.value = [...tasks.value];
};

// Function to handle form submission
const createTask = () => {
  submitted.value = true;
  if (!selectedEvent.value || !selectedCommittee.value || !selectedTask.value) {
    console.warn("Please fill all fields.");
    return;
  }
  console.log("Form submitted with:", {
    event: selectedEvent.value,
    committee: selectedCommittee.value,
    task: selectedTask.value,
  });
};
</script>

<template>
  <div class="create-category-wrapper">
    <Card class="create-category-form">
      <template #title> Task List Form </template>

      <template #content>
        <form @submit.prevent="createTask" class="p-fluid">
          <!-- Event Search Bar -->
          <div class="p-field">
            <label for="event">Event</label>
            <AutoComplete
              id="event"
              v-model="selectedEvent"
              :suggestions="filteredEvents"
              @complete="searchEvent"
              @focus="showAllEvents"
              optionLabel="name"
              placeholder="Search for an Event"
              class="w-full"
            />
          </div>

          <!-- Committee Search Bar -->
          <div class="p-field">
            <label for="committee">Committee</label>
            <AutoComplete
              id="committee"
              v-model="selectedCommittee"
              :suggestions="filteredCommittees"
              @complete="searchCommittee"
              @focus="showAllCommittees"
              optionLabel="name"
              placeholder="Search for a Committee"
              class="w-full"
            />
          </div>

          <!-- Task Search Bar -->
          <div class="p-field">
            <label for="task">Task</label>
            <AutoComplete
              id="task"
              v-model="selectedTask"
              :suggestions="filteredTasks"
              @complete="searchTask"
              @focus="showAllTasks"
              optionLabel="name"
              placeholder="Search for a Task"
              class="w-full"
            />
          </div>

          <!-- Submit Button -->
          <Button type="submit" label="Submit" icon="pi pi-check" class="p-button-success" />
        </form>
      </template>
    </Card>
  </div>
</template>
