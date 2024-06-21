import * as _ from 'lodash';
import { NodeModel as BaseNodeModel } from '@projectstorm/react-diagrams';
import { PortModel } from './PortModel';

export class NodeModel extends BaseNodeModel {
  constructor(element) {
    super('before_trigger', element.id);

    this.name = element.name;
    this.selectedTrigger = element.selectedTrigger;
    this.time = element.time !== undefined ? element.time : 10;
    this.timeUnit = element.timeUnit !== undefined ? element.timeUnit : 'hours';

    this.addPort(new PortModel('right'));
  }

  deSerialize(ob, engine) {
    super.deSerialize(ob, engine);
    this.name = ob.name;
    this.selectedTrigger = ob.selectedTrigger;
    this.time = ob.time;
    this.timeUnit = ob.timeUnit;
  }

  serialize() {
    return _.merge(super.serialize(), {
      name: this.name,
      selectedTrigger: this.selectedTrigger,
      time: this.time,
      timeUnit: this.timeUnit
    });
  }
}
