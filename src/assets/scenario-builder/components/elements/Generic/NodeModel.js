import { NodeModel as BaseNodeModel } from '@projectstorm/react-diagrams';
import * as _ from 'lodash';
import { PortModel } from './PortModel';

export class NodeModel extends BaseNodeModel {
  constructor(element) {
    super('generic', element.id);

    this.name = element.name;
    this.selectedGeneric = element.selectedGeneric;
    this.options = element.options;
    this.addPort(new PortModel('left'));
    this.addPort(new PortModel('right'));
  }

  deSerialize(ob, engine) {
    super.deSerialize(ob, engine);
    this.name = ob.name;
    this.selectedGeneric = ob.selectedGeneric;
    this.options = ob.options;
  }

  serialize() {
    return _.merge(super.serialize(), {
      name: this.name,
      selectedGeneric: this.selectedGeneric,
      options: this.options,
    });
  }
}
