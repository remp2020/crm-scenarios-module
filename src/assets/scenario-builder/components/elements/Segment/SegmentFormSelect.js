import React from 'react';
import { useSelector } from 'react-redux';
import Autocomplete from '@mui/material/Autocomplete';
import TextField from '@mui/material/TextField';
import { makeStyles } from '@mui/styles';
import { createFilterOptions } from '@mui/material/Autocomplete';

const useStyles = makeStyles((theme) => ({
  autocomplete: {
    margin: theme.spacing(1)
  },
  subtitle: {
    paddingLeft: '6px',
    color: theme.palette.grey[600]
  }
}));

function optionLabel(option) {
  return typeof (option) === 'string' ? option : option.name;
}

function optionSelected(option, value) {
  if (value && value.hasOwnProperty('code')) {
    return option.code === value.code;
  }
  return false;
}

function value(selectedSegment, items) {
  const item = items?.filter(item => item.code === selectedSegment)[0];
  return item ? item : null;
}

// Defines what values are searched within option
const filterOptions = createFilterOptions({
  matchFrom: 'any',
  trim: true,
  ignoreAccents: true,
  ignoreCase: true,
  stringify: option => {
    return option.name + ' ' + option.code;
  }
});

export default function SegmentFormSelect(props) {
  const classes = useStyles();
  const items = useSelector(state => {
    const segment = state.segments.availableSegments.find(
      item => item.table === props.selectedSegmentSourceTable
    );

    if (segment) {
      return [...segment.segments].sort(
        (a, b) => a.group.sorting - b.group.sorting === 0
          ? a.group.id - b.group.id
          : a.group.sorting - b.group.sorting
      );
    }

    return [];
  });

  return (
    <Autocomplete
      fullWidth
      className={classes.autocomplete}
      value={value(props.selectedSegment, items)}
      options={items}
      isOptionEqualToValue={optionSelected}
      getOptionLabel={optionLabel}
      groupBy={(option) => option.group.name}
      filterOptions={filterOptions}
      onChange={(event, value) => {
        props.onSegmentSelectedChange(value);
      }}
      renderInput={params => (
        <TextField {...params} variant="standard" label="Segment" fullWidth/>
      )}
      renderOption={(props, option, {selected}) => (
        <div {...props}>
          <span className={classes.title}>{optionLabel(option)}</span>
          <small className={classes.subtitle}>({option.code})</small>
        </div>
      )}
    />
  );
}
