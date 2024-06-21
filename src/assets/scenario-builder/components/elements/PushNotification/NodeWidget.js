import * as React from 'react';
import { connect } from 'react-redux';
import ActionIcon from '@material-ui/icons/PhonelinkRing';
import Grid from '@material-ui/core/Grid';
import TextField from '@material-ui/core/TextField';
import Button from '@material-ui/core/Button';
import Dialog from '@material-ui/core/Dialog';
import DialogActions from '@material-ui/core/DialogActions';
import DialogContent from '@material-ui/core/DialogContent';
import DialogContentText from '@material-ui/core/DialogContentText';
import DialogTitle from '@material-ui/core/DialogTitle';
import { PortWidget } from '../../widgets/PortWidget';
import StatisticsTooltip from '../../StatisticTooltip';
import { setCanvasZoomingAndPanning } from '../../../actions';
import Autocomplete from "@material-ui/lab/Autocomplete";
import StatisticBadge from "../../StatisticBadge";

class NodeWidget extends React.Component {
  constructor(props) {
    super(props);
    this.state = {
      nodeFormName: this.props.node.name,
      selectedTemplate: this.props.node.selectedTemplate,
      selectedApplication: this.props.node.selectedApplication,
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

  getTemplatesInSelectableFormat = () => {
    return this.props.templates.map(item => {
      return {
        value: item.code,
        label: item.name,
      };
    });
  };

  getApplicationsInSelectableFormat = () => {
    return this.props.applications.map(item => {
      return {
        value: item.code,
        label: item.name,
      };
    });
  };

  getSelectedTemplateValue = () => {
    const selected = this.props.templates.find(
      item => item.code === this.props.node.selectedTemplate
    );

    return selected ? ` - ${selected.name}` : '';
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
            <ActionIcon />
          </div>

          <div className={this.bem('__ports')}>
            <div className={this.bem('__left')}>
              <PortWidget name='left' node={this.props.node} />
            </div>
            <div className={this.bem('__right')}>
              <PortWidget name='right' node={this.props.node} />
              <StatisticBadge elementId={this.props.node.id} color="#dc73ff" position="right" />
            </div>
          </div>
        </div>
        <div className={this.bem('__title')}>
          <div className={this.bem('__name')}>
            {this.props.node.name
              ? this.props.node.name
              : `Notification ${this.getSelectedTemplateValue()}`}
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
          fullWidth
        >
          <DialogTitle id='form-dialog-title'>Notification node</DialogTitle>

          <DialogContent>
            <DialogContentText>Sends a push notification to user.</DialogContentText>

            <Grid container>
              <Grid item xs={6}>
                <TextField
                  margin='normal'
                  id='action-name'
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

            <Grid container alignItems='center' alignContent='space-between'>
              <Grid item xs={12}>
                <Autocomplete
                  value={this.getTemplatesInSelectableFormat().find(
                    option => option.value === this.state.selectedTemplate
                  )}
                  options={this.getTemplatesInSelectableFormat()}
                  getOptionLabel={(option) => option.label}
                  style={{ marginBottom: 16 }}
                  onChange={(event, selectedOption) => {
                    if (selectedOption !== null) {
                      this.setState({
                        selectedTemplate: selectedOption.value
                      });
                    }
                  }}
                  renderInput={params => (
                    <TextField {...params} variant="standard" label="Notification template" fullWidth />
                  )}
                />
              </Grid>
            </Grid>

            <Grid container alignItems='center' alignContent='space-between'>
              <Grid item xs={12}>
                <Autocomplete
                  value={this.getApplicationsInSelectableFormat().find(
                    option => option.value === this.state.selectedApplication
                  )}
                  options={this.getApplicationsInSelectableFormat()}
                  getOptionLabel={(option) => option.label}
                  style={{ marginBottom: 16 }}
                  onChange={(event, selectedOption) => {
                    if (selectedOption !== null) {
                      this.setState({
                        selectedApplication: selectedOption.value
                      });
                    }
                  }}
                  renderInput={params => (
                    <TextField {...params} variant="standard" label="Application" fullWidth />
                  )}
                />
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
                this.props.node.selectedTemplate = this.state.selectedTemplate;
                this.props.node.selectedApplication = this.state.selectedApplication;

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
  return {
    templates: state.pushNotifications.availableTemplates,
    applications: state.pushNotifications.availableApplications,
  };
}

export default connect(mapStateToProps)(NodeWidget);
