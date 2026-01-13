import { ref, computed, onMounted, onUnmounted } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import axios from 'axios';
import { format } from 'date-fns';
import { useToast } from 'primevue/usetoast';

export function useNotifications(popoverRef) {
    const page = usePage();
    const toast = useToast();
    const notifications = ref([]);
    const displayLimit = ref(10);
    let poller = null;

    const fetchNotifications = async () => {
        try {
            const response = await axios.get(route('api.notifications.index'));
            notifications.value = response.data.map(notification => ({
                ...notification,
                timestamp: new Date(notification.created_at),
                formattedTimestamp: format(new Date(notification.created_at), "MMMM dd, yyyy HH:mm"),
                isRead: notification.read
            }));
        } catch (error) {
            console.error("Error fetching notifications:", error);
        }
    };

    const markNotificationAsRead = async (notification) => {
        if (!notification.isRead) {
            try {
                await axios.put(route('api.notifications.update', { notification: notification.id }));
                notification.isRead = true;
            } catch (error) {
                console.error("Error marking notification as read:", error);
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

    const onNotificationClick = (notification) => {
        markNotificationAsRead(notification);
        // Assuming the notification message contains a link or you have a default view
        // if (notification.link) {
        //     router.visit(notification.link);
        // }
        if (popoverRef.value) {
            popoverRef.value.hide();
        }
    };
    
    const pollForUpdates = async (isInitialLoad = false) => {
        try {
            const response = await axios.get(route('api.notifications.index'));
            const newNotifications = response.data.map(notification => ({
                ...notification,
                timestamp: new Date(notification.created_at),
                formattedTimestamp: format(new Date(notification.created_at), "MMMM dd, yyyy HH:mm"),
                isRead: notification.read
            }));

            if (!isInitialLoad && newNotifications.length > notifications.value.length) {
                const latestNewNotification = newNotifications[0];
                toast.add({
                    severity: 'info',
                    summary: 'New Notification',
                    detail: latestNewNotification.message.substring(0, 100) + (latestNewNotification.message.length > 100 ? '...' : ''),
                    life: 6000
                });
                playNotificationSound();
            }
            notifications.value = newNotifications;
        } catch (error) {
            console.error("Error fetching updates:", error);
        }
    };

    const loadMore = () => { displayLimit.value += 10; };
    const markAllAsRead = () => notifications.value.forEach(n => !n.isRead && markNotificationAsRead(n));
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
        fetchNotifications();
        poller = setInterval(() => pollForUpdates(false), 10000);
    });

    onUnmounted(() => {
        if (poller) clearInterval(poller);
    });

    return { notifications, unreadCount, hasUnreadNotifications, displayedNotifications, showLoadMore, onNotificationClick, markAllAsRead, loadMore, toggleNotifications };
}

