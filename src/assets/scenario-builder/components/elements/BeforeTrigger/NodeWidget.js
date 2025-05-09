import React, { useState } from 'react';
import { useSelector } from 'react-redux';
import NotificationsActiveIcon from '@mui/icons-material/NotificationsActive';
import Grid from '@mui/material/Grid';
import TextField from '@mui/material/TextField';
import Button from '@mui/material/Button';
import Dialog from '@mui/material/Dialog';
import DialogActions from '@mui/material/DialogActions';
import DialogContent from '@mui/material/DialogContent';
import DialogContentText from '@mui/material/DialogContentText';
import DialogTitle from '@mui/material/DialogTitle';
import Autocomplete from '@mui/material/Autocomplete';
import StatisticsTooltip from '../../StatisticTooltip';
import FormControl from '@mui/material/FormControl';
import InputLabel from '@mui/material/InputLabel';
import Select from '@mui/material/Select';
import MenuItem from '@mui/material/MenuItem';
import StatisticBadge from '../../StatisticBadge';
import { Handle, Position } from 'reactflow';
import { NodePopover } from '../../NodePopover';
import { useNode } from '../../../hooks/useNode';

const NodeWidget = (props) => {
  const beforeTriggers = useSelector(state => state.beforeTriggers.availableBeforeTriggers);
  const [selectedTrigger, setSelectedTrigger] = useState(props.data.node.selectedTrigger);
  const [nodeFormBeforeTime, setNodeFormBeforeTime] = useState(props.data.node.time);
  const [timeUnit, setTimeUnit] = useState(props.data.node.timeUnit);
  const {
    bem,
    getClassName,
    anchorElementForTooltip,
    anchorElForPopover,
    deleteNode,
    closePopover,
    dialogOpened,
    openDialog,
    onNodeClick,
    onNodeDoubleClick,
    nodeFormName,
    setNodeFormName,
    closeDialog,
    onDialogOpen,
    handleNodeMouseEnter,
    handleNodeMouseLeave
  } = useNode(props)

  onDialogOpen(() => {
    setNodeFormBeforeTime(props.data.node.time);
    setTimeUnit(props.data.node.timeUnit);
  })

  const getTriggersInSelectableFormat = () => {
    return beforeTriggers.map(trigger => {
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

    return selected ? `${props.data.node.time} ${props.data.node.timeUnit} - ${selected.label} event` : 'Before/After Event';
  };

  return (
    <div
      className={getClassName()}
      style={{background: props.data.node.color}}
      onClick={onNodeClick}
      onDoubleClick={onNodeDoubleClick}
      onMouseEnter={handleNodeMouseEnter}
      onMouseLeave={handleNodeMouseLeave}
    >
      <NodePopover
        anchorEl={anchorElForPopover}
        onClose={closePopover}
        onEdit={openDialog}
        onDelete={deleteNode}
      />
      <div className="node-container">
        <div className={bem('__icon')}>
          <NotificationsActiveIcon/>
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
            <StatisticBadge elementId={props.id} color="#00b5ad" position="right"/>
          </div>
        </div>
      </div>

      <div className={bem('__title')}>
        <div className={bem('__name')}>
          {props.data.node.name
            ? props.data.node.name
            : getSelectedTriggerValue()}
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
        aria-labelledby="form-dialog-title"
        onKeyUp={event => {
          if (event.key === 'Delete' || event.key === 'Backspace') {
            event.preventDefault();
            event.stopPropagation();
            return false;
          }
        }}
      >
        <DialogTitle id="form-dialog-title">Before/after event node</DialogTitle>

        <DialogContent>
          <DialogContentText>
            Events are emitted in advanced of trigger according to selected time period.
          </DialogContentText>

          <Grid container>
            <Grid item xs={6}>
              <TextField
                margin="normal"
                id="trigger-name"
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

          <Grid container>
            <Grid item xs={12}>
              <Autocomplete
                value={getTriggersInSelectableFormat().find(
                  option => option.value === selectedTrigger
                )}
                options={getTriggersInSelectableFormat()}
                isOptionEqualToValue={(option, value) => option.value === value.value}
                getOptionLabel={(option) => option.label}
                style={{marginBottom: 16}}
                onChange={(event, selectedOption) => {
                  if (selectedOption !== null) {
                    setSelectedTrigger(selectedOption.value);
                  }
                }}
                renderInput={params => (
                  <TextField {...params} variant="standard" label="Trigger" fullWidth/>
                )}
              />
            </Grid>
          </Grid>

          <Grid container>
            <Grid item xs={6}>
              <TextField
                id="waiting-time"
                label="Time offset"
                type="number"
                variant="standard"
                fullWidth
                value={nodeFormBeforeTime}
                onChange={event => {
                  setNodeFormBeforeTime(event.target.value);
                }}
              />
            </Grid>
            <Grid item xs={6}>
              <FormControl fullWidth variant="standard">
                <InputLabel htmlFor="time-unit">Time unit</InputLabel>
                <Select
                  variant="standard"
                  value={timeUnit}
                  onChange={event => {
                    setTimeUnit(event.target.value);
                  }}
                  inputProps={{
                    name: 'time-unit',
                    id: 'time-unit'
                  }}
                >
                  <MenuItem value="minutes">Minutes</MenuItem>
                  <MenuItem value="hours">Hours</MenuItem>
                  <MenuItem value="days">Days</MenuItem>
                </Select>
              </FormControl>
            </Grid>
          </Grid>
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
            onClick={() => {
              props.data.node.name = nodeFormName;
              props.data.node.selectedTrigger = selectedTrigger;
              props.data.node.time = nodeFormBeforeTime;
              props.data.node.timeUnit = timeUnit;

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
