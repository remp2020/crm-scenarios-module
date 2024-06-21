import * as React from 'react';
import { connect } from 'react-redux';
import SwapVertIcon from '@material-ui/icons/SwapVert';
import Grid from '@material-ui/core/Grid';
import TextField from '@material-ui/core/TextField';
import Button from '@material-ui/core/Button';
import Dialog from '@material-ui/core/Dialog';
import DialogActions from '@material-ui/core/DialogActions';
import DialogContent from '@material-ui/core/DialogContent';
import DialogTitle from '@material-ui/core/DialogTitle';
import { PortWidget } from './../../widgets/PortWidget';
import { setCanvasZoomingAndPanning } from '../../../actions';
import DialogContentText from "@material-ui/core/DialogContentText";
import VariantBuilder from "./VariantBuilder";
import {Typography} from "@material-ui/core";
import {PortModel} from './PortModel';
import StatisticBadge from "../../StatisticBadge";
import StatisticsTooltip from "../../StatisticTooltip";

class NodeWidget extends React.Component {

  constructor(props) {
    super(props);

    // Use it to access VariantBuilder state
    this.builderRef = React.createRef();

    this.state = {
      nodeFormName: this.props.node.name,
      enabledSave: true,
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

  enableSave = enable => {
    // prevents re-rendering, setState only if value differs
    if (this.state.enabledSave !== enable) {
      this.setState( {enabledSave: enable });
    }
  };

  syncNodeModel = () => {

    // Add ports if is not in variants
    this.props.node.variants.forEach((variant, index) => {
      if (!this.props.node.getPort('right.' + index)) {
        this.props.node.addPort(new PortModel('right.' + index));
      }
    });

    // Remove ports if is not in variants
    for (const portName in this.props.node.getPorts()) {
      if (portName.startsWith('right.')) {
        let index = portName.split('.')[1];
        if (!this.props.node.variants[index]) {
          let port = this.props.node.getPort(portName);

          for (const [link] of Object.entries(port.getLinks())) {
            this.props.diagramEngine.diagramModel.removeLink(link);
          }
          this.props.node.removePort(port);
        }
      }
    }
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
        <div className={this.bem('__title')}>
          <div className={this.bem('__name')}>
            {this.props.node.name
              ? this.props.node.name
              : 'AB Test'}
          </div>
        </div>

        <div className='node-container'>
          <div className={this.bem('__icon')}>
            <SwapVertIcon />
          </div>

          <div className={this.bem('__ports')}>
            <div className={this.bem('__left')}>
              <PortWidget name='left' node={this.props.node} />
            </div>
          </div>

          <div className={this.bem('__ports')}>
            {this.props.node.variants.flatMap((variant, index) => (
              <div className={this.bem('__right')} key={"right-port-" + index} style={{position: 'relative'}}>
                <PortWidget name={"right." + index} node={this.props.node} />
                  <div className={this.bem('__description')}>
                    <StatisticBadge elementId={this.props.node.id} index={variant.code} color="#767676" position="right-condensed" />
                    <Typography
                      style={{fontSize: '0.8rem', marginLeft: '5px'}}
                      noWrap
                    >
                      {variant.name} ({variant.distribution}%)
                    </Typography>
                  </div>
              </div>
            ))}
          </div>
        </div>

        <StatisticsTooltip
          id={this.props.node.id}
          anchorElement={this.state.anchorElementForTooltip}
          variants={this.props.node.variants}
        />

        <Dialog
          open={this.state.dialogOpened}
          onClose={this.closeDialog}
          maxWidth='md'
          aria-labelledby='form-dialog-title'
          onKeyUp={event => {
            if (event.keyCode === 46 || event.keyCode === 8) {
              event.preventDefault();
              event.stopPropagation();
              return false;
            }
          }}
        >
          <DialogTitle id='form-dialog-title'>AB Test</DialogTitle>

          <DialogContent>

            <DialogContentText>
              A/B testing is comparing two versions of either a webpage, email campaign or an aspect in a scenario to evaluate which performs best.
              With the different variants shown to your customers, you can determine which version is the most effective.
            </DialogContentText>

            <Grid container>
              <Grid style={{marginBottom: '10px'}} item xs={6}>
                <TextField
                  margin='normal'
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

            <VariantBuilder
              variants={this.props.node.variants}
              node={this.props.node}
              onEnableSave={this.enableSave}
              ref={this.builderRef}>
            </VariantBuilder>

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
              disabled={!this.state.enabledSave}
              onClick={() => {
                // https://github.com/projectstorm/react-diagrams/issues/50

                this.props.node.name = this.state.nodeFormName;
                this.props.node.variants = this.builderRef.current.state.variants;

                this.syncNodeModel();

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
  return { dispatch };
}

export default connect(mapStateToProps)(NodeWidget);