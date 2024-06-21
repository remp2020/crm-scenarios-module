import * as React from 'react';
import { DefaultLinkFactory } from '@projectstorm/react-diagrams';

import { LinkModel } from './LinkModel';

export class LinkFactory extends DefaultLinkFactory {
  constructor() {
    super();
    this.type = 'custom';
  }

  getNewInstance(initialConfig) {
    return new LinkModel();
  }

  generateLinkSegment(model, widget, selected, path) {
    return (
      <path
        className={selected ? widget.bem('--path-selected') : ''}
        strokeWidth={model.width}
        stroke={model.color}
        d={path}
      />
    );
  }
}
