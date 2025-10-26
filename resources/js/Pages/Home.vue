<script setup>
import { ref, onMounted, computed, watch } from "vue";
import { addMonths, isBefore } from 'date-fns';
import { loadToggleState, saveToggleState } from '@/utils/localStorage.js';
import { usePage, router } from "@inertiajs/vue3";
import { useEvents } from '@/composables/useEvents.js';
import { useAnnouncements } from '@/composables/useAnnouncements.js';
import EventCalendar from '@/Components/EventCalendar.vue';
import AnnouncementsBoard from '@/Components/AnnouncementsBoard.vue';
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

const page = usePage();
const user = computed(() => page.props.auth.user);
const allUsers = computed(() => page.props.all_users || []);
const currentView = ref('events'); // 'events' or 'announcements'
const {
    searchQuery, startDateFilter, endDateFilter, showDateFilter,
    toggleDateFilter, clearDateFilter, clearFilters
} = useFilters();

const {
    allNews, fetchEvents, filteredNews, ongoingEvents, eventsThisMonth, upcomingEvents,
    isNewEvent, getUpcomingTag, getUpcomingSeverity
} = useEvents({ searchQuery, startDateFilter, endDateFilter });

// Create a separate, unfiltered instance of useEvents just for the carousel.
const { carouselEvents } = useEvents({
    // We pass empty refs here so the carousel is not affected by the page's filters.
    searchQuery: ref(''), startDateFilter: ref(null), endDateFilter: ref(null)
});

const {
    eventAnnouncements, fetchAnnouncements, addAnnouncement, updateAnnouncement, deleteAnnouncementById
} = useAnnouncements({ searchQuery, startDateFilter, endDateFilter }, allNews);

onMounted(async () => {
    await fetchEvents();
    // fetchAnnouncements depends on allNews from fetchEvents
    fetchAnnouncements(); // fire-and-forget for faster initial render
});

const announcementEvents = computed(() => {
  const threeMonthsFromNow = addMonths(new Date(), 3);
  const upcomingFiltered = upcomingEvents.value.filter(event =>
    isBefore(new Date(event.startDate), threeMonthsFromNow)
  );
  return [...ongoingEvents.value, ...upcomingFiltered];
});

</script>

<template>
  <div class="min-h-screen flex flex-col">
    <!-- News and Update Title -->
    <!-- <h1 class="text-2xl font-bold mt-4 text-center text-slate-800">News and Updates</h1> -->

    <!-- Carousel Banner -->
    <div v-if="carouselEvents.length > 0" class="w-full relative group/carousel">
        <Carousel :value="carouselEvents" :numVisible="1" :numScroll="1" circular :autoplayInterval="5000" :showIndicators="true" class="home-carousel">
            <template #item="slotProps">
                <div class="relative w-full h-56 md:h-96 lg:h-[400px] bg-gray-700 overflow-hidden" @click.stop="router.visit(route('event.details', { id: slotProps.data.id }))">
                    <Link :href="route('event.details', { id: slotProps.data.id })" preserve-scroll>
                        <img v-if="slotProps.data.image" :src=" slotProps.data.image" :alt="slotProps.data.title" class="w-full h-full object-cover transition-transform duration-300 group-hover/carousel:scale-105" draggable="false">
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
        <SearchFilterBar
          v-model:searchQuery="searchQuery"
          :placeholder="currentView === 'events' ? 'Search events...' : 'Search announcements...'"
          :show-date-filter="true"
          :is-date-filter-active="showDateFilter"
          :show-clear-button="!!(searchQuery || startDateFilter || endDateFilter)"
          @toggle-date-filter="toggleDateFilter"
          @clear-filters="clearFilters"
        />
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
        <div v-if="ongoingEvents.length > 0" class="w-full mt-6 bg-white/60 p-4 rounded-xl border border-gray-200/80 shadow-sm">
          <div class="flex flex-col sm:flex-row justify-between items-center mb-2 gap-2">
            <h2 class="section-title text-center sm:text-left flex-grow">Ongoing Events</h2>
            <Button
              size="small"
              :icon="showOngoingEvents ? 'pi pi-chevron-up' : 'pi pi-chevron-down'"
              :label="showOngoingEvents ? 'Hide' : 'Show'"
              @click="showOngoingEvents = !showOngoingEvents"
              class="p-button-text"
            />
          </div>
          <div v-if="showOngoingEvents">
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
              <div v-for="(event, index) in ongoingEvents" :key="'ongoing-' + index" class="group relative h-full">
                <Link :href="route('event.details', { id: event.id })" preserve-scroll class="block h-full">
                  <Card class="h-full flex flex-col justify-between compact-event-card">
                    <template #header>
                      <div class="h-36 bg-gray-300 rounded-t-lg flex items-center justify-center overflow-hidden relative">
                        <div class="absolute inset-0 bg-gradient-to-b from-gray-900/20 to-transparent z-10"></div>
                        <img v-if="event.image" :src="event.image" class="h-full w-full object-cover group-hover:scale-105 transition-transform duration-300" alt="Event image" loading="lazy"/>
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
        <div v-if="eventsThisMonth.length > 0" class="w-full mt-6 bg-white/60 p-4 rounded-xl border border-gray-200/80 shadow-sm">
          <div class="flex flex-col sm:flex-row justify-between items-center mb-2 gap-2">
            <h2 class="section-title text-center sm:text-left flex-grow">Events This Month</h2>
            <Button
              size="small"
              :icon="showEventsThisMonth ? 'pi pi-chevron-up' : 'pi pi-chevron-down'"
              :label="showEventsThisMonth ? 'Hide' : 'Show'"
              @click="showEventsThisMonth = !showEventsThisMonth"
              class="p-button-text"
            />
          </div>
          <div v-if="showEventsThisMonth">
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
              <div v-for="(event, index) in eventsThisMonth" :key="'month-' + index" class="group relative h-full">
                <Link :href="route('event.details', { id: event.id })" preserve-scroll class="block h-full">
                  <Card class="h-full flex flex-col justify-between compact-event-card">
                    <template #header>
                      <div class="h-36 bg-gray-300 rounded-t-lg flex items-center justify-center overflow-hidden relative">
                        <div class="absolute inset-0 bg-gradient-to-b from-gray-900/20 to-transparent z-10"></div>
                        <img v-if="event.image" :src="event.image" class="h-full w-full object-cover group-hover:scale-105 transition-transform duration-300" alt="Event image" loading="lazy"/>
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
        <div v-if="upcomingEvents.length > 0" class="w-full mt-6 bg-white/60 p-4 rounded-xl border border-gray-200/80 shadow-sm">
          <div class="flex flex-col sm:flex-row justify-between items-center mb-2 gap-2">
            <h2 class="section-title text-center sm:text-left flex-grow">Upcoming Events</h2>
            <Button
              size="small"
              :icon="showUpcomingEvents ? 'pi pi-chevron-up' : 'pi pi-chevron-down'"
              :label="showUpcomingEvents ? 'Hide' : 'Show'"
              @click="showUpcomingEvents = !showUpcomingEvents"
              class="p-button-text"
            />
          </div>
          <div v-if="showUpcomingEvents">
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
              <div v-for="(event, index) in upcomingEvents" :key="'upcoming-' + index" class="group relative h-full">
                <Link :href="route('event.details', { id: event.id })" preserve-scroll class="block h-full">
                  <Card class="h-full flex flex-col justify-between compact-event-card">
                    <template #header>
                      <div class="h-36 bg-gray-300 rounded-t-lg flex items-center justify-center overflow-hidden relative">
                        <div class="absolute inset-0 bg-gradient-to-b from-gray-900/20 to-transparent z-10"></div>
                        <img v-if="event.image" :src="event.image" class="h-full w-full object-cover group-hover:scale-105 transition-transform duration-300" alt="Event image" loading="lazy"/>
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
            <h2 class="section-title mb-6 mt-6 text-center">Event Calendar</h2>
          <EventCalendar :events="filteredNews" />
        </div>
      </div>
    </div>

    <!-- Announcement Board -->
    <div v-if="currentView === 'announcements'" class="w-full mt-8">
        <AnnouncementsBoard
            :announcements="eventAnnouncements"
            :search-query="searchQuery"
            :start-date-filter="startDateFilter"
            :end-date-filter="endDateFilter"
            context="home"
            :events-for-picker="announcementEvents"
            @announcement-added="addAnnouncement"
            @announcement-updated="updateAnnouncement"
            @announcement-deleted="deleteAnnouncementById"
        />
    </div>

    </div>
    <!-- Dialogs -->
    <LoadingSpinner :show="saving" />
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

.date-filter-calendar {
  width: 100%;
}

.home-carousel {
    position: relative;
    width: 100%;
}

:deep(.home-carousel .p-carousel-prev-button),
:deep(.home-carousel .p-carousel-next-button) {
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

.group\/carousel:hover :deep(.home-carousel .p-carousel-prev-button),
.group\/carousel:hover :deep(.home-carousel .p-carousel-next-button) {
    opacity: 1; /* Show on hover of the container */
}

:deep(.home-carousel .p-carousel-prev-button:hover),
:deep(.home-carousel .p-carousel-next-button:hover) {
    background-color: rgba(255, 255, 255, 0.7) !important;
}

:deep(.home-carousel .p-carousel-prev-button) {
    left: 1rem;
}

:deep(.home-carousel .p-carousel-next-button) {
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

:deep(.home-carousel .p-carousel-indicator.p-highlight button),
:deep(.home-carousel .p-carousel-indicator[data-p-active="true"] button) {
    background-color: rgba(255, 255, 255, 0.9) !important;
    width: 2rem;
    border-radius: 9999px;
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
