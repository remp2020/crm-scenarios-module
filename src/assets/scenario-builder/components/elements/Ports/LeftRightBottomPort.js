import * as _ from 'lodash';
import { PortModel as BasePortModel } from '@projectstorm/react-diagrams';

import { LinkModel } from './../Link';

export class LeftRightBottomPort extends BasePortModel {
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

  serialize() {
    return _.merge(super.serialize(), {
      position: this.position
    });
  }

  canLinkToPort(port) {
    return this.in !== port.in;
  }

  deSerialize(data, engine) {
    super.deSerialize(data, engine);
    this.position = data.position;
  }

  createLinkModel() {
    return new LinkModel();
  }
}
