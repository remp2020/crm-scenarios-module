import React, { useImperativeHandle, useReducer, useContext, forwardRef, createContext, Fragment } from 'react';
import { useSelector } from 'react-redux';
import Button from '@mui/material/Button';
import Grid from '@mui/material/Grid';
import ButtonGroup from '@mui/material/ButtonGroup';
import DeleteIcon from '@mui/icons-material/Delete';
import AddIcon from '@mui/icons-material/AddCircleOutline';
import { Card, CardContent, FormControl, InputLabel, Select, MenuItem, IconButton } from '@mui/material';
import { makeStyles } from '@mui/styles';
import StringLabeledArrayParam from '../params/StringLabeledArrayParam';
import BooleanParam from '../params/BooleanParam';
import NumberParam from '../params/NumberParam';
import TimeframeParam from '../params/TimeframeParam';
import { emptyNode, reducer, actionSetEvent, actionSetKeyForNode, actionAddCriterion, actionDeleteNode } from './criteriaReducer';

const BuilderDispatch = createContext(null);

////////////////////
// CriterionParam
////////////////////

// Props - node, blueprint
function CriterionParam(props) {
  const dispatch = useContext(BuilderDispatch);

  let param = props.node.params.filter(param => param.key === props.blueprint.key)[0];
  // input name identifying both criterion (using artifically generated ID) and its param (using param's key)
  let name = [props.node.id, param.key];

  switch (props.blueprint.type) {
    case 'string_labeled_array':
      return (<StringLabeledArrayParam name={name} values={param.values} blueprint={props.blueprint} dispatch={dispatch}></StringLabeledArrayParam>);
    case 'boolean':
      return (<BooleanParam name={name} values={param.values} blueprint={props.blueprint} dispatch={dispatch}></BooleanParam>);
    case 'number':
      return (<NumberParam name={name} values={param.values} blueprint={props.blueprint} dispatch={dispatch} hideLabel={true}></NumberParam>);
    case 'timeframe':
      return (<TimeframeParam name={name} values={param.values} blueprint={props.blueprint} dispatch={dispatch}></TimeframeParam>);
    default:
      throw new Error("unsupported node type " + props.blueprint.type);
  }
}

// Props - node, blueprint
function CriterionParams(props) {
  return (
    <>
      {props.blueprint.map(paramBlueprint => (
        <CriterionParam key={paramBlueprint.key} node={props.node} blueprint={paramBlueprint}></CriterionParam>
      ))}
    </>
  )
}

////////////////////
// CriteriaForm
////////////////////

const useCriteriaFormStyles = makeStyles({
  cardContent: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'flex-end',
    backgroundColor: '#F2F2F2',
  },
  formControl: {
    minWidth: '180px',
  },
});

// Props - node, criteriaBlueprint
function CriteriaForm(props) {
  const classes = useCriteriaFormStyles();
  const dispatch = useContext(BuilderDispatch);

  return (
    <Card>
      <CardContent className={classes.cardContent}>
        <FormControl className={classes.formControl} variant='standard'>
          <InputLabel id="select-criteria-label">Criterion</InputLabel>
          <Select
            variant="standard"
            labelId="select-criteria-label"
            id="select-criteria"
            placeholder="Select criteria"
            value={props.node.key}
            onChange={e => {
                let criterionBlueprint = props.criteriaBlueprint.filter(cb => cb.key === e.target.value)[0];
                let params = criterionBlueprint.params.map(criterionParam => ({
                  key: criterionParam.key,
                  // TODO: load default 'values' structure according to criterion param type
                  values: {}
                }))
                dispatch(actionSetKeyForNode(props.node.id, e.target.value, params));
              }
            }
          >
            {props.criteriaBlueprint.map(cr => (
              <MenuItem key={cr.key} value={cr.key}>{cr.label}</MenuItem>
            ))}
          </Select>
        </FormControl>
        <IconButton onClick={() => dispatch(actionDeleteNode(props.node.id))}
          size="small"
          className={classes.icon}
          aria-label="delete">
          <DeleteIcon />
        </IconButton>
      </CardContent>

      { props.node.key &&
        <CardContent>
          <CriterionParams
            node={props.node}
            blueprint={props.criteriaBlueprint.filter(cr => cr.key === props.node.key)[0].params}>
          </CriterionParams>
        </CardContent>
      }
    </Card>
  );
}

////////////////////
// CriteriaTable
////////////////////

const useCriteriaTableStyles = makeStyles({
  andContainer: {
    display: 'flex',
    justifyContent: 'center',
    flexGrow: 0,
    maxWidth: '100%',
    flexBasis: '100%',
    paddingTop: '12px',
    marginBottom: '-12px'
  }
});

// Props - criteriaBlueprint, nodes
function CriteriaTable(props) {
  const classes = useCriteriaTableStyles();
  const dispatch = useContext(BuilderDispatch);

  return (
    <>
      {props.nodes.map((node, index) => (
        <Fragment key={node.id}>
          { index >= 1 &&
            <div className={classes.andContainer}>AND</div>
          }
          <Grid item xs={12}>
            <CriteriaForm
              node={node}
              criteriaBlueprint={props.criteriaBlueprint}>
            </CriteriaForm>
          </Grid>
        </Fragment>
      ))}

      <Grid item xs={12}>
        <Button onClick={() => dispatch(actionAddCriterion())} className={classes.button} startIcon={<AddIcon />}>
          Add criterion
        </Button>
      </Grid>
    </>
  )
}

////////////////////
// CriteriaBuilder
////////////////////

const useCriteriaBuilderStyles = makeStyles({
  selectedButton: {
    backgroundColor: "#E4E4E4"
  },
  deselectedButton: {
    color:  "#A6A6A6"
  }
});

function CriteriaBuilder(props, ref) {
  const classes = useCriteriaBuilderStyles();
  const criteria = useSelector(state => state.criteria.criteria);

  const [state, dispatch] = useReducer(reducer, {
    version: 1,
    event: criteria[0]?.event,
    nodes: [emptyNode()] // by default, one empty node
  , ...props.conditions});

  // expose state to outer node
  useImperativeHandle(ref, () => ({
    state: state
  }));

  return (
    <BuilderDispatch.Provider value={dispatch}>
      <Grid container item xs={12} spacing={3}>
        <Grid item xs={12}>
          <ButtonGroup aria-label="outlined button group">
            {criteria.map(criteriaBlueprint => (
              <Button
                onClick={() => dispatch(actionSetEvent(criteriaBlueprint.event))}
                className={state.event === criteriaBlueprint.event ? classes.selectedButton : classes.deselectedButton}
                key={criteriaBlueprint.event}>{criteriaBlueprint.event}</Button>
            ))}
          </ButtonGroup>
        </Grid>

        {criteria.filter(cb => cb.event === state.event).map(criteriaBlueprint => (
            <CriteriaTable
              key={criteriaBlueprint.event}
              criteriaBlueprint={criteriaBlueprint.criteria}
              nodes={state.nodes}></CriteriaTable>
          )
        )}
      </Grid>
    </BuilderDispatch.Provider>
  )
}

// forwardRef is here used to access local state from parent node
export default forwardRef(CriteriaBuilder)
