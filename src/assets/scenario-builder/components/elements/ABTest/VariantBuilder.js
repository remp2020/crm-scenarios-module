import React, { forwardRef, useImperativeHandle, useReducer, useState } from 'react';
import Grid from '@mui/material/Grid';
import {
  Dialog,
  DialogActions,
  DialogContent,
  DialogTitle,
  FormControl,
  IconButton,
  Input,
  InputAdornment,
  InputLabel,
  TextField
} from '@mui/material';
import AddCircle from '@mui/icons-material/AddCircleOutline';
import AddIcon from '@mui/icons-material/AddCircleOutline';
import DeleteIcon from '@mui/icons-material/Delete';
import Button from '@mui/material/Button';
import EditIcon from '@mui/icons-material/Edit';
import ShowIcon from '@mui/icons-material/Visibility';
import { v4 as uuid } from 'uuid';
import DialogContentText from '@mui/material/DialogContentText';
import { v1 } from '../../../api_routes';

function reducer(state, action) {

  let isError = false;

  switch (action.type) {
    case 'ADD_VARIANT':
      return {
        ...state,
        variants: [
          ...state.variants, {
            "code": uuid().slice(0, 6),
            "name": "Variant " + String.fromCharCode(65 + state.variants.length),
            "distribution": 0
          }
        ]
      };

    case 'DELETE_VARIANT':
      let variants = state.variants.filter((element, index) => index !== action.payload.index);
      if (variants.reduce((sum, item) => sum + parseInt(item.distribution), 0) !== 100) {
        isError = true;
      }

      return {
        ...state,
        variants: variants,
        isError: isError
      };

    case 'UPDATE_VARIANT_DISTRIBUTION':
      let filteredVariants = state.variants.filter((element, index) => index !== action.payload.index);
      isError = (filteredVariants.reduce((sum, item) => sum + parseInt(item.distribution), 0) + parseInt(action.payload.value)) !== 100;

      return {
        ...state,
        variants: state.variants.map((element, index) => {
          if (index === action.payload.index) {
            return {
              ...element,
              distribution: action.payload.value
            }
          }
          return element;
        }),
        isError: isError,
      };

    case 'UPDATE_VARIANT_NAME':
      return {
        ...state,
        variants: state.variants.map((element, index) => {
          if (index === action.payload.index) {
            return {
              ...element,
              name: action.payload.value
            }
          }
          return element;
        })
      };

    case 'UPDATE_SEGMENT_NAME':
      return {
        ...state,
        variants: state.variants.map((element, index) => {
          if (index === action.payload.index) {
            return {
              ...element,
              segment: {
                name: action.payload.name
              }
            }
          }
          return element;
        })
      };

    default:
      throw new Error("unsupported action type " + action.type);
  }
}

function VariantBuilder(props, ref) {
  const [state, dispatch] = useReducer(reducer, {
    ...props
  });

  if (state.isError !== undefined) {
    props.onEnableSave(!state.isError);
  }

  // expose state to outer node
  useImperativeHandle(ref, () => ({
    state: state
  }));

  return (
    <form autoComplete="off" noValidate>
      {state.variants.flatMap((variant, index) => (
        <Grid container spacing={1} key={"grid-index-" + index}>
          <Grid item xs={1}>
              <TextField
                variant='standard'
                disabled={true}
                label="ID"
                value={variant.code}
              />
          </Grid>
          <Grid item xs={5}>
            <FormControl fullWidth>
              <TextField
                variant='standard'
                label="Variant name"
                value={variant.name}
                required={true}
                onChange={(element) => dispatch({type: 'UPDATE_VARIANT_NAME', payload: {index: index, value: element.target.value}})}
              />
            </FormControl>
          </Grid>
          <Grid item xs={2}>
            <FormControl variant='standard'>
              <InputLabel error={state.isError}>Distribution</InputLabel>
              <Input
                error={state.isError}
                required={true}
                type="number"
                value={variant.distribution}
                onChange={(element) => dispatch({type: 'UPDATE_VARIANT_DISTRIBUTION', payload: {index: index, value: element.target.value}})}
                endAdornment={
                  <InputAdornment position="end">%</InputAdornment>
                }
              />
            </FormControl>
          </Grid>

          <Grid item xs={1} style={{display: 'flex', alignItems: 'center', justifyContent: 'space-evenly'}}>
            {index > 1 &&
            <IconButton
              onClick={() => {dispatch({type: 'DELETE_VARIANT', payload: {index: index}})}}
              size="small"
              aria-label="delete"
            >
              <DeleteIcon/>
            </IconButton>
            }
          </Grid>

          <Grid item xs={3} style={{display: 'flex', alignItems: 'center'}}>

            <SegmentForm
              index={index}
              variantCode={variant.code}
              segment={variant.segment ?? {}}
              dispatch={dispatch}
              variantName={variant.name}
              nodeName={props.node.name}
              scenarioName={props.node.scenarioName}
            />

          </Grid>

        </Grid>
      ))}

      <Grid container>
        <Grid item xs={12}>
          <Button
            startIcon={<AddIcon />}
            onClick={() => {dispatch({type: 'ADD_VARIANT'})}}
          >
            Add variant
          </Button>
        </Grid>
      </Grid>
    </form>
);
}

function SegmentForm(props) {

  const defaultSegmentName = "Scenario variant: " + props.scenarioName + " - " + props.nodeName + " - " + props.variantName + " (" + props.variantCode + ")";

  const [open, setOpen] = useState(false);
  const [name, setName] = useState(props.segment.name ?? defaultSegmentName);

  const handleClickOpen = () => {
    setOpen(true);
  };

  const handleCancel = () => {
    setName(props.segment.name ?? defaultSegmentName);
    setOpen(false);
  };

  const handleSave = () => {
    props.dispatch({
      type: 'UPDATE_SEGMENT_NAME',
      payload: {
        index: props.index,
        name: name
      }
    });

    setOpen(false);
  };

  return (
    <FormControl>

      <Button
        size="small"
        variant="outlined"
        color="secondary"
        disableElevation onClick={handleClickOpen}
        startIcon={props.segment.name
          ? <EditIcon />
          : <AddCircle />
        }>
        {props.segment.name ? 'Edit segment' : 'Create segment'}
      </Button>

      <Dialog open={open} fullWidth maxWidth="md">
        <DialogTitle id="form-dialog-title">{props.segment.name ? 'Edit segment' : 'Create segment'}</DialogTitle>
        <DialogContent>
          <DialogContentText>
            To create associated segment to variant, please enter segment name.
          </DialogContentText>
          <TextField
            autoFocus
            margin="dense"
            label="Segment name"
            type="string"
            variant='standard'
            value={name}
            onChange={e => { setName(e.target.value)}}
            fullWidth
          />

          {props.segment.code ?
            <div>
              <TextField
                fullWidth
                margin="dense"
                label="Segment code"
                variant='standard'
                type="string"
                value={props.segment.code}
                disabled={true}
              />
              <Button
                size="small"
                variant="standard"
                color="secondary"
                disableElevation
                startIcon={<ShowIcon/>}
                onClick={() => {window.open(v1.segments.show(props.segment.id))}}
              >
                View segment
              </Button>
            </div> : ''}
        </DialogContent>

        <DialogActions>
          <Button onClick={handleCancel} color="secondary">
            Cancel
          </Button>
          <Button onClick={handleSave} color="primary">
            {props.segment.name ? 'Save': 'Create'}
          </Button>
        </DialogActions>
      </Dialog>
    </FormControl>
  );
}

// // forwardRef is here used to access local state from parent node
export default forwardRef(VariantBuilder)
