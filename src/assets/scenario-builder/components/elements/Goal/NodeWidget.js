import * as React from 'react';
import { connect } from 'react-redux';
import OkIcon from '@material-ui/icons/Check';
import TimeoutIcon from '@material-ui/icons/AccessTime';
import Grid from '@material-ui/core/Grid';
import TextField from '@material-ui/core/TextField';
import Button from '@material-ui/core/Button';
import Dialog from '@material-ui/core/Dialog';
import DialogActions from '@material-ui/core/DialogActions';
import DialogContent from '@material-ui/core/DialogContent';
import FormControl from '@material-ui/core/FormControl';
import InputLabel from '@material-ui/core/InputLabel';
import Select from '@material-ui/core/Select';
import MenuItem from '@material-ui/core/MenuItem';
import DialogContentText from '@material-ui/core/DialogContentText';
import DialogTitle from '@material-ui/core/DialogTitle';
import GoalIcon from '@material-ui/icons/CheckBox';

import StatisticsTooltip from '../../StatisticTooltip';
import { PortWidget } from './../../widgets/PortWidget';
import { setCanvasZoomingAndPanning } from '../../../actions';
import StatisticBadge from "../../StatisticBadge";
import {Autocomplete} from "@material-ui/lab";

class NodeWidget extends React.Component {
  constructor(props) {
    super(props);
    this.state = {
      nodeFormName: this.props.node.name,
      selectedGoals: this.props.node.selectedGoals,
      timeoutTime: this.props.node.timeoutTime,
      timeoutUnit: this.props.node.timeoutUnit,
      recheckPeriodTime: this.props.node.recheckPeriodTime,
      recheckPeriodUnit: this.props.node.recheckPeriodUnit,
      dialogOpened: false,
      anchorElementForTooltip: null,
    };
  }

  bem(selector) {
    return (
      this.props.classBaseName +
      selector +
      ' ' +
      this.props.className +
      selector +
      ' '
    );
  }

  getClassName() {
    return this.props.classBaseName + ' ' + this.props.className;
  }

  openDialog = () => {
    this.setState({
      dialogOpened: true,
      nodeFormName: this.props.node.name,
      selectedGoals: this.props.node.selectedGoals,
      timeoutTime: this.props.node.timeoutTime,
      timeoutUnit: this.props.node.timeoutUnit,
      recheckPeriodTime: this.props.node.recheckPeriodTime,
      recheckPeriodUnit: this.props.node.recheckPeriodUnit,
      anchorElementForTooltip: null
    });
    this.props.dispatch(setCanvasZoomingAndPanning(false));
  };

  closeDialog = () => {
    this.setState({ dialogOpened: false });
    this.props.dispatch(setCanvasZoomingAndPanning(true));
  };

  transformOptionsForSelect = () => {
    const goals = this.props.goals.map(goal => ({
      value: goal.code,
      label: goal.name,
    }));
    return goals;
  };

  handleNodeMouseEnter = event => {
    if (!this.state.dialogOpened) {
      this.setState({ anchorElementForTooltip: event.currentTarget });
    }
  };

  handleNodeMouseLeave = () => {
    this.setState({ anchorElementForTooltip: null });
  };

  getSelectedGoals = () => {
    if (this.state.selectedGoals === undefined) {
      return [];
    }

    return this.props.goals.filter((item) => {
      return this.state.selectedGoals.includes(item.code)
    }, this.state.selectedGoals);
  };

  render() {
    return (
      <div
        className={this.getClassName()}
        onDoubleClick={() => {
          this.openDialog();
        }}
        onMouseEnter={this.handleNodeMouseEnter}
        onMouseLeave={this.handleNodeMouseLeave}
      >
        <div className={this.bem('__title')}>
          <div className={this.bem('__name')}>
            {this.props.node.name ? this.props.node.name : 'Goal'}
          </div>
        </div>

        <div className='node-container'>
          <div className={this.bem('__icon')}>
            <GoalIcon />
          </div>

          <div className={this.bem('__ports')}>
            <div className={this.bem('__left')}>
              <PortWidget name='left' node={this.props.node} />
            </div>

            <div className={this.bem('__right')}>
              <PortWidget name='right' node={this.props.node} />
                {this.props.statistics[this.props.node.id] ?
                  <StatisticBadge elementId={this.props.node.id} color="#21ba45" position="right" /> :
                  <OkIcon style={{position: 'absolute', top: '-5px', right: '-30px', color: '#2ECC40'}} />
                }
            </div>

            <div className={this.bem('__bottom')}>
              <PortWidget name='bottom' node={this.props.node} />
              {this.props.statistics[this.props.node.id] ?
                <StatisticBadge elementId={this.props.node.id} color="#db2828" position="bottom" /> :
                <TimeoutIcon style={{position: 'absolute', top: '15px', right: '-5px', color: '#FF695E'}} />
              }
            </div>
          </div>
        </div>

        <StatisticsTooltip
          id={this.props.node.id}
          anchorElement={this.state.anchorElementForTooltip}
        />

        <Dialog
          open={this.state.dialogOpened}
          onClose={this.closeDialog}
          aria-labelledby='form-dialog-title'
          onKeyUp={event => {
            if (event.keyCode === 46 || event.keyCode === 8) {
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
                  fullWidth
                  value={this.state.nodeFormName}
                  onChange={event => {
                    this.setState({
                      nodeFormName: event.target.value
                    });
                  }}
                />
              </Grid>
            </Grid>

            <Grid container style={{marginBottom: '10px'}}>
              <Grid item xs={12}>
                <Autocomplete
                  multiple
                  value={this.getSelectedGoals()}
                  options={this.props.goals}
                  getOptionLabel={(option) => option.name}
                  disableClearable={true}
                  onChange={(event, values) => {
                    if (values !== null) {
                      this.setState({
                        selectedGoals: values.map(item => item.code)
                      });
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
                  helperText="How often goals completition should be checked"
                  fullWidth
                  value={this.state.recheckPeriodTime}
                  onChange={event => {
                    this.setState({
                      recheckPeriodTime: event.target.value
                    });
                  }}
                />
              </Grid>
              <Grid item xs={6}>
                <FormControl fullWidth>
                  <InputLabel htmlFor='time-unit'>Time unit</InputLabel>
                  <Select
                    value={this.state.recheckPeriodUnit}
                    onChange={event => {
                      this.setState({
                        recheckPeriodUnit: event.target.value
                      });
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
                  placeholder="No timeout"
                  helperText="Optionally select a timeout"
                  fullWidth
                  value={this.state.timeoutTime}
                  onChange={event => {
                    this.setState({
                      timeoutTime: event.target.value
                    });
                  }}
                />
              </Grid>
              <Grid item xs={6}>
                <FormControl fullWidth>
                  <InputLabel htmlFor='time-unit'>Time unit</InputLabel>
                  <Select
                    value={this.state.timeoutUnit}
                    onChange={event => {
                      this.setState({
                        timeoutUnit: event.target.value
                      });
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
                this.closeDialog();
              }}
            >
              Cancel
            </Button>

            <Button
              color='primary'
              onClick={() => {
                this.props.node.name = this.state.nodeFormName;
                this.props.node.selectedGoals = this.state.selectedGoals;
                this.props.node.timeoutTime = this.state.timeoutTime;
                this.props.node.timeoutUnit = this.state.timeoutUnit;
                this.props.node.recheckPeriodTime = this.state.recheckPeriodTime;
                this.props.node.recheckPeriodUnit = this.state.recheckPeriodUnit;
                this.props.diagramEngine.repaintCanvas();
                this.closeDialog();
              }}
            >
              Save changes
            </Button>
          </DialogActions>
        </Dialog>
      </div>
    );
  }
}

function mapStateToProps(state) {
  const { goals, dispatch } = state;

  return {
    goals: goals.availableGoals,
    dispatch,
    statistics: state.statistics.statistics
  };
}

export default connect(mapStateToProps)(NodeWidget);
