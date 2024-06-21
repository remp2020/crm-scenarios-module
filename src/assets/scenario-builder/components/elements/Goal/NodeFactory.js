import * as React from 'react';
import { AbstractNodeFactory } from '@projectstorm/react-diagrams';

import NodeWidget from './NodeWidget';
import { NodeModel } from './NodeModel';

export class NodeFactory extends AbstractNodeFactory {
  constructor() {
    super('goal');
  }

  generateReactWidget(diagramEngine, node) {
    return (
      <NodeWidget
        diagramEngine={diagramEngine}
        node={node}
        classBaseName='diamond-node'
        className='goal-node'
      />
    );
  }

  getNewInstance() {
    return new NodeModel();
  }
}
