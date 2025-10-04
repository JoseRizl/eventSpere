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
    date = dateInput;
  } else if (typeof dateInput === 'string') {
    // Attempt to parse multiple common formats
    date = parseISO(dateInput); // Handles 'yyyy-MM-dd' and full ISO strings
    if (!isValid(date)) {
      date = parse(dateInput, 'MMM-dd-yyyy', new Date());
    }
  } else {
    date = new Date(dateInput);
  }

  if (isNaN(date.getTime())) return null;

  if (timeStr && /^\d{2}:\d{2}$/.test(timeStr)) {
    const [hours, minutes] = timeStr.split(':').map(Number);
    if (!isNaN(hours) && !isNaN(minutes)) {
      date.setHours(hours, minutes, 0, 0);
    }
  } else {
    // If no time is provided, treat it as start of the day to avoid timezone issues with date-only strings
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
    const parsed = parse(timeString, 'HH:mm', new Date());
    return format(parsed, 'hh:mm a');
  } catch {
    return 'Invalid Time';
  }
};
