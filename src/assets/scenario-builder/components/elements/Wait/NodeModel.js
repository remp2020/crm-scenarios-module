import * as _ from 'lodash';
import { NodeModel as BaseNodeModel } from '@projectstorm/react-diagrams';
import { PortModel } from './PortModel';

export class NodeModel extends BaseNodeModel {
  constructor(element) {
    super('wait', element.id);

    this.name = element.name;
    this.waitingTime = element.waitingTime !== undefined ? element.waitingTime : 10;
    this.waitingUnit = element.waitingUnit !== undefined ? element.waitingUnit : 'minutes';

    this.addPort(new PortModel('left'));
    this.addPort(new PortModel('right'));
  }

  deSerialize(ob, engine) {
    super.deSerialize(ob, engine);
    this.name = ob.name;
    this.waitingTime = ob.waitingTime;
    this.waitingUnit = ob.waitingUnit;
  }

  serialize() {
    return _.merge(super.serialize(), {
      name: this.name,
      waitingTime: this.waitingTime,
      waitingUnit: this.waitingUnit
    });
  }
}
