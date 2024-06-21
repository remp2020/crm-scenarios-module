import { DiagramEngine, DiagramModel } from '@projectstorm/react-diagrams';

// import the custom models
import {
  SimplePortFactory,
  Banner,
  Email,
  Generic,
  Segment,
  Trigger,
  BeforeTrigger,
  Wait,
  Goal,
  Condition,
  PushNotification,
  ABTest
} from './elements';

import './sass/main.scss';
import { LinkFactory } from './elements/Link';
import { RenderService } from './../services/RenderService';

export class Application {
  activeModel;
  diagramEngine;

  constructor(payload) {
    this.diagramEngine = new DiagramEngine();
    this.diagramEngine.installDefaultFactories();
    this.activeModel = new DiagramModel();
    this.renderService = new RenderService(this.activeModel);
    this.payload = payload;
    this.corruptedPayload = false;

    if (payload) {
      this.renderPayload();
    } else {
      this.registerCustomModels();
    }
  }

  renderPayload() {
    this.registerCustomModels();
    try {
      this.renderService.renderPayload(this.payload);
    } catch(ex) {
      // In case of rendering error, dump loaded model, log and flag as corrupted scenario
      console.log(ex.message);
      this.corruptedPayload = true;
      this.activeModel = new DiagramModel();
    }
    
    this.diagramEngine.setDiagramModel(this.activeModel);
    this.diagramEngine.repaintCanvas();
  }

  registerCustomModels() {
    this.diagramEngine.registerLinkFactory(new LinkFactory());
    this.diagramEngine.registerPortFactory(
      new SimplePortFactory('email', config => new Email.PortModel())
    );
    this.diagramEngine.registerNodeFactory(new Email.NodeFactory());

    this.diagramEngine.registerPortFactory(
      new SimplePortFactory('generic', config => new Generic.PortModel())
    );
    this.diagramEngine.registerNodeFactory(new Generic.NodeFactory());

    this.diagramEngine.registerPortFactory(
      new SimplePortFactory('segment', config => new Segment.PortModel())
    );
    this.diagramEngine.registerNodeFactory(new Segment.NodeFactory());

    this.diagramEngine.registerPortFactory(
      new SimplePortFactory('trigger', config => new Trigger.PortModel())
    );
    this.diagramEngine.registerNodeFactory(new Trigger.NodeFactory());

    this.diagramEngine.registerPortFactory(
        new SimplePortFactory('before_trigger', config => new BeforeTrigger.PortModel())
    );
    this.diagramEngine.registerNodeFactory(new BeforeTrigger.NodeFactory());

    this.diagramEngine.registerPortFactory(
      new SimplePortFactory('wait', config => new Wait.PortModel())
    );
    this.diagramEngine.registerNodeFactory(new Wait.NodeFactory());

    // Goal
    this.diagramEngine.registerPortFactory(
      new SimplePortFactory('goal', config => new Goal.PortModel())
    );
    this.diagramEngine.registerNodeFactory(new Goal.NodeFactory());

    // Banner
    this.diagramEngine.registerPortFactory(
      new SimplePortFactory('banner', config => new Banner.PortModel())
    );
    this.diagramEngine.registerNodeFactory(new Banner.NodeFactory());

    // Condition
    this.diagramEngine.registerPortFactory(
      new SimplePortFactory('condition', config => new Condition.PortModel())
    );
    this.diagramEngine.registerNodeFactory(new Condition.NodeFactory());

    // Notification
    this.diagramEngine.registerPortFactory(
      new SimplePortFactory('push_notification', config => new PushNotification.PortModel())
    );
    this.diagramEngine.registerNodeFactory(new PushNotification.NodeFactory());

    // AB Test
    this.diagramEngine.registerPortFactory(
      new SimplePortFactory('ab_test', config => new ABTest.PortModel())
    );
    this.diagramEngine.registerNodeFactory(new ABTest.NodeFactory());
  }

  getActiveDiagram() {
    return this.activeModel;
  }

  getDiagramEngine() {
    return this.diagramEngine;
  }

  isCorruptedPayload() {
    return this.corruptedPayload;
  }
}
