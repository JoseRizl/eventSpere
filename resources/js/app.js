import './bootstrap';

import router from './routes'; // Adjust the path to your `router.js` file

import { createApp, h } from 'vue';
import { createInertiaApp, Head, Link } from '@inertiajs/vue3';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

// PrimeVue
import PrimeVue from 'primevue/config';
import Aura from '@primevue/themes/aura';
import Card from 'primevue/card';  // Import the Card component
import 'primeicons/primeicons.css';
  // PrimeVue Components
  import InputText from "primevue/inputtext";
  import Textarea from "primevue/textarea";
  import Button from "primevue/button";
  import Checkbox from "primevue/checkbox";
  import Tooltip from "primevue/tooltip";
  import DatePicker from 'primevue/datepicker';
  import DataTable from 'primevue/datatable';
  import Column from 'primevue/column';
  import Dialog from 'primevue/dialog';
  import Dropdown from 'primevue/dropdown';


// Pinia
import { createPinia } from 'pinia';

// Axios (optional for interacting with db.json)
import axios from 'axios';

// Main Layout
import MainLayout from './Layouts/MainLayout.vue';

// Initialize the Inertia App
createInertiaApp({
  title: (title) => `EMS ${title}`,
  resolve: (name) => {
    const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });
    let page = pages[`./Pages/${name}.vue`];
    page.default.layout = page.default.layout || MainLayout;
    return page;
  },
  setup({ el, App, props, plugin }) {
    const app = createApp({ render: () => h(App, props) });

    // Create Pinia Instance
    const pinia = createPinia();

    app
      .use(router)
      .use(plugin)
      .use(createPinia())
      .use(ZiggyVue)
      .use(pinia) // Add Pinia
      .directive('tooltip', Tooltip)
      .use(PrimeVue, {
        theme: {
          preset: Aura,
          options: {
            prefix: 'msu-hris',
            darkModeSelector: 'light',
            cssLayer: false,
          },
        },
        zIndex: {
          modal: 1100, // dialog, drawer
          overlay: 1000, // select, popover
          menu: 1000, // overlay menus
          tooltip: 1100, // tooltip
        },
      })
      .component('Head', Head)
      .component('Link', Link)
      .component('Card', Card) // Register components globally
      .component('InputText', InputText)
      .component('Textarea', Textarea)
      .component('Button', Button)
      .component('Checkbox', Checkbox) // Register Checkbox
      .component('Tooltip', Tooltip)
      .component('DatePicker', DatePicker)
      .component('DataTable', DataTable)
      .component('Column', Column)
      .component('Dialog', Dialog)
      .component('Dropdown', Dropdown)

      .mount(el);

    // Axios Base URL (for JSON Server)
    axios.defaults.baseURL = 'http://localhost:3000'; // Update if your JSON server runs on a different port
  },
  progress: {
    color: '#29d',
    includeCSS: true,
    showSpinner: false,
  },
});
