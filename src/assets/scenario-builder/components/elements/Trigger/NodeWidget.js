import { useSelector } from 'react-redux';
import TriggerIcon from '@mui/icons-material/Notifications';
import Grid from '@mui/material/Grid';
import TextField from '@mui/material/TextField';
import Button from '@mui/material/Button';
import Dialog from '@mui/material/Dialog';
import DialogActions from '@mui/material/DialogActions';
import DialogContent from '@mui/material/DialogContent';
import DialogContentText from '@mui/material/DialogContentText';
import DialogTitle from '@mui/material/DialogTitle';
import Autocomplete from '@mui/material/Autocomplete';
import StatisticBadge from "../../StatisticBadge";
import StatisticsTooltip from "../../StatisticTooltip";
import { Handle, Position } from 'reactflow'
import React, { useState } from 'react';
import { store } from '../../../store';
import { setCanvasZoomingAndPanning } from '../../../store/canvasSlice';
import { bemClassName } from '../../../utils/bem';

const NodeWidget = (props) => {
  const [nodeFormName, setNodeFormName] = useState(props.data.node.name);
  const [dialogOpened, setDialogOpened] = useState(false);
  const [selectedTrigger, setSelectedTrigger] = useState(props.data.node.selectedTrigger);
  const [anchorElementForTooltip, setAnchorElementForTooltip] = useState(null);
  const triggers = useSelector(state => state.triggers.availableTriggers)

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

  const handleNodeMouseEnter = event => {
    if (!dialogOpened) {
      setAnchorElementForTooltip(event.currentTarget);
    }
  };

  const handleNodeMouseLeave = () => {
    setAnchorElementForTooltip(null);
  };

  const getTriggersInSelectableFormat = () => {
    return triggers.map(trigger => {
      return {
        value: trigger.code,
        label: trigger.name
      };
    });
  };

  const getSelectedTriggerValue = () => {
    const selected = getTriggersInSelectableFormat().find(
      trigger => trigger.value === props.data.node.selectedTrigger
    );

    return selected ? ` - ${selected.label}` : '';
  };

  return (
    <div className={getClassName()}
      style={{ background: props.data.node.color }}
    >
      <div className='node-container'
         onDoubleClick={() => {openDialog();}}
         onMouseEnter={handleNodeMouseEnter}
         onMouseLeave={handleNodeMouseLeave}
      >
        <div className={bem('__icon')}>
          <TriggerIcon />
        </div>

        <div className={bem('__ports')}>
          <div className={bem('__right')}>
            <Handle
              type="source"
              id="right"
              position={Position.Right}
              isConnectable={props.isConnectable}
              className="port"
            />
            <StatisticBadge elementId={props.id} color="#21ba45" position="right" />
          </div>
        </div>
      </div>

      <div className={bem('__title')}>
        <div className={bem('__name')}>
          {props.data.node.name
            ? props.data.node.name
            : `Event ${getSelectedTriggerValue()}`}
        </div>
      </div>

      <StatisticsTooltip
        id={props.id}
        isTrigger={true}
        anchorElement={anchorElementForTooltip}
      />

      <Dialog
        open={dialogOpened}
        onClose={closeDialog}
        aria-labelledby='form-dialog-title'
        onKeyUp={event => {
          if (event.key === 'Delete' || event.key === 'Backspace') {
            event.preventDefault();
            event.stopPropagation();
            return false;
          }
        }}
      >
        <DialogTitle id='form-dialog-title'>Event node</DialogTitle>

        <DialogContent>
          <DialogContentText>
            Events are emitted on any change related to user. We recommend to
            combine "before" events with "Wait" operations to achieve
            execution at any desired time.
          </DialogContentText>

          <Grid container>
            <Grid item xs={6}>
              <TextField
                margin='normal'
                id='trigger-name'
                label='Node name'
                variant='standard'
                fullWidth
                value={nodeFormName}
                onChange={event => {
                  setNodeFormName(event.target.value)
                }}
              />
            </Grid>
          </Grid>

          <Grid container>
            <Grid item xs={12}>
              <Autocomplete
                  value={getTriggersInSelectableFormat().find(
                    option => option.value === selectedTrigger
                  )}
                  options={getTriggersInSelectableFormat()}
                  getOptionLabel={(option) => option.label}
                  isOptionEqualToValue={(option, value) => option.value === value.value}
                  style={{ marginTop: 16 }}
                  onChange={(event, selectedOption) => {
                    if (selectedOption !== null) {
                      setSelectedTrigger(selectedOption.value)
                    }
                  }}
                  renderInput={params => (
                      <TextField {...params} variant="standard" label="Trigger" fullWidth />
                  )}
                />
            </Grid>
          </Grid>
        </DialogContent>

        <DialogActions>
          <Button
            color='secondary'
            onClick={() => {
              closeDialog();
            }}
          >
            Cancel
          </Button>

          <Button
            color='primary'
            onClick={() => {
              props.data.node.name = nodeFormName;
              props.data.node.selectedTrigger = selectedTrigger;

              closeDialog();
            }}
          >
            Save changes
          </Button>
        </DialogActions>
      </Dialog>
    </div>
  );
}

export default NodeWidget;
