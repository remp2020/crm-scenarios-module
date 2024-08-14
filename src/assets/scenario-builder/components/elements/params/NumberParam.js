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
    minWidth: 100,
  },
  formLabel: {
    display: 'block',
    marginBottom: theme.spacing(1),
  },
  numberInput: {
    marginRight: theme.spacing(1),
    marginBottom: theme.spacing(1),
  }
}));

export default function NumberParam(props) {
  const classes = useStyles();

  // if only one operator, select it by default
  if (props.values.operator === undefined && props.blueprint.operators && props.blueprint.operators.length === 1) {
    props.dispatch(actionSetParamValues(props.name, {
      operator: props.blueprint.operators[0]
    }));
  }

  const handleOperatorChange = (event) => {
    props.dispatch(actionUpdateParamValues(props.name, {
      operator: event.target.value
    }));
  };

  const handleInputChange = (event) => {
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
        className={classes.numberInput}
        label={props.blueprint.unit}
        type="number"
        variant='standard'
        onChange={handleInputChange}
        value={props.values.selection ?? ''}
        // attributes passed down to <input> HTML tag
        inputProps={props.blueprint.numberInputAttributes ?? {}}
      />
    </Fragment>
  );
}

NumberParam.propTypes = {
  // name identifying input (same function as in HTML <input>), used in dispatch
  name: PropTypes.any.isRequired,
  // values, example: {selection: 3, operator: '>'}
  values: PropTypes.object.isRequired,
  // blueprint, example: {label: 'Subscription type length', type: 'number', 'operators': ['=', '<', '>'], unit: 'Day(s)', numberInputAttributes: {min: 0}}
  blueprint: PropTypes.object.isRequired,
  dispatch: PropTypes.func.isRequired,
  hideOperator: PropTypes.bool,
  hideLabel: PropTypes.bool,
};
