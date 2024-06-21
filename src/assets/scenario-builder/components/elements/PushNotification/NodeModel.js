import { NodeModel as BaseNodeModel } from '@projectstorm/react-diagrams';
import * as _ from 'lodash';
import { PortModel } from './PortModel';

export class NodeModel extends BaseNodeModel {
  constructor(element) {
    super('push_notification', element.id);

    this.name = element.name;
    this.selectedTemplate = element.selectedTemplate;
    this.selectedApplication = element.selectedApplication;
    this.addPort(new PortModel('left'));
    this.addPort(new PortModel('right'));
  }

  deSerialize(ob, engine) {
    super.deSerialize(ob, engine);
    this.name = ob.name;
    this.selectedTemplate = ob.selectedTemplate;
    this.selectedApplication = ob.selectedApplication;
  }

  serialize() {
    return _.merge(super.serialize(), {
      name: this.name,
      selectedTemplate: this.selectedTemplate,
      selectedApplication: this.selectedApplication,
    });
  }
}
