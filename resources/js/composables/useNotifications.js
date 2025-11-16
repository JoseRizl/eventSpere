import { ref, computed, onMounted, onUnmounted } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import axios from 'axios';
import { format, subMonths } from 'date-fns';
import { useToast } from 'primevue/usetoast';

export function useNotifications(popoverRef) {
    const page = usePage();
    const toast = useToast();

    const allUsers = computed(() => page.props.all_users || []);
    const notifications = ref([]);
    const readNotificationIds = ref([]);
    const displayLimit = ref(10);
    let poller = null;

    const loadReadNotifications = () => {
        const storedIds = localStorage.getItem('readNotificationIds');
        if (storedIds) {
            readNotificationIds.value = JSON.parse(storedIds);
        }
    };

    const markNotificationAsRead = (notificationId) => {
        if (!readNotificationIds.value.includes(notificationId)) {
            readNotificationIds.value.push(notificationId);
            localStorage.setItem('readNotificationIds', JSON.stringify(readNotificationIds.value));
            const notification = notifications.value.find(n => n.id === notificationId);
            if (notification) {
                notification.isRead = true;
            }
        }
    };

    const playNotificationSound = () => {
        if (typeof window === 'undefined') return;
        const audio = new Audio('/sounds/notification.mp3');
        audio.play().catch(error => {
            console.log("Notification sound was blocked by the browser.", error);
        });
    };

    const pollForUpdates = async (isInitialLoad = false) => {
        try {
            const [eventsResponse, eventAnnouncementsResponse] = await Promise.all([
                axios.get(route('api.events.index')),
                axios.get(route('api.announcements.index'))
            ]);

            const allEvents = [...eventsResponse.data].filter((event) => !event.archived);
            const eventMap = allEvents.reduce((map, event) => {
                map[event.id] = event;
                return map;
            }, {});

            const employeesMap = allUsers.value.reduce((map, emp) => {
                map[emp.id] = emp;
                return map;
            }, {});

            const oneMonthAgo = subMonths(new Date(), 1);

            // Ensure announcement data is always an array before mapping
            const announcementsData = Array.isArray(eventAnnouncementsResponse.data) ? eventAnnouncementsResponse.data : [];

            const announcementNotifications = announcementsData.map(ann => ({
                id: `ann-${ann.id}`,
                type: 'announcement',
                timestamp: new Date(ann.timestamp),
                data: {
                    ...ann,
                    event: eventMap[ann.event_id],
                    employee: employeesMap[ann.user_id] || { name: 'Admin' }
                },
                message: ann.message,
                title: eventMap[ann.event_id] ? `Announcement: ${eventMap[ann.event_id]?.title}` : 'General Announcement',
                link: eventMap[ann.event_id] ? route('event.details', { id: eventMap[ann.event_id].id, view: 'announcements' }) : null,
                formattedTimestamp: format(new Date(ann.timestamp), "MMMM dd, yyyy HH:mm"),
            }));

            const newEventNotifications = allEvents
                .filter(event => event.createdAt)
                .map(event => ({
                    id: `evt-${event.id}`,
                    type: 'newEvent',
                    timestamp: new Date(event.createdAt),
                    data: event,
                    message: `New event created: ${event.title}`,
                    title: 'New Event',
                    link: route('event.details', { id: event.id }),
                    formattedTimestamp: format(new Date(event.createdAt), "MMMM dd, yyyy HH:mm"),
                }));

            const allNotifications = [...announcementNotifications, ...newEventNotifications]
                .filter(n => n.timestamp >= oneMonthAgo)
                .sort((a, b) => b.timestamp - a.timestamp)
                .map(n => ({
                    ...n,
                    isRead: readNotificationIds.value.includes(n.id)
                }));

            if (!isInitialLoad && allNotifications.length > 0 && notifications.value.length > 0 && allNotifications[0].id !== notifications.value[0].id) {
                const newNotification = allNotifications[0];
                toast.add({
                    severity: 'info',
                    summary: newNotification.type === 'announcement' ? 'New Announcement' : 'New Event',
                    detail: newNotification.message.substring(0, 100) + (newNotification.message.length > 100 ? '...' : ''),
                    life: 6000
                });
                playNotificationSound();
            }
            notifications.value = allNotifications;
        } catch (error) {
            console.error("Error fetching updates:", error);
        }
    };

    const onNotificationClick = (notification) => {
        markNotificationAsRead(notification.id);
        if (notification.link) {
            router.visit(notification.link);
            if (popoverRef.value) {
                popoverRef.value.hide();
            }
        }
    };

    const loadMore = () => { displayLimit.value += 10; };
    const markAllAsRead = () => notifications.value.forEach(n => !n.isRead && markNotificationAsRead(n.id));
    const unreadCount = computed(() => notifications.value.filter(n => !n.isRead).length);
    const hasUnreadNotifications = computed(() => unreadCount.value > 0);
    const displayedNotifications = computed(() => notifications.value.slice(0, displayLimit.value));
    const showLoadMore = computed(() => notifications.value.length > displayLimit.value);
    const toggleNotifications = (event) => {
        if (popoverRef.value) {
            popoverRef.value.toggle(event);
            displayLimit.value = 10;
        }
    };

    onMounted(() => {
        loadReadNotifications();
        pollForUpdates(true);
        poller = setInterval(() => pollForUpdates(false), 10000);
    });

    onUnmounted(() => {
        if (poller) clearInterval(poller);
    });

    return { notifications, unreadCount, hasUnreadNotifications, displayedNotifications, showLoadMore, onNotificationClick, markAllAsRead, loadMore, toggleNotifications };
}
