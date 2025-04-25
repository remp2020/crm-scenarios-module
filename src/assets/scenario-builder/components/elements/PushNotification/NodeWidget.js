import { useSelector } from 'react-redux';
import ActionIcon from '@mui/icons-material/PhonelinkRing';
import Grid from '@mui/material/Grid';
import TextField from '@mui/material/TextField';
import Button from '@mui/material/Button';
import Dialog from '@mui/material/Dialog';
import DialogActions from '@mui/material/DialogActions';
import DialogContent from '@mui/material/DialogContent';
import DialogContentText from '@mui/material/DialogContentText';
import DialogTitle from '@mui/material/DialogTitle';
import StatisticsTooltip from '../../StatisticTooltip';
import Autocomplete from '@mui/material/Autocomplete';
import StatisticBadge from '../../StatisticBadge';
import { Handle, Position } from 'reactflow';
import React, { useState } from 'react';
import { NodePopover } from '../../NodePopover';
import { useNode } from '../../../hooks/useNode';

const NodeWidget = (props) => {
  const [selectedTemplate, setSelectedTemplate] = useState(props.data.node.selectedTemplate);
  const [selectedApplication, setSelectedApplication] = useState(props.data.node.selectedApplication);
  const templates = useSelector(state => state.pushNotifications.availableTemplates)
  const applications = useSelector(state => state.pushNotifications.availableApplications)
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
    handleNodeMouseEnter,
    handleNodeMouseLeave
  } = useNode(props)

  const getTemplatesInSelectableFormat = () => {
    return templates.map(item => {
      return {
        value: item.code,
        label: item.name,
      };
    });
  };

  const getApplicationsInSelectableFormat = () => {
    return applications.map(item => {
      return {
        value: item.code,
        label: item.name,
      };
    });
  };

  const getSelectedTemplateValue = () => {
    const selected = templates.find(
      item => item.code === props.data.node.selectedTemplate
    );

    return selected ? ` - ${selected.name}` : '';
  };

  return (
    <div
      className={getClassName()}
      style={{ background: props.data.node.color }}
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
      <div className='node-container'>
        <div className={bem('__icon')}>
          <ActionIcon />
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
            <StatisticBadge elementId={props.id} color="#dc73ff" position="right" />
          </div>
        </div>
      </div>
      <div className={bem('__title')}>
        <div className={bem('__name')}>
          {props.data.node.name
            ? props.data.node.name
            : `Notification ${getSelectedTemplateValue()}`}
        </div>
      </div>

      <StatisticsTooltip
        id={props.id}
        anchorElement={anchorElementForTooltip}
      />

      <Dialog
        open={dialogOpened}
        onClose={closeDialog}
        aria-labelledby='form-dialog-title'
        onKeyUp={event => {
          if (event.key === 'Delete' || event.key === 'Backspace') {
            event.preventDefault();
            event.stopPropagation();
            return false;
          }
        }}
        fullWidth
      >
        <DialogTitle id='form-dialog-title'>Notification node</DialogTitle>

        <DialogContent>
          <DialogContentText>Sends a push notification to user.</DialogContentText>

          <Grid container>
            <Grid item xs={6}>
              <TextField
                margin='normal'
                id='action-name'
                label='Node name'
                variant='standard'
                fullWidth
                value={nodeFormName}
                onChange={event => {
                  setNodeFormName(event.target.value)
                }}
              />
            </Grid>
          </Grid>

          <Grid container alignItems='center' alignContent='space-between'>
            <Grid item xs={12}>
              <Autocomplete
                value={getTemplatesInSelectableFormat().find(
                  option => option.value === selectedTemplate
                )}
                isOptionEqualToValue={(option, value) => option.value === value.value}
                options={getTemplatesInSelectableFormat()}
                getOptionLabel={(option) => option.label}
                style={{ marginBottom: 16 }}
                onChange={(event, selectedOption) => {
                  if (selectedOption !== null) {
                    setSelectedTemplate(selectedOption.value)
                  }
                }}
                renderInput={params => (
                  <TextField {...params} variant="standard" label="Notification template" fullWidth />
                )}
              />
            </Grid>
          </Grid>

          <Grid container alignItems='center' alignContent='space-between'>
            <Grid item xs={12}>
              <Autocomplete
                value={getApplicationsInSelectableFormat().find(
                  option => option.value === selectedApplication
                )}
                isOptionEqualToValue={(option, value) => option.value === value.value}
                options={getApplicationsInSelectableFormat()}
                getOptionLabel={(option) => option.label}
                style={{ marginBottom: 16 }}
                onChange={(event, selectedOption) => {
                  if (selectedOption !== null) {
                    setSelectedApplication(selectedOption.value)
                  }
                }}
                renderInput={params => (
                  <TextField {...params} variant="standard" label="Application" fullWidth />
                )}
              />
            </Grid>
          </Grid>
        </DialogContent>

        <DialogActions>
          <Button
            color='secondary'
            onClick={() => {
              closeDialog();
            }}
          >
            Cancel
          </Button>

          <Button
            color='primary'
            onClick={() => {
              props.data.node.name = nodeFormName;
              props.data.node.selectedTemplate = selectedTemplate;
              props.data.node.selectedApplication = selectedApplication;

              closeDialog();
            }}
          >
            Save changes
          </Button>
        </DialogActions>
      </Dialog>
    </div>
  );
}
export default NodeWidget;
