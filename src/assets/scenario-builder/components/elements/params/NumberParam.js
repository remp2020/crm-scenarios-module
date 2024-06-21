import React from 'react';
import PropTypes from 'prop-types';
import TextField from '@material-ui/core/TextField';
import InputLabel from '@material-ui/core/InputLabel';
import FormLabel from '@material-ui/core/FormLabel';
import MenuItem from '@material-ui/core/MenuItem';
import FormControl from '@material-ui/core/FormControl';
import Select from '@material-ui/core/Select';
import { makeStyles } from '@material-ui/core/styles';
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
    <React.Fragment>
      {!props.hideLabel &&
      <FormLabel className={classes.formLabel}>
        {props.blueprint.label}
      </FormLabel>
      }

      {props.blueprint.operators && props.blueprint.operators.length &&
      <FormControl className={classes.formControl} disabled={props.blueprint.operators.length === 1}>
          <InputLabel>Operator</InputLabel>
          <Select
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

      <TextField className={classes.numberInput}
          label={props.blueprint.unit}
          type="number"
          onChange={handleInputChange}
          value={props.values.selection ?? ''}
          // attributes passed down to <input> HTML tag
          inputProps={props.blueprint.numberInputAttributes ?? {}}
        />
    </React.Fragment>
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