import { NodeModel as BaseNodeModel } from '@projectstorm/react-diagrams';
import * as _ from 'lodash';
import { PortModel } from './PortModel';

export class NodeModel extends BaseNodeModel {
  constructor(element) {
    super('banner', element.id);

    this.name = element.name;
    this.selectedBanner = element.selectedBanner;

    this.expiresInTime = element.expiresInTime;
    this.expiresInUnit = element.expiresInUnit;

    this.addPort(new PortModel('left'));
    this.addPort(new PortModel('right'));
  }

  deSerialize(ob, engine) {
    super.deSerialize(ob, engine);
    this.name = ob.name;
    this.selectedBanner = ob.selectedBanner;

    this.expiresInTime = ob.expiresInTime || '1';
    this.expiresInUnit = ob.expiresInUnit || 'days';
  }

  serialize() {
    return _.merge(super.serialize(), {
      name: this.name,
      selectedBanner: this.selectedBanner,
      expiresInTime: this.expiresInTime,
      expiresInUnit: this.expiresInUnit,
    });
  }
}
