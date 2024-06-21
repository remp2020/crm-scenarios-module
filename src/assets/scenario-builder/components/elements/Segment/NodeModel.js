import { NodeModel as BaseNodeModel } from '@projectstorm/react-diagrams';
import * as _ from 'lodash';
import { PortModel } from './PortModel';

export class NodeModel extends BaseNodeModel {
  constructor(element) {
    super('segment', element.id);

    this.name = element.name;
    this.selectedSegment = element.selectedSegment;

    this.addPort(new PortModel('left'));
    this.addPort(new PortModel('bottom'));
    this.addPort(new PortModel('right'));
  }

  deSerialize(ob, engine) {
    super.deSerialize(ob, engine);
    this.name = ob.name;
    this.selectedSegment = ob.selectedSegment;
  }

  serialize() {
    return _.merge(super.serialize(), {
      name: this.name,
      selectedSegment: this.selectedSegment
    });
  }
}