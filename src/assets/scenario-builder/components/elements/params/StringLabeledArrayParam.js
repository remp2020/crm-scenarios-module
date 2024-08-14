import React from 'react';
import PropTypes from 'prop-types';
import Autocomplete from '@mui/material/Autocomplete';
import { createFilterOptions } from '@mui/material/Autocomplete';
import { Box, TextField } from '@mui/material';
import { makeStyles } from '@mui/styles';
import { actionSetParamValues } from './actions';

const elementStyles = makeStyles(theme => ({
  // Puts visually OR/AND between tags
  chipRoot: props => ({
    "&:not(:first-child)": {
      "&::before": {
        content: "'" + props.operator + "'",
        textTransform: 'uppercase',
        position: 'absolute',
        left: '-20px',
      },
      marginLeft: '20px'
    },
    position: 'relative'
  }),
  subtitle: {
    paddingLeft: '6px',
    color: theme.palette.grey[600]
  },
  autocomplete: {
    marginBottom: theme.spacing(1),
  }
}));

function selectedOptions(selectedValues, options) {
  const s = new Set(selectedValues);
  let selected = options.filter(option => {
    let has = s.has(option.value);
    if (has) {
      // for free-solo mode
      s.delete(option.value);
    }
    return has;
  });
  // If free solo mode is enabled, there might be additional selected values (outside of options), add them as well
  return selected.concat([...s]);
}

function optionLabel(option) {
  if (typeof(option) === 'string') {
    // free-solo value
    return option;
  } else {
    // predefined option value
    return option.label;
  }
}

function optionSubtitle(option) {
  if (typeof(option) === 'string') {
    return '';
  } else {
    return option.subtitle !== undefined ? option.subtitle : '';
  }
}

function optionGroup(option) {
  if (typeof(option) === 'string') {
    // free-solo value
    return '';
  } else if (option.hasOwnProperty('group')) {
    return option.group;
  } else {
    return '';
  }
}

// Defines what values are searched within option
const filterOptions = createFilterOptions({
  matchFrom: 'any',
  trim: true,
  ignoreAccents: true,
  ignoreCase: true,
  stringify: option => {
    return optionLabel(option) + " " + optionSubtitle(option);
  },
});

export default function StringLabeledArrayParam(props) {
  const classes = elementStyles({operator: props.blueprint.operator});
  const handleChange = (event, values) => {
    props.dispatch(actionSetParamValues(props.name, {
      operator: props.blueprint.operator, // TODO add ability to change operator
      selection: values.map(item => {
        if (typeof(item) === 'string') {
          // free-solo value
          return item;
        } else {
          // predefined option value
          return item.value;
        }
      })
    }));
  };

  return (
    <Autocomplete
        multiple
        disableCloseOnSelect
        ChipProps={{
          classes: {
            root: classes.chipRoot
          }
        }}
        options={props.blueprint.options}
        getOptionLabel={optionLabel}
        onChange={handleChange}
        value={selectedOptions(props.values.selection, props.blueprint.options)}
        freeSolo={props.blueprint.freeSolo}
        groupBy={optionGroup}
        filterOptions={filterOptions}
        renderInput={params => (
          <TextField
            {...params}
            variant='standard'
            label={props.blueprint.label}
            placeholder=""
            className={classes.autocomplete}
            fullWidth
          />
        )}
        renderOption={(props, option, { selected }) => (
          <Box component="li" {...props}>
            {optionLabel(option)}
            <small className={classes.subtitle}>{optionSubtitle(option)}</small>
          </Box>
        )}
      />
  );
}

StringLabeledArrayParam.propTypes = {
  // name identifying input (same function as in HTML <input>), used in dispatch
  name: PropTypes.any.isRequired,
  // values, example: {selection: ['city_1'], operator: 'or'}
  values: PropTypes.object.isRequired,
  // blueprint, example: {label: 'Cities', type: 'string_labeled_array', options: [{value: 'city_1', label: 'City 1', subtitle: '(best city)' group: 'Group 1'}], operator: 'or', freeSolo: true}
  blueprint: PropTypes.object.isRequired,
  dispatch: PropTypes.func.isRequired,
};
