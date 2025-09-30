import './bootstrap';
import '../css/app.css';
import '../css/bracket.css';

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
  import Select from 'primevue/select';
  import Toast from 'primevue/toast';
  import ToastService from 'primevue/toastservice';
  import Skeleton from 'primevue/skeleton';


// Pinia
import { createPinia } from 'pinia';

// Axios (optional for interacting with db.json)
import axios from 'axios';

// Main Layout
import MainLayout from './Layouts/MainLayout.vue';

// Common Custom Components
import LoadingSpinner from './Components/LoadingSpinner.vue';
import ConfirmationDialog from './Components/ConfirmationDialog.vue';
import SuccessDialog from './Components/SuccessDialog.vue';
import SearchFilterBar from './Components/SearchFilterBar.vue';

// Initialize the Inertia App
createInertiaApp({
  title: (title) => `EMS ${title}`,
  resolve: (name) => {
    const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });
    const page = pages[`./Pages/${name}.vue`];
    if (!page) {
      throw new Error(`Page ${name} not found`);
    }
    const component = page.default;
    component.layout = component.layout || MainLayout;
    return component;
  },
  setup({ el, App, props, plugin }) {
    const app = createApp({ render: () => h(App, props) });

    // Create Pinia Instance
    const pinia = createPinia();

    app
      .use(plugin)
      .use(ZiggyVue)
      .use(pinia) // Add Pinia
      .use(ToastService)
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
      .component('Select', Select)
      .component('Toast', Toast)
      .component('LoadingSpinner', LoadingSpinner)
      .component('ConfirmationDialog', ConfirmationDialog)
      .component('SuccessDialog', SuccessDialog)
      .component('SearchFilterBar', SearchFilterBar)
      .component('Skeleton', Skeleton)

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
