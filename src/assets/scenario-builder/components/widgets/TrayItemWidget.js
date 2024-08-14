import React from 'react';
import ListItem from '@mui/material/ListItem';
import ListItemIcon from '@mui/material/ListItemIcon';
import ListItemText from '@mui/material/ListItemText';

export const TrayItemWidget = (props) => {
  return (
    <ListItem
      // button
      key={props.name}
      draggable={true}
      onDragStart={event => {
        event.dataTransfer.setData(
          'application/reactflow',
          props.model.type
        );
          event.dataTransfer.effectAllowed = 'move';
      }}
      className='tray-item'
    >
      <ListItemIcon>{props.icon}</ListItemIcon>
      <ListItemText primary={props.name} />
    </ListItem>
  );
}
