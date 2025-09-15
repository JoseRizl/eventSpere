<script setup>
import { Link, useForm, usePage, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Popover from 'primevue/popover';
import Badge from 'primevue/badge';
import Toast from 'primevue/toast';
import Menu from 'primevue/menu';
import { useNotifications } from '@/composables/useNotifications.js';

// Ref
const toggleMenu = ref(false);
const menuBarItems = ref([]);
const isSidebarCollapsed = ref(false);
const op = ref();
const profileMenu = ref();
const menuStyle = ref({});

const page = usePage();
const user = computed(() => page.props.auth.user);

const {
    notifications, unreadCount, hasUnreadNotifications,
    displayedNotifications, showLoadMore, onNotificationClick,
    markAllAsRead, loadMore, toggleNotifications
} = useNotifications(op);


const sideBarItems = computed(() => {
  const allItems = [
    {
      separator: true,
    },
    {
      label: 'Dashboard',
      icon: 'pi pi-chart-bar',
      route: route('dashboard'),
      roles: ['Principal', 'Admin'],
    },
    {
      label: 'News and Updates',
      icon: 'pi pi-objects-column',
      route: route('home'),
    },
    {
      label: 'Events',
      icon: 'pi pi-calendar-clock',
      route: route('events.index'),
      roles: ['Admin', 'Principal'],
    },
    {
      label: 'Brackets',
      icon: 'pi pi-ticket',
      route: route('bracket'),
      roles: ['Admin', 'SportsManager'],
    },
    {
      label: 'Categories/Tags',
      icon: 'pi pi-palette',
      route: route('category.list'),
      roles: ['Admin', 'Principal'],
    },
    {
      label: 'Archive',
      icon: 'pi pi-folder',
      route: route('archive'),
      roles: ['Admin', 'Principal'],
    },
  ];

  const userRole = user.value?.role;

  return allItems.filter(item => {
    if (item.separator) return true;
    if (!item.roles) return true;
    return user.value && item.roles.includes(user.value.role);
  });
});

const toggleSidebar = () => {
    isSidebarCollapsed.value = !isSidebarCollapsed.value;
};

const logout = () => {
    const form = useForm({});
    form.post(route('logout'));
};

const profileMenuItems = computed(() => (user.value ? [
    {
        label: 'Logout',
        icon: 'pi pi-sign-out',
        command: logout
    }
] : [
    {
        label: 'Login',
        icon: 'pi pi-sign-in',
        command: () => router.visit(route('login'))
    }
]));
const toggleProfileMenu = (event) => {
    menuStyle.value = { width: `${event.currentTarget.offsetWidth}px` };
    profileMenu.value.toggle(event);
};

</script>

<template>
    <div class="bg-gray-100">
        <Toast position="bottom-right" />
        <div class="flex flex-col md:flex-row">
            <header id="main-header" class="fixed top-0 left-0 right-0 z-[1100] w-full">
                <Menubar :model="menuBarItems" class="w-full md:w-100 !border-l-0 !rounded-none">
                    <template #start>
                        <div class="flex items-center gap-4">
                            <button
                                v-if="user"
                                @click="toggleSidebar"
                                class="p-2 rounded-md hover:bg-gray-200 transition-colors duration-200 flex items-center justify-center"
                                style="height: 2.25rem; width: 2.25rem;"
                                v-tooltip="isSidebarCollapsed ? 'Expand Menu' : 'Collapse Menu'"
                            >
                                <i class="pi pi-bars text-lg"></i>
                            </button>
                            <Link :href="route('home')" class="flex items-center gap-3 text-surface-800 dark:text-surface-0 no-underline">
                                <Avatar image="/images/NCSlogo.png" shape="circle" class="menubar-logo" />
                                <span class="text-xl font-semibold">Event Sphere</span>
                            </Link>
                        </div>
                    </template>
                    <template #end>
                        <div class="flex items-center gap-2 mr-2">
                            <button @click="toggleNotifications" v-ripple :class="['relative p-ripple p-2 rounded-full hover:bg-surface-100 dark:hover:bg-surface-800 transition-colors duration-200 text-slate-600', { 'notification-bell-ring': hasUnreadNotifications }]">
                                <i class="pi pi-bell text-lg" />
                                <Badge v-if="hasUnreadNotifications" severity="danger" class="absolute top-1 right-1 !p-0 !w-2 !h-2"></Badge>
                            </button>
                            <Popover ref="op">
                                <div class="w-80">
                                    <div class="flex justify-between items-center mb-2">
                                        <h3 class="font-bold text-center flex-1">Notifications</h3>
                                        <button @click="markAllAsRead" v-if="unreadCount > 0" class="text-sm text-blue-600 hover:underline p-1">Mark all as read</button>
                                    </div>
                                    <ul v-if="notifications.length > 0" class="max-h-96 overflow-y-auto announcement-list">
                                        <li
                                        v-for="notification in displayedNotifications"
                                        :key="notification.id"
                                        @click="onNotificationClick(notification)"
                                        :class="[
                                            'group p-3 border-b flex justify-between items-start transition-colors duration-150',
                                            notification.link ? 'cursor-pointer' : '',
                                            !notification.isRead ? 'bg-blue-50 hover:bg-blue-100' : 'hover:bg-gray-100'
                                        ]"
                                        >
                                        <div class="flex-1 min-w-0">
                                            <span class="text-xs font-semibold text-blue-600 group-hover:underline block truncate">
                                                {{ notification.title }}
                                            </span>
                                            <p class="break-words text-sm w-full">{{ notification.message }}</p>
                                            <p class="text-xs text-gray-500 mt-2">{{ notification.formattedTimestamp }}</p>
                                        </div>
                                        <div class="flex-shrink-0 ml-2" v-if="!notification.isRead">
                                            <div class="w-2 h-2 bg-blue-500 rounded-full mt-1"></div>
                                        </div>
                                        </li>
                                    </ul>
                                    <p v-else class="text-center text-sm text-gray-500 py-4">You're all caught up!</p>
                                    <div v-if="showLoadMore" class="text-center mt-2">
                                        <button @click="loadMore" class="text-sm font-semibold text-blue-600 hover:underline p-2 w-full">Load More</button>
                                    </div>
                                </div>
                            </Popover>

                            <button @click="toggleProfileMenu" aria-haspopup="true" aria-controls="profile_menu" v-ripple class="relative overflow-hidden border-0 bg-transparent flex items-center justify-center px-3 py-2 hover:bg-surface-100 dark:hover:bg-surface-800 rounded-md cursor-pointer transition-colors duration-200">
                                <Avatar :image="user ? 'https://primefaces.org/cdn/primevue/images/avatar/amyelsner.png' : undefined" :icon="!user ? 'pi pi-user' : undefined" class="mr-2" shape="circle" />
                                <span class="inline-flex flex-col items-start">
                                    <span class="font-bold text-xs">{{ user?.name || 'Guest' }}</span>
                                    <span class="text-xs">{{ user?.role || 'Account' }}</span>
                                </span>
                            </button>
                            <Menu ref="profileMenu" id="profile_menu" :model="profileMenuItems" :popup="true" appendTo="self" :pt="{ root: { style: menuStyle } }" />
                        </div>
                    </template>
                </Menubar>
            </header>
            <aside v-if="user" id="main-sidebar" :class="[
                'fixed top-0 left-0 h-screen',
                isSidebarCollapsed ? 'sidebar-collapsed' : 'sidebar-expanded'
            ]" style="padding-top:3.5rem;">
                <div class="card flex flex-col h-full">
                    <!-- Sidebar Menu - Collapsible -->
                    <TieredMenu
                        :model="sideBarItems"
                        :class="[
                            'w-full hidden h-full !rounded-none lg:block',
                            isSidebarCollapsed ? 'menu-collapsed' : ''
                        ]"
                    >
                        <template #item="{ item, props, hasSubmenu }">
                            <Link
                                v-if="item.route"
                                v-ripple
                                :href="item.route"
                                class="flex items-center cursor-pointer text-surface-700 dark:text-surface-0 px-4 py-2"
                                v-tooltip="isSidebarCollapsed ? item.label : ''"
                            >
                                <span :class="item.icon" />
                                <span v-if="!isSidebarCollapsed" class="ml-2">{{ item.label }}</span>
                                <Badge v-if="item.badge" class="ml-auto" :value="item.badge" />
                            </Link>
                            <a
                                v-else
                                v-ripple
                                @click="item.action"
                                :href="item.url"
                                :target="item.target"
                                v-bind="props.action"
                                v-tooltip="isSidebarCollapsed ? item.label : ''"
                            >
                                <span :class="item.icon" />
                                <span v-if="!isSidebarCollapsed" class="ml-2">{{ item.label }}</span>
                                <span v-if="hasSubmenu" class="pi pi-angle-right ml-auto" />
                            </a>
                        </template>
                    </TieredMenu>
                </div>
            </aside>

            <!-- Main Content -->
            <main class="flex-1">
                <div id="main-content-wrapper" :class="[
                    'md:mt-12 px-4 py-4',
                    user ? (isSidebarCollapsed ? 'lg:ml-[48px]' : 'lg:ml-60') : ''
                ]">
                    <slot />
                </div>
            </main>

        </div>
    </div>
</template>

<style scoped>
/* Sidebar width for expanded/collapsed */
.sidebar-expanded {
    width: 240px;
}
.sidebar-collapsed {
    width: 48px;
}

/* Transition for sidebar width and main content margin */
#main-sidebar, #main-content-wrapper {
    transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1), margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Ensure sidebar background collapses with sidebar */
.card {
    width: 100%;
    min-width: 0;
    transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

/* Add hover effect for sidebar items */
:deep(.p-tieredmenu .p-menuitem-link:hover) {
    background-color: rgba(0, 0, 0, 0.04);
}

/* Menu item styles */
:deep(.p-tieredmenu .p-menuitem-link) {
    justify-content: flex-start;
    padding: 0.75rem 1rem;
}

/* Remove menu item background in collapsed mode except on hover */
:deep(.p-tieredmenu.menu-collapsed .p-menuitem-link) {
    background: transparent !important;
}

/* On hover, show background only behind the icon */
:deep(.p-tieredmenu.menu-collapsed .p-menuitem-link:hover) {
    background-color: rgba(0, 0, 0, 0.04) !important;
}

/* Collapsed menu styles */
:deep(.p-tieredmenu.menu-collapsed .p-menuitem-link span[class^="pi-"]) {
    margin: 0 auto;
    display: block;
    text-align: center;
}

/* Burger button styles */
button {
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6c757d;
}

button:hover {
    background-color: rgba(0, 0, 0, 0.04);
    color: #495057;
}

.menubar-logo {
    width: 2.25rem;
    height: 2.25rem;
}

/* Ensure icons are visible and clickable */
:deep(.p-tieredmenu .p-menuitem-link span[class^="pi-"]) {
    font-size: 1.25rem;
    min-width: 1.5rem;
    text-align: center;
}

/* Add hover effect for icons */
:deep(.p-tieredmenu .p-menuitem-link:hover span[class^="pi-"]) {
    color: #7e0bc1;
}

/* Menubar title styles */
:deep(.p-menubar .p-menubar-start) {
    margin-right: 1rem;
}

:deep(.p-menubar .p-menubar-start .text-xl) {
    color: #495057;
    font-weight: 600;
}

:deep(.p-menubar .p-menubar-start .flex) {
    align-items: center;
    gap: 0.75rem;
}

.announcement-list::-webkit-scrollbar {
  width: 6px;
}

.announcement-list::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 10px;
}

.announcement-list::-webkit-scrollbar-thumb {
  background: #c1c1c1;
  border-radius: 10px;
}

.announcement-list::-webkit-scrollbar-thumb:hover {
  background: #a8a8a8;
}

.notification-bell-ring {
  animation: ring-animation 1.5s ease-in-out infinite;
}

@keyframes ring-animation {
  0%, 100% { transform: rotate(0); }
  10%, 30%, 50%, 70% { transform: rotate(-15deg); }
  20%, 40%, 60%, 80% { transform: rotate(15deg); }
}

/* Custom Toast Style */
:deep(.p-toast-message-info) {
    border-style: solid;
    border-width: 0 0 0 6px;
    border-color: #6366F1; /* indigo-500 */
    background: #EEF2FF; /* indigo-50 */
}
:deep(.p-toast-message-info .p-toast-summary) {
    font-weight: 600;
    color: #4338CA; /* indigo-700 */
}
:deep(.p-toast-message-info .p-toast-detail) {
    color: #4f46e5; /* indigo-600 */
}

.no-underline {
  text-decoration: none;
}
</style>
