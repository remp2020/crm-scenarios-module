import * as React from 'react';
import { connect } from 'react-redux';
import ConditionIcon from '@material-ui/icons/CallSplit';
import OkIcon from '@material-ui/icons/Check';
import Grid from '@material-ui/core/Grid';
import NopeIcon from '@material-ui/icons/Close';
import TextField from '@material-ui/core/TextField';
import Button from '@material-ui/core/Button';
import Dialog from '@material-ui/core/Dialog';
import DialogActions from '@material-ui/core/DialogActions';
import DialogContent from '@material-ui/core/DialogContent';
import DialogTitle from '@material-ui/core/DialogTitle';
import CriteriaBuilder from './CriteriaBuilder';
import { PortWidget } from '../../widgets/PortWidget';
import { setCanvasZoomingAndPanning } from '../../../actions';
import StatisticBadge from "../../StatisticBadge";
import StatisticsTooltip from "../../StatisticTooltip";

class NodeWidget extends React.Component {
  constructor(props) {
    super(props);

    // Use it to access CriteriaBuilder state
    this.builderRef = React.createRef();

    this.state = {
      nodeFormName: this.props.node.name,
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


  render() {
    let displayStatisticBadge = false;
    if (this.props.statistics.length === 0 || this.props.statistics[this.props.node.id]) {
      displayStatisticBadge = true;
    }
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
        <div className={this.bem('__title')}>
          <div className={this.bem('__name')}>
            {this.props.node.name
              ? this.props.node.name
              : 'Condition'}
          </div>
        </div>

        <div className='node-container'>
          <div className={this.bem('__icon')}>
            <ConditionIcon />
          </div>

          <div className={this.bem('__ports')}>
            <div className={this.bem('__left')}>
              <PortWidget name='left' node={this.props.node} />
            </div>

            <div className={this.bem('__right')}>
              <PortWidget name='right' node={this.props.node} />
              {displayStatisticBadge ?
                <StatisticBadge elementId={this.props.node.id} color="#21ba45" position="right" /> :
                <OkIcon style={{position: 'absolute', top: '-5px', right: '-30px', color: '#2ECC40'}}/>
              }
            </div>

            <div className={this.bem('__bottom')}>
              <PortWidget name='bottom' node={this.props.node} />
              {displayStatisticBadge ?
                <StatisticBadge elementId={this.props.node.id} color="#db2828" position="bottom"/> :
                <NopeIcon style={{position: 'absolute', top: '15px', right: '-5px', color: '#FF695E'}}/>
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
          <DialogTitle id='form-dialog-title'>
            Event Condition
          </DialogTitle>

          <DialogContent>
            <Grid container>
              <Grid style={{marginBottom: '10px'}} item xs={6}>
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

              <CriteriaBuilder
                conditions={this.props.node.conditions}
                ref={this.builderRef}>
              </CriteriaBuilder>
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
                this.props.node.conditions = this.builderRef.current.state;

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
  const { dispatch } = state;
  return {
    dispatch,
    statistics: state.statistics.statistics
  };
}

export default connect(mapStateToProps)(NodeWidget);