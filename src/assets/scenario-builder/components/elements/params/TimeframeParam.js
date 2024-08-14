import React, { Fragment } from 'react';
import PropTypes from 'prop-types';
import TextField from '@mui/material/TextField';
import InputLabel from '@mui/material/InputLabel';
import FormLabel from '@mui/material/FormLabel';
import MenuItem from '@mui/material/MenuItem';
import FormControl from '@mui/material/FormControl';
import Select from '@mui/material/Select';
import { makeStyles } from '@mui/styles';
import { actionSetParamValues, actionUpdateParamValues } from './actions';

const useStyles = makeStyles((theme) => ({
  formControl: {
    marginRight: theme.spacing(1),
    marginBottom: theme.spacing(1),
    minWidth: 150,
  },
  formLabel: {
    display: 'block',
    marginBottom: theme.spacing(1),
  },
  amountInput: {
    marginRight: theme.spacing(1),
    marginBottom: theme.spacing(1),
  }
}));

export default function TimeframeParam(props) {
  const classes = useStyles();

  // if only one operator, select it by default
  if (props.values.operator === undefined && props.blueprint.operators && props.blueprint.operators.length === 1) {
    props.dispatch(actionSetParamValues(props.name, {
      operator: props.blueprint.operators[0]
    }));
  }

  // if only one unit, select it by default
  if (props.values.unit === undefined && props.blueprint.units && props.blueprint.units.length === 1) {
    props.dispatch(actionUpdateParamValues(props.name, {
      unit: props.blueprint.units[0]
    }));
  }

  const handleOperatorChange = (event) => {
    props.dispatch(actionUpdateParamValues(props.name, {
      operator: event.target.value
    }));
  };

  const handleUnitChange = (event) => {
    props.dispatch(actionUpdateParamValues(props.name, {
      unit: event.target.value
    }));
  };

  const handleAmountInputChange = (event) => {
    props.dispatch(actionUpdateParamValues(props.name, {
      selection: event.target.value
    }));
  };

  return (
    <Fragment>
      {!props.hideLabel &&
      <FormLabel className={classes.formLabel}>
        {props.blueprint.label}
      </FormLabel>
      }

      {props.blueprint.operators && props.blueprint.operators.length &&
        <FormControl className={classes.formControl} disabled={props.blueprint.operators.length === 1} variant='standard'>
          <InputLabel>Operator</InputLabel>
          <Select
            variant="standard"
            autoWidth
            value={props.values.operator ?? ''}
            onChange={handleOperatorChange}
          >
            {props.blueprint.operators.map(op => (
              <MenuItem key={op} value={op}>{op}</MenuItem>
            ))}
          </Select>
        </FormControl>
      }

      <TextField
        className={classes.amountInput}
        label={props.blueprint.amountLabel}
        type="number"
        variant='standard'
        onChange={handleAmountInputChange}
        value={props.values.selection ?? ''}
        // attributes passed down to <input> HTML tag
        inputProps={props.blueprint.amountInputAttributes ?? {}}
      />

      {props.blueprint.units && props.blueprint.units.length &&
        <FormControl className={classes.formControl} disabled={props.blueprint.units.length === 1} variant='standard'>
          <InputLabel>{props.blueprint.unitsLabel}</InputLabel>
          <Select
            variant="standard"
            autoWidth
            value={props.values.unit ?? ''}
            onChange={handleUnitChange}
          >
            {props.blueprint.units.map(op => (
              <MenuItem key={op} value={op}>{op}</MenuItem>
            ))}
          </Select>
        </FormControl>
      }

    </Fragment>
  );
};

TimeframeParam.propTypes = {
  // name identifying input (same function as in HTML <input>), used in dispatch
  name: PropTypes.any.isRequired,
  // values, example: {selection: true}
  values: PropTypes.object.isRequired,
  // blueprint, example: {label: 'Timeframe', type: 'timeframe', 'operators': ['=', '<', '>'], 'units': ['days', 'months', 'years'], 'amountLabel': 'Amount', 'unitsLabel': 'Time unit', 'amountInputAttributes': {min: 0}}
  blueprint: PropTypes.object.isRequired,
  dispatch: PropTypes.func.isRequired,
};
