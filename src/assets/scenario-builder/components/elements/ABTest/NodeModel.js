import { NodeModel as BaseNodeModel } from '@projectstorm/react-diagrams';
import * as _ from 'lodash';
import { PortModel } from './PortModel';
import uuidv4 from 'uuid/v4';

export class NodeModel extends BaseNodeModel {
  constructor(element) {
    super('ab_test', element.id);

    this.name = element.name;
    this.scenarioName = element.scenarioName;

    if (element.variants) {
      this.variants = element.variants;
    } else {
      this.variants = [{
        code: uuidv4().slice(0, 6),
        name: 'Variant A',
        distribution: 50,
      }, {
        code: uuidv4().slice(0, 6),
        name: 'Variant B',
        distribution: 50,
      }];
    }

    this.addPort(new PortModel('left'));

    this.variants.forEach((item, index) =>
      this.addPort(new PortModel('right.' + index))
    );
  }

  deSerialize(ob, engine) {
    super.deSerialize(ob, engine);
    this.name = ob.name;
    this.variants = ob.variants;
  }

  serialize() {
    return _.merge(super.serialize(), {
      name: this.name,
      variants: this.variants,
    });
  }
}
