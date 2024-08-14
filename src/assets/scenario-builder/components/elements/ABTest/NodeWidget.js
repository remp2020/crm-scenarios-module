import React, { useState, createRef } from 'react';
import SwapVertIcon from '@mui/icons-material/SwapVert';
import Grid from '@mui/material/Grid';
import TextField from '@mui/material/TextField';
import Button from '@mui/material/Button';
import Dialog from '@mui/material/Dialog';
import DialogActions from '@mui/material/DialogActions';
import DialogContent from '@mui/material/DialogContent';
import DialogTitle from '@mui/material/DialogTitle';
import DialogContentText from '@mui/material/DialogContentText';
import VariantBuilder from './VariantBuilder';
import { Typography } from '@mui/material';
import StatisticBadge from '../../StatisticBadge';
import StatisticsTooltip from '../../StatisticTooltip';
import { Handle, Position } from 'reactflow';
import { store } from '../../../store';
import { setCanvasZoomingAndPanning } from '../../../store/canvasSlice';
import { bemClassName } from '../../../utils/bem';

const NodeWidget = (props) => {

  // Use it to access VariantBuilder state
  const builderRef = createRef();

  const [nodeFormName, setNodeFormName] = useState(props.data.node.name);
  const [enabledSave, setEnabledSave] = useState(true);
  const [dialogOpened, setDialogOpened] = useState(false);
  const [anchorElementForTooltip, setAnchorElementForTooltip] = useState(null);

  const bem = (selector) => bemClassName(
    selector,
    props.data.node.classBaseName,
    props.data.node.className
  )

  const getClassName = () => {
    return props.data.node.classBaseName + ' ' + props.data.node.className;
  };

  const openDialog = () => {
    if (dialogOpened) {
      return
    }

    setDialogOpened(true);
    setNodeFormName(props.data.node.name);
    setAnchorElementForTooltip(null);
    store.dispatch(setCanvasZoomingAndPanning(false));
  };

  const closeDialog = () => {
    setDialogOpened(false);
    store.dispatch(setCanvasZoomingAndPanning(true));
  };

  const handleNodeMouseEnter = ({ currentTarget }) => {
    if (!dialogOpened) {
      setAnchorElementForTooltip(currentTarget);
    }
  };

  const handleNodeMouseLeave = () => {
    setAnchorElementForTooltip(null);
  };

  const enableSave = enable => {
    // prevents re-rendering, setState only if value differs
    if (enabledSave !== enable) {
      setEnabledSave(enable);
    }
  };

  return (
    <div
      className={getClassName()}
      style={{background: props.data.node.color}}
      onDoubleClick={() => {
        openDialog();
      }}
      onMouseEnter={handleNodeMouseEnter}
      onMouseLeave={handleNodeMouseLeave}
    >
      <div className={bem('__title')}>
        <div className={bem('__name')}>
          {props.data.node.name
            ? props.data.node.name
            : 'AB Test'}
        </div>
      </div>

      <div className="node-container">
        <div className={bem('__icon')}>
          <SwapVertIcon/>
        </div>

        <div className={bem('__ports')}>
          <div className={bem('__left')}>
            <Handle
              type="target"
              id="left"
              position={Position.Left}
              onConnect={(params) => console.log('handle onConnect', params)}
              isConnectable={props.isConnectable}
              className="port"
            />
          </div>
        </div>

        <div className={bem('__ports')}>
          {props.data.node.variants.flatMap((variant, index) => (
            <div className={bem('__right')} key={'right-port-' + index} style={{position: 'relative'}}>
              <Handle
                type="source"
                id={`right.${index}`}
                position={Position.Right}
                isConnectable={props.isConnectable}
                className="port"
              />
              <div className={bem('__description')}>
                <StatisticBadge elementId={props.id} index={variant.code} color="#767676" position="right-condensed"/>
                <Typography
                  style={{fontSize: '0.8rem', marginLeft: '5px'}}
                  noWrap
                >
                  {variant.name} ({variant.distribution}%)
                </Typography>
              </div>
            </div>
          ))}
        </div>
      </div>

      <StatisticsTooltip
        id={props.id}
        anchorElement={anchorElementForTooltip}
        variants={props.data.node.variants}
      />

      <Dialog
        open={dialogOpened}
        onClose={closeDialog}
        maxWidth="md"
        aria-labelledby="form-dialog-title"
        onKeyUp={event => {
          if (event.key === 'Delete' || event.key === 'Backspace') {
            event.preventDefault();
            event.stopPropagation();
            return false;
          }
        }}
      >
        <DialogTitle id="form-dialog-title">AB Test</DialogTitle>

        <DialogContent>

          <DialogContentText>
            A/B testing is comparing two versions of either a webpage, email campaign or an aspect in a scenario to
            evaluate which performs best.
            With the different variants shown to your customers, you can determine which version is the most effective.
          </DialogContentText>

          <Grid container>
            <Grid style={{marginBottom: '10px'}} item xs={6}>
              <TextField
                margin="normal"
                label="Node name"
                variant="standard"
                fullWidth
                value={nodeFormName}
                onChange={event => {
                  setNodeFormName(event.target.value);
                }}
              />
            </Grid>
          </Grid>

          <VariantBuilder
            variants={props.data.node.variants}
            node={props.data.node}
            onEnableSave={enableSave}
            ref={builderRef}
          >
          </VariantBuilder>

        </DialogContent>

        <DialogActions>
          <Button
            color="secondary"
            onClick={() => {
              closeDialog();
            }}
          >
            Cancel
          </Button>

          <Button
            color="primary"
            disabled={!enabledSave}
            onClick={() => {
              props.data.node.name = nodeFormName;
              props.data.node.variants = builderRef.current.state.variants;

              closeDialog();
            }}
          >
            Save changes
          </Button>
        </DialogActions>
      </Dialog>
    </div>
  );
};

export default NodeWidget;
