import * as React from 'react';
import { AbstractNodeFactory } from '@projectstorm/react-diagrams';

import NodeWidget from './NodeWidget';
import { NodeModel } from './NodeModel';

export class NodeFactory extends AbstractNodeFactory {
  constructor() {
    super('ab_test');
  }

  generateReactWidget(diagramEngine, node) {
    return (
      <NodeWidget
        diagramEngine={diagramEngine}
        node={node}
        classBaseName='diamond-node'
        className='abtest-node'
      />
    );
  }

  getNewInstance() {
    return new NodeModel();
  }
}
