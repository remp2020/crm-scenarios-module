const DATE_TIME_OPTIONS = {
  day: 'numeric',
  month: 'short',
  year: 'numeric',
  hour: '2-digit',
  minute: '2-digit',
  second: '2-digit'
};

const TIME_OPTIONS = {
  hour: '2-digit',
  minute: '2-digit',
  second: '2-digit'
};

const MINUTE = 60;
const HOUR = 60 * MINUTE;
const DAY = 24 * HOUR;

function parse(value) {
  if (!value) {
    return null;
  }

  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? null : date;
}

// the API sends RFC3339 timestamps, everything is rendered in the browser's locale and timezone
export function formatDateTime(value) {
  const date = parse(value);
  return date === null ? '—' : date.toLocaleString(undefined, DATE_TIME_OPTIONS);
}

export function formatTime(value) {
  const date = parse(value);
  return date === null ? '—' : date.toLocaleTimeString(undefined, TIME_OPTIONS);
}

/**
 * Coarse duration between two timestamps - two units at most, so it stays readable
 * in the narrow sidebar ("2 d 18 h", "4 m 12 s", "< 1 s").
 */
export function formatDuration(from, to) {
  const start = parse(from);
  const end = parse(to);

  if (start === null || end === null) {
    return '—';
  }

  const seconds = Math.max(0, Math.round((end.getTime() - start.getTime()) / 1000));

  if (seconds < 1) {
    return '< 1 s';
  }
  if (seconds < MINUTE) {
    return `${seconds} s`;
  }
  if (seconds < HOUR) {
    return join(Math.floor(seconds / MINUTE), 'm', Math.floor(seconds % MINUTE), 's');
  }
  if (seconds < DAY) {
    return join(Math.floor(seconds / HOUR), 'h', Math.floor((seconds % HOUR) / MINUTE), 'm');
  }
  return join(Math.floor(seconds / DAY), 'd', Math.floor((seconds % DAY) / HOUR), 'h');
}

function join(value, unit, rest, restUnit) {
  return rest === 0 ? `${value} ${unit}` : `${value} ${unit} ${rest} ${restUnit}`;
}
