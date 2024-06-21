import * as _ from 'lodash';
import { PortModel as BasePortModel } from '@projectstorm/react-diagrams';

import { LinkModel } from './../Link';

export class LeftRightPort extends BasePortModel {
  in;
  position;

  constructor(pos = 'left', type) {
    super(pos, type);

    this.position = pos;
    this.in = this.position === 'left';
  }

  link(port) {
    let link = this.createLinkModel();

    link.setSourcePort(this);
    link.setTargetPort(port);

    return link;
  }

  canLinkToPort(port) {
    return this.in !== port.in;
  }

  serialize() {
    return _.merge(super.serialize(), {
      position: this.position
    });
  }

  deSerialize(data, engine) {
    super.deSerialize(data, engine);
    this.position = data.position;
  }

  createLinkModel() {
    return new LinkModel();
  }
}
