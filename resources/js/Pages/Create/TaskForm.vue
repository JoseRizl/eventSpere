<script setup>
import { ref, onMounted } from "vue";
import Dropdown from "primevue/dropdown";
import Card from "primevue/card";
import Button from "primevue/button";
import axios from "axios";
import { Textarea } from "primevue";

// Reactive state for form fields
const selectedEvent = ref(null);

// List of task entries
const tasksList = ref([
  { committee: null, employee: null, task: "" }
]);

// Options
const committees = ref([
  { name: "Committee 1", id: 1 },
  { name: "Committee 2", id: 2 },
  { name: "Committee 3", id: 3 }
]);

const employees = ref([
  { name: "Alice", committeeId: 1 },
  { name: "Bob", committeeId: 1 },
  { name: "Charlie", committeeId: 2 },
  { name: "Dave", committeeId: 3 },
]);

const tasks = ref([
  { name: "Event Planning", value: "event_planning" },
  { name: "Venue Setup", value: "venue_setup" },
  { name: "Budget Management", value: "budget_management" }
]);

const events = ref([]); // Changed to an empty ref for dynamic fetching


// Fetch events from `db.json` on mount
onMounted(async () => {
  try {
    const response = await axios.get("http://localhost:3000/events");
    events.value = response.data.filter(event => !event.archived);
  } catch (error) {
    console.error("Error fetching events:", error);
  }
});

// Filter employees based on selected committee
const getFilteredEmployees = (committee) =>
  employees.value.filter((employee) => employee.committeeId === committee?.id);

// Add new task entry
const addTask = () => {
  tasksList.value.push({ committee: null, employee: null, task: "" });
};

// Function to handle form submission
const submitted = ref(false);
const createTask = () => {
  submitted.value = true;

  if (!selectedEvent.value || tasksList.value.some(task =>
    !task.committee || !task.employee || !task.task
  )) {
    console.warn("Please fill all fields.");
    return;
  }

  console.log("Form submitted with:", {
    event: selectedEvent.value,
    tasks: tasksList.value
  });
};
</script>

<template>
  <div class="create-category-wrapper">
    <Card class="create-category-form">
      <template #title> Task List Form </template>

      <template #content>
        <form @submit.prevent="createTask" class="p-fluid">
          <!-- Event Dropdown -->
          <div class="p-field">
            <label for="event">Event</label>
            <Dropdown
              id="event"
              v-model="selectedEvent"
              :options="events"
              optionLabel="title"
              placeholder="Select an Event"
              filter
              class="w-full"
            />
          </div>

          <!-- Task Entries -->
          <div v-if="selectedEvent">
            <div
              v-for="(taskEntry, index) in tasksList"
              :key="index"
              class="border p-3 mb-3 rounded-md bg-gray-100"
            >
              <h3 class="mb-2">Task {{ index + 1 }}</h3>

              <!-- Committee Dropdown -->
              <div class="p-field">
                <label :for="'committee-' + index">Committee</label>
                <Dropdown
                  :id="'committee-' + index"
                  v-model="taskEntry.committee"
                  :options="committees"
                  optionLabel="name"
                  placeholder="Select a Committee"
                  filter
                  class="w-full"
                />
              </div>

              <!-- Employee Dropdown (Filtered) -->
              <div v-if="taskEntry.committee" class="p-field">
                <label :for="'employee-' + index">Employee</label>
                <Dropdown
                  :id="'employee-' + index"
                  v-model="taskEntry.employee"
                  :options="getFilteredEmployees(taskEntry.committee)"
                  optionLabel="name"
                  placeholder="Select an Employee"
                  filter
                  class="w-full"
                />
              </div>

              <!-- Task Textbox -->
            <div v-if="taskEntry.employee" class="p-field">
            <label :for="'task-' + index">Task</label>
            <Textarea
                :id="'task-' + index"
                v-model="taskEntry.task"
                placeholder="Enter Task"
                class="w-full"
            />
            </div>

            </div>

            <!-- Add Another Task Button -->
            <Button
              type="button"
              label="Add Another Task"
              icon="pi pi-plus"
              class="p-button-secondary"
              @click="addTask"
            />
          </div>

          <!-- Submit Button -->
          <Button
            type="submit"
            label="Submit"
            icon="pi pi-check"
            class="p-button-success mt-3"
          />
        </form>
      </template>
    </Card>
  </div>
</template>
