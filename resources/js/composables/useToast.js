import { useToast as usePrimeToast } from 'primevue/usetoast';
import { watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function useToast() {
    const toast = usePrimeToast();
    const { flash } = usePage();

    // Watch for flash messages
    watch(() => flash, (newFlash) => {
        if (newFlash.success) {
            toast.add({
                severity: 'success',
                summary: 'Success',
                detail: newFlash.success,
                life: 3000
            });
        }
        if (newFlash.error) {
            toast.add({
                severity: 'error',
                summary: 'Error',
                detail: newFlash.error,
                life: 3000
            });
        }
    }, { deep: true });

    const showSuccess = (message) => {
        toast.add({
            severity: 'success',
            summary: 'Success',
            detail: message,
            life: 3000
        });
    };

    const showError = (message) => {
        toast.add({
            severity: 'error',
            summary: 'Error',
            detail: message,
            life: 3000
        });
    };

    const showInfo = (message) => {
        toast.add({
            severity: 'info',
            summary: 'Info',
            detail: message,
            life: 3000
        });
    };

    const showWarning = (message) => {
        toast.add({
            severity: 'warn',
            summary: 'Warning',
            detail: message,
            life: 3000
        });
    };

    return {
        toast,
        showSuccess,
        showError,
        showInfo,
        showWarning
    };
}
