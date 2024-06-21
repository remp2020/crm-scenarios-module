import { NodeModel as BaseNodeModel } from '@projectstorm/react-diagrams';
import * as _ from 'lodash';
import { PortModel } from './PortModel';

export class NodeModel extends BaseNodeModel {
  constructor(element) {
    super('email', element.id);

    this.name = element.name;
    this.selectedMail = element.selectedMail;
    this.addPort(new PortModel('left'));
    this.addPort(new PortModel('right'));
  }

  deSerialize(ob, engine) {
    super.deSerialize(ob, engine);
    this.name = ob.name;
    this.selectedMail = ob.selectedMail;
  }

  serialize() {
    return _.merge(super.serialize(), {
      name: this.name,
      selectedMail: this.selectedMail,
    });
  }
}
