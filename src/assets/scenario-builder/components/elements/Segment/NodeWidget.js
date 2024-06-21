import * as React from 'react';
import { connect } from 'react-redux';
import SegmentIcon from '@material-ui/icons/SubdirectoryArrowRight';
import Grid from '@material-ui/core/Grid';
import TextField from '@material-ui/core/TextField';
import Button from '@material-ui/core/Button';
import Dialog from '@material-ui/core/Dialog';
import DialogActions from '@material-ui/core/DialogActions';
import DialogContent from '@material-ui/core/DialogContent';
import DialogContentText from '@material-ui/core/DialogContentText';
import DialogTitle from '@material-ui/core/DialogTitle';
import Icon from '@material-ui/core/Icon';
import { PortWidget } from '../../widgets/PortWidget';
import { setCanvasZoomingAndPanning } from '../../../actions';
import SegmentSelector from './SegmentSelector';
import * as config from '../../../config';
import { styled } from '@material-ui/core/styles';
import StatisticBadge from "../../StatisticBadge";
import StatisticsTooltip from "../../StatisticTooltip";
import OkIcon from "@material-ui/icons/Check";
import NopeIcon from "@material-ui/icons/Close";

const NewSegmentButton = styled(Button)({
  marginRight: 'auto'
});

class NodeWidget extends React.Component {
  constructor(props) {
    super(props);
    this.state = {
      nodeFormName: this.props.node.name,
      selectedSegment: this.props.node.selectedSegment,
      dialogOpened: false,
      anchorElementForTooltip: null,
      creatingNewSegment: false,
      selectedSegmentSourceTable: null
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
      selectedSegment: this.props.node.selectedSegment,
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

  actionSetTable = table => {
    if (this.state.selectedSegmentSourceTable !== table) {
      this.setState({selectedSegment: null});
      this.setState({selectedSegmentSourceTable: table});
    }
  };

  segmentSelectedChange = segment => {
    let value = null;
    if (segment && segment.hasOwnProperty('code')) {
      value = segment.code;
    }

    this.setState({selectedSegment: value});
  };

  getSelectedSegmentValue = () => {
    const selected = this.props.segments.flatMap(
      item => item.segments).find(
        segment => segment.code === this.props.node.selectedSegment
    );

    return selected ? ` - ${selected.name}` : '';
  };

  handleNewSegmentClick = () => {
    window.open(config.URL_SEGMENT_NEW);
  };

  render() {
    let displayStatisticBadge = false;
    if (this.props.statistics.length === 0 || this.props.statistics[this.props.node.id]) {
      displayStatisticBadge = true;
    }

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
            {this.props.node.name
              ? this.props.node.name
              : `Segment ${this.getSelectedSegmentValue()}`}
          </div>
        </div>

        <div className='node-container'>
          <div className={this.bem('__icon')}>
            <SegmentIcon />
          </div>

          <div className={this.bem('__ports')}>
            <div className={this.bem('__left')}>
              <PortWidget name='left' node={this.props.node} />
            </div>

            <div className={this.bem('__right')}>
              <PortWidget name='right' node={this.props.node} />
              {displayStatisticBadge ?
                <StatisticBadge elementId={this.props.node.id} color="#21ba45" position="right" /> :
                <OkIcon style={{position: 'absolute', top: '-5px', right: '-30px', color: '#2ECC40'}} />
              }
            </div>

            <div className={this.bem('__bottom')}>
              <PortWidget name='bottom' node={this.props.node} />
              {displayStatisticBadge ?
                <StatisticBadge elementId={this.props.node.id} color="#db2828" position="bottom" /> :
                <NopeIcon style={{position: 'absolute', top: '15px', right: '-5px', color: '#FF695E'}} />
              }
            </div>
          </div>
        </div>

        <StatisticsTooltip
          id={this.props.node.id}
          anchorElement={this.state.anchorElementForTooltip}
        />

        <Dialog
          fullWidth={true}
          maxWidth='md'
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
          <DialogTitle id='form-dialog-title'>Segment node</DialogTitle>

          <DialogContent>
            <DialogContentText>
              Segments evaluate user's presence in a group of users defined
              by system-provided conditions. Execution flow can be directed
              based on presence/absence of user within the selected segment.
              You can either pick one of the existing segments or create a
              new one.
            </DialogContentText>

            <Grid container spacing={3}>
              <Grid item xs={6}>
                <TextField
                  margin='normal'
                  id='segment-name'
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

            <Grid container spacing={3} alignItems='flex-end'>
              <Grid item xs={12}>
                <SegmentSelector
                    selectedSegment={this.state.selectedSegment}
                    selectedSegmentSourceTable={this.state.selectedSegmentSourceTable}
                    onSegmentTypeButtonClick={this.actionSetTable}
                    onSegmentSelectedChange={this.segmentSelectedChange}
                >
                </SegmentSelector>
              </Grid>
            </Grid>
          </DialogContent>

          <DialogActions>
            <NewSegmentButton 
              color='primary'
              onClick={this.handleNewSegmentClick}
            >
              <Icon style={{ marginRight: '5px' }}>add_circle</Icon>
              New segment
            </NewSegmentButton>

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
                this.props.node.selectedSegment = this.state.selectedSegment;

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
  const { segments, dispatch } = state;

  return {
    segments: segments.avalaibleSegments,
    statistics: state.statistics.statistics,
    dispatch
  };
}

export default connect(mapStateToProps)(NodeWidget);
