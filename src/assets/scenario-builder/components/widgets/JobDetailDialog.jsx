import React from 'react';
import Button from '@mui/material/Button';
import Dialog from '@mui/material/Dialog';
import DialogActions from '@mui/material/DialogActions';
import DialogContent from '@mui/material/DialogContent';
import DialogTitle from '@mui/material/DialogTitle';
import Typography from '@mui/material/Typography';
import { makeStyles } from '@mui/styles';
import { formatDateTime, formatDuration } from '../../utils/datetime';
import JourneyNodeIcon from './JourneyNodeIcon';

const useStyles = makeStyles(theme => ({
  title: {
    alignItems: 'center',
    display: 'flex',
    gap: theme.spacing(1.5)
  },
  titleIcon: {
    color: theme.palette.grey[600],
    flexShrink: 0
  },
  metaTable: {
    borderCollapse: 'collapse',
    marginBottom: theme.spacing(2),
    width: '100%'
  },
  metaLabel: {
    color: theme.palette.grey[600],
    padding: theme.spacing(0.25, 1.5, 0.25, 0),
    textAlign: 'left',
    verticalAlign: 'top',
    whiteSpace: 'nowrap',
    width: '1%'
  },
  metaValue: {
    padding: theme.spacing(0.25, 0),
    textAlign: 'left',
    verticalAlign: 'top',
    wordBreak: 'break-word'
  },
  empty: {
    color: theme.palette.grey[600],
    margin: theme.spacing(0.5, 0, 2)
  },
  pre: {
    backgroundColor: theme.palette.grey[100],
    border: 'none',
    borderRadius: theme.shape.borderRadius,
    margin: theme.spacing(0.5, 0, 2),
    maxHeight: '260px',
    overflow: 'auto',
    padding: theme.spacing(1),
    whiteSpace: 'pre-wrap',
    wordBreak: 'break-word'
  }
}));

function stringify(value) {
  if (value === null || value === undefined) {
    return null;
  }
  // a payload that failed to parse server side arrives as the raw string
  return typeof value === 'string' ? value : JSON.stringify(value, null, 2);
}

const MetaRow = ({ label, children }) => {
  const classes = useStyles();

  return (
    <tr>
      <th scope='row' className={classes.metaLabel}>{label}</th>
      <td className={classes.metaValue}>{children}</td>
    </tr>
  );
};

const JsonSection = ({ title, value }) => {
  const classes = useStyles();
  const content = stringify(value);

  return (
    <>
      <Typography variant='subtitle2'>{title}</Typography>
      {content === null
        ? <Typography variant='body2' className={classes.empty}>—</Typography>
        : <pre className={classes.pre}>{content}</pre>}
    </>
  );
};

const JobDetailDialog = ({ job, onClose }) => {
  const classes = useStyles();

  return (
    <Dialog
      open={job !== null}
      onClose={onClose}
      maxWidth='md'
      fullWidth
      aria-labelledby='job-detail-dialog-title'
      // reactflow ignores key presses inside .nokey, so Delete won't remove the selected node
      className='nokey'
    >
      <DialogTitle id='job-detail-dialog-title' className={classes.title}>
        {job && <JourneyNodeIcon node={job.node} className={classes.titleIcon}/>}
        {job?.node.name}
      </DialogTitle>
      <DialogContent>
        {job && (
          <>
            <table className={classes.metaTable}>
              <tbody>
                <MetaRow label='Job'>#{job.job_id}</MetaRow>
                <MetaRow label='Node'>
                  {job.node.name} ({job.node.type ?? job.node.kind})
                  {job.node.deleted && ' · deleted from the scenario'}
                </MetaRow>
                <MetaRow label='State'>{job.state}{job.in_progress && ' · in progress'}</MetaRow>
                {job.retry_count > 0 && <MetaRow label='Retries'>{job.retry_count}</MetaRow>}
                <MetaRow label='Created'>{formatDateTime(job.created_at)}</MetaRow>
                <MetaRow label='Started'>{formatDateTime(job.started_at)}</MetaRow>
                {job.wait_until && <MetaRow label='Waits until'>{formatDateTime(job.wait_until)}</MetaRow>}
                <MetaRow label='Finished'>{formatDateTime(job.finished_at)}</MetaRow>
                <MetaRow label='Duration'>{formatDuration(job.started_at, job.finished_at)}</MetaRow>
              </tbody>
            </table>

            <JsonSection title='Result' value={job.result}/>
            <JsonSection title='Context' value={job.context}/>
            <JsonSection title='Parameters' value={job.parameters}/>
          </>
        )}
      </DialogContent>
      <DialogActions>
        <Button color='primary' onClick={onClose}>Close</Button>
      </DialogActions>
    </Dialog>
  );
};

export default JobDetailDialog;
