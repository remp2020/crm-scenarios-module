import * as React from 'react';
import axios from 'axios';
import { connect } from 'react-redux';
import compose from 'recompose/compose';
import { DiagramWidget, NodeModel } from '@projectstorm/react-diagrams';
import Button from '@material-ui/core/Button';
import Drawer from '@material-ui/core/Drawer';
import AppBar from '@material-ui/core/AppBar';
import CssBaseline from '@material-ui/core/CssBaseline';
import Toolbar from '@material-ui/core/Toolbar';
import List from '@material-ui/core/List';
import ListSubheader from '@material-ui/core/ListSubheader';
import Typography from '@material-ui/core/Typography';
import Grid from '@material-ui/core/Grid';
import { withStyles } from '@material-ui/core/styles';
import CircularProgress from '@material-ui/core/CircularProgress';
import EmailIcon from '@material-ui/icons/Mail';
import ExtensionIcon from '@material-ui/icons/Extension';
import BannerIcon from '@material-ui/icons/Adjust';
import TriggerIcon from '@material-ui/icons/Notifications';
import WaitIcon from '@material-ui/icons/AccessAlarmsOutlined';
import SegmentIcon from '@material-ui/icons/SubdirectoryArrowRight';
import ConditionIcon from '@material-ui/icons/CallSplit';
import GoalIcon from '@material-ui/icons/CheckBox';
import NotificationsActiveIcon from '@material-ui/icons/NotificationsActive';
import PushNotificationIcon from '@material-ui/icons/PhonelinkRing';
import ABTestIcon from '@material-ui/icons/SwapVert';

import * as config from './../../config';
import { TrayItemWidget } from './TrayItemWidget';
import { ExportService } from '../../services/ExportService';
import Notification from '../Notification';
import {
  Email,
  Generic,
  Segment,
  Trigger,
  BeforeTrigger,
  Wait,
  Goal,
  Banner,
  Condition,
  PushNotification,
  ABTest
} from './../elements';
import {
  setScenarioId,
  setScenarioName,
  setCanvasNotification,
  setScenarioLoading
} from '../../actions';
import {ZoomIn, ZoomOut, ZoomOutMap} from "@material-ui/icons";
import {Divider} from "@material-ui/core";

const drawerWidth = 240;

const styles = theme => ({
  root: {
    display: 'flex'
  },
  appBar: {
    zIndex: theme.zIndex.drawer + 1
  },
  drawer: {
    width: drawerWidth,
    flexShrink: 0
  },
  drawerPaper: {
    width: drawerWidth
  },
  content: {
    flexGrow: 1,
    padding: 0
  },
  toolbar: theme.mixins.toolbar
});

const ctrlKey = 17,
    cmdKey = 91,
    vKey = 86,
    cKey = 67;

class BodyWidget extends React.Component {
  constructor(props) {
    super(props);
    this.state = {
      editingName: false,
      editedName: ''
    };

    this.ctrlDown = false;
    this.nodesToCopy = [];

    // Required to bind 'this' inside callback methods
    this.keydownHandler = this.keydownHandler.bind(this);
    this.keyupHandler = this.keyupHandler.bind(this);
    this.copyNode = this.copyNode.bind(this);
  }

  copyNode(nodeId) {
    let offset = { x: 75, y: 75 };
    let model = this.props.app.getDiagramEngine().getDiagramModel();
    let nodes = model.getNodes();

    if (nodes[nodeId] !== undefined) {
      let newNode = nodes[nodeId].clone({});
      newNode.setPosition(newNode.x + offset.x, newNode.y + offset.y);
      newNode.selected = false;
      model.addNode(newNode);
      this.forceUpdate();
    } else {
      console.warn("Unable to copy node with ID " + nodeId);
    }
  }

  keydownHandler(e) {
    if (e.keyCode === ctrlKey || e.keyCode === cmdKey) {
      this.ctrlDown = true;
    }

    // CTRL/CMD + C
    if (this.ctrlDown && (e.keyCode === cKey)) {
      let model = this.props.app.getDiagramEngine().getDiagramModel();
      this.nodesToCopy = [];
      for (const node of model.getSelectedItems()) {
        // currently do not allow to copy links
        if (node.selected && node instanceof NodeModel) {
          this.nodesToCopy.push(node.id);
        }
      }
    }

    // CTRL/CMD + V
    if (this.ctrlDown && (e.keyCode === vKey)) {
      for (const nodeId of this.nodesToCopy) {
        this.copyNode(nodeId);
      }
      this.nodesToCopy = [];
    }
  }

  keyupHandler(e) {
    if (e.keyCode === ctrlKey || e.keyCode === cmdKey) {
      this.ctrlDown = false;
    }
  }

  componentDidMount() {
    document.addEventListener('keydown', this.keydownHandler);
    document.addEventListener('keyup', this.keyupHandler);
  }
  
  componentWillUnmount() {
    document.removeEventListener('keydown', this.keydownHandler);
    document.removeEventListener('keyup', this.keyupHandler);
  }

  componentDidUpdate(prevProps) {
    // Typical usage (don't forget to compare props):
    if (this.props.app.isCorruptedPayload() === true && prevProps.app.isCorruptedPayload() === false) {
      this.props.dispatch(
        setCanvasNotification({
          open: true,
          variant: 'error',
          text: 'Unable to load corrupted scenario.'
        })
      );
    }
  }

  saveChanges = () => {
    const { dispatch } = this.props;

    // Check for corruption to prevent override
    if (this.props.app.isCorruptedPayload()) {
      dispatch(
        setCanvasNotification({
          open: true,
          variant: 'error',
          text: 'Cannot modify corrupted scenario.'
        })
      );
      return;
    };

    const exportService = new ExportService(
      this.props.app.getDiagramEngine().getDiagramModel()
    );

    const payload = {
      name: this.props.scenario.name,
      ...exportService.exportPayload()
    };

    const scenarioId = this.props.scenario.id;
    if (scenarioId) {
      payload.id = scenarioId;
    }

    dispatch(setScenarioLoading(true));

    axios
      .post(`${config.URL_SCENARIO_CREATE}`, payload)
      .then(response => {
        dispatch(setScenarioId(response.data.id));
        dispatch(setScenarioLoading(false));
        dispatch(
          setCanvasNotification({
            open: true,
            variant: 'success',
            text: 'Scenario saving succeeded.'
          })
        );
      })
      .catch(error => {
        dispatch(setScenarioLoading(false));

        let errorMessage = 'Scenario saving failed.';
        if (error.response.data && error.response.data.message) {
          errorMessage = error.response.data.message;
        }

        dispatch(
          setCanvasNotification({
            open: true,
            variant: 'error',
            text: errorMessage
          })
        );
        console.log(error);
      });
  };

  startEditingName = () => {
    this.setState({
      editedName: this.props.scenario.name,
      editingName: true
    });
  };

  cancelEditingName = () => {
    this.setState({
      editedName: '',
      editingName: false
    });
  };

  submitEditingName = () => {
    if (this.state.editedName.trim().length === 0) {
      this.cancelEditingName();
      return;
    }

    this.props.dispatch(setScenarioName(this.state.editedName));
    this.setState({
      editedName: '',
      editingName: false
    });
  };

  handleCloseAndSaveDuringChangingName = event => {
    if (event.keyCode === 27) {
      this.cancelEditingName();
    } else if (event.keyCode === 13) {
      this.submitEditingName();
    }
  };

  handleNameTyping = event => {
    this.setState({
      editedName: event.target.value
    });
  };

  closeNotification = () => {
    this.props.dispatch(setCanvasNotification({ open: false }));
  };

  zoomOut = () => {
    let zoomLevel = this.props.app.diagramEngine.getDiagramModel().getZoomLevel();
    this.props.app.diagramEngine.getDiagramModel().setZoomLevel(zoomLevel - 5);
    this.props.app.diagramEngine.repaintCanvas();
  };

  zoomIn = () => {
    let zoomLevel = this.props.app.diagramEngine.getDiagramModel().getZoomLevel();
    this.props.app.diagramEngine.getDiagramModel().setZoomLevel(zoomLevel + 5);
    this.props.app.diagramEngine.repaintCanvas();
  };

  zoomToFit = () => {
    this.props.app.diagramEngine.zoomToFit();
  };

  render() {
    const { classes, canvas } = this.props;

    const diagramProps = {
      className: 'srd-demo-canvas',
      diagramEngine: this.props.app.getDiagramEngine(),
      maxNumberPointsPerLink: 0,
      allowLooseLinks: false,
      allowCanvasTranslation: canvas.pannable,
      allowCanvasZoom: canvas.zoomable
    }; // as DiagramProps;

    return (
      <div className='body'>
        <div className={classes.root}>
          <CssBaseline />
          <AppBar position='fixed' className={classes.appBar}>
            <Toolbar>
              <Grid container>
                <Grid item xs={4}>
                  <Typography variant='h6' color='inherit' noWrap>
                    {this.state.editingName ? (
                      <input
                        autoFocus
                        type='text'
                        value={this.state.editedName}
                        onChange={this.handleNameTyping}
                        onKeyDown={this.handleCloseAndSaveDuringChangingName}
                        onBlur={this.submitEditingName}
                        className='changing-name-input'
                      />
                    ) : (
                      <span
                        onClick={this.startEditingName}
                        className='scenario-name'
                      >
                        {this.props.scenario.name}
                      </span>
                    )}
                  </Typography>
                </Grid>

                <Grid item xs={8}>
                  <Grid container direction='row' justify='flex-end'>
                    {!!this.props.scenario.loading && (
                      <CircularProgress
                        className='circular-loading'
                        size={19}
                        color='inherit'
                      />
                    )}
                      <Button
                        title="Zoom in"
                        size='small'
                        variant='contained'
                        color='primary'
                        onClick={() => this.zoomIn()}
                      >
                        <ZoomIn />
                      </Button>
                      <Button
                        title="Zoom out"
                        size='small'
                        variant='contained'
                        color='primary'
                        onClick={() => this.zoomOut()}
                      >
                        <ZoomOut titleAccess="Test" />
                      </Button>
                      <Button
                        title="Zoom to fit"
                        size='small'
                        variant='contained'
                        color='primary'
                        onClick={() => this.zoomToFit()}
                      >
                        <ZoomOutMap />
                      </Button>
                    <Divider orientation="vertical" variant="middle" flexItem />
                    <Button
                      size='small'
                      variant='contained'
                      color='secondary'
                      onClick={() => this.saveChanges()}
                    >
                      {this.props.scenario.id ? 'Update' : 'Save'}
                    </Button>
                  </Grid>
                </Grid>
              </Grid>
            </Toolbar>
          </AppBar>
          <Drawer
            className={classes.drawer}
            variant='permanent'
            classes={{
              paper: classes.drawerPaper
            }}
          >
            <div className={classes.toolbar} />
            <List
              component='nav'
              subheader={
                <ListSubheader component='div'>Triggers</ListSubheader>
              }
            >
              <TrayItemWidget
                model={{ type: 'trigger' }}
                name='Event'
                icon={<TriggerIcon />}
              />

              <TrayItemWidget
                  model={{ type: 'before_trigger' }}
                  name='Before Event'
                  icon={<NotificationsActiveIcon />}
              />
            </List>

            <List
              component='nav'
              subheader={<ListSubheader component='div'>Actions</ListSubheader>}
            >
              <TrayItemWidget
                model={{ type: 'email' }}
                name='Send email'
                icon={<EmailIcon />}
              />

              <TrayItemWidget
                model={{ type: 'generic' }}
                name='Run generic action'
                icon={<ExtensionIcon />}
              />

              {config.BANNER_ENABLED &&
                <TrayItemWidget
                  model={{ type: 'banner' }}
                  name='Show banner'
                  icon={<BannerIcon />}
                />
              }

              {config.PUSH_NOTIFICATION_ENABLED &&
                <TrayItemWidget
                  model={{ type: 'push_notification' }}
                  name='Send notification'
                  icon={<PushNotificationIcon />}
                />
              }

            </List>

            <List
              component='nav'
              subheader={
                <ListSubheader component='div'>Operations</ListSubheader>
              }
            >
              <TrayItemWidget
                model={{ type: 'segment' }}
                name='Segment'
                icon={<SegmentIcon />}
              />

              <TrayItemWidget
                model={{ type: 'condition' }}
                name='Condition'
                icon={<ConditionIcon />}
              />

              <TrayItemWidget
                model={{ type: 'wait' }}
                name='Wait'
                icon={<WaitIcon />}
              />

              <TrayItemWidget
                model={{ type: 'goal' }}
                name='Goal'
                icon={<GoalIcon />}
              />

              <TrayItemWidget
                model={{ type: 'ab_test' }}
                name='AB Test'
                icon={<ABTestIcon />}
              />
            </List>
          </Drawer>
          <Notification
            variant={this.props.canvas.notification.variant}
            text={this.props.canvas.notification.text}
            open={this.props.canvas.notification.open}
            handleClose={this.closeNotification}
          />

          <main className={classes.content}>
            <div
              className='diagram-layer'
              onDrop={event => {
                const stormDiagramNode = event.dataTransfer.getData(
                  'storm-diagram-node'
                );
                if (!stormDiagramNode) return;
                var data = JSON.parse(stormDiagramNode);

                var node = null;
                if (data.type === 'email') {
                  node = new Email.NodeModel({});
                } else if (data.type === 'generic') {
                  node = new Generic.NodeModel({});
                } else if (data.type === 'banner') {
                  node = new Banner.NodeModel({
                    expiresInUnit: 'days',
                    expiresInTime: 1,
                  });
                } else if (data.type === 'push_notification') {
                  node = new PushNotification.NodeModel({});
                } else if (data.type === 'segment') {
                  node = new Segment.NodeModel({});
                } else if (data.type === 'condition') {
                  node = new Condition.NodeModel({});
                } else if (data.type === 'trigger') {
                  node = new Trigger.NodeModel({});
                } else if (data.type === 'before_trigger') {
                  node = new BeforeTrigger.NodeModel({});
                } else if (data.type === 'wait') {
                  node = new Wait.NodeModel({});
                } else if (data.type === 'goal') {
                  node = new Goal.NodeModel({
                    recheckPeriodUnit: 'hours',
                    recheckPeriodTime: 1,
                  });
                } else if (data.type === 'ab_test') {
                  node = new ABTest.NodeModel({
                    name: "AB Test",
                    scenarioName: this.props.scenario.name
                  });
                }
                var points = this.props.app
                  .getDiagramEngine()
                  .getRelativeMousePoint(event);
                node.x = points.x;
                node.y = points.y;
                this.props.app
                  .getDiagramEngine()
                  .getDiagramModel()
                  .addNode(node);
                this.forceUpdate();
              }}
              onDragOver={event => {
                event.preventDefault();
              }}
            >
              <DiagramWidget {...diagramProps} />
            </div>
          </main>
        </div>
      </div>
    );
  }
}

function mapStateToProps(state) {
  return {
    canvas: state.canvas,
    scenario: state.scenario
  };
}

export default compose(
  withStyles(styles, { name: 'BodyWidget' }),
  connect(
    mapStateToProps,
    null
  )
)(BodyWidget);
