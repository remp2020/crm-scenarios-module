import * as React from 'react';

import { PortWidget as BasePortWidget } from '@projectstorm/react-diagrams';

export class PortWidget extends BasePortWidget {
  constructor(props) {
    super(props);
    this.state = {
      selected: false
    };
  }

  getClassName() {
    return (
      'port ' +
      super.getClassName() +
      (this.state.selected ? this.bem('--selected') : '')
    );
  }

  render() {
    return (
      <div
        {...this.getProps()}
        onMouseEnter={() => {
          this.setState({ selected: true });
        }}
        onMouseLeave={() => {
          this.setState({ selected: false });
        }}
        data-name={this.props.name}
        data-nodeid={this.props.node.getID()}
      >
        {this.props.children}
      </div>
    );
  }
}
