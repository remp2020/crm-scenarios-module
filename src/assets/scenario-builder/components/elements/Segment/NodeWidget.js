import React, { useState } from 'react';
import { useSelector } from 'react-redux';
import SegmentIcon from '@mui/icons-material/SubdirectoryArrowRight';
import Grid from '@mui/material/Grid';
import TextField from '@mui/material/TextField';
import Button from '@mui/material/Button';
import Dialog from '@mui/material/Dialog';
import DialogActions from '@mui/material/DialogActions';
import DialogContent from '@mui/material/DialogContent';
import DialogContentText from '@mui/material/DialogContentText';
import DialogTitle from '@mui/material/DialogTitle';
import Icon from '@mui/material/Icon';
import SegmentSelector from './SegmentSelector';
import { styled } from '@mui/material/styles';
import StatisticBadge from '../../StatisticBadge';
import StatisticsTooltip from '../../StatisticTooltip';
import OkIcon from '@mui/icons-material/Check';
import NopeIcon from '@mui/icons-material/Close';
import { Handle, Position } from 'reactflow';
import { v1 } from '../../../api_routes';
import { NodePopover } from '../../NodePopover';
import { useNode } from '../../../hooks/useNode';

const NewSegmentButton = styled(Button)({
  marginRight: 'auto'
});

const NodeWidget = (props) => {
  const [selectedSegment, setSelectedSegment] = useState(props.data.node.selectedSegment);
  const [selectedSegmentSourceTable, setSelectedSegmentSourceTable] = useState(null);
  const segments = useSelector(state => state.segments.availableSegments);
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
    onDialogOpen,
    handleNodeMouseEnter,
    handleNodeMouseLeave
  } = useNode(props)

  onDialogOpen(() => {
    setSelectedSegment(props.data.node.selectedSegment);
  })

  const actionSetTable = table => {
    if (selectedSegmentSourceTable !== table) {
      setSelectedSegment(null);
      setSelectedSegmentSourceTable(table);
    }
  };

  const segmentSelectedChange = segment => {
    let value = null;
    if (segment && segment.hasOwnProperty('code')) {
      value = segment.code;
    }

    setSelectedSegment(value);
  };

  const getSelectedSegmentValue = () => {
    const selected = segments.flatMap(
      item => item.segments).find(
      segment => segment.code === props.data.node.selectedSegment
    );

    return selected ? ` - ${selected.name}` : '';
  };

  const handleNewSegmentClick = () => {
    window.open(v1.segments.new);
  };


  let displayStatisticBadge = false;
  if (statistics.length === 0 || statistics[props.id]) {
    displayStatisticBadge = true;
  }

  return (
    <div
      className={getClassName()}
      onClick={onNodeClick}
      onDoubleClick={onNodeDoubleClick}
      onMouseEnter={handleNodeMouseEnter}
      onMouseLeave={handleNodeMouseLeave}
    >
      <div className={bem('__title')}>
        <div className={bem('__name')}>
          {props.data.node.name
            ? props.data.node.name
            : `Segment ${getSelectedSegmentValue()}`}
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
          <SegmentIcon/>
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
            {displayStatisticBadge ?
              <StatisticBadge elementId={props.id} color="#21ba45" position="right"/> :
              <OkIcon style={{position: 'absolute', top: '-5px', right: '-30px', color: '#2ECC40'}}/>
            }
          </div>

          <div className={bem('__bottom')}>
            <Handle
              type="source"
              id="bottom"
              position={Position.Bottom}
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
        <DialogTitle id="form-dialog-title">Segment node</DialogTitle>

        <DialogContent>
          <DialogContentText>
            Segments evaluate user's presence in a group of users defined
            by system-provided conditions. Execution flow can be directed
            based on presence/absence of user within the selected segment.
            You can either pick one of the existing segments or create a
            new one.
          </DialogContentText>

          <Grid container spacing={3}>
            <Grid item xs={6}>
              <TextField
                margin="normal"
                id="segment-name"
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

          <Grid container spacing={3} alignItems="flex-end">
            <Grid item xs={12}>
              <SegmentSelector
                selectedSegment={selectedSegment}
                selectedSegmentSourceTable={selectedSegmentSourceTable}
                onSegmentTypeButtonClick={actionSetTable}
                onSegmentSelectedChange={segmentSelectedChange}
              >
              </SegmentSelector>
            </Grid>
          </Grid>
        </DialogContent>

        <DialogActions>
          <NewSegmentButton
            color="primary"
            onClick={handleNewSegmentClick}
          >
            <Icon style={{marginRight: '5px'}}>add_circle</Icon>
            New segment
          </NewSegmentButton>

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
              props.data.node.selectedSegment = selectedSegment;

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
