import * as React from 'react';
import { connect } from 'react-redux';
import ActionIcon from '@material-ui/icons/Mail';
import Grid from '@material-ui/core/Grid';
import TextField from '@material-ui/core/TextField';
import Button from '@material-ui/core/Button';
import Dialog from '@material-ui/core/Dialog';
import DialogActions from '@material-ui/core/DialogActions';
import DialogContent from '@material-ui/core/DialogContent';
import DialogContentText from '@material-ui/core/DialogContentText';
import DialogTitle from '@material-ui/core/DialogTitle';
import {styled} from '@material-ui/core/styles';
import { PortWidget } from '../../widgets/PortWidget';
import { setCanvasZoomingAndPanning } from '../../../actions';
import StatisticBadge from "../../StatisticBadge";
import StatisticsTooltip from "../../StatisticTooltip";
import {Autocomplete} from "@material-ui/lab";
import {withStyles} from "@material-ui/core";
import {createFilterOptions} from "@material-ui/lab/Autocomplete";

const PreviewEmailButton = styled(Button)({
  marginRight: 'auto'
});

const styles = theme => ({
  autocomplete: {
    margin: theme.spacing(1)
  },
  subtitle: {
    paddingLeft: '6px',
    color: theme.palette.grey[600]
  },
});

class NodeWidget extends React.Component {
  constructor(props) {
    super(props);
    this.state = {
      nodeFormName: this.props.node.name,
      selectedMail: this.props.node.selectedMail,
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
      selectedMail: this.props.node.selectedMail,
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

  getSelectedMail = () => {
    const selected = this.props.mails.find(
      mail => mail.code === this.state.selectedMail
    );

    return selected ? selected : null;
  };

  getSelectedMailValue = () => {
    const selected = this.props.mails.find(
      mail => mail.code === this.props.node.selectedMail
    );

    return selected ? ` - ${selected.name}` : '';
  };

  filterOptions = () => createFilterOptions({
    matchFrom: 'any',
    trim: true,
    ignoreAccents: true,
    ignoreCase: true,
    stringify: option => {
      return option.name + " " + option.code;
    },
  });

  render() {
    const {classes} = this.props;

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
              <StatisticBadge elementId={this.props.node.id} color="#a291fb" position="right" />
            </div>
          </div>
        </div>
        <div className={this.bem('__title')}>
          <div className={this.bem('__name')}>
            {this.props.node.name
              ? this.props.node.name
              : `Mail ${this.getSelectedMailValue()}`}
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
          <DialogTitle id='form-dialog-title'>Email node</DialogTitle>

          <DialogContent>
            <DialogContentText>Sends an email to user.</DialogContentText>

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
                  value={this.getSelectedMail()}
                  options={this.props.mails}
                  getOptionLabel={(option) => option.name}
                  disableClearable={true}
                  filterOptions={this.filterOptions()}
                  groupBy={(option) => option.mail_type.code}
                  onChange={(event, selectedOption) => {
                    if (selectedOption !== null) {
                      this.setState({
                        selectedMail: selectedOption.code
                      })
                    }
                  }}
                  renderInput={params => (
                    <TextField {...params} variant="standard" label="Selected Mail" fullWidth />
                  )}
                  renderOption={(option, { selected }) => (
                    <div>
                      <span className={classes.title}>{option.name}</span>
                      <small className={classes.subtitle}>({option.code})</small>
                    </div>
                  )}
                />
              </Grid>
            </Grid>
          </DialogContent>

          <DialogActions>
            {this.props.mails.filter(mail => mail.link && mail.code === this.state.selectedMail).map(item => 
              <PreviewEmailButton color='primary' href={item.link} target="_blank">
                <ActionIcon style={{ marginRight: '5px' }}/>Preview
              </PreviewEmailButton>
            )}
            
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
                this.props.node.selectedMail = this.state.selectedMail;

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
    mails: state.mails.availableMails
  };
}

export default connect(mapStateToProps)(
  withStyles(styles)(NodeWidget)
);
