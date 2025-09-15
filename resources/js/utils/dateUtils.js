import { format, parse, parseISO, isValid } from 'date-fns';

export const getFullDateTime = (dateInput, timeStr) => {
  if (!dateInput) return null;

  let date;
  if (typeof dateInput === 'string' && /^[A-Za-z]{3}-\d{2}-\d{4}$/.test(dateInput)) {
    date = parse(dateInput, 'MMM-dd-yyyy', new Date());
  } else {
    date = new Date(dateInput);
  }

  if (isNaN(date.getTime())) return null;

  if (timeStr) {
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

export const formatDisplayDate = (dateString) => {
  if (!dateString) return '';
  try {
    let date = parseISO(dateString);
    if (isValid(date)) return format(date, 'MMM-dd-yyyy');

    date = parse(dateString, 'yyyy-MM-dd', new Date());
    if (isValid(date)) return format(date, 'MMM-dd-yyyy');

    date = parse(dateString, 'MMM-dd-yyyy', new Date());
    return isValid(date) ? format(date, 'MMM-dd-yyyy') : 'Invalid Date';
  } catch {
    return 'Invalid Date';
  }
};

export const formatDisplayTime = (timeString) => {
  if (!timeString) return '';
  try {
    const parsed = parse(timeString, 'HH:mm', new Date());
    return format(parsed, 'hh:mm a');
  } catch {
    return 'Invalid Time';
  }
};
