import React, { useMemo, useState } from 'react';
import PropTypes from 'prop-types';
import Autocomplete, { createFilterOptions } from '@mui/material/Autocomplete';
import { Box, Checkbox, TextField } from '@mui/material';
import CheckBoxOutlineBlankIcon from '@mui/icons-material/CheckBoxOutlineBlank';
import CheckBoxIcon from '@mui/icons-material/CheckBox';
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
      marginLeft: '20px',
      maxWidth: 'calc(100% - 20px)',
    },
    position: 'relative'
  }),
  subtitle: {
    marginLeft: 'auto',
    paddingLeft: '12px',
    fontSize: '0.75rem',
    color: theme.palette.grey[500],
    fontFamily: 'monospace',
    whiteSpace: 'nowrap',
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

function isOptionEqualToValue(option, value) {
  if (typeof option === 'string' || typeof value === 'string') {
    return option === value;
  }
  return option.value === value.value;
}

const getOptionKey = option => typeof option === 'string' ? option : option.value;

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
  const [inputValue, setInputValue] = useState('');
  // Memoize value by selection content (not reference) to prevent MUI from
  // resetting the internal inputValue on every re-render.
  const value = useMemo(
    () => selectedOptions(props.values.selection, props.blueprint.options),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [props.values.selection?.join('\0') ?? '']
  );
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
        limitTags={5}
        disableCloseOnSelect
        sx={{
          '& .MuiAutocomplete-inputRoot': {
            maxHeight: '250px',
            overflow: 'hidden auto',
          },
          '& .MuiInput-underline::before, & .MuiInput-underline::after': {
            display: 'none',
          },
        }}
        ChipProps={{
          classes: {
            root: classes.chipRoot
          }
        }}
        options={props.blueprint.options}
        getOptionLabel={optionLabel}
        onChange={handleChange}
        value={value}
        freeSolo={props.blueprint.freeSolo}
        groupBy={optionGroup}
        filterOptions={filterOptions}
        onClose={() => setInputValue('')}
        inputValue={inputValue}
        onInputChange={(event, newInputValue, reason) => {
          if (reason !== 'reset') {
            setInputValue(newInputValue);
          }
        }}
        getOptionKey={getOptionKey}
        isOptionEqualToValue={isOptionEqualToValue}
        ListboxProps={{ style: {
          maxHeight: '300px',
          maskImage: 'linear-gradient(to bottom, transparent, black 8px, black calc(100% - 8px), transparent)',
          WebkitMaskImage: 'linear-gradient(to bottom, transparent, black 8px, black calc(100% - 8px), transparent)',
        } }}
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
        renderOption={(props, option, { selected }) => {
          const subtitle = optionSubtitle(option);
          return (
            <Box component="li" {...props} sx={{ display: 'flex', alignItems: 'center' }}>
              <Checkbox
                icon={<CheckBoxOutlineBlankIcon fontSize="small" />}
                checkedIcon={<CheckBoxIcon fontSize="small" />}
                checked={selected}
                size="small"
                sx={{ mr: 1, p: 0 }}
              />
              {optionLabel(option)}
              {subtitle && <small className={classes.subtitle}>{subtitle}</small>}
            </Box>
          );
        }}
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
