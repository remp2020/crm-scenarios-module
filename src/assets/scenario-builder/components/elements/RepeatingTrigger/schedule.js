export const DAILY = 'daily';
export const WEEKLY = 'weekly';
export const MONTHLY = 'monthly';

export const FREQUENCIES = [
    {value: DAILY, label: 'Daily'},
    {value: WEEKLY, label: 'Weekly'},
    {value: MONTHLY, label: 'Monthly'}
];

// ISO-8601 day numbers, 1 = Monday
export const WEEK_DAYS = [
    {value: 1, label: 'Monday'},
    {value: 2, label: 'Tuesday'},
    {value: 3, label: 'Wednesday'},
    {value: 4, label: 'Thursday'},
    {value: 5, label: 'Friday'},
    {value: 6, label: 'Saturday'},
    {value: 7, label: 'Sunday'}
];

export const MONTH_DAYS = Array.from({length: 31}, (item, index) => index + 1);

export const defaultDayFor = (frequency) => {
    if (frequency === WEEKLY) {
        return 1;
    }
    if (frequency === MONTHLY) {
        return 1;
    }
    return null;
};

/**
 * Short human-readable description of the schedule, used as the node caption.
 */
export const describeSchedule = (frequency, day, time) => {
    if (frequency === WEEKLY) {
        const weekDay = WEEK_DAYS.find(item => item.value === day);
        return weekDay ? `${weekDay.label} at ${time}` : null;
    }

    if (frequency === MONTHLY) {
        return day ? `Day ${day} at ${time}` : null;
    }

    return `Daily at ${time}`;
};
