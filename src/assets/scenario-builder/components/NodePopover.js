import React, { useEffect, useState } from 'react';
import { makeStyles } from '@mui/styles';
import Popover from '@mui/material/Popover';
import { IconButton } from '@mui/material';
import DeleteIcon from '@mui/icons-material/Delete';
import EditIcon from '@mui/icons-material/Edit';

const useStyles = makeStyles(() => ({
  nodePopoverButton: {
    borderRadius: 0,
    background: '#494949',
    color: '#d5d5d5',
    '&:hover': {
      background: '#5a5a5a',
      color: '#ffffff',
    },
  }
}))

export const NodePopover = ({ anchorEl, onClose, onEdit, onDelete }) => {
  const [open, setOpen] = useState(Boolean(anchorEl));
  const classes = useStyles()
  const anchorRect = anchorEl?.getBoundingClientRect()

  useEffect(() => {
    setOpen(Boolean(anchorEl))
  }, [anchorEl]);

  return anchorEl && anchorRect && (
    <Popover
      open={open}
      anchorEl={anchorEl}
      onClose={onClose}
      anchorReference="anchorPosition"
      anchorPosition={{
        top: anchorRect.bottom + 10,
        left: anchorRect.left + anchorRect.width / 2,
      }}
      transformOrigin={{
        vertical: 'top',
        horizontal: 'center',
      }}
    >
      <IconButton title="Edit" size="small" onClick={onEdit} className={classes.nodePopoverButton}>
        <EditIcon fontSize="inherit"/>
      </IconButton>
      <IconButton title="Delete" size="small" onClick={onDelete} className={classes.nodePopoverButton}>
        <DeleteIcon fontSize="inherit"/>
      </IconButton>
    </Popover>
  );
}
