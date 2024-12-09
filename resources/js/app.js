import './bootstrap';

import { createApp, h } from 'vue'
import { createInertiaApp, Head, Link } from '@inertiajs/vue3'
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

// PrimeVue
import PrimeVue from 'primevue/config';
import Aura from '@primevue/themes/aura';
import 'primeicons/primeicons.css'

import MainLayout from './Layouts/MainLayout.vue'

createInertiaApp({
  title: (title) => `HRIS ${title}`,
  resolve: name => {
    const pages = import.meta.glob('./Pages/**/*.vue', { eager: true })
    let page = pages[`./Pages/${name}.vue`];
    page.default.layout = page.default.layout || MainLayout;
    return page;
  },
  setup({ el, App, props, plugin }) {
    createApp({ render: () => h(App, props) })
      .use(plugin)
      .use(ZiggyVue)
      .use(PrimeVue, {
        theme: {
            preset: Aura,
            options: {
              prefix: 'msu-hris',
              darkModeSelector: 'light',
              cssLayer: false
            }
        },
        zIndex: {
            modal: 1100,        //dialog, drawer
            overlay: 1000,      //select, popover
            menu: 1000,         //overlay menus
            tooltip: 1100       //tooltip
        }
      })
      .component('Head', Head)
      .component('Link', Link)
      .mount(el)
  },
  progress: {
    color: '#29d',
    includeCSS: true,
    showSpinner: false,
  },
})
