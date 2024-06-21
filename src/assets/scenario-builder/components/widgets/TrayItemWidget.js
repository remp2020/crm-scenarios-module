import * as React from 'react';

import ListItem from '@material-ui/core/ListItem';
import ListItemIcon from '@material-ui/core/ListItemIcon';
import ListItemText from '@material-ui/core/ListItemText';

export class TrayItemWidget extends React.Component {
  constructor(props) {
    super(props);
    this.state = {};
  }

  render() {
    return (
      <ListItem
        // button
        key={this.props.name}
        draggable={true}
        onDragStart={event => {
          event.dataTransfer.setData(
            'storm-diagram-node',
            JSON.stringify(this.props.model)
          );
        }}
        className='tray-item'
      >
        <ListItemIcon>{this.props.icon}</ListItemIcon>
        <ListItemText primary={this.props.name} />
      </ListItem>
    );
  }
}
