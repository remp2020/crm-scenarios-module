import React, { useCallback, useEffect, useState } from 'react';
import axios from 'axios';
import { useDispatch, useSelector } from 'react-redux';
import { makeStyles } from '@mui/styles';
import Button from '@mui/material/Button';
import Drawer from '@mui/material/Drawer';
import AppBar from '@mui/material/AppBar';
import CssBaseline from '@mui/material/CssBaseline';
import Toolbar from '@mui/material/Toolbar';
import List from '@mui/material/List';
import ListSubheader from '@mui/material/ListSubheader';
import Typography from '@mui/material/Typography';
import Grid from '@mui/material/Grid';
import CircularProgress from '@mui/material/CircularProgress';
import EmailIcon from '@mui/icons-material/Mail';
import ExtensionIcon from '@mui/icons-material/Extension';
import BannerIcon from '@mui/icons-material/Adjust';
import TriggerIcon from '@mui/icons-material/Notifications';
import WaitIcon from '@mui/icons-material/AccessAlarmsOutlined';
import SegmentIcon from '@mui/icons-material/SubdirectoryArrowRight';
import ConditionIcon from '@mui/icons-material/CallSplit';
import GoalIcon from '@mui/icons-material/CheckBox';
import NotificationsActiveIcon from '@mui/icons-material/NotificationsActive';
import PushNotificationIcon from '@mui/icons-material/PhonelinkRing';
import ABTestIcon from '@mui/icons-material/SwapVert';
import * as config from './../../config';
import { TrayItemWidget } from './TrayItemWidget';
import { ExportService } from '../../services';
import Notification from '../Notification';
import { ZoomIn, ZoomOut, ZoomOutMap } from '@mui/icons-material';
import { Divider } from '@mui/material';
import FlowWidget from './FlowWidget';
import { ReactFlowProvider } from 'reactflow';
import { setScenarioId, setScenarioLoading, setScenarioName } from '../../store/scenarioSlice';
import { setCanvasNotification } from '../../store/canvasSlice';
import { v1 } from '../../api_routes';
import { store } from '../../store';

const {dispatch} = store;
const drawerWidth = 240;

const useStyles = makeStyles(theme => ({
  root: {
    display: 'flex'
  },
  appBar: {
    zIndex: theme.zIndex.drawer + 1,
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
  toolbar: theme.mixins.toolbar,
  divider: {
    marginLeft: '16px',
    marginRight: '16px'
  }
}));

const BodyWidget = ({ app }) => {
  const classes = useStyles();
  const canvas = useSelector(state => state.canvas);
  const scenario = useSelector(state => state.scenario);

  const [editingName, setEditingName] = useState(false);
  const [editedName, setEditedName] = useState('');

  const saveChanges = useCallback(() => {
    if (app.isCorruptedPayload()) {
      dispatch(
        setCanvasNotification({
          open: true,
          variant: 'error',
          text: 'Cannot modify corrupted scenario.'
        })
      );
      return;
    }

    const exportService = new ExportService(app.getDiagramService());

    const payload = {
      name: scenario.name,
      ...exportService.exportPayload()
    };

    const scenarioId = scenario.id;
    if (scenarioId) {
      payload.id = scenarioId;
    }

    dispatch(setScenarioLoading(true));

    axios
      .post(`${v1.scenario.create}`, payload)
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
      });
  }, [app, dispatch, scenario]);

  const startEditingName = () => {
    setEditedName(scenario.name);
    setEditingName(true);
  };

  const cancelEditingName = () => {
    setEditedName('');
    setEditingName(false);
  };

  const submitEditingName = () => {
    if (editedName.trim().length === 0) {
      cancelEditingName();
      return;
    }

    dispatch(setScenarioName(editedName));
    setEditedName('');
    setEditingName(false);
  };

  const handleCloseAndSaveDuringChangingName = (event) => {
    if (event.key === 'Escape') {
      cancelEditingName();
    } else if (event.key === 'Enter') {
      submitEditingName();
    }
  };

  const handleNameTyping = (event) => {
    setEditedName(event.target.value);
  };

  const closeNotification = () => {
    dispatch(setCanvasNotification({ open: false }));
  };

  const zoomOut = () => {
    app.diagramService.getDiagram().zoomOut();
  };

  const zoomIn = () => {
    app.diagramService.getDiagram().zoomIn();
  };

  const zoomToFit = () => {
    app.diagramService.getDiagram().fitView();
  };

  return (
    <div className='body'>
      <div className={classes.root}>
        <CssBaseline />
        <AppBar position='fixed' className={classes.appBar}>
          <Toolbar>
            <Grid container>
              <Grid item xs={4}>
                <Typography variant='h6' color='inherit' noWrap>
                  {editingName ? (
                    <input
                      autoFocus
                      type='text'
                      value={editedName}
                      onChange={handleNameTyping}
                      onKeyDown={handleCloseAndSaveDuringChangingName}
                      onBlur={submitEditingName}
                      className='changing-name-input'
                    />
                  ) : (
                    <span
                      onClick={startEditingName}
                      className='scenario-name'
                    >
                      {scenario.name}
                    </span>
                  )}
                </Typography>
              </Grid>

              <Grid item xs={8}>
                <Grid container direction='row' justifyContent='flex-end'>
                  {!!scenario.loading && (
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
                    onClick={zoomIn}
                  >
                    <ZoomIn />
                  </Button>
                  <Button
                    title="Zoom out"
                    size='small'
                    variant='contained'
                    color='primary'
                    onClick={zoomOut}
                  >
                    <ZoomOut titleAccess="Test" />
                  </Button>
                  <Button
                    title="Zoom to fit"
                    size='small'
                    variant='contained'
                    color='primary'
                    onClick={zoomToFit}
                  >
                    <ZoomOutMap />
                  </Button>
                  <Divider orientation="vertical" variant="middle" flexItem className={classes.divider} />
                  <Button
                    size='small'
                    variant='contained'
                    color='secondary'
                    onClick={saveChanges}
                  >
                    {scenario.id ? 'Update' : 'Save'}
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
              model={{ type: 'trigger', shape: 'square' }}
              name='Event'
              icon={<TriggerIcon />}
            />

            <TrayItemWidget
              model={{ type: 'before_trigger', shape: 'square' }}
              name='Before Event'
              icon={<NotificationsActiveIcon />}
            />
          </List>

          <List
            component='nav'
            subheader={<ListSubheader component='div'>Actions</ListSubheader>}
          >
            <TrayItemWidget
              model={{ type: 'email', shape: 'square' }}
              name='Send email'
              icon={<EmailIcon />}
            />

            <TrayItemWidget
              model={{ type: 'generic', shape: 'square' }}
              name='Run generic action'
              icon={<ExtensionIcon />}
            />

            {config.BANNER_ENABLED &&
              <TrayItemWidget
                model={{ type: 'banner', shape: 'square' }}
                name='Show banner'
                icon={<BannerIcon />}
              />
            }

            {config.PUSH_NOTIFICATION_ENABLED &&
              <TrayItemWidget
                model={{ type: 'push_notification', shape: 'square' }}
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
              model={{ type: 'segment', shape: 'diamond' }}
              name='Segment'
              icon={<SegmentIcon />}
            />

            <TrayItemWidget
              model={{ type: 'condition', shape: 'diamond' }}
              name='Condition'
              icon={<ConditionIcon />}
            />

            <TrayItemWidget
              model={{ type: 'wait', shape: 'round' }}
              name='Wait'
              icon={<WaitIcon />}
            />

            <TrayItemWidget
              model={{ type: 'goal', shape: 'diamond' }}
              name='Goal'
              icon={<GoalIcon />}
            />

            <TrayItemWidget
              model={{ type: 'ab_test', shape: 'diamond' }}
              name='AB Test'
              icon={<ABTestIcon />}
            />
          </List>
        </Drawer>
        <Notification
          variant={canvas.notification.variant}
          text={canvas.notification.text}
          open={canvas.notification.open}
          handleClose={closeNotification}
        />

        <main className={classes.content}>
          <div className='diagram-layer'>
            <ReactFlowProvider>
              <FlowWidget app={app} scenario={scenario} />
            </ReactFlowProvider>
          </div>
        </main>
      </div>
    </div>
  );
};

export default BodyWidget;
