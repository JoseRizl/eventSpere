<script setup>
import { Link, useForm, usePage, router } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue';
import Badge from 'primevue/badge';
import Toast from 'primevue/toast';
import Popover from 'primevue/popover';
import Avatar from 'primevue/avatar';
import { useNotifications } from '@/composables/useNotifications.js';

// Ref
const isDesktopCollapsed = ref(false);
const isMobileSidebarOpen = ref(false);
const isMobile = ref(window.innerWidth < 1024);
const op = ref();
const openDropdown = ref(null);
const profileMenu = ref();
const hideHeader = ref(false);

const sidebarWidth = 'w-64';

const page = usePage();
const user = computed(() => page.props.auth.user);

const backgroundStyle = computed(() => {
  const patternUrl = "url(\"data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%239C92AC' fill-opacity='0.12'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E\")";

  if (user.value) {
    // This creates the same gradient as 'bg-gradient-to-br from-blue-50 via-indigo-50 to-white'
    const gradient = 'linear-gradient(to bottom right, #eff6ff, #eef2ff, #ffffff)';
    return {
      backgroundImage: `${patternUrl}, ${gradient}`,
      backgroundAttachment: 'fixed'
    };
  }
  // This is 'bg-blue-100'
  return {
    backgroundImage: patternUrl,
    backgroundColor: '#dbeafe',
    backgroundAttachment: 'fixed'
  };
});

// Check if a route is currently active
const isActive = (routeName) => route().current(routeName);

const {
    notifications,
    unreadCount,
    hasUnreadNotifications: userHasUnread,
    displayedNotifications,
    showLoadMore,
    onNotificationClick,
    markAllAsRead,
    loadMore,
    toggleNotifications: originalToggleNotifications
} = useNotifications(op);

// --- Guest Notification Logic ---
const guestHasOpenedNotifications = ref(false);
onMounted(() => {
    if (!user.value) {
        guestHasOpenedNotifications.value = sessionStorage.getItem('guestHasOpenedNotifications') === 'true';
    }
});

const hasUnreadNotifications = computed(() => {
    if (user.value) return userHasUnread.value;
    // For guests, "unread" means there are notifications and they haven't opened the panel this session.
    return notifications.value.length > 0 && !guestHasOpenedNotifications.value;
});

const isNotificationRead = (notification) => {
    if (user.value) return notification.isRead;
    // For guests, all notifications are considered "read" once the panel is opened.
    return guestHasOpenedNotifications.value;
};

const toggleNotifications = (event) => {
    originalToggleNotifications(event);
    if (!user.value) {
        guestHasOpenedNotifications.value = true;
        sessionStorage.setItem('guestHasOpenedNotifications', 'true');
    }
};

const logout = () => {
    const form = useForm({});
    form.post(route('logout'));
};

const sideBarItems = computed(() => {
  const allItems = [
    {
      label: 'Dashboard',
      icon: 'pi pi-chart-bar',
      routeName: 'dashboard',
      roles: ['Principal'],
    },
    {
      label: 'Home',
      icon: 'pi pi-home',
      routeName: 'home',
    },
    {
      label: 'Events List',
      icon: 'pi pi-calendar-clock',
      routeName: 'events.index',
      roles: ['Admin', 'Principal', 'TournamentManager'],
    },
    {
      label: 'Brackets List',
      icon: 'pi pi-ticket',
      routeName: 'bracket',
      roles: ['Admin', 'TournamentManager'],
    },
    {
      label: 'Labels',
      icon: 'pi pi-palette',
      roles: ['Admin', 'Principal'],
      routeName: 'category.list',
      items: [
        {
          label: 'Categories',
          icon: 'pi pi-bookmark',
          routeName: 'category.list',
          routeParams: { view: 'categories' },
        },
        {
          label: 'Tags',
          icon: 'pi pi-tags',
          routeName: 'category.list',
          routeParams: { view: 'tags' },
        },
      ],
    },
    {
      label: 'Archive',
      icon: 'pi pi-folder',
      routeName: 'archive',
      roles: ['Admin', 'Principal'],
    },
  ];
  return allItems.filter(item => {
    if (!item.roles) return true;
    return user.value && item.roles.includes(user.value.role);
  });
});

const toggleSidebar = () => {
    if (isMobile.value) {
        isMobileSidebarOpen.value = !isMobileSidebarOpen.value;
    } else {
        isDesktopCollapsed.value = !isDesktopCollapsed.value;
    }
};

const isSubmenuActive = (item) => {
    if (!item.items) return false;
    return item.items.some(subItem => route().current(subItem.routeName, subItem.routeParams));
};

const closeMobileSidebar = () => {
    isMobileSidebarOpen.value = false;
};

const toggleDropdown = (label) => {
    if (isDesktopCollapsed.value) {
        isDesktopCollapsed.value = false;
        // After sidebar expands, open the dropdown
        nextTick(() => {
            openDropdown.value = label;
        });
    } else {
        openDropdown.value = openDropdown.value === label ? null : label;
    }
};

// Auto-open dropdown on page load/navigation
watch(() => page.url, () => {
    const activeItem = sideBarItems.value.find(item =>
        item.items && item.items.some(subItem => route().current(subItem.routeName, subItem.routeParams))
    );
    if (activeItem) openDropdown.value = activeItem.label;
}, { immediate: true });

const profileMenuItems = computed(() => (user.value ? [
    { label: 'Logout', icon: 'pi pi-sign-out', command: logout }
] : []));

const toggleProfileMenu = (event) => {
    profileMenu.value.toggle(event);
};

const handleResize = () => {
    const mobileState = window.innerWidth < 1024;
    if (isMobile.value && !mobileState) {
        // Transitioning from mobile to desktop
        isMobileSidebarOpen.value = false; // Close mobile sidebar
    }
    isMobile.value = mobileState;
};

onMounted(() => {
  hideHeader.value = window.hideHeader || false;
  window.setHideHeader = (val) => { hideHeader.value = val; };
});

onMounted(() => {
    window.addEventListener('resize', handleResize);
});
onUnmounted(() => {
    window.removeEventListener('resize', handleResize);
});
</script>

<template>
    <div :style="backgroundStyle" :class="['min-h-screen', user ? 'relative' : '']">
        <Toast position="bottom-right" />

        <!-- Background Design Elements -->
        <template v-if="user">
            <div class="absolute -z-10 top-16 right-16 w-40 h-40 bg-gradient-to-br from-blue-200/10 to-indigo-300/10 rounded-full blur-2xl animate-pulse"></div>
            <div class="absolute -z-10 bottom-20 left-20 w-32 h-32 bg-gradient-to-br from-purple-200/10 to-pink-300/10 rounded-full blur-xl animate-pulse" style="animation-delay: 1.5s;"></div>
            <div class="absolute -z-10 top-1/2 left-1/3 w-24 h-24 bg-gradient-to-br from-indigo-200/10 to-blue-300/10 rounded-full blur-lg animate-pulse" style="animation-delay: 2.5s;"></div>
        </template>

        <!-- Top Navbar -->
        <nav class="bg-white/95 backdrop-blur-sm shadow-sm sticky top-0 w-full px-4 h-16 flex justify-between items-center z-50 border-b border-gray-200">
            <div class="flex items-center space-x-4">
                <button v-if="user && !hideHeader" @click="toggleSidebar" class="p-2 rounded-full hover:bg-gray-200 transition-colors">
                    <i class="pi pi-bars text-xl text-gray-600"></i>
                </button>
                <Link :href="route('home')" class="flex items-center gap-3 text-surface-800 dark:text-surface-0 no-underline">
                    <Avatar image="/images/NCSlogo.png" shape="circle" class="menubar-logo" />
                    <span class="text-xl font-semibold">Event Sphere</span>
                </Link>
            </div>

            <div class="flex items-center gap-2">
                <button @click="toggleNotifications" v-ripple :class="['relative p-ripple flex items-center justify-center h-10 w-10 rounded-full hover:bg-gray-200 transition-colors text-slate-600', { 'notification-bell-ring': hasUnreadNotifications }]">
                    <i class="pi pi-bell text-xl" />
                    <Badge v-if="hasUnreadNotifications" severity="danger" class="absolute top-1 right-1 !p-0 !w-2 !h-2"></Badge>
                </button>
                <Popover ref="op">
                    <div class="w-80">
                        <div class="flex justify-between items-center mb-2">
                            <h3 class="font-bold text-center flex-1">Notifications</h3>
                            <button @click="markAllAsRead" v-if="user && unreadCount > 0" class="text-sm text-blue-600 hover:underline p-1">Mark all as read</button>
                        </div>
                        <ul v-if="notifications.length > 0" class="max-h-96 overflow-y-auto announcement-list">
                            <li
                            v-for="notification in displayedNotifications"
                            :key="notification.id"
                            @click="onNotificationClick(notification)"
                            :class="[
                                'group p-3 border-b flex justify-between items-start transition-colors duration-150',
                                notification.link ? 'cursor-pointer' : '',
                                !isNotificationRead(notification) ? 'bg-blue-50 hover:bg-blue-100' : 'hover:bg-gray-100'
                            ]"
                            >
                            <div class="flex-1 min-w-0">
                                <span class="text-xs font-semibold text-blue-600 group-hover:underline block truncate">
                                    {{ notification.title }}
                                </span>
                                <p class="break-words text-sm w-full">{{ notification.message }}</p>
                                <p class="text-xs text-gray-500 mt-2">{{ notification.formattedTimestamp }}</p>
                            </div>
                            <div class="flex-shrink-0 ml-2" v-if="!isNotificationRead(notification)">
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

                <div v-if="user && !hideHeader" class="flex items-center cursor-pointer hover:bg-gray-200 rounded-full transition-colors p-1" @click="toggleProfileMenu">
                    <div class="relative">
                        <img :src="user.profile_pic || 'https://primefaces.org/cdn/primevue/images/avatar/amyelsner.png'"
                                class="h-8 w-8 rounded-full object-cover"
                                alt="Profile Picture"/>
                    </div>
                    <div class="ml-2 hidden md:block" v-show="!isDesktopCollapsed">
                        <div class="text-sm font-bold text-gray-800">{{ user.name }}</div>
                        <div class="text-sm font-semibold text-blue-600 capitalize flex items-center">
                            <span class="mr-1">🎓</span>
                            {{ user.role }}
                        </div>
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

        <div class="flex flex-1 relative">
            <!-- Mobile Sidebar Backdrop -->
            <div v-if="isMobileSidebarOpen" @click="closeMobileSidebar" class="fixed inset-0 bg-black/30 z-50 lg:hidden"></div>

            <!-- Sidebar -->
            <div v-if="user && !hideHeader" :class="[ 'bg-white/95 backdrop-blur-sm border-r border-gray-200 flex flex-col transition-transform lg:transition-all duration-300 overflow-y-auto z-[60] lg:z-50',
                'fixed top-16 h-[calc(100vh-4rem)]', isMobile ? (isMobileSidebarOpen ? 'translate-x-0' : '-translate-x-full') : (isDesktopCollapsed ? 'w-20' : sidebarWidth) ]" >
                <!-- Navigation -->
                <nav class="px-3 pt-4 flex-grow">
                    <div v-for="(item, index) in sideBarItems" :key="index" class="mb-2">
                        <!-- Dropdown Menu Item -->
                        <div v-if="item.items">
                            <button @click="toggleDropdown(item.label)"
                                class="flex items-center justify-between w-full p-3 rounded-lg transition-colors duration-200"
                                :class="{
                                    'bg-blue-500 text-white font-semibold shadow-md': isSubmenuActive(item),
                                    'bg-blue-100 text-blue-700': !isSubmenuActive(item) && openDropdown === item.label,
                                    'text-gray-600 hover:bg-gray-100': !isSubmenuActive(item) && openDropdown !== item.label
                                }">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0">
                                        <i :class="item.icon" class="text-xl w-6 text-center"></i>
                                    </div>
                                    <span v-show="!isDesktopCollapsed" class="ml-3 text-sm font-medium">{{ item.label }}</span>
                                </div>
                                <i v-show="!isDesktopCollapsed" :class="['pi', openDropdown === item.label ? 'pi-chevron-down' : 'pi-chevron-right', 'transition-transform duration-200 text-xs']"></i>
                            </button>
                            <div v-if="openDropdown === item.label && !isDesktopCollapsed" class="mt-1 pl-6 space-y-1 py-1">
                                <Link v-for="subItem in item.items" :key="subItem.label" :href="route(subItem.routeName, subItem.routeParams)"
                                    class="flex items-center p-2 rounded-md transition-colors text-sm"
                                    :class="route().current(subItem.routeName, subItem.routeParams) ? 'bg-blue-100 text-blue-700 font-semibold' : 'text-gray-500 hover:bg-gray-100'">
                                    <i :class="subItem.icon" class="mr-3"></i>
                                    <span>{{ subItem.label }}</span>
                                </Link>
                            </div>
                        </div>
                        <!-- Regular Link Item -->
                        <Link v-else-if="item.routeName"
                            :href="route(item.routeName)"
                            class="flex items-center p-3 rounded-lg transition-colors duration-200"
                            :class="isActive(item.routeName) ? 'bg-blue-500 text-white font-semibold shadow-md' : 'text-gray-600 hover:bg-gray-100'">
                            <div class="flex-shrink-0">
                                <i :class="item.icon" class="text-xl w-6 text-center"></i>
                            </div>
                            <span v-show="!isDesktopCollapsed" class="ml-3 text-sm font-medium">{{ item.label }}</span>
                        </Link>
                    </div>
                </nav>

                <!-- Logout Section -->
                <div v-if="user && ['Principal', 'Admin', 'TournamentManager'].includes(user.role)" class="px-3 py-3 mt-auto">
                    <hr class="border-gray-200 mb-3">
                    <button @click="logout" class="flex items-center w-full text-gray-600 hover:bg-gray-100 rounded-lg p-3 transition-colors duration-200">
                        <div class="flex-shrink-0"><i class="pi pi-sign-out text-xl w-6 text-center"></i></div>
                        <span v-show="!isDesktopCollapsed" class="ml-3 text-sm font-medium">Logout</span>
                    </button>
                </div>
            </div>

            <!-- Page Content -->
            <main :class="['flex-1 transition-all duration-300 p-4 sm:p-6 lg:pt-22 min-w-0', user && !hideHeader ? (isDesktopCollapsed ? 'lg:ml-20' : 'lg:ml-64') : '']">
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
    width: 16rem; /* w-64 */
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
