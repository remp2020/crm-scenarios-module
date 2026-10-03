import React, { useEffect, useMemo, useState } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import { useReactFlow, useStore } from 'reactflow';
import ArrowBack from '@mui/icons-material/ArrowBack';
import MyLocation from '@mui/icons-material/MyLocation';
import Alert from '@mui/material/Alert';
import Button from '@mui/material/Button';
import Chip from '@mui/material/Chip';
import CircularProgress from '@mui/material/CircularProgress';
import IconButton from '@mui/material/IconButton';
import List from '@mui/material/List';
import ListItemButton from '@mui/material/ListItemButton';
import ListItemIcon from '@mui/material/ListItemIcon';
import Typography from '@mui/material/Typography';
import { makeStyles } from '@mui/styles';
import { setNodeDetailOpened } from '../../store/canvasSlice';
import { setHoveredJourneyNode, setSelectedJourney } from '../../store/journeysSlice';
import { formatDateTime, formatDuration, formatTime } from '../../utils/datetime';
import JobDetailDialog from './JobDetailDialog';
import JourneyNodeIcon from './JourneyNodeIcon';

const useStyles = makeStyles(theme => ({
  journey: {
    marginTop: theme.spacing(2)
  },
  back: {
    marginLeft: '-8px'
  },
  header: {
    color: theme.palette.grey[600],
    marginBottom: theme.spacing(1)
  },
  incomplete: {
    marginBottom: theme.spacing(1)
  },
  list: {
    padding: 0
  },
  item: {
    alignItems: 'flex-start',
    paddingLeft: 0,
    paddingRight: 0
  },
  content: {
    flex: 1,
    minWidth: 0
  },
  locate: {
    padding: '3px',
    marginTop: '-2px',
    color: theme.palette.grey[600],
    '& svg': {
      fontSize: '16px'
    }
  },
  icon: {
    minWidth: '32px',
    marginTop: '2px',
    color: theme.palette.grey[700]
  },
  meta: {
    color: theme.palette.grey[600]
  },
  missing: {
    height: '16px',
    marginLeft: theme.spacing(0.75),
    borderRadius: '4px',
    fontSize: '0.65rem',
    fontWeight: 600,
    verticalAlign: 'middle'
  },
  failed: {
    color: theme.palette.error.main
  },
  inProgress: {
    color: theme.palette.primary.main,
    fontWeight: 600
  },
  progress: {
    display: 'block',
    margin: `${theme.spacing(2)} auto`
  }
}));

// running jobs are not in history yet, the API adds them from the jobs table
const stateLabel = (job) => {
  if (!job.in_progress) {
    return job.state;
  }
  return job.wait_until ? 'waiting' : `${job.state} (in progress)`;
};

const ScenarioJourneyJobs = () => {
  const classes = useStyles();
  const dispatch = useDispatch();
  const { selectedJourney, jobs, jobsLoading } = useSelector(state => state.journeys);
  const [detailJob, setDetailJob] = useState(null);
  // joined into a string so dragging nodes around doesn't re-render the list
  const canvasNodeIdsKey = useStore(state => Array.from(state.nodeInternals.keys()).join(','));
  const canvasNodeIds = useMemo(() => new Set(canvasNodeIdsKey.split(',')), [canvasNodeIdsKey]);
  const detailOpened = detailJob !== null;
  const { getNode, getZoom, setCenter } = useReactFlow();
  // every journey starts with a trigger
  const incomplete = jobs.length > 0 && jobs[0].node.kind !== 'trigger';

  const stateClassName = (job) => {
    if (job.in_progress) {
      return classes.inProgress;
    }
    return job.state === 'failed' ? classes.failed : undefined;
  };

  const locateNode = (uuid) => {
    const node = getNode(uuid);
    if (!node) {
      return;
    }
    const position = node.positionAbsolute ?? node.position;
    setCenter(
      position.x + (node.width ?? 0) / 2,
      position.y + (node.height ?? 0) / 2,
      { zoom: getZoom(), duration: 400 }
    );
  };

  // blocks canvas copy/paste/undo shortcuts while the dialog is open
  useEffect(() => {
    if (!detailOpened) {
      return;
    }
    dispatch(setNodeDetailOpened(true));
    return () => dispatch(setNodeDetailOpened(false));
  }, [detailOpened, dispatch]);

  return (
    <div className={classes.journey}>
      <Button
        size='small'
        className={classes.back}
        startIcon={<ArrowBack/>}
        onClick={() => dispatch(setSelectedJourney(null))}
      >
        Back to journeys
      </Button>

      <Typography variant='body2' className={classes.header}>
        {formatDateTime(selectedJourney.started_at)} ·{' '}
        {formatDuration(selectedJourney.started_at, selectedJourney.ended_at)}
      </Typography>

      {jobsLoading && <CircularProgress size={20} className={classes.progress}/>}

      {!jobsLoading && jobs.length === 0 && (
        <Typography variant='body2' className={classes.meta}>
          No job recorded for this journey.
        </Typography>
      )}

      {!jobsLoading && jobs.length > 0 && (
        <>
          {incomplete && (
            <Alert severity='warning' className={classes.incomplete}>
              Journey is incomplete - some jobs may be missing.
            </Alert>
          )}
          <List className={classes.list}>
            {jobs.map(job => (
              <ListItemButton
                key={job.job_id}
                className={classes.item}
                onClick={() => setDetailJob(job)}
                onMouseEnter={() => dispatch(setHoveredJourneyNode(job.node.uuid))}
                onMouseLeave={() => dispatch(setHoveredJourneyNode(null))}
              >
                <ListItemIcon className={classes.icon}>
                  <JourneyNodeIcon node={job.node} fontSize='small'/>
                </ListItemIcon>
                <div className={classes.content}>
                  <Typography variant='body2'>
                    {job.node.name}
                    {!canvasNodeIds.has(job.node.uuid) && (
                      <Chip
                        label='MISSING'
                        size='small'
                        color='error'
                        variant='outlined'
                        className={classes.missing}
                        title='Node is not in the current scenario'
                      />
                    )}
                  </Typography>
                  <Typography variant='body2' className={classes.meta}>
                    {job.node.type ?? job.node.kind} ·{' '}
                    <span className={stateClassName(job)}>
                      {stateLabel(job)}
                    </span>
                  </Typography>
                  <Typography variant='body2' className={classes.meta}>
                    {job.in_progress ? (
                      job.wait_until
                        ? <>{formatTime(job.created_at)} → until {formatDateTime(job.wait_until)}</>
                        : <>{formatTime(job.created_at)} → …</>
                    ) : (
                      <>
                        {formatTime(job.created_at)} → {formatTime(job.finished_at)} ·{' '}
                        {formatDuration(job.started_at, job.finished_at)}
                      </>
                    )}
                  </Typography>
                </div>
                {canvasNodeIds.has(job.node.uuid) && (
                  <IconButton
                    size='small'
                    className={classes.locate}
                    title='Locate on canvas'
                    onClick={(e) => {
                      // don't open the job detail
                      e.stopPropagation();
                      locateNode(job.node.uuid);
                    }}
                    onMouseDown={(e) => e.stopPropagation()}
                  >
                    <MyLocation fontSize='small'/>
                  </IconButton>
                )}
              </ListItemButton>
            ))}
          </List>
        </>
      )}

      <JobDetailDialog job={detailJob} onClose={() => setDetailJob(null)}/>
    </div>
  );
};

export default ScenarioJourneyJobs;
