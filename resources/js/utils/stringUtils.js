export const truncate = (text, { length = 15, suffix = '...', fallback = 'TBD' } = {}) => {
  if (!text) return fallback;
  if (text.length > length) {
    return text.substring(0, length) + suffix;
  }
  return text;
};
