import React from 'react';
import {useSelector} from "react-redux";
import Autocomplete from "@material-ui/lab/Autocomplete";
import TextField from "@material-ui/core/TextField";
import { makeStyles } from '@material-ui/core/styles';
import { createFilterOptions } from '@material-ui/lab/Autocomplete';

const useStyles = makeStyles((theme) => ({
    autocomplete: {
        margin: theme.spacing(1)
    },
    subtitle: {
        paddingLeft: '6px',
        color: theme.palette.grey[600]
    },
  }));

function optionLabel(option) {
    return typeof(option) === 'string' ? option : option.name;
}

function optionSelected(option, value) {
    if (value && value.hasOwnProperty('code')) {
        return option.code === value.code;
    }
    return false;
}

function value(selectedSegment, items) {
    const item = items.filter(item => item.code === selectedSegment)[0];
    return item ? item : null;
}

// Defines what values are searched within option
const filterOptions = createFilterOptions({
    matchFrom: 'any',
    trim: true,
    ignoreAccents: true,
    ignoreCase: true,
    stringify: option => {
      return option.name + " " + option.code;
    },
  });

export default function SegmentFormSelect(props) {
    const classes = useStyles();
    const items = useSelector(state => state.segments.avalaibleSegments.filter(
        item => item.table === props.selectedSegmentSourceTable
    ))[0].segments.sort(
        (a,b) => a.group.sorting - b.group.sorting === 0 ? a.group.id - b.group.id : a.group.sorting - b.group.sorting
    );

    return (
        <Autocomplete
            fullWidth
            className={classes.autocomplete}
            value={value(props.selectedSegment, items)}
            options={items}
            getOptionSelected={optionSelected}
            getOptionLabel={optionLabel}
            groupBy={(option) => option.group.name}
            filterOptions={filterOptions}
            onChange={(event, value) => {props.onSegmentSelectedChange(value)}}
            renderInput={params => (
                <TextField {...params} variant="standard" label="Segment" fullWidth />
            )}
            renderOption={(option, { selected }) => (
                <div>
                  <span className={classes.title}>{optionLabel(option)}</span>
                  <small className={classes.subtitle}>({option.code})</small>
                </div>
              )}
        />
    );
}