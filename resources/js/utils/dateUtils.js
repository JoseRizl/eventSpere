import { format, parse, parseISO, isValid } from 'date-fns';

/**
 * Robustly parses a date string that could be in various formats (ISO, yyyy-MM-dd, MMM-dd-yyyy)
 * and combines it with a time string.
 * @param {string | Date | number | null} dateInput - The date value.
 * @param {string | null} timeStr - The time string in 'HH:mm' format.
 * @returns {Date | null} A Date object or null if invalid.
 */
export const getFullDateTime = (dateInput, timeStr) => {
  if (!dateInput) return null;

  let date;
  if (dateInput instanceof Date && isValid(dateInput)) {
    date = new Date(dateInput);
  } else if (typeof dateInput === 'string') {
    // If it's a date string with time, parse it directly
    if (dateInput.includes('T') || dateInput.includes(' ')) {
      date = parseISO(dateInput);
    } else {
      // For date-only strings, parse as local date
      date = parse(dateInput, 'yyyy-MM-dd', new Date());
    }
  } else {
    date = new Date(dateInput);
  }

  if (!isValid(date)) return null;

  // Handle time string
  if (timeStr && /^\d{1,2}:\d{2}(?::\d{2})?$/.test(timeStr)) {
    const [hours, minutes] = timeStr.split(':').map(Number);
    if (!isNaN(hours) && !isNaN(minutes)) {
      date.setHours(hours, minutes, 0, 0);
    }
  } else if (timeStr === null || timeStr === undefined) {
    // If time is explicitly set to null/undefined, keep the original time
    // Otherwise, default to start of day
    date.setHours(0, 0, 0, 0);
  }

  return date;
};

/**
 * Formats a date string or Date object into a consistent 'MMM-dd-yyyy' display format.
 * @param {string | Date | number | null} dateString - The date to format.
 * @returns {string} The formatted date string or 'Invalid Date'.
 */
export const formatDisplayDate = (dateString) => {
  if (!dateString) return '';
  try {
    // getFullDateTime is robust enough to parse various inputs
    const date = getFullDateTime(dateString, null);
    return isValid(date) ? format(date, 'MMM-dd-yyyy') : 'Invalid Date';
  } catch {
    return 'Invalid Date';
  }
};

/**
 * Formats a time string from 'HH:mm' to 'hh:mm a' display format.
 * @param {string | null} timeString - The time string to format.
 * @returns {string} The formatted time string or 'Invalid Time'.
 */
export const formatDisplayTime = (timeString) => {
  if (!timeString) return '';
  try {
    // Try parsing with seconds first
    let parsed = parse(timeString, 'HH:mm:ss', new Date());
    if (!isValid(parsed)) {
        // Fallback to parsing without seconds
        parsed = parse(timeString, 'HH:mm', new Date());
    }

    if (isValid(parsed)) {
        return format(parsed, 'hh:mm a');
    }

    // If it's still not valid, it might be a full ISO string
    const isoParsed = parseISO(timeString);
    if(isValid(isoParsed)) {
        return format(isoParsed, 'hh:mm a');
    }

    return 'Invalid Time';
  } catch {
    return 'Invalid Time';
  }
};
