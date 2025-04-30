<script setup>
import { Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
// Ref
const toggleMenu = ref(false);
const menuBarItems = ref([]);

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

const logout = () => {
    const form = useForm({});
    form.post(route('logout'));
};

</script>

<template>
    <div class="bg-gray-100">
        <div class="flex flex-col md:flex-row ">


            <aside class="fixed top-0 left-0 h-screen">
                <div class="card flex justify-center h-full">
                    <TieredMenu :model="sideBarItems" class="w-full hidden h-16 flex-shrink-0 !rounded-none lg:block lg:w-60  lg:h-full">
                    <template #start>
                        <span class="inline-flex items-center gap-1 px-2 py-2">
                            <Avatar image="/images/NCSlogo.png" shape="circle" />
                            <span class="text-xl font-semibold">Event Sphere</span>
                        </span>
                    </template>

                    <template #item="{ item, props, hasSubmenu }">
                        <Link v-if="item.route" v-ripple :href="item.route" class="flex items-center cursor-pointer text-surface-700 dark:text-surface-0 px-4 py-2">
                            <span :class="item.icon" />
                            <span class="ml-2">{{ item.label }}</span>
                            <Badge v-if="item.badge" class="ml-auto" :value="item.badge" />
                        </Link>
                        <a v-else v-ripple @click="item.action" :href="item.url":target="item.target" v-bind="props.action">
                            <span :class="item.icon" />
                            <span class="ml-2">{{ item.label }}</span>
                            <span v-if="hasSubmenu" class="pi pi-angle-right ml-auto" />
                        </a>
                    </template>
                    <!-- <template #submenulabel="{ item }">
                        <span class="text-primary font-bold">{{ item.label }}</span>
                    </template>-->

                </TieredMenu>
                </div>
            </aside>


            <!-- Main Content -->
            <main class="flex-1">
                <header class="fixed top-0 left-0 right-0 z-[1100] lg:left-60">
                    <Menubar :model="menuBarItems" class="w-full md:w-100 !border-l-0 !rounded-none">
                        <!-- <template #start>
                            <span class="pi pi-bars"></span>
                        </template> -->
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
                                    <span class="font-bold text-xs">Admin</span>
                                    <span class="text-xs">Admin</span>
                                </span>
                            </button>
                        </template>
                    </Menubar>
                    <!-- </nav> -->
                </header>

                <div class="md:mt-12 px-4 py-4 lg:ml-60">
                    <slot />
                    <!-- <RouterView />-->
                </div>

            </main>

        </div>
    </div>
</template>
