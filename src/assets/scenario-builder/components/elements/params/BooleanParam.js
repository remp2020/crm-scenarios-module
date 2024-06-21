import React from 'react';
import PropTypes from 'prop-types';
import Switch from '@material-ui/core/Switch';
import FormControlLabel from '@material-ui/core/FormControlLabel';
import { actionSetParamValues } from './actions';
import { makeStyles } from '@material-ui/core/styles';

const useStyles = makeStyles(theme => ({
  formControl: {
    marginBottom: theme.spacing(1),
  }
}));

export default function BooleanParam(props) {
    const classes = useStyles();

    // if not selected yet, set selection to True
    if (props.values.selection === undefined) {
      props.dispatch(actionSetParamValues(props.name, {
        selection: true
      }));
    }

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