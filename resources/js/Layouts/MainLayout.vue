<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
// Ref
const toggleMenu = ref(false);
const menuBarItems = ref([]);
const isSidebarCollapsed = ref(false);

const sideBarItems = ref([
    {
        separator: true
    },
    {
        label: 'News and Updates',
        icon: 'pi pi-objects-column',
        route: route('home'),
    },
    {
        label: 'Events',
        icon: 'pi pi-calendar-clock',
        route: route('event.list'),
    },
    {
        label: 'Categories/Tags',
        icon: 'pi pi-palette',
        route: route('category.list')
    },
    {
        label: 'Sports',
        icon: 'pi pi-ticket',
        route: route('bracket')
    },
    {
        label: 'Archive',
        icon: 'pi pi-folder',
        route: route('archive')
    },
    {
        separator: true
    },
    {
        label: 'Log Out',
        icon: 'pi pi-sign-out',
        shortcut: '⌘+S',
        action: () => {
            logout();
        },
    },
]);

const toggleSidebar = () => {
    isSidebarCollapsed.value = !isSidebarCollapsed.value;
};

const logout = () => {
    const form = useForm({});
    form.post(route('logout'));
};

</script>

<template>
    <div class="bg-gray-100">
        <div class="flex flex-col md:flex-row">
            <header class="fixed top-0 left-0 right-0 z-[1100] w-full transition-all duration-300 ease-in-out">
                <Menubar :model="menuBarItems" class="w-full md:w-100 !border-l-0 !rounded-none">
                    <template #start>
                        <div class="flex items-center gap-3">
                            <button
                                @click="toggleSidebar"
                                class="p-2 rounded-md hover:bg-gray-200 transition-colors duration-200 flex items-center justify-center"
                                style="height: 2.25rem; width: 2.25rem;"
                                v-tooltip="isSidebarCollapsed ? 'Expand Menu' : 'Collapse Menu'"
                            >
                                <i class="pi pi-bars text-lg"></i>
                            </button>
                            <Avatar image="/images/NCSlogo.png" shape="circle" class="menubar-logo" />
                            <span class="text-xl font-semibold">Event Sphere</span>
                        </div>
                    </template>
                    <template #end>
                        <button v-ripple class="relative overflow-hidden w-full border-0 bg-transparent flex items-start justify-center pl-4 hover:bg-surface-100 dark:hover:bg-surface-800 rounded-none cursor-pointer transition-colors duration-200">
                            <Avatar image="https://primefaces.org/cdn/primevue/images/avatar/amyelsner.png" class="mr-2" shape="circle" />
                            <span class="inline-flex flex-col items-start">
                                <span class="font-bold text-xs">Admin</span>
                                <span class="text-xs">Admin</span>
                            </span>
                        </button>
                    </template>
                </Menubar>
            </header>
            <aside :class="[
                'fixed top-0 left-0 h-screen transition-all duration-300 ease-in-out',
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
                <div :class="[
                    'md:mt-12 px-4 py-4 transition-all duration-300 ease-in-out',
                    isSidebarCollapsed ? 'lg:ml-[48px] ml-[48px]' : 'lg:ml-60 ml-60'
                ]">
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>

<style scoped>
.transition-all {
    transition-property: all;
    transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
    transition-duration: 300ms;
}

/* Sidebar width for expanded/collapsed */
.sidebar-expanded {
    width: 240px;
}
.sidebar-collapsed {
    width: 48px;
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
</style>
