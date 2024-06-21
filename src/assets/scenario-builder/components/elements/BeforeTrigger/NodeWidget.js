import * as React from 'react';
import { connect } from 'react-redux';
import NotificationsActiveIcon from '@material-ui/icons/NotificationsActive';
import Grid from '@material-ui/core/Grid';
import TextField from '@material-ui/core/TextField';
import Button from '@material-ui/core/Button';
import Dialog from '@material-ui/core/Dialog';
import DialogActions from '@material-ui/core/DialogActions';
import DialogContent from '@material-ui/core/DialogContent';
import DialogContentText from '@material-ui/core/DialogContentText';
import DialogTitle from '@material-ui/core/DialogTitle';
import { PortWidget } from '../../widgets/PortWidget';
import Autocomplete from '@material-ui/lab/Autocomplete';
import StatisticsTooltip from '../../StatisticTooltip';
import { setCanvasZoomingAndPanning } from '../../../actions';
import FormControl from "@material-ui/core/FormControl";
import InputLabel from "@material-ui/core/InputLabel";
import Select from "@material-ui/core/Select";
import MenuItem from "@material-ui/core/MenuItem";
import StatisticBadge from "../../StatisticBadge";

class NodeWidget extends React.Component {
  constructor(props) {
    super(props);

    this.state = {
      nodeFormName: this.props.node.name,
      selectedTrigger: this.props.node.selectedTrigger,
      nodeFormBeforeTime: this.props.node.time,
      timeUnit: this.props.node.timeUnit,
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
      nodeFormName: this.props.node.name,
      nodeFormBeforeTime: this.props.node.time,
      timeUnit: this.props.node.timeUnit,
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

  getTriggersInSelectableFormat = () => {
    return this.props.beforeTriggers.map(trigger => {
      return {
        value: trigger.code,
        label: trigger.name
      };
    });
  };

  getSelectedTriggerValue = () => {
    const selected = this.getTriggersInSelectableFormat().find(
      trigger => trigger.value === this.props.node.selectedTrigger
    );

    return selected ? `${this.props.node.time} ${this.props.node.timeUnit} before - ${selected.label} event` : 'Before Event';
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
            <NotificationsActiveIcon />
          </div>

          <div className={this.bem('__ports')}>
            <div className={this.bem('__right')}>
              <PortWidget name='right' node={this.props.node} />
              <StatisticBadge elementId={this.props.node.id} color="#00b5ad" position="right" />
            </div>
          </div>
        </div>

        <div className={this.bem('__title')}>
          <div className={this.bem('__name')}>
            {this.props.node.name
              ? this.props.node.name
              : this.getSelectedTriggerValue()}
          </div>
        </div>

        <StatisticsTooltip
          id={this.props.node.id}
          isTrigger={true}
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
          <DialogTitle id='form-dialog-title'>Before event node</DialogTitle>

          <DialogContent>
            <DialogContentText>
              Events are emitted in advanced of trigger according to selected time period.
            </DialogContentText>

            <Grid container>
              <Grid item xs={6}>
                <TextField
                  margin='normal'
                  id='trigger-name'
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
              <Grid item xs={12}>
                <Autocomplete
                    value={this.getTriggersInSelectableFormat().find(
                      option => option.value === this.state.selectedTrigger
                    )}
                    options={this.getTriggersInSelectableFormat()}
                    getOptionLabel={(option) => option.label}
                    style={{ marginBottom: 16 }}
                    onChange={(event, selectedOption) => {
                      if (selectedOption !== null) {
                        this.setState({
                          selectedTrigger: selectedOption.value
                        });
                      }
                    }}
                    renderInput={params => (
                        <TextField {...params} variant="standard" label="Trigger" fullWidth />
                    )}
                  />
              </Grid>
            </Grid>

            <Grid container>
              <Grid item xs={6}>
                <TextField
                    id='waiting-time'
                    label='Before time'
                    type='number'
                    fullWidth
                    value={this.state.nodeFormBeforeTime}
                    onChange={event => {
                      this.setState({
                        nodeFormBeforeTime: event.target.value
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

                this.props.node.name = this.state.nodeFormName;
                this.props.node.selectedTrigger = this.state.selectedTrigger;
                this.props.node.time = this.state.nodeFormBeforeTime;
                this.props.node.timeUnit = this.state.timeUnit;

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
  const { beforeTriggers } = state;

  return {
    beforeTriggers: beforeTriggers.availableBeforeTriggers
  };
}

export default connect(mapStateToProps)(NodeWidget);
