import React, { useState } from 'react';
import { useSelector } from 'react-redux';
import ActionIcon from '@mui/icons-material/Adjust';
import Grid from '@mui/material/Grid';
import TextField from '@mui/material/TextField';
import Button from '@mui/material/Button';
import Dialog from '@mui/material/Dialog';
import DialogActions from '@mui/material/DialogActions';
import DialogContent from '@mui/material/DialogContent';
import DialogContentText from '@mui/material/DialogContentText';
import DialogTitle from '@mui/material/DialogTitle';
import FormControl from '@mui/material/FormControl';
import InputLabel from '@mui/material/InputLabel';
import Select from '@mui/material/Select';
import MenuItem from '@mui/material/MenuItem';
import StatisticsTooltip from '../../StatisticTooltip';
import StatisticBadge from '../../StatisticBadge';
import { Autocomplete } from '@mui/material';
import { Handle, Position } from 'reactflow';
import { NodePopover } from '../../NodePopover';
import { useNode } from '../../../hooks/useNode';

const NodeWidget = (props) => {
  const [selectedBanner, setSelectedBanner] = useState(props.data.node.selectedBanner);
  const [expiresInTime, setExpiresInTime] = useState(props.data.node.expiresInTime);
  const [expiresInUnit, setExpiresInUnit] = useState(props.data.node.expiresInUnit);
  const banners = useSelector((state) => state.banners.availableBanners);
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

  const getSelectedBanner = () => {
    const selected = banners.find(
      banner => banner.id === selectedBanner
    );

    return selected ? selected : null;
  };

  const getSelectedBannerValue = () => {
    const selected = banners.find(
      banner => banner.id === props.data.node.selectedBanner
    );

    return selected ? ` - ${selected.name}` : '';
  };

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
            <StatisticBadge elementId={props.id} color="#6435c9" position="right"/>
          </div>
        </div>
      </div>
      <div className={bem('__title')}>
        <div className={bem('__name')}>
          {props.data.node.name
            ? props.data.node.name
            : `Banner ${getSelectedBannerValue()}`}
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
        <DialogTitle id="form-dialog-title">Banner node</DialogTitle>

        <DialogContent>
          <DialogContentText>Shows a one-time banner to user.</DialogContentText>

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

          <Grid container style={{marginBottom: '10px'}}>
            <Grid item xs={12}>
              <Autocomplete
                value={getSelectedBanner()}
                options={banners}
                getOptionLabel={(option) => option.name}
                disableClearable={true}
                onChange={(event, selectedOption) => {
                  if (selectedOption !== null) {
                    setSelectedBanner(selectedOption.id);
                  }
                }}
                renderInput={params => (
                  <TextField {...params} variant="standard" label="Selected Banner" fullWidth/>
                )}
              />
            </Grid>
          </Grid>

          <Grid container spacing={1}>
            <Grid item xs={6}>
              <TextField
                id="expires-in-time"
                label="Expires in"
                type="number"
                helperText="Banner is not shown after given period"
                variant="standard"
                fullWidth
                value={expiresInTime}
                onChange={event => {
                  setExpiresInTime(event.target.value);
                }}
              />
            </Grid>
            <Grid item xs={6}>
              <FormControl fullWidth variant="standard">
                <InputLabel htmlFor="time-unit">Time unit</InputLabel>
                <Select
                  variant="standard"
                  value={expiresInUnit}
                  onChange={event => {
                    setExpiresInUnit(event.target.value);
                  }}
                  inputProps={{
                    name: 'expires-in-unit',
                    id: 'expires-in-unit'
                  }}
                >
                  <MenuItem value="minutes">Minutes</MenuItem>
                  <MenuItem value="hours">Hours</MenuItem>
                  <MenuItem value="days">Days</MenuItem>
                </Select>
              </FormControl>
            </Grid>
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
              props.data.node.selectedBanner = selectedBanner;
              props.data.node.expiresInTime = expiresInTime;
              props.data.node.expiresInUnit = expiresInUnit;

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
