<script setup>
import { ref } from 'vue';

// Ref
const toggleMenu = ref(false);
const sideBarItems = ref([
    {
        separator: true
    },
    {
        label: 'Dashboard',
        icon: 'pi pi-objects-column',
        // route: '/home'
    },
    {
        label: 'Messages',
        icon: 'pi pi-inbox',
        badge: 2
    },
    {
        label: 'File',
        icon: 'pi pi-file',
        items: [
            {
                label: 'New',
                icon: 'pi pi-plus',
            },
            {
                label: 'Open',
                icon: 'pi pi-folder-open',
                shortcut: '⌘+O'
            },
            {
                label: 'Print',
                icon: 'pi pi-print',
                shortcut: '⌘+P'
            }
        ]
    },
    {
        label: 'Edit',
        icon: 'pi pi-file-edit',
        items: [
            {
                label: 'Copy',
                icon: 'pi pi-copy',
                shortcut: '⌘+C'
            },
            {
                label: 'Delete',
                icon: 'pi pi-times',
                shortcut: '⌘+D'
            }
        ]
    },
    {
        label: 'Search',
        icon: 'pi pi-search',
        shortcut: '⌘+S'
    },
    {
        label: 'Share',
        icon: 'pi pi-share-alt',
        items: [
            {
                label: 'Slack',
                icon: 'pi pi-slack',
                badge: 2
            },
            {
                label: 'Whatsapp',
                icon: 'pi pi-whatsapp',
                badge: 3
            }
        ]
    }
]);

const menuBarItems = ref([
    {
        label: 'Home',
        icon: 'pi pi-home'
    },
    {
        label: 'Features',
        icon: 'pi pi-star'
    },
    {
        label: 'Projects',
        icon: 'pi pi-search',
        items: [
            {
                label: 'Components',
                icon: 'pi pi-bolt'
            },
            {
                label: 'Blocks',
                icon: 'pi pi-server'
            },
            {
                label: 'UI Kit',
                icon: 'pi pi-pencil'
            },
            {
                label: 'Templates',
                icon: 'pi pi-palette',
                items: [
                    {
                        label: 'Apollo',
                        icon: 'pi pi-palette'
                    },
                    {
                        label: 'Ultima',
                        icon: 'pi pi-palette'
                    }
                ]
            }
        ]
    },
    {
        label: 'Contact',
        icon: 'pi pi-envelope'
    }
]);

</script>

<template>

    <div class="bg-gray-100">
        <div class="flex flex-col md:flex-row h-screen">


        <aside class="fixed top-0 left-0 h-screen">
            <div class="card flex justify-center h-full">
                <TieredMenu :model="sideBarItems" class="w-full md:w-60 h-16 md:h-full flex-shrink-0 !rounded-none">
                <template #start>
                    <span class="inline-flex items-center gap-1 px-2 py-2">
                        <Avatar image="https://msunaawan.edu.ph/wp-content/uploads/elementor/thumbs/Resized-for-Docs_MSUN-LOGO-1-qukqvmb3wm17j2ukfd3r16tafpfhgujscsqt1iex2g.png" shape="circle" />
                        <span class="text-xl font-semibold">HRIS</span>
                    </span>
                </template>
                <template #item="{ item, props, hasSubmenu }">
                    <router-link v-if="item.route" v-slot="{ href, navigate }" :to="item.route" custom>
                        <a v-ripple :href="href" v-bind="props.action" @click="navigate">
                            <span :class="item.icon" />
                            <span class="ml-2">{{ item.label }}</span>
                            <Badge v-if="item.badge" class="ml-auto" :value="item.badge" />
                        </a>
                    </router-link>
                    <a v-else v-ripple :href="item.url" :target="item.target" v-bind="props.action">
                        <span :class="item.icon" />
                        <span class="ml-2">{{ item.label }}</span>
                        <span v-if="hasSubmenu" class="pi pi-angle-right ml-auto" />
                    </a>
                </template>
                <!-- <template #submenulabel="{ item }">
                    <span class="text-primary font-bold">{{ item.label }}</span>
                </template>
                <template #item="{ item, props }">
                    <a v-ripple class="flex items-center" v-bind="props.action">
                        <span :class="item.icon" />
                        <span>{{ item.label }}</span>
                        <Badge v-if="item.badge" class="ml-auto" :value="item.badge" />
                        <span v-if="item.shortcut" class="ml-auto border border-surface rounded bg-emphasis text-muted-color text-xs p-1">{{ item.shortcut }}</span>
                    </a>
                </template> -->
                <!-- <template #end>
                    <button v-ripple class="relative overflow-hidden w-full border-0 bg-transparent flex items-start p-2 pl-4 hover:bg-surface-100 dark:hover:bg-surface-800 rounded-none cursor-pointer transition-colors duration-200">
                        <Avatar image="https://primefaces.org/cdn/primevue/images/avatar/amyelsner.png" class="mr-2" shape="circle" />
                        <span class="inline-flex flex-col items-start">
                            <span class="font-bold">Amy Elsner</span>
                            <span class="text-sm">Admin</span>
                        </span>
                    </button>
                </template> -->
            </TieredMenu>
            </div>
        </aside>


            <!-- Main Content -->
            <main class="flex-1">
                <header class="fixed top-0 left-60 right-0 z-[1100]">
                    <!-- <nav> -->
                        <!-- <div class="space-x-6">
                            <Link :href="route('home')" class="nav-link">Home</Link>
                        </div>

                        <div class="space-x-6">
                            <Link :href="route('login')" class="nav-link">Login</Link>
                            <Link :href="route('register')" class="nav-link">Register</Link>
                        </div> -->
                        <Menubar :model="menuBarItems" class="w-full md:w-100 !border-l-0 !rounded-none">
                            <template #start>
                                <span class="pi pi-bars"></span>
                            </template>
                            <template #item="{ item, props, hasSubmenu, root }">
                                <a v-ripple class="flex items-center" v-bind="props.action">
                                    <span>{{ item.label }}</span>
                                    <Badge v-if="item.badge" :class="{ 'ml-auto': !root, 'ml-2': root }" :value="item.badge" />
                                    <span v-if="item.shortcut" class="ml-auto border border-surface rounded bg-emphasis text-muted-color text-xs p-1">{{ item.shortcut }}</span>
                                    <i v-if="hasSubmenu" :class="['pi pi-angle-down ml-auto', { 'pi-angle-down': root, 'pi-angle-right': !root }]"></i>
                                </a>
                            </template>
                            <template #end>
                                <button v-ripple class="relative overflow-hidden w-full border-0 bg-transparent flex items-start justify-center pl-4 hover:bg-surface-100 dark:hover:bg-surface-800 rounded-none cursor-pointer transition-colors duration-200">
                                    <Avatar image="https://primefaces.org/cdn/primevue/images/avatar/amyelsner.png" class="mr-2" shape="circle" />
                                    <span class="inline-flex flex-col items-start">
                                        <span class="font-bold text-xs">Amy Elsner</span>
                                        <span class="text-xs">Admin</span>
                                    </span>
                                </button>
                            </template>
                        </Menubar>
                    <!-- </nav> -->
                </header>

                <div class="md:mt-12 md:ml-60 px-4 py-4">
                    <slot />
                </div>
            </main>

        </div>
    </div>
</template>
