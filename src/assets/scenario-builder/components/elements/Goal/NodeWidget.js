import { useSelector } from 'react-redux';
import OkIcon from '@mui/icons-material/Check';
import TimeoutIcon from '@mui/icons-material/AccessTime';
import Grid from '@mui/material/Grid';
import TextField from '@mui/material/TextField';
import Button from '@mui/material/Button';
import Dialog from '@mui/material/Dialog';
import DialogActions from '@mui/material/DialogActions';
import DialogContent from '@mui/material/DialogContent';
import FormControl from '@mui/material/FormControl';
import InputLabel from '@mui/material/InputLabel';
import Select from '@mui/material/Select';
import MenuItem from '@mui/material/MenuItem';
import DialogContentText from '@mui/material/DialogContentText';
import DialogTitle from '@mui/material/DialogTitle';
import GoalIcon from '@mui/icons-material/CheckBox';
import StatisticsTooltip from '../../StatisticTooltip';
import StatisticBadge from "../../StatisticBadge";
import { Autocomplete } from "@mui/material";
import { Handle, Position } from 'reactflow'
import { store } from '../../../store';
import { setCanvasZoomingAndPanning } from '../../../store/canvasSlice';
import React, { useState } from 'react';
import { bemClassName } from '../../../utils/bem';

const NodeWidget = (props) => {
  const [nodeFormName, setNodeFormName] = useState(props.data.node.name);
  const [selectedGoals, setSelectedGoals] = useState(props.data.node.selectedGoals);
  const [timeoutTime, setTimeoutTime] = useState(props.data.node.timeoutTime);
  const [timeoutUnit, setTimeoutUnit] = useState(props.data.node.timeoutUnit);
  const [recheckPeriodTime, setRecheckPeriodTime] = useState(props.data.node.recheckPeriodTime);
  const [recheckPeriodUnit, setRecheckPeriodUnit] = useState(props.data.node.recheckPeriodUnit);
  const [dialogOpened, setDialogOpened] = useState(false);
  const [anchorElementForTooltip, setAnchorElementForTooltip] = useState(null);
  const goals = useSelector(state => state.goals.availableGoals)
  const statistics = useSelector(state => state.statistics.statistics)

  const bem = (selector) => bemClassName(
    selector,
    props.data.node.classBaseName,
    props.data.node.className
  )

  const getClassName = () => {
    return props.data.node.classBaseName + ' ' + props.data.node.className;
  }

  const openDialog = () => {
    if (dialogOpened) {
      return
    }

    setDialogOpened(true);
    setNodeFormName(props.data.node.name);
    setSelectedGoals(props.data.node.selectedGoals);
    setTimeoutTime(props.data.node.timeoutTime);
    setTimeoutUnit(props.data.node.timeoutUnit);
    setRecheckPeriodTime(props.data.node.recheckPeriodTime);
    setRecheckPeriodUnit(props.data.node.recheckPeriodUnit);
    setAnchorElementForTooltip(null);
    store.dispatch(setCanvasZoomingAndPanning(false));
  };

  const closeDialog = () => {
    setDialogOpened(false)
    store.dispatch(setCanvasZoomingAndPanning(true));
  };

  const handleNodeMouseEnter = event => {
    if (!dialogOpened) {
      setAnchorElementForTooltip(event.currentTarget)
    }
  };

  const handleNodeMouseLeave = () => {
    setAnchorElementForTooltip(null)
  };

  const getSelectedGoals = () => {
    if (selectedGoals === undefined) {
      return [];
    }

    return goals.filter((item) => {
      return selectedGoals.includes(item.code)
    }, selectedGoals);
  };

  return (
    <div
      className={getClassName()}
      onDoubleClick={() => {
        openDialog();
      }}
      onMouseEnter={handleNodeMouseEnter}
      onMouseLeave={handleNodeMouseLeave}
    >
      <div className={bem('__title')}>
        <div className={bem('__name')}>
          {props.data.node.name ? props.data.node.name : 'Goal'}
        </div>
      </div>

      <div className='node-container'>
        <div className={bem('__icon')}>
          <GoalIcon />
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
            {statistics[props.id] ?
              <StatisticBadge elementId={props.id} color="#21ba45" position="right" /> :
              <OkIcon style={{position: 'absolute', top: '-5px', right: '-30px', color: '#2ECC40'}} />
            }
          </div>

          <div className={bem('__bottom')}>
            <Handle
              type="source"
              id="bottom"
              position={Position.Bottom}
              isConnectable={props.isConnectable}
              className="port"
            />
            {statistics[props.id] ?
              <StatisticBadge elementId={props.id} color="#db2828" position="bottom" /> :
              <TimeoutIcon style={{position: 'absolute', top: '15px', right: '-5px', color: '#FF695E'}} />
            }
          </div>
        </div>
      </div>

      <StatisticsTooltip
        id={props.id}
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
        <DialogTitle id='form-dialog-title'>Goal node</DialogTitle>

        <DialogContent>
          <DialogContentText>
            Goal node evaluates whether user has completed selected onboarding goals.
            Timeout value can be optionally specified, defining a point in time when evalution of completed goals is stopped.
            Execution flow can be directed two ways from the node - a positive direction, when all goals are completed, or a negative one, when timeout threshold is reached.
          </DialogContentText>

          <Grid container>
            <Grid item xs={6}>
              <TextField
                margin='normal'
                id='goal-name'
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

          <Grid container style={{marginBottom: '10px'}}>
            <Grid item xs={12}>
              <Autocomplete
                multiple
                value={getSelectedGoals()}
                options={goals}
                getOptionLabel={(option) => option.name}
                isOptionEqualToValue={(option, value) => option.key === value.key}
                disableClearable={true}
                onChange={(event, values) => {
                  if (values !== null) {
                    setSelectedGoals(values.map(item => item.code))
                  }
                }}
                renderInput={params => (
                  <TextField {...params} variant="standard" label="Selected Goal(s)" fullWidth />
                )}
              />
            </Grid>
          </Grid>

          <Grid container spacing={1}>
            <Grid item xs={6}>
              <TextField
                id='recheck-period-time'
                label='Recheck period time'
                type='number'
                variant='standard'
                helperText="How often goals completition should be checked"
                fullWidth
                value={recheckPeriodTime}
                onChange={event => {
                  setRecheckPeriodTime(event.target.value)
                }}
              />
            </Grid>
            <Grid item xs={6}>
              <FormControl fullWidth variant='standard'>
                <InputLabel htmlFor='time-unit'>Time unit</InputLabel>
                <Select
                  variant="standard"
                  value={recheckPeriodUnit}
                  onChange={event => {
                    setRecheckPeriodUnit(event.target.value)
                  }}
                  inputProps={{
                    name: 'recheck-period-unit',
                    id: 'recheck-period-unit'
                  }}
                >
                  <MenuItem value='minutes'>Minutes</MenuItem>
                  <MenuItem value='hours'>Hours</MenuItem>
                  <MenuItem value='days'>Days</MenuItem>
                </Select>
              </FormControl>
            </Grid>
          </Grid>

          <Grid container spacing={1}>
            <Grid item xs={6}>
              <TextField
                id='timeout-time'
                label='Timeout time'
                type='number'
                variant='standard'
                placeholder="No timeout"
                helperText="Optionally select a timeout"
                fullWidth
                value={timeoutTime}
                onChange={event => {
                  setTimeoutTime(event.target.value)
                }}
              />
            </Grid>
            <Grid item xs={6}>
              <FormControl fullWidth variant='standard'>
                <InputLabel htmlFor='time-unit'>Time unit</InputLabel>
                <Select
                  variant="standard"
                  value={timeoutUnit}
                  onChange={event => {
                    setTimeoutUnit(event.target.value)
                  }}
                  inputProps={{
                    name: 'time-unit',
                    id: 'time-unit'
                  }}
                >
                  <MenuItem value='minutes'>Minutes</MenuItem>
                  <MenuItem value='hours'>Hours</MenuItem>
                  <MenuItem value='days'>Days</MenuItem>
                </Select>
              </FormControl>
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
              props.data.node.selectedGoals = selectedGoals;
              props.data.node.timeoutTime = timeoutTime;
              props.data.node.timeoutUnit = timeoutUnit;
              props.data.node.recheckPeriodTime = recheckPeriodTime;
              props.data.node.recheckPeriodUnit = recheckPeriodUnit;

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
