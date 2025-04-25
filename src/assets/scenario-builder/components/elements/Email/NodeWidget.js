import React, { useState } from 'react';
import { useSelector } from 'react-redux';
import ActionIcon from '@mui/icons-material/Mail';
import Grid from '@mui/material/Grid';
import TextField from '@mui/material/TextField';
import Button from '@mui/material/Button';
import Dialog from '@mui/material/Dialog';
import DialogActions from '@mui/material/DialogActions';
import DialogContent from '@mui/material/DialogContent';
import DialogContentText from '@mui/material/DialogContentText';
import DialogTitle from '@mui/material/DialogTitle';
import { styled } from '@mui/material/styles';
import StatisticBadge from '../../StatisticBadge';
import StatisticsTooltip from '../../StatisticTooltip';
import { Autocomplete } from '@mui/material';
import { makeStyles } from '@mui/styles';
import { createFilterOptions } from '@mui/material/Autocomplete';
import { Handle, Position } from 'reactflow';
import { bemClassName } from '../../../utils/bem';
import { NodePopover } from '../../NodePopover';
import { useNode } from '../../../hooks/useNode';

const PreviewEmailButton = styled(Button)({
  marginRight: 'auto'
});

const useStyles = makeStyles(theme => ({
  autocomplete: {
    margin: theme.spacing(1)
  },
  subtitle: {
    paddingLeft: '6px',
    color: theme.palette.grey[600]
  }
}));

const NodeWidget = (props) => {
  const classes = useStyles();
  const [selectedMail, setSelectedMail] = useState(props.data.node.selectedMail);
  const mails = useSelector(state => state.mails.availableMails);
  const {
    bem,
    getClassName,
    anchorElementForTooltip,
    anchorElForPopover,
    deleteNode,
    closePopover,
    dialogOpened,
    openDialog,
    onNodeClick,
    onNodeDoubleClick,
    nodeFormName,
    setNodeFormName,
    closeDialog,
    onDialogOpen,
    handleNodeMouseEnter,
    handleNodeMouseLeave
  } = useNode(props)

  onDialogOpen(() => {
    setSelectedMail(props.data.node.selectedMail);
  })

  const getSelectedMail = () => {
    const selected = mails.find(
      mail => mail.code === selectedMail
    );

    return selected ? selected : null;
  };

  const getSelectedMailValue = () => {
    const selected = mails.find(
      mail => mail.code === props.data.node.selectedMail
    );

    return selected ? ` - ${selected.name}` : '';
  };

  const filterOptions = () => createFilterOptions({
    matchFrom: 'any',
    trim: true,
    ignoreAccents: true,
    ignoreCase: true,
    stringify: option => {
      return option.name + ' ' + option.code;
    }
  });

  return (
    <div
      className={getClassName()}
      style={{background: props.data.node.color}}
      onClick={onNodeClick}
      onDoubleClick={onNodeDoubleClick}
      onMouseEnter={handleNodeMouseEnter}
      onMouseLeave={handleNodeMouseLeave}
    >
      <NodePopover
        anchorEl={anchorElForPopover}
        onClose={closePopover}
        onEdit={openDialog}
        onDelete={deleteNode}
      />
      <div className="node-container">
        <div className={bem('__icon')}>
          <ActionIcon/>
        </div>

        <div className={bem('__ports')}>
          <div className={bem('__left')}>
            <Handle
              type="target"
              id="left"
              position={Position.Left}
              onConnect={(params) => console.log('handle onConnect', params)}
              isConnectable={props.isConnectable}
              className="port"
            />
          </div>
          <div className={bem('__right')}>
            <Handle
              type="source"
              id="right"
              position={Position.Right}
              isConnectable={props.isConnectable}
              className="port"
            />
            <StatisticBadge elementId={props.id} color="#a291fb" position="right"/>
          </div>
        </div>
      </div>
      <div className={bem('__title')}>
        <div className={bem('__name')}>
          {props.data.node.name
            ? props.data.node.name
            : `Mail ${getSelectedMailValue()}`}
        </div>
      </div>

      <StatisticsTooltip
        id={props.id}
        anchorElement={anchorElementForTooltip}
      />

      <Dialog
        open={dialogOpened}
        onClose={closeDialog}
        aria-labelledby="form-dialog-title"
        onKeyUp={event => {
          if (event.key === 'Delete' || event.key === 'Backspace') {
            event.preventDefault();
            event.stopPropagation();
            return false;
          }
        }}
        fullWidth
      >
        <DialogTitle id="form-dialog-title">Email node</DialogTitle>

        <DialogContent>
          <DialogContentText>Sends an email to user.</DialogContentText>

          <Grid container>
            <Grid item xs={6}>
              <TextField
                margin="normal"
                id="action-name"
                label="Node name"
                variant="standard"
                fullWidth
                value={nodeFormName}
                onChange={event => {
                  setNodeFormName(event.target.value);
                }}
              />
            </Grid>
          </Grid>

          <Grid container alignItems="center" alignContent="space-between">
            <Grid item xs={12}>
              <Autocomplete
                value={getSelectedMail()}
                options={mails}
                getOptionLabel={(option) => option.name}
                disableClearable={true}
                filterOptions={filterOptions()}
                groupBy={(option) => option.mail_type.code}
                onChange={(event, selectedOption) => {
                  if (selectedOption !== null) {
                    setSelectedMail(selectedOption.code);
                  }
                }}
                renderInput={params => (
                  <TextField {...params} variant="standard" label="Selected Mail" fullWidth/>
                )}
                renderOption={(props, option) => (
                  <div {...props}>
                    <span className={classes.title}>{option.name}</span>
                    <small className={classes.subtitle}>({option.code})</small>
                  </div>
                )}
              />
            </Grid>
          </Grid>
        </DialogContent>

        <DialogActions>
          {mails.filter(mail => mail.link && mail.code === selectedMail).map(item =>
            <PreviewEmailButton color="primary" href={item.link} target="_blank" key={item.code}>
              <ActionIcon style={{marginRight: '5px'}}/>Preview
            </PreviewEmailButton>
          )}

          <Button
            color="secondary"
            onClick={() => {
              closeDialog();
            }}
          >
            Cancel
          </Button>

          <Button
            color="primary"
            onClick={() => {
              props.data.node.name = nodeFormName;
              props.data.node.selectedMail = selectedMail;

              closeDialog();
            }}
          >
            Save changes
          </Button>
        </DialogActions>
      </Dialog>
    </div>
  );
};

export default NodeWidget;
