import React, { createRef } from 'react';
import { useSelector } from 'react-redux';
import ConditionIcon from '@mui/icons-material/CallSplit';
import OkIcon from '@mui/icons-material/Check';
import Grid from '@mui/material/Grid';
import NopeIcon from '@mui/icons-material/Close';
import TextField from '@mui/material/TextField';
import Button from '@mui/material/Button';
import Dialog from '@mui/material/Dialog';
import DialogActions from '@mui/material/DialogActions';
import DialogContent from '@mui/material/DialogContent';
import DialogTitle from '@mui/material/DialogTitle';
import CriteriaBuilder from './CriteriaBuilder';
import StatisticBadge from '../../StatisticBadge';
import StatisticsTooltip from '../../StatisticTooltip';
import { Handle, Position } from 'reactflow';
import { NodePopover } from '../../NodePopover';
import { useNode } from '../../../hooks/useNode';

const NodeWidget = (props) => {
  // Use it to access CriteriaBuilder state
  const builderRef = createRef();
  const statistics = useSelector(state => state.statistics.statistics);
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

  let displayStatisticBadge = false;
  if (statistics.length === 0 || statistics[props.id]) {
    displayStatisticBadge = true;
  }

  return (
    <div
      className={getClassName()}
      style={{background: props.data.node.color}}
      onClick={onNodeClick}
      onDoubleClick={onNodeDoubleClick}
      onMouseEnter={handleNodeMouseEnter}
      onMouseLeave={handleNodeMouseLeave}
    >
      <div className={bem('__title')}>
        <div className={bem('__name')}>
          {props.data.node.name
            ? props.data.node.name
            : 'Condition'}
        </div>
      </div>

      <NodePopover
        anchorEl={anchorElForPopover}
        onClose={closePopover}
        onEdit={openDialog}
        onDelete={deleteNode}
      />

      <div className="node-container">
        <div className={bem('__icon')}>
          <ConditionIcon/>
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
              position={Position.Right}
              id="right"
              isConnectable={props.isConnectable}
              className="port"
            />
            {displayStatisticBadge ?
              <StatisticBadge elementId={props.id} color="#21ba45" position="right"/> :
              <OkIcon style={{position: 'absolute', top: '-5px', right: '-30px', color: '#2ECC40'}}/>
            }
          </div>

          <div className={bem('__bottom')}>
            <Handle
              type="source"
              position={Position.Bottom}
              id="bottom"
              isConnectable={props.isConnectable}
              className="port"
            />
            {displayStatisticBadge ?
              <StatisticBadge elementId={props.id} color="#db2828" position="bottom"/> :
              <NopeIcon style={{position: 'absolute', top: '15px', right: '-5px', color: '#FF695E'}}/>
            }
          </div>
        </div>
      </div>

      <StatisticsTooltip
        id={props.id}
        anchorElement={anchorElementForTooltip}
      />

      <Dialog
        fullWidth={true}
        maxWidth="md"
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
      >
        <DialogTitle id="form-dialog-title">
          Event Condition
        </DialogTitle>

        <DialogContent>
          <Grid container>
            <Grid style={{marginBottom: '10px'}} item xs={6}>
              <TextField
                margin="normal"
                id="trigger-name"
                label="Node name"
                variant="standard"
                fullWidth
                value={nodeFormName}
                onChange={event => {
                  setNodeFormName(event.target.value);
                }}
              />
            </Grid>

            <CriteriaBuilder
              conditions={props.data.node.conditions}
              ref={builderRef}
            >
            </CriteriaBuilder>
          </Grid>
        </DialogContent>

        <DialogActions>
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
              props.data.node.conditions = builderRef.current.state;

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
