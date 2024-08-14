import React, {forwardRef, useContext, useImperativeHandle, useReducer, createContext} from "react";
import {createOption, reducer, actionDeleteOption, actionCreateOption} from "./optionsReducer";
import Card from "@mui/material/Card";
import {CardContent} from "@mui/material";
import {makeStyles} from "@mui/styles";
import BooleanParam from "../params/BooleanParam";
import NumberParam from "../params/NumberParam";
import StringLabeledArrayParam from "../params/StringLabeledArrayParam";

const OptionsFormDispatch = createContext(null);

function Option(props) {
  const dispatch = useContext(OptionsFormDispatch);

  switch (props.blueprint.type) {
    case 'boolean':
      return (
        <BooleanParam name={props.blueprint.key} values={props.option.values} blueprint={props.blueprint} dispatch={dispatch} />
      );
    case 'number':
      return (
        <NumberParam name={props.blueprint.key} values={props.option.values} blueprint={props.blueprint} dispatch={dispatch} />
      );
    case 'string_labeled_array':
      return (
       <StringLabeledArrayParam name={props.blueprint.key} values={props.option.values} blueprint={props.blueprint} dispatch={dispatch} />
      );
    default:
      throw new Error("unsupported option type " + props.blueprint.type);
  }
}

const useOptionsFormStyles = makeStyles((theme) => ({
  option: {
    flex: '0 0 100%',
    borderBottom: '1px solid #dfdfdf',
    marginBottom: theme.spacing(2),
    paddingBottom: theme.spacing(1),
    "&:last-child": {
      borderBottom: 'none',
      marginBottom: 0,
      paddingBottom: 0
    }
  }
}));

function OptionsForm(props, ref) {
  const classes = useOptionsFormStyles();
  const [state, dispatch] = useReducer(reducer, {
    version: 1,
    // event: criteria[0].event,
    options: props.options ?? [] // by default, one empty node
  });

  // expose state to outer node
  useImperativeHandle(ref, () => ({
    state: state
  }));

  // remove all options which doesnt have blueprint for current generic
  state.options.forEach(option => {
    if (props.blueprints.find(blueprint => blueprint.key === option.key) === undefined) {
      dispatch(actionDeleteOption(option.key));
    }
  });

  // create not existing options from blueprints
  props.blueprints.forEach(blueprint => {
    if (state.options.find(option => blueprint.key === option.key) === undefined) {
      dispatch(actionCreateOption(blueprint.key));
    }
  });

  return (
    <OptionsFormDispatch.Provider value={dispatch}>
      <Card>
        <CardContent className={classes.cardContent}>
            {props.blueprints.map((blueprint) => {
              let option = state.options.find(option => option.key === blueprint.key) ?? createOption(blueprint.key, null);
              return (
                <div className={classes.option} key={blueprint.key}>
                  <Option
                    option={option}
                    blueprint={blueprint.blueprint}
                  />
                </div>
              );
            })}
        </CardContent>
      </Card>
    </OptionsFormDispatch.Provider>
  );
}

// forwardRef is here used to access local state from parent node
export default forwardRef(OptionsForm);
