import * as _ from 'lodash';
import { NodeModel as BaseNodeModel } from '@projectstorm/react-diagrams';
import { PortModel } from './PortModel';

export class NodeModel extends BaseNodeModel {
  constructor(element) {
    super('trigger', element.id);

    this.name = element.name;
    this.selectedTrigger = element.selectedTrigger;
    this.addPort(new PortModel('right'));
  }

  deSerialize(ob, engine) {
    super.deSerialize(ob, engine);
    this.name = ob.name;
    this.selectedTrigger = ob.selectedTrigger;
  }

  serialize() {
    return _.merge(super.serialize(), {
      name: this.name,
      selectedTrigger: this.selectedTrigger
    });
  }
}
