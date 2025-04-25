import React, { useState } from 'react';
import WaitIcon from '@mui/icons-material/AccessAlarmsOutlined';
import Grid from '@mui/material/Grid';
import TextField from '@mui/material/TextField';
import Button from '@mui/material/Button';
import Dialog from '@mui/material/Dialog';
import MenuItem from '@mui/material/MenuItem';
import InputLabel from '@mui/material/InputLabel';
import Select from '@mui/material/Select';
import FormControl from '@mui/material/FormControl';
import DialogActions from '@mui/material/DialogActions';
import DialogContent from '@mui/material/DialogContent';
import DialogContentText from '@mui/material/DialogContentText';
import DialogTitle from '@mui/material/DialogTitle';
import StatisticBadge from '../../StatisticBadge';
import StatisticsTooltip from '../../StatisticTooltip';
import { Handle, Position } from 'reactflow';
import { NodePopover } from '../../NodePopover';
import { useNode } from '../../../hooks/useNode';

const NodeWidget = (props) => {
  const [nodeFormWaitingTime, setNodeFormWaitingTime] = useState(props.data.node.waitingTime);
  const [timeUnit, setTimeUnit] = useState(props.data.node.waitingUnit);
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
    setNodeFormWaitingTime(props.data.node.waitingTime);
    setTimeUnit(props.data.node.waitingUnit);
  })

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
          <WaitIcon/>
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
          <div className={bem('__right')}>
            <Handle
              type="source"
              id="right"
              position={Position.Right}
              isConnectable={props.isConnectable}
              className="port"
            />
            <StatisticBadge elementId={props.id} color="#ff851b" position="right"/>
          </div>
        </div>
      </div>
      <div className={bem('__title')}>
        <div className={bem('__name')}>
          {props.data.node.name
            ? props.data.node.name
            : `Wait - ${props.data.node.waitingTime} ${
              props.data.node.waitingUnit
            }`}
        </div>
      </div>

      <StatisticsTooltip
        id={props.id}
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
        <DialogTitle id="form-dialog-title">Wait node</DialogTitle>
        <DialogContent>
          <DialogContentText>
            Postpones the execution of next node in flow by selected amount of
            time.
          </DialogContentText>

          <Grid container>
            <Grid item xs={6}>
              <TextField
                margin="normal"
                id="waiting-time"
                label="Node name"
                variant="standard"
                fullWidth
                value={nodeFormName}
                onChange={event => {
                  setNodeFormName(event.target.value)
                }}
              />
            </Grid>
          </Grid>

          <Grid container>
            <Grid item xs={6}>
              <TextField
                id="waiting-time"
                label="Waiting time"
                type="number"
                variant="standard"
                fullWidth
                value={nodeFormWaitingTime}
                onChange={event => {
                  setNodeFormWaitingTime(event.target.value)
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
                    setTimeUnit(event.target.value)
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
              props.data.node.waitingTime = nodeFormWaitingTime;
              props.data.node.name = nodeFormName;
              props.data.node.waitingUnit = timeUnit;

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
