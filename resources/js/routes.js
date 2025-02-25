import { createRouter, createWebHistory } from 'vue-router';
import Home from './Pages/Home.vue';
import EventDetails from './Pages/Events/EventDetails.vue';
import EventList from './Pages/List/EventList.vue';
import CreateEvent from './Pages/Create/CreateEvent.vue';

const routes = [
  { path: '/home', name: 'HomeAlias', component: Home },
  { path: '/event-list', name: 'EventList', component: EventList },
  { path: '/create-event', name: 'CreateEvent', component: CreateEvent },
  { path: '/events/:id', name: 'EventDetails', component: EventDetails, props: true },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

export default router;
