export function loadToggleState(key, defaultValue) {
  const saved = localStorage.getItem(key);
  return saved !== null ? JSON.parse(saved) : defaultValue;
}

export function saveToggleState(key, value) {
  localStorage.setItem(key, JSON.stringify(value));
}
