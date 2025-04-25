import React, { createRef, useState } from 'react';
import * as _ from 'lodash';
import { useSelector } from 'react-redux';
import ActionIcon from '@mui/icons-material/Extension';
import Grid from '@mui/material/Grid';
import TextField from '@mui/material/TextField';
import Button from '@mui/material/Button';
import Dialog from '@mui/material/Dialog';
import DialogActions from '@mui/material/DialogActions';
import DialogContent from '@mui/material/DialogContent';
import DialogContentText from '@mui/material/DialogContentText';
import DialogTitle from '@mui/material/DialogTitle';
import StatisticsTooltip from '../../StatisticTooltip';
import Autocomplete, { createFilterOptions } from '@mui/material/Autocomplete';
import { makeStyles } from '@mui/styles';
import OptionsForm from './OptionsForm';
import StatisticBadge from '../../StatisticBadge';
import { Handle, Position } from 'reactflow';
import { NodePopover } from '../../NodePopover';
import { useNode } from '../../../hooks/useNode';

const useStyles = makeStyles(theme => ({
  autocomplete: {
    margin: theme.spacing(1)
  },
  subtitle: {
    paddingLeft: '6px',
    color: theme.palette.grey[600]
  }
}));

const filterOptions = createFilterOptions({
  matchFrom: 'any',
  trim: true,
  ignoreAccents: true,
  ignoreCase: true,
  stringify: option => {
    return option.label + ' ' + option.value;
  }
});

const NodeWidget = (props) => {
  const optionsFormRef = createRef();
  const classes = useStyles();
  const [selectedGeneric, setSelectedGeneric] = useState(props.data.node.selectedGeneric);
  const generics = useSelector(state => state.generics.generics);
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

  const getSelectedGeneric = () => {
    const match = generics.find(generic => {
      return generic.code === selectedGeneric;
    });

    return match ? match : null;
  };

  const getSelectedGenericOptionBlueprints = () => {
    const generic = getSelectedGeneric();

    let blueprints = [];
    if (generic !== null && generic.options !== null) {
      _.forOwn(generic.options, function (value, key) {
        blueprints.push({
          key: key,
          blueprint: value
        });
      });
    }

    return blueprints;
  };

  // maybe refactor to more effective way if is a problem
  const transformOptionsForSelect = () => {
    return generics.map(item => ({
      value: item.code,
      label: item.label
    }));
  };

  const getSelectedGenericDefaultLabel = () => {
    const generic = getSelectedGeneric();
    return generic ? ` - ${generic.label}` : '';
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
            <StatisticBadge elementId={props.id} color="#a291fb" position="right"/>
          </div>
        </div>
      </div>
      <div className={bem('__title')}>
        <div className={bem('__name')}>
          {props.data.node.name
            ? props.data.node.name
            : `Generic ${getSelectedGenericDefaultLabel()}`}
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
        <DialogTitle id="form-dialog-title">Generic action node</DialogTitle>

        <DialogContent>
          <DialogContentText>Runs defined generic action.</DialogContentText>

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
                  setNodeFormName(event.target.value)
                }}
              />
            </Grid>
          </Grid>

          <Grid container alignItems="center" alignContent="space-between">
            <Grid item xs={12}>
              <Autocomplete
                value={getSelectedGeneric()}
                options={transformOptionsForSelect()}
                getOptionLabel={(option) => option.label}
                isOptionEqualToValue={(option, value) => option.key === value.key}
                disableClearable={true}
                filterOptions={filterOptions}
                onChange={(event, selectedOption) => {
                  if (selectedOption !== null) {
                    setSelectedGeneric(selectedOption.value)
                  }
                }}
                renderInput={params => (
                  <TextField {...params} variant="standard" label="Action" fullWidth/>
                )}
                renderOption={(props, option) => (
                  <div {...props}>
                    <span className={classes.title}>{option.label}</span>
                    <small className={classes.subtitle}>({option.value})</small>
                  </div>
                )}
              />
            </Grid>
          </Grid>

          {selectedGeneric && getSelectedGenericOptionBlueprints().length > 0 &&
            <Grid container alignItems="center" alignContent="space-between">
              <Grid item xs={12}>
                <p>Options</p>
                <OptionsForm
                  options={props.data.node.options}
                  blueprints={getSelectedGenericOptionBlueprints()}
                  ref={optionsFormRef}
                />
              </Grid>
            </Grid>
          }
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
              props.data.node.selectedGeneric = selectedGeneric;
              props.data.node.options = optionsFormRef.current ? optionsFormRef.current.state.options : [];

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
