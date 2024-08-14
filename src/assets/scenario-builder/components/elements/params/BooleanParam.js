import React, { useEffect } from 'react';
import PropTypes from 'prop-types';
import Switch from '@mui/material/Switch';
import FormControlLabel from '@mui/material/FormControlLabel';
import { actionSetParamValues } from './actions';
import { makeStyles } from '@mui/styles';

const useStyles = makeStyles(theme => ({
  formControl: {
    marginBottom: theme.spacing(1),
  }
}));

export default function BooleanParam(props) {
  const classes = useStyles();

  useEffect(() => {
    // if not selected yet, set selection to True
    if (props.values.selection === undefined) {
      props.dispatch(actionSetParamValues(props.name, {
        selection: true
      }));
    }
  }, [props.values.selection, props.dispatch, props.name]);

  const handleChange = (event) => {
    props.dispatch(actionSetParamValues(props.name, {
      selection: event.target.checked
    }));
  };

  return (
    <FormControlLabel
        onChange={handleChange}
        control={<Switch />}
        checked={props.values.selection !== undefined && props.values.selection}
        label={props.blueprint.label}
        className={classes.formControl}
      />
  );
}

BooleanParam.propTypes = {
  // name identifying input (same function as in HTML <input>), used in dispatch
  name: PropTypes.any.isRequired,
  // values, example: {selection: true}
  values: PropTypes.object.isRequired,
  // blueprint, example: {label: 'Is recurrent', type: 'boolean'}
  blueprint: PropTypes.object.isRequired,
  dispatch: PropTypes.func.isRequired,
};
