import React from 'react';
import PropTypes from 'prop-types';
import Grid from '@mui/material/Grid';
import TextField from '@mui/material/TextField';
import FormControl from '@mui/material/FormControl';
import FormHelperText from '@mui/material/FormHelperText';
import InputLabel from '@mui/material/InputLabel';
import MenuItem from '@mui/material/MenuItem';
import Select from '@mui/material/Select';
import {
    DAILY,
    FREQUENCIES,
    MONTH_DAYS,
    MONTHLY,
    WEEK_DAYS,
    WEEKLY,
    defaultDayFor
} from './schedule';

/**
 * Frequency picker with the day input the chosen frequency needs:
 *   - daily   - time only
 *   - weekly  - one day of week + time
 *   - monthly - one day of month + time
 */
export default function ScheduleForm(props) {
    const {frequency, day, time, onFrequencyChange, onDayChange, onTimeChange} = props;

    const handleFrequencyChange = (event) => {
        const selected = event.target.value;
        onFrequencyChange(selected);
        // Keep an already picked day when it is still valid for the new frequency.
        if (selected === DAILY) {
            onDayChange(null);
        } else if (day === null || (selected === WEEKLY && day > 7)) {
            onDayChange(defaultDayFor(selected));
        }
    };

    return (
        <Grid container spacing={3} alignItems="flex-start">
            <Grid item xs={4}>
                <FormControl fullWidth variant="standard">
                    <InputLabel htmlFor="repeating-trigger-frequency">Repeats</InputLabel>
                    <Select
                        variant="standard"
                        value={frequency}
                        onChange={handleFrequencyChange}
                        inputProps={{
                            name: 'repeating-trigger-frequency',
                            id: 'repeating-trigger-frequency'
                        }}
                    >
                        {FREQUENCIES.map(item => (
                            <MenuItem key={item.value} value={item.value}>{item.label}</MenuItem>
                        ))}
                    </Select>
                </FormControl>
            </Grid>

            {frequency === WEEKLY &&
                <Grid item xs={5}>
                    <FormControl fullWidth variant="standard">
                        <InputLabel htmlFor="repeating-trigger-day-of-week">On</InputLabel>
                        <Select
                            variant="standard"
                            value={day ?? ''}
                            onChange={event => {
                                onDayChange(event.target.value);
                            }}
                            inputProps={{
                                name: 'repeating-trigger-day-of-week',
                                id: 'repeating-trigger-day-of-week'
                            }}
                        >
                            {WEEK_DAYS.map(weekDay => (
                                <MenuItem key={weekDay.value} value={weekDay.value}>{weekDay.label}</MenuItem>
                            ))}
                        </Select>
                    </FormControl>
                </Grid>
            }

            {frequency === MONTHLY &&
                <Grid item xs={5}>
                    <FormControl fullWidth variant="standard">
                        <InputLabel htmlFor="repeating-trigger-day-of-month">On day</InputLabel>
                        <Select
                            variant="standard"
                            value={day ?? ''}
                            onChange={event => {
                                onDayChange(event.target.value);
                            }}
                            inputProps={{
                                name: 'repeating-trigger-day-of-month',
                                id: 'repeating-trigger-day-of-month'
                            }}
                        >
                            {MONTH_DAYS.map(monthDay => (
                                <MenuItem key={monthDay} value={monthDay}>{monthDay}</MenuItem>
                            ))}
                        </Select>
                        {day > 28 &&
                            <FormHelperText>
                                Months without this day fire on their last day instead.
                            </FormHelperText>
                        }
                    </FormControl>
                </Grid>
            }

            <Grid item xs={3}>
                <TextField
                    id="repeating-trigger-time"
                    label="At"
                    type="time"
                    variant="standard"
                    fullWidth
                    value={time}
                    onChange={event => {
                        onTimeChange(event.target.value);
                    }}
                    inputProps={{step: 300}}
                />
            </Grid>
        </Grid>
    );
}

ScheduleForm.propTypes = {
    frequency: PropTypes.oneOf([DAILY, WEEKLY, MONTHLY]).isRequired,
    // Day of week (1-7) for weekly, day of month (1-31) for monthly, null for daily
    day: PropTypes.number,
    time: PropTypes.string.isRequired,
    onFrequencyChange: PropTypes.func.isRequired,
    onDayChange: PropTypes.func.isRequired,
    onTimeChange: PropTypes.func.isRequired
};
