<script setup>
import { ref, onMounted, computed, watch, nextTick } from "vue";
import { getFullDateTime } from '@/utils/dateUtils.js';
import { loadToggleState, saveToggleState } from '@/utils/localStorage.js';
import { Link, usePage, router } from "@inertiajs/vue3";
import { useEvents } from '@/composables/useEvents.js';
import { useAnnouncements } from '@/composables/useAnnouncements.js';
import EventCalendar from '@/Components/EventCalendar.vue';
import ConfirmationDialog from '@/Components/ConfirmationDialog.vue';
import SuccessDialog from '@/Components/SuccessDialog.vue';
import LoadingSpinner from '@/Components/LoadingSpinner.vue';
import Avatar from 'primevue/avatar';
import InputText from 'primevue/inputtext';
import DatePicker from 'primevue/datepicker';
import Button from 'primevue/button';
import Carousel from 'primevue/carousel';
import Tag from 'primevue/tag';
import Card from 'primevue/card';
import { useFilters } from '@/composables/useFilters.js';
const showEventsThisMonth = ref(loadToggleState('showEventsThisMonth', true));
const saving = ref(false);
const showSuccessDialog = ref(false);
const successMessage = ref('');
const showOngoingEvents = ref(loadToggleState('showOngoingEvents', true));
const showUpcomingEvents = ref(loadToggleState('showUpcomingEvents', true));
watch(showEventsThisMonth, (val) => saveToggleState('showEventsThisMonth', val));
watch(showOngoingEvents, (val) => saveToggleState('showOngoingEvents', val));
watch(showUpcomingEvents, (val) => saveToggleState('showUpcomingEvents', val));
const showErrorDialog = ref(false);
const errorMessage = ref('');
const showDeleteAnnouncementConfirm = ref(false);
const announcementToDelete = ref(null);

const page = usePage();
const user = computed(() => page.props.auth.user);
const allUsers = computed(() => page.props.all_users || []);
const currentView = ref('events'); // 'events' or 'announcements'
const {
    searchQuery, startDateFilter, endDateFilter, showDateFilter,
    toggleDateFilter, clearDateFilter, clearFilters
} = useFilters();

const {
    allNews, fetchEvents, filteredNews, ongoingEvents, eventsThisMonth,
    upcomingEvents, carouselEvents, isNewEvent, getUpcomingTag, getUpcomingSeverity
} = useEvents({ searchQuery, startDateFilter, endDateFilter });

const {
    eventAnnouncements, fetchAnnouncements, filteredAnnouncements, deleteAnnouncement
} = useAnnouncements({ searchQuery, startDateFilter, endDateFilter }, allNews);

onMounted(async () => {
    await fetchEvents();
    // fetchAnnouncements depends on allNews from fetchEvents
    await fetchAnnouncements();
});

const promptDeleteAnnouncement = (announcement) => {
  announcementToDelete.value = announcement;
  showDeleteAnnouncementConfirm.value = true;
};

const confirmDeleteAnnouncement = async () => {
  if (!announcementToDelete.value) return;
  saving.value = true;
  try {
    await deleteAnnouncement(announcementToDelete.value.id);
    successMessage.value = 'Announcement deleted successfully.';
    showSuccessDialog.value = true;
  } catch (error) {
    console.error('Error deleting announcement:', error);
    errorMessage.value = 'Failed to delete announcement.';
    showErrorDialog.value = true;
  } finally {
    saving.value = false;
    showDeleteAnnouncementConfirm.value = false;
    announcementToDelete.value = null;
  }
};

</script>

<template>
  <div class="min-h-screen flex flex-col">
    <!-- News and Update Title -->
    <h1 class="text-2xl font-bold mt-4 text-center text-slate-800">News and Updates</h1>

    <!-- Carousel Banner -->
    <div v-if="carouselEvents.length > 0" class="w-full relative group/carousel">
        <Carousel :value="carouselEvents" :numVisible="1" :numScroll="1" circular :autoplayInterval="5000" class="home-carousel">
            <template #item="slotProps">
                <div class="relative w-full h-80 md:h-[500px] bg-gray-700 overflow-hidden">
                    <Link :href="route('event.details', { id: slotProps.data.id })">
                        <img v-if="slotProps.data.image" :src="slotProps.data.image" :alt="slotProps.data.title" class="w-full h-full object-cover transition-transform duration-300 group-hover/carousel:scale-105">
                        <div v-else class="w-full h-full bg-gray-300 flex items-center justify-center">
                            <img src="/resources/images/NCSlogo.png" class="w-32 h-32 object-contain opacity-50" alt="Event Placeholder" />
                        </div>
                        <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/40 to-transparent"></div>
                        <div class="absolute bottom-0 left-0 p-6 text-white">
                            <Tag :value="getUpcomingTag(slotProps.data)" :severity="getUpcomingSeverity(slotProps.data)" class="mb-2" />
                            <h2 class="text-2xl md:text-3xl font-bold line-clamp-2">{{ slotProps.data.title }}</h2>
                            <p class="text-sm mt-1">{{ slotProps.data.formattedDate }}</p>
                        </div>
                    </Link>
                </div>
            </template>
        </Carousel>
    </div>

    <div class="py-8 px-4">

    <!-- View Toggle -->
    <div class="w-full">
      <div class="flex border-b">
        <div class="flex items-center">
          <button
          @click="currentView = 'events'"
          :class="[
            'px-4 py-2 font-semibold transition-colors duration-200',
            currentView === 'events'
              ? 'border-b-2 border-purple-500 text-purple-600'
              : 'text-gray-500 hover:text-purple-600',
          ]"
        >
          Events
          </button>
          <button
          @click="currentView = 'announcements'"
          :class="[
            'px-4 py-2 font-semibold transition-colors duration-200',
            currentView === 'announcements'
              ? 'border-b-2 border-purple-500 text-purple-600'
              : 'text-gray-500 hover:text-purple-600',
          ]"
        >
          Announcements
          </button>
        </div>
      </div>

      <!-- Filters -->
      <div class="mt-4">
        <div class="flex items-center gap-2 w-full max-w-lg">
            <div class="relative flex-grow">
                <span class="search-icon">🔍</span>
                <InputText v-model="searchQuery" :placeholder="currentView === 'events' ? 'Search events...' : 'Search announcements...'" class="w-full" />
                <div class="absolute top-0 right-0 h-full flex items-center pr-2 gap-1">
                    <Button
                        icon="pi pi-calendar"
                        class="p-button-text text-gray-500 hover:bg-gray-200"
                        @click="toggleDateFilter"
                        :class="{ 'text-purple-600': showDateFilter }"
                        v-tooltip.top="'Filter by date'"
                    />
                    <Button v-if="searchQuery || startDateFilter || endDateFilter" icon="pi pi-times" class="p-button-text p-button-danger" @click="clearFilters" v-tooltip.top="'Clear All Filters'" />
                </div>
            </div>
        </div>
      </div>

      <!-- Date Filter Calendar -->
      <div v-if="showDateFilter" class="mt-2 bg-white p-4 rounded-lg shadow-md flex flex-col gap-2 max-w-lg">
          <div class="flex flex-col sm:flex-row gap-2 items-start w-full">
            <div class="flex flex-col gap-1 flex-1 w-full">
              <label>From:</label>
              <DatePicker
                v-model="startDateFilter"
                dateFormat="M-dd-yy"
                :showIcon="true"
                placeholder="Start date"
                class="date-filter-calendar"
              />
            </div>
            <div class="flex flex-col gap-1 flex-1 w-full">
              <label>To:</label>
              <DatePicker
                v-model="endDateFilter"
                dateFormat="M-dd-yy"
                :showIcon="true"
                placeholder="End date"
                class="date-filter-calendar"
              />
            </div>
          </div>
          <Button v-if="startDateFilter || endDateFilter" icon="pi pi-times" class="p-button-text p-button-rounded self-end text-red-500 hover:bg-red-500/10" @click="clearDateFilter" v-tooltip.top="'Clear date filter'" />
      </div>
    </div>

    <!-- Conditional Content -->
    <div v-if="currentView === 'events'" class="w-full">
      <div v-if="!filteredNews.length && (searchQuery || startDateFilter || endDateFilter)" class="w-full mt-8 flex flex-col items-center justify-center py-10 bg-gray-50 rounded-lg shadow-inner border border-dashed border-gray-300 text-center text-gray-500">
        <span class="text-4xl mb-2">🧐</span>
        <span class="font-semibold text-lg">No Events Found</span>
        <span class="text-sm">Try adjusting your search or date filters.</span>
      </div>
      <div v-else>
        <!-- Ongoing Events -->
        <div v-if="ongoingEvents.length > 0" class="w-full mt-4">
          <div class="relative flex justify-center items-center mb-2">
            <h2 class="text-2xl font-bold">Ongoing Events</h2>
            <div class="absolute right-0">
                <Button
                  size="small"
                  :icon="showOngoingEvents ? 'pi pi-chevron-up' : 'pi pi-chevron-down'"
                  :label="showOngoingEvents ? 'Hide' : 'Show'"
                  @click="showOngoingEvents = !showOngoingEvents"
                  class="p-button-text"
                />
            </div>
          </div>
          <div v-if="showOngoingEvents">
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
              <div v-for="(event, index) in ongoingEvents" :key="'ongoing-' + index" class="group relative h-full">
                <Link :href="route('event.details', { id: event.id })" preserve-scroll class="block h-full">
                  <Card class="h-full flex flex-col justify-between compact-event-card">
                    <template #header>
                      <div class="h-36 bg-gray-300 rounded-t-lg flex items-center justify-center overflow-hidden relative">
                        <div class="absolute inset-0 bg-gradient-to-b from-gray-900/20 to-transparent z-10"></div>
                        <img v-if="event.image" :src="event.image" class="h-full w-full object-cover group-hover:scale-105 transition-transform duration-300" alt="Event image"/>
                        <img v-else src="/resources/images/NCSlogo.png" class="w-24 h-24 object-contain opacity-50" alt="Event Placeholder" />
                        <Tag v-if="isNewEvent(event)" value="NEW" severity="success" class="absolute top-2 right-2 z-20"/>
                      </div>
                    </template>
                    <template #title>
                      <h3 class="text-base font-medium overflow-hidden line-clamp-1 cursor-help" v-tooltip.top="event.title">{{ event.title }}</h3>
                    </template>
                    <template #subtitle>
                      <div class="flex items-center gap-2">
                        <span class="text-sm text-gray-500">{{ event.formattedDate }}</span>
                        <Tag :value="getUpcomingTag(event)" :severity="getUpcomingSeverity(event)" class="text-xs"/>
                      </div>
                    </template>
                    <template #content>
                      <div class="relative flex-1 overflow-hidden h-10 flex items-center">
                        <p class="text-sm text-gray-600 line-clamp-2">{{ event.description }}</p>
                        <div class="absolute inset-0 bg-white/50 backdrop-blur-[2px] flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300 z-10">
                            <Button label="View Details" icon="pi pi-info-circle" class="p-button-text p-button-sm no-hover" @click.stop="$inertia.visit(route('event.details', { id: event.id }))"/>
                        </div>
                      </div>
                    </template>
                  </Card>
                </Link>
              </div>
            </div>
          </div>
        </div>

        <!-- Events This Month -->
        <div v-if="eventsThisMonth.length > 0" class="w-full mt-4">
          <div class="relative flex justify-center items-center mb-2">
            <h2 class="text-2xl font-bold">Events This Month</h2>
            <div class="absolute right-0">
                <Button size="small" :icon="showEventsThisMonth ? 'pi pi-chevron-up' : 'pi pi-chevron-down'" :label="showEventsThisMonth ? 'Hide' : 'Show'" @click="showEventsThisMonth = !showEventsThisMonth" class="p-button-text"/>
            </div>
          </div>
          <div v-if="showEventsThisMonth">
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
              <div v-for="(event, index) in eventsThisMonth" :key="'month-' + index" class="group relative h-full">
                <Link :href="route('event.details', { id: event.id })" preserve-scroll class="block h-full">
                  <Card class="h-full flex flex-col justify-between compact-event-card">
                    <template #header>
                      <div class="h-36 bg-gray-300 rounded-t-lg flex items-center justify-center overflow-hidden relative">
                        <div class="absolute inset-0 bg-gradient-to-b from-gray-900/20 to-transparent z-10"></div>
                        <img v-if="event.image" :src="event.image" class="h-full w-full object-cover group-hover:scale-105 transition-transform duration-300" alt="Event image"/>
                        <img v-else src="/resources/images/NCSlogo.png" class="w-24 h-24 object-contain opacity-50" alt="Event Placeholder" />
                        <Tag v-if="isNewEvent(event)" value="NEW" severity="success" class="absolute top-2 right-2 z-20"/>
                      </div>
                    </template>
                    <template #title>
                      <h3 class="text-base font-medium overflow-hidden line-clamp-1 cursor-help" v-tooltip.top="event.title">{{ event.title }}</h3>
                    </template>
                    <template #subtitle>
                      <div class="flex items-center gap-2">
                        <span class="text-sm text-gray-500">{{ event.formattedDate }}</span>
                        <Tag :value="getUpcomingTag(event)" :severity="getUpcomingSeverity(event)" class="text-xs"/>
                      </div>
                    </template>
                    <template #content>
                      <div class="relative flex-1 overflow-hidden h-10 flex items-center">
                        <p class="text-sm text-gray-600 line-clamp-2">{{ event.description }}</p>
                        <div class="absolute inset-0 bg-white/50 backdrop-blur-[2px] flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300 z-10">
                            <Button label="View Details" icon="pi pi-info-circle" class="p-button-text p-button-sm no-hover" @click.stop="$inertia.visit(route('event.details', { id: event.id }))"/>
                        </div>
                      </div>
                    </template>
                  </Card>
                </Link>
              </div>
            </div>
          </div>
        </div>

        <!-- Upcoming Events -->
        <div v-if="upcomingEvents.length > 0" class="w-full mt-4">
          <div class="relative flex justify-center items-center mb-2">
            <h2 class="text-2xl font-bold">Upcoming Events</h2>
            <div class="absolute right-0">
                <Button size="small" :icon="showUpcomingEvents ? 'pi pi-chevron-up' : 'pi pi-chevron-down'" :label="showUpcomingEvents ? 'Hide' : 'Show'" @click="showUpcomingEvents = !showUpcomingEvents" class="p-button-text"/>
            </div>
          </div>
          <div v-if="showUpcomingEvents">
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
              <div v-for="(event, index) in upcomingEvents" :key="'upcoming-' + index" class="group relative h-full">
                <Link :href="route('event.details', { id: event.id })" preserve-scroll class="block h-full">
                  <Card class="h-full flex flex-col justify-between compact-event-card">
                    <template #header>
                      <div class="h-36 bg-gray-300 rounded-t-lg flex items-center justify-center overflow-hidden relative">
                        <div class="absolute inset-0 bg-gradient-to-b from-gray-900/20 to-transparent z-10"></div>
                        <img v-if="event.image" :src="event.image" class="h-full w-full object-cover group-hover:scale-105 transition-transform duration-300" alt="Event image"/>
                        <img v-else src="/resources/images/NCSlogo.png" class="w-24 h-24 object-contain opacity-50" alt="Event Placeholder" />
                        <Tag v-if="isNewEvent(event)" value="NEW" severity="success" class="absolute top-2 right-2 z-20"/>
                      </div>
                    </template>
                    <template #title>
                      <h3 class="text-base font-medium overflow-hidden line-clamp-1 cursor-help" v-tooltip.top="event.title">{{ event.title }}</h3>
                    </template>
                    <template #subtitle>
                      <div class="flex items-center gap-2">
                        <span class="text-sm text-gray-500">{{ event.formattedDate }}</span>
                        <Tag :value="getUpcomingTag(event)" :severity="getUpcomingSeverity(event)" class="text-xs"/>
                      </div>
                    </template>
                    <template #content>
                      <div class="relative flex-1 overflow-hidden h-10 flex items-center">
                        <p class="text-sm text-gray-600 line-clamp-2">{{ event.description }}</p>
                        <div class="absolute inset-0 bg-white/50 backdrop-blur-[2px] flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300 z-10">
                            <Button label="View Details" icon="pi pi-info-circle" class="p-button-text p-button-sm no-hover" @click.stop="$inertia.visit(route('event.details', { id: event.id }))"/>
                        </div>
                      </div>
                    </template>
                  </Card>
                </Link>
              </div>
            </div>
          </div>
        </div>

        <!-- Event Calendar -->
        <div class="w-full">
            <h2 class="text-2xl font-bold mb-6 mt-6 text-center">Event Calendar</h2>
          <EventCalendar :events="filteredNews" />
        </div>
      </div>
    </div>

    <!-- Announcement Board -->
    <div v-if="currentView === 'announcements'" class="w-full mt-8">
      <h2 class="text-xl font-semibold mb-5">Announcement Board</h2>
      <div v-if="filteredAnnouncements.length" class="space-y-6">
        <div
          v-for="announcement in filteredAnnouncements"
          :key="announcement.id"
          :id="`announcement-${announcement.id}`"
          @click="announcement.event && router.visit(route('event.details', { id: announcement.event.id, view: 'announcements' }))"
          :class="['relative p-6 bg-white rounded-lg shadow-lg border-l-4 border-blue-500', announcement.event ? 'cursor-pointer hover:bg-gray-50 transition-colors' : '']"
        >
          <!-- User Avatar and Name -->
          <div v-if="user?.role === 'Admin' || user?.role === 'Principal'" class="absolute top-2 right-2 z-10">
            <Button
                icon="pi pi-trash"
                class="p-button-text p-button-danger p-button-rounded"
                @click.stop="promptDeleteAnnouncement(announcement)"
                v-tooltip.top="'Delete Announcement'"
            />
          </div>
          <div class="flex items-center mb-3">
            <Avatar
              image="https://primefaces.org/cdn/primevue/images/avatar/amyelsner.png"
              class="mr-2"
              shape="circle"
              size="small"
            />
            <span class="text-gray-600 text-sm font-semibold">{{ announcement.employee?.name || 'Admin' }}</span>
          </div>
          <div v-if="announcement.event" class="mb-3">
            <span class="text-sm text-gray-600">For event:</span>
            <Link :href="route('event.details', { id: announcement.event.id, view: 'announcements' })" @click.stop class="font-semibold text-blue-700 hover:underline ml-1 text-base">
              {{ announcement.event.title }}
            </Link>
          </div>
          <p class="text-gray-800 text-lg leading-relaxed">{{ announcement.message }}</p>
          <img
            v-if="announcement.image"
            :src="announcement.image"
            alt="Announcement image"
            class="mt-4 rounded-lg max-w-md mx-auto h-auto shadow-md"
          />
          <p class="text-sm text-gray-500 mt-4 text-right">{{ announcement.formattedTimestamp }}</p>
        </div>
      </div>
      <div v-else-if="searchQuery || startDateFilter || endDateFilter" class="flex flex-col items-center justify-center py-10 bg-gray-50 rounded-lg shadow-inner border border-dashed border-gray-300 text-center text-gray-500">
        <span class="text-4xl mb-2">🧐</span>
        <span class="font-semibold text-lg">No Announcements Found</span>
        <span class="text-sm">Try adjusting your search or date filters.</span>
      </div>
      <div v-else class="flex flex-col items-center justify-center py-10 bg-gray-50 rounded-lg shadow-inner border border-dashed border-gray-300 text-center text-gray-500">
        <span class="text-4xl mb-2">📢</span>
        <span class="font-semibold text-lg">No announcements yet</span>
        <span class="text-sm">Check back later for updates.</span>
      </div>
    </div>

    </div>
    <!-- Dialogs -->
    <LoadingSpinner :show="saving" />
    <SuccessDialog
      v-model:show="showSuccessDialog"
      :message="successMessage"
    />
    <div v-if="showErrorDialog" class="fixed inset-0 bg-black bg-opacity-30 flex items-center justify-center" style="z-index: 9998;">
      <div class="bg-white p-6 rounded-lg shadow-lg max-w-sm w-full">
        <h2 class="text-lg font-semibold text-red-700 mb-2">Error</h2>
        <p class="text-sm text-gray-700 mb-4">{{ errorMessage }}</p>
        <div class="flex justify-end">
          <button @click="showErrorDialog = false" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">Close</button>
        </div>
      </div>
    </div>
    <ConfirmationDialog
      v-model:show="showDeleteAnnouncementConfirm"
      title="Delete Announcement?"
      message="Are you sure you want to delete this announcement?"
      confirmText="Yes, Delete"
      confirmButtonClass="bg-red-600 hover:bg-red-700"
      @confirm="confirmDeleteAnnouncement"
    />
  </div>
</template>

<style scoped>
.z-20 {
  z-index: 20;
}

/* Remove menu item background in collapsed mode except on hover */
:deep(.p-tieredmenu.menu-collapsed .p-menuitem-link) {
  background: transparent !important;
}

/* On hover, show background only behind the icon */
:deep(.p-tieredmenu.menu-collapsed .p-menuitem-link:hover) {
  background-color: rgba(0, 0, 0, 0.04) !important;
}

:deep(.compact-event-card .p-card-body) {
    padding: 0.75rem 1rem;
    gap: 0.5rem;
}
:deep(.compact-event-card .p-card-subtitle) {
    margin-bottom: 0.25rem;
}
:deep(.compact-event-card .p-card-content) {
    padding-top: 0.25rem;
    padding-bottom: 0.25rem;
}
:deep(.compact-event-card .p-card-footer) {
    padding-top: 0;
}

.max-h-96.overflow-y-auto::-webkit-scrollbar {
  width: 6px;
}

.max-h-96.overflow-y-auto::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 10px;
}

.max-h-96.overflow-y-auto::-webkit-scrollbar-thumb {
  background: #c1c1c1; /* Lighter gray */
  border-radius: 10px;
}

.max-h-96.overflow-y-auto::-webkit-scrollbar-thumb:hover {
  background: #a8a8a8; /* Darker gray on hover */
}

.highlight {
  background-color: #e9d5ff; /* light purple */
  transition: background-color 0.5s ease-out;
}

.search-icon {
    position: absolute;
    left: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
}
.date-filter-calendar {
  width: 100%;
}

:deep(.home-carousel .p-carousel-prev),
:deep(.home-carousel .p-carousel-next) {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    z-index: 10;
    background-color: rgba(255, 255, 255, 0.3) !important;
    border-radius: 50%;
    width: 3rem;
    height: 3rem;
    color: black !important;
    transition: background-color 0.2s, opacity 0.2s;
    opacity: 0; /* Hide by default */
}

.group\/carousel:hover :deep(.home-carousel .p-carousel-prev),
.group\/carousel:hover :deep(.home-carousel .p-carousel-next) {
    opacity: 1; /* Show on hover of the container */
}

:deep(.home-carousel .p-carousel-prev:hover),
:deep(.home-carousel .p-carousel-next:hover) {
    background-color: rgba(255, 255, 255, 0.7) !important;
}

:deep(.home-carousel .p-carousel-prev) {
    left: 1rem;
}

:deep(.home-carousel .p-carousel-next) {
    right: 1rem;
}

/* Adjust indicator position to be a bit higher */
:deep(.home-carousel .p-carousel-indicator-list) {
    position: absolute;
    bottom: 0;
    left: 50%;
    transform: translateX(-50%);
    z-index: 10;
    padding: 1rem;
}

:deep(.home-carousel .p-carousel-indicator button) {
    background-color: rgba(255, 255, 255, 0.4) !important;
    width: 0.75rem;
    height: 0.75rem;
    border-radius: 50%;
    transition: width 0.3s ease, background-color 0.3s ease;
}

:deep(.home-carousel .p-carousel-indicator.p-highlight button) {
    background-color: rgba(255, 255, 255, 0.9) !important;
    width: 2rem;
    border-radius: 9999px;
}

:deep(.p-inputtext) {
    padding-left: 2.5rem; /* Space for search icon */
    padding-right: 5.5rem; /* Space for action buttons */
}

/* Custom Severity Tag Colors */
:deep(.p-tag-success) {
    background-color: #10B981; /* Tailwind green-500 */
    color: #ffffff;
}
:deep(.p-tag-warning) {
    background-color: #F59E0B; /* Tailwind amber-500 */
    color: #ffffff;
}
:deep(.p-tag-danger) {
    background-color: #EF4444; /* Tailwind red-500 */
    color: #ffffff;
}
:deep(.p-tag-info) {
    background-color: #3B82F6; /* Tailwind blue-500 */
    color: #ffffff;
}
</style>
