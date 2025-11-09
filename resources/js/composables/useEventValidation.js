import { parse, format, parseISO, isValid, endOfDay, startOfDay } from 'date-fns';
import { getFullDateTime } from '@/utils/dateUtils.js';

export function useEventValidation() {
    const formatDateForPicker = (dateInput) => {
        if (!dateInput) return null;
        try {
            if (dateInput instanceof Date && isValid(dateInput)) {
                return dateInput;
            }
            let date;
            if (typeof dateInput === 'string') {
                // Handle time-only strings like "HH:mm"
                if (/^\d{2}:\d{2}$/.test(dateInput)) {
                    return dateInput; // Return as is, let other functions handle it
                }

                date = parseISO(dateInput);
                if (!isValid(date)) {
                    // Handle YYYY-MM-DD format from database
                    date = parse(dateInput, 'yyyy-MM-dd', new Date());
                }
                // Handle MMM-dd-yyyy format from other parts of the app
                if (!isValid(date)) {
                    date = parse(dateInput, 'MMM-dd-yyyy', new Date());
                }
            } else {
                date = new Date(dateInput);
            }
            return isValid(date) ? date : null;
        } catch {
            return null;
        }
    };

    const formatDisplayDate = (dateString) => {
        if (!dateString) return '';
        const date = formatDateForPicker(dateString);
        return date ? format(date, 'MMM-dd-yyyy') : 'Invalid Date';
    };

    const validateEvent = (eventData, options = {}) => {
        const { isSubmitting = false, originalEvent = null } = options;
        try {
            const startDate = formatDateForPicker(eventData.startDate);
            const endDate = formatDateForPicker(eventData.endDate);

            // When editing, prevent moving a future event's start date to the past.
            if (originalEvent && startDate) {
                const originalStartDate = formatDateForPicker(originalEvent.startDate);
                const today = new Date();
                today.setHours(0, 0, 0, 0);

                if (originalStartDate && originalStartDate >= today && startDate < today) {
                    return "A future event's start date cannot be moved to the past.";
                }
            }
            if (isSubmitting) {
                if (!startDate || !endDate) {
                    return "Both start and end dates are required.";
                }
                if (startDate && endDate && endDate < startDate) {
                    return "End date cannot be earlier than start date.";
                }
            }

            const isAllDay = eventData.isAllDay;

            if (!isAllDay && isSubmitting) {
                if (!eventData.startTime || !eventData.endTime) {
                    return "Start and end times are required for non-all-day events.";
                }
                const startDateTime = getFullDateTime(startDate, eventData.startTime);
                const endDateTime = getFullDateTime(endDate, eventData.endTime);
                if (endDateTime <= startDateTime) {
                    return "End date/time must be after start date/time.";
                }
            }

            // Activity validation (if activities exist on the event data)
            if (Array.isArray(eventData.activities)) {
                const eventStartDT = !isAllDay && startDate && eventData.startTime ? getFullDateTime(startDate, eventData.startTime) : null;
                const eventEndDT = !isAllDay && endDate && eventData.endTime ? getFullDateTime(endDate, eventData.endTime) : null;

                for (let i = 0; i < eventData.activities.length; i++) {
                    const act = eventData.activities[i];
                    if (!act) continue;

                    const actDate = formatDateForPicker(act.date);

                    // Normalize all dates to the start of the day for a pure date comparison
                    const normActDate = actDate ? startOfDay(actDate) : null;
                    const normStartDate = startDate ? startOfDay(startDate) : null;
                    const normEndDate = endDate ? startOfDay(endDate) : null;

                    if (!normActDate || (normStartDate && normActDate < normStartDate) || (normEndDate && normActDate > normEndDate)) {
                        return `Activity ${i + 1}: date must be within the event's date range.`;
                    }

                    const actStartDT = act.startTime ? parse(act.startTime, 'HH:mm', actDate) : null;
                    const actEndDT = act.endTime ? parse(act.endTime, 'HH:mm', actDate) : null;

                    if (actStartDT && actEndDT && actEndDT <= actStartDT) {
                        return `Activity ${i + 1}: end time must be after start time.`; // This is now correctly validated
                    }

                    // For non-all-day events, check if activity times are within the event's time window.
                    if (!isAllDay && eventStartDT && eventEndDT && actStartDT) {
                        if (actStartDT < eventStartDT || actStartDT > eventEndDT) {
                            return `Activity ${i + 1} start time is outside the event's window (${format(eventStartDT, 'p')} - ${format(eventEndDT, 'p')}).`;
                        }
                        if (actEndDT && (actEndDT < eventStartDT || actEndDT > eventEndDT)) {
                            return `Activity ${i + 1} end time is outside the event's window (${format(eventStartDT, 'p')} - ${format(eventEndDT, 'p')}).`;
                        }
                    }
                }
            }

            return ""; // No error
        } catch (e) {
            return "An error occurred during date validation.";
        }
    };

    return { validateEvent, formatDateForPicker, formatDisplayDate };
}
