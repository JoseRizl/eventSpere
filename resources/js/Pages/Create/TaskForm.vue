<script setup>
import { ref } from "vue";
import Dropdown from "primevue/dropdown";
import Card from "primevue/card";
import Button from "primevue/button";

// Reactive state for form fields
const selectedCommittee = ref(null);
const selectedTask = ref(null);
const selectedParticipants = ref(null);

// Dropdown options
const committees = ref([
  { name: "Committee 1"},
  { name: "Committee 2" },
  { name: "Committee 3"}
]);

const tasks = ref([
  { name: "Event Planning", value: "event_planning" },
  { name: "Venue Setup", value: "venue_setup" },
  { name: "Budget Management", value: "budget_management" }
]);

const participants = ref([
  { name: "All"},
  { name: "Grade 1"},
  { name: "Grade 2"},
  { name: "Grade 3"},
  { name: "Grade 4"},
  { name: "Grade 5"},
  { name: "Grade 6"}
]);

const events = ref([
  { name: "4 A.M. Zumba"},
  { name: "Foundation Day"},
  { name: "bbal"}
]);

const submitted = ref(false);
const form = ref({ category: null });

// Function to handle form submission
/*const createTask = () => {
  submitted.value = true;
  if (!form.value.category) return;
  console.log("Form submitted with:", {

  });
};*/
</script>

<template>
  <div class="create-category-wrapper">
    <Card class="create-category-form">
      <template #title> Task List Form </template>

      <template #content>
        <form @submit.prevent="createTask" class="p-fluid">
            <!-- Event Dropdown -->
          <div class="p-field">
            <label for="category">Event</label>
            <Dropdown
              id="category"
              v-model="selectedEvent"
              :options="events"
              optionLabel="name"
              placeholder="Select Event"
              class="w-full"
            />
          </div>
          <!-- Committee Dropdown -->
          <div class="p-field">
            <label for="category">Committee</label>
            <Dropdown
              id="category"
              v-model="selectedCommittee"
              :options="committees"
              optionLabel="name"
              placeholder="Assign Committee"
              class="w-full"
            />
          </div>

          <!-- Task Dropdown -->
          <div class="p-field">
            <label for="task">Task</label>
            <Dropdown
              id="task"
              v-model="selectedTask"
              :options="tasks"
              optionLabel="name"
              placeholder="Assign Task"
              class="w-full"
            />
          </div>

          <!-- Participants Dropdown -->
          <div class="p-field">
            <label for="participants">Participants</label>
            <Dropdown
              id="participants"
              v-model="selectedParticipants"
              :options="participants"
              optionLabel="name"
              placeholder="Select Participants"
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
