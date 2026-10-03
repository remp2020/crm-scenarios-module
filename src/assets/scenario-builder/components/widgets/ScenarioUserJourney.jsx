import React, { useEffect } from 'react';
import { useDispatch, useSelector } from 'react-redux';
import CircularProgress from '@mui/material/CircularProgress';
import List from '@mui/material/List';
import ListItemButton from '@mui/material/ListItemButton';
import Typography from '@mui/material/Typography';
import { makeStyles } from '@mui/styles';
import { fetchJourneyJobs, fetchUserJourneys } from '../../actions';
import { resetJourneys, setSelectedJourney } from '../../store/journeysSlice';
import { formatDateTime, formatDuration } from '../../utils/datetime';
import ScenarioJourneyJobs from './ScenarioJourneyJobs';

const useStyles = makeStyles(theme => ({
  journey: {
    marginTop: theme.spacing(2)
  },
  hint: {
    color: theme.palette.grey[600]
  },
  heading: {
    color: theme.palette.grey[600],
    textTransform: 'uppercase',
    letterSpacing: '0.5px'
  },
  list: {
    padding: 0
  },
  item: {
    display: 'block',
    paddingLeft: 0,
    paddingRight: 0
  },
  meta: {
    color: theme.palette.grey[600]
  },
  progress: {
    display: 'block',
    margin: `${theme.spacing(2)} auto`
  }
}));

const ScenarioUserJourney = ({ scenarioId }) => {
  const classes = useStyles();
  const dispatch = useDispatch();
  const selectedUser = useSelector(state => state.users.selectedUser);
  const journeys = useSelector(state => state.journeys);

  const userId = selectedUser?.id;

  useEffect(() => {
    dispatch(resetJourneys());

    if (userId) {
      dispatch(fetchUserJourneys(scenarioId, userId));
    }
  }, [dispatch, scenarioId, userId]);

  if (!selectedUser) {
    return null;
  }

  if (journeys.selectedJourney) {
    return <ScenarioJourneyJobs/>;
  }

  if (journeys.loading) {
    return <CircularProgress size={20} className={classes.progress}/>;
  }

  if (journeys.items.length === 0) {
    return (
      <div className={classes.journey}>
        <Typography variant='body2'>
          No journey recorded for this user in this scenario.
        </Typography>
        <Typography variant='body2' className={classes.hint}>
          Only the last few months of job history are kept, older journeys are already gone.
        </Typography>
      </div>
    );
  }

  return (
    <div className={classes.journey}>
      <Typography variant='caption' className={classes.heading}>
        {journeys.hasMore
          ? `${journeys.items.length}+ journeys (latest shown)`
          : `${journeys.items.length} journey${journeys.items.length === 1 ? '' : 's'}`}
      </Typography>
      <List className={classes.list}>
        {journeys.items.map(journey => (
          <ListItemButton
            key={journey.journey_id}
            className={classes.item}
            onClick={() => {
              dispatch(setSelectedJourney(journey));
              dispatch(fetchJourneyJobs(scenarioId, journey.journey_id));
            }}
          >
            <Typography variant='body2'>
              {formatDateTime(journey.started_at)}
            </Typography>
            <Typography variant='body2' className={classes.meta}>
              {formatDuration(journey.started_at, journey.ended_at)} ·{' '}
              {journey.jobs_count} job{journey.jobs_count === 1 ? '' : 's'} ·{' '}
              ends {formatDateTime(journey.ended_at)}
            </Typography>
          </ListItemButton>
        ))}
      </List>
    </div>
  );
};

export default ScenarioUserJourney;
