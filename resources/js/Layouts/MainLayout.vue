<script setup>
import { Link, useForm, usePage, router } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import Badge from 'primevue/badge';
import Toast from 'primevue/toast';
import Popover from 'primevue/popover';
import Avatar from 'primevue/avatar';
import { useNotifications } from '@/composables/useNotifications.js';

// Ref
const isCollapsed = ref(false);
const op = ref();
const profileMenu = ref();
const hideHeader = ref(false);

const page = usePage();
const user = computed(() => page.props.auth.user);

const backgroundClass = computed(() => {
  if (user.value) {
    return 'bg-gradient-to-br from-blue-50 via-white to-blue-100 relative overflow-hidden';
  }
  return 'bg-gray-100';
});

// Check if a route is currently active
const isActive = (routeName) => route().current(routeName);

const {
    notifications, unreadCount, hasUnreadNotifications,
    displayedNotifications, showLoadMore, onNotificationClick,
    markAllAsRead, loadMore, toggleNotifications
} = useNotifications(op);


const sideBarItems = computed(() => {
  const allItems = [
    {
      label: 'Dashboard',
      icon: 'pi pi-chart-bar',
      route: route('dashboard'),
      roles: ['Principal', 'Admin'],
    },
    {
      label: 'News and Updates',
      icon: 'pi pi-home',
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
      label: 'Categories',
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
    {
      label: 'Logout',
      icon: 'pi pi-sign-out',
      action: logout,
      roles: ['Principal', 'Admin', 'SportsManager'],
    },
  ];

  return allItems.filter(item => {
    if (!item.roles) return true;
    return user.value && item.roles.includes(user.value.role);
  });
});

const toggleSidebar = () => {
    isCollapsed.value = !isCollapsed.value;
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
    profileMenu.value.toggle(event);
};

onMounted(() => {
  hideHeader.value = window.hideHeader || false;
  window.setHideHeader = (val) => { hideHeader.value = val; };
});

</script>

<template>
    <div :class="[backgroundClass, 'flex min-h-screen']">
        <Toast position="bottom-right" />

        <!-- Background Design Elements -->
        <template v-if="user">
            <div class="absolute -z-10 top-16 right-16 w-40 h-40 bg-gradient-to-br from-blue-200/10 to-indigo-300/10 rounded-full blur-2xl animate-pulse"></div>
            <div class="absolute -z-10 bottom-20 left-20 w-32 h-32 bg-gradient-to-br from-purple-200/10 to-pink-300/10 rounded-full blur-xl animate-pulse" style="animation-delay: 1.5s;"></div>
            <div class="absolute -z-10 top-1/2 left-1/3 w-24 h-24 bg-gradient-to-br from-indigo-200/10 to-blue-300/10 rounded-full blur-lg animate-pulse" style="animation-delay: 2.5s;"></div>
        </template>

        <!-- Sidebar -->
        <div v-if="user && !hideHeader" :class="[ 'fixed top-0 left-0 h-screen bg-gradient-to-b from-blue-50 to-indigo-100 shadow-xl flex flex-col transition-all duration-300 overflow-y-auto rounded-r-3xl border-r-4 border-blue-200', isCollapsed ? 'w-20' : 'w-56' ]">
            <!-- Sidebar Header -->
            <div class="p-4 border-b border-blue-200 flex items-center justify-center">
                <button @click="toggleSidebar" class="p-3 bg-blue-100 hover:bg-blue-200 rounded-xl transition-all duration-300 transform hover:scale-105 shadow-md">
                    <i class="pi pi-bars text-xl text-blue-700"></i>
                </button>
            </div>

            <!-- Navigation -->
            <nav class="mt-6 px-4">
                <div v-for="(item, index) in sideBarItems" :key="index" class="mb-3">
                    <Link v-if="item.route"
                        :href="item.route"
                        class="flex items-center p-4 rounded-2xl transition-all duration-300 transform hover:scale-102 shadow-lg"
                        :class="isActive(item.route) ? 'bg-blue-500 text-white shadow-xl border-2 border-blue-400' : 'bg-white/80 text-blue-700 hover:bg-blue-100 hover:text-blue-800 border-2 border-transparent'">
                        <div class="flex-shrink-0">
                            <i :class="item.icon" class="text-2xl"></i>
                        </div>
                        <span v-show="!isCollapsed" class="ml-4 text-base font-semibold">{{ item.label }}</span>
                    </Link>

                    <button v-else @click="item.action"
                        class="flex items-center w-full text-blue-700 bg-red-100/80 hover:bg-red-200 rounded-2xl p-4 transition-all duration-300 transform hover:scale-102 shadow-lg border-2 border-red-300">
                        <div class="flex-shrink-0">
                            <i :class="item.icon" class="text-2xl"></i>
                        </div>
                        <span v-show="!isCollapsed" class="ml-4 text-base font-semibold">{{ item.label }}</span>
                    </button>
                </div>
            </nav>
        </div>

        <!-- Main Content -->
        <div :class="['flex-1 transition-all duration-300 flex flex-col h-screen', user && !hideHeader ? (isCollapsed ? 'ml-20' : 'ml-56') : '']">
            <!-- Top Navbar -->
            <nav v-if="!hideHeader" class="bg-gradient-to-r from-white via-blue-50 to-purple-50 shadow-xl sticky top-0 w-full px-6 py-3 flex justify-between items-center z-40 rounded-b-3xl border-b-4 border-blue-200 backdrop-blur-sm">
                <div class="flex items-center space-x-4">
                     <Link :href="route('home')" class="flex items-center gap-3 text-surface-800 dark:text-surface-0 no-underline">
                        <Avatar image="/images/NCSlogo.png" shape="circle" class="menubar-logo" />
                        <span class="text-xl font-semibold">Event Sphere</span>
                    </Link>
                </div>

                <div class="flex items-center gap-2">
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

                    <div v-if="user" class="flex items-center cursor-pointer hover:bg-blue-100 rounded-xl transition-all duration-300 transform hover:scale-105 p-2" @click="toggleProfileMenu">
                        <div class="relative">
                            <img :src="user.profile_pic || 'https://primefaces.org/cdn/primevue/images/avatar/amyelsner.png'"
                                    class="h-10 w-10 rounded-full object-cover border-2 border-blue-300 shadow-lg"
                                    alt="Profile Picture"/>
                            <div class="absolute -bottom-1 -right-1 text-sm animate-pulse">⭐</div>
                        </div>
                        <div class="ml-3" v-show="!isCollapsed">
                            <div class="text-lg font-bold text-gray-800">{{ user.name }}</div>
                            <div class="text-sm font-semibold text-blue-600 capitalize flex items-center">
                                <span class="mr-1">🎓</span>
                                {{ user.role }}
                            </div>
                        </div>
                    </div>
                    <div v-else class="flex items-center cursor-pointer hover:bg-blue-100 rounded-xl transition-all duration-300 transform hover:scale-105 p-2" @click="toggleProfileMenu">
                        <Avatar icon="pi pi-user" class="h-10 w-10" shape="circle" />
                        <div class="ml-3" v-show="!isCollapsed">
                            <div class="text-lg font-bold text-gray-800">Guest</div>
                            <div class="text-sm font-semibold text-blue-600">Account</div>
                        </div>
                    </div>
                    <Popover ref="profileMenu">
                        <div class="w-48 p-2">
                            <button
                                v-for="item in profileMenuItems"
                                :key="item.label"
                                @click="item.command"
                                class="w-full text-left p-2 hover:bg-gray-100 rounded-md flex items-center gap-2 transition-colors duration-150"
                            >
                                <i :class="item.icon" class="text-gray-500"></i>
                                <span class="font-medium text-gray-700">{{ item.label }}</span>
                            </button>
                        </div>
                    </Popover>
                </div>
            </nav>

            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto p-6">
                <slot />
            </main>
        </div>
    </div>
</template>

<style scoped>
.menubar-logo {
    width: 2.25rem;
    height: 2.25rem;
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

.sidebar-expanded {
    width: 14rem; /* w-56 */
}
.sidebar-collapsed {
    width: 5rem; /* w-20 */
}

:deep(.p-toast-message-info) {
    border: 0;
    box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
}

:deep(.p-toast-message-info .p-toast-message-content) {
    background: #4f46e5; /* indigo-600 */
    color: #ffffff;
}
</style>
