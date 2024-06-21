import * as React from 'react';
import { connect } from 'react-redux';
import WaitIcon from '@material-ui/icons/AccessAlarmsOutlined';
import Grid from '@material-ui/core/Grid';
import TextField from '@material-ui/core/TextField';
import Button from '@material-ui/core/Button';
import Dialog from '@material-ui/core/Dialog';
import MenuItem from '@material-ui/core/MenuItem';
import InputLabel from '@material-ui/core/InputLabel';
import Select from '@material-ui/core/Select';
import FormControl from '@material-ui/core/FormControl';
import DialogActions from '@material-ui/core/DialogActions';
import DialogContent from '@material-ui/core/DialogContent';
import DialogContentText from '@material-ui/core/DialogContentText';
import DialogTitle from '@material-ui/core/DialogTitle';
import { PortWidget } from '../../widgets/PortWidget';
import { setCanvasZoomingAndPanning } from '../../../actions';
import StatisticBadge from "../../StatisticBadge";
import StatisticsTooltip from "../../StatisticTooltip";

class NodeWidget extends React.Component {
  constructor(props) {
    super(props);
    this.state = {
      nodeFormWaitingTime: this.props.node.waitingTime,
      nodeFormName: this.props.node.name,
      timeUnit: this.props.node.waitingUnit,
      dialogOpened: false,
      anchorElementForTooltip: null
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
      nodeFormWaitingTime: this.props.node.waitingTime,
      nodeFormName: this.props.node.name,
      timeUnit: this.props.node.waitingUnit,
      anchorElementForTooltip: null
    });
    this.props.dispatch(setCanvasZoomingAndPanning(false));
  };

  closeDialog = () => {
    this.setState({ dialogOpened: false });
    this.props.dispatch(setCanvasZoomingAndPanning(true));
  };

  handleNodeMouseEnter = event => {
    if (!this.state.dialogOpened) {
      this.setState({ anchorElementForTooltip: event.currentTarget });
    }
  };

  handleNodeMouseLeave = () => {
    this.setState({ anchorElementForTooltip: null });
  };

  render() {
    return (
      <div
        className={this.getClassName()}
        style={{ background: this.props.node.color }}
        onDoubleClick={() => {
          this.openDialog();
        }}
        onMouseEnter={this.handleNodeMouseEnter}
        onMouseLeave={this.handleNodeMouseLeave}
      >
        <div className='node-container'>
          <div className={this.bem('__icon')}>
            <WaitIcon />
          </div>
          <div className={this.bem('__ports')}>
            <div className={this.bem('__left')}>
              <PortWidget name='left' node={this.props.node} />
            </div>
            <div className={this.bem('__right')}>
              <PortWidget name='right' node={this.props.node} />
              <StatisticBadge elementId={this.props.node.id} color="#ff851b" position="right" />
            </div>
          </div>
        </div>
        <div className={this.bem('__title')}>
          <div className={this.bem('__name')}>
            {this.props.node.name
              ? this.props.node.name
              : `Wait - ${this.props.node.waitingTime} ${
                  this.props.node.waitingUnit
                }`}
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
          <DialogTitle id='form-dialog-title'>Wait node</DialogTitle>
          <DialogContent>
            <DialogContentText>
              Postpones the execution of next node in flow by selected amount of
              time.
            </DialogContentText>

            <Grid container>
              <Grid item xs={6}>
                <TextField
                  margin='normal'
                  id='waiting-time'
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

            <Grid container>
              <Grid item xs={6}>
                <TextField
                  id='waiting-time'
                  label='Waiting time'
                  type='number'
                  fullWidth
                  value={this.state.nodeFormWaitingTime}
                  onChange={event => {
                    this.setState({
                      nodeFormWaitingTime: event.target.value
                    });
                  }}
                />
              </Grid>
              <Grid item xs={6}>
                <FormControl fullWidth>
                  <InputLabel htmlFor='time-unit'>Time unit</InputLabel>
                  <Select
                    value={this.state.timeUnit}
                    onChange={event => {
                      this.setState({
                        timeUnit: event.target.value
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
                // https://github.com/projectstorm/react-diagrams/issues/50 huh

                this.props.node.waitingTime = this.state.nodeFormWaitingTime;
                this.props.node.name = this.state.nodeFormName;
                this.props.node.waitingUnit = this.state.timeUnit;

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
  const { segments } = state;

  return {
    segments: segments.avalaibleSegments
  };
}

export default connect(mapStateToProps)(NodeWidget);
