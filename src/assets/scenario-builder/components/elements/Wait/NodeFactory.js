import { AbstractNodeFactory } from '@projectstorm/react-diagrams';

import NodeWidget from './NodeWidget';
import { NodeModel } from './NodeModel';
import * as React from 'react';

export class NodeFactory extends AbstractNodeFactory {
  constructor() {
    super('wait');
  }

  generateReactWidget(diagramEngine, node) {
    return (
      <NodeWidget
        diagramEngine={diagramEngine}
        node={node}
        classBaseName='round-node'
        className='wait-node'
      />
    );
  }

  getNewInstance() {
    return new NodeModel();
  }
}
