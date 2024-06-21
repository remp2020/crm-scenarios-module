import flatMap from 'lodash/flatMap';

// import the custom models
import {
  Banner,
  Email,
  Segment,
  Trigger,
  Wait,
  Goal,
  Condition,
  BeforeTrigger,
  Generic,
  PushNotification,
  ABTest
} from './../components/elements';
import { BANNER_ENABLED } from './../config';

function minutesToTimeUnit(minutes) {
  if (minutes === 0) {
    return {
      unit: 'minutes',
      time: minutes
    };
  }

  if (minutes % 1440 === 0) {
    return {
      unit: 'days',
      time: minutes / 1440 
    };
  } else if (minutes % 60 === 0) {
    return {
      unit: 'hours',
      time: minutes / 60 
    };
  }
  return {
    unit: 'minutes',
    time: minutes
  };
}

export class RenderService {
  constructor(activeModel, payload = {}) {
    this.activeModel = activeModel;
    this.payload = payload;
  }

  renderPayload(payload) {
    this.payload = payload;

    const visual = this.payload.visual;

    //render Nodes
    flatMap(payload.elements, element => {
      let node = null;

      if (element.type === 'segment') {

        element.selectedSegment = element.segment.code;
        node = new Segment.NodeModel(element);

      } else if (element.type === 'condition') {

        node = new Condition.NodeModel({
          id: element.id,
          name: element.name,
          conditions: element.condition.conditions
        });

      } else if (element.type === 'email') {

        element.selectedMail = element.email.code;
        node = new Email.NodeModel(element);

      } else if (element.type === 'generic') {

        element.selectedGeneric = element.generic.code;
        element.options = element.generic.options;
        node = new Generic.NodeModel(element);

      } else if (element.type === 'wait') {

        const timeUnit = minutesToTimeUnit(element.wait.minutes);
        element.waitingUnit = timeUnit.unit;
        element.waitingTime = timeUnit.time;

        node = new Wait.NodeModel(element);

      } else if (element.type === 'banner') {

        if (!BANNER_ENABLED) {
          throw Error("BANNER_ENABLED configuration is false, but loaded scenario contains banner element.");
        }

        const timeUnit = minutesToTimeUnit(element.banner.expiresInMinutes);
        element.expiresInUnit = timeUnit.unit;
        element.expiresInTime = timeUnit.time;

        element.selectedBanner = element.banner.id;

        node = new Banner.NodeModel(element);

      } else if (element.type === 'goal') {

        if (element.goal.hasOwnProperty("timeoutMinutes")) {
          const timeUnit = minutesToTimeUnit(element.goal.timeoutMinutes);
          element.timeoutUnit = timeUnit.unit;
          element.timeoutTime = timeUnit.time;
        }

        const recheckPeriodTimeUnit = minutesToTimeUnit(element.goal.recheckPeriodMinutes);
        element.recheckPeriodUnit = recheckPeriodTimeUnit.unit;
        element.recheckPeriodTime = recheckPeriodTimeUnit.time;

        element.selectedGoals = element.goal.codes;

        node = new Goal.NodeModel(element);

      } else if (element.type === 'push_notification') {

        element.selectedTemplate = element.push_notification.template;
        element.selectedApplication = element.push_notification.application;

        node = new PushNotification.NodeModel(element);

      } else if (element.type === 'ab_test') {

        node = new ABTest.NodeModel({
          'id': element.id,
          'name': element.name,
          'variants': element.ab_test.variants,
          'scenarioName': this.payload.name
        });
      }

      this.activeModel.addNode(node);
      node.setPosition(visual[element.id].x, visual[element.id].y);
    });

    // link nodes
    flatMap(payload.elements, element => {
      let sourceNode = this.activeModel.getNode(element.id);

      element[element.type].descendants.forEach(item => {
        this.linkNodes(sourceNode, this.activeModel.getNode(item.uuid), item.direction, item.position);
      });
    });

    // renderTriggers
    flatMap(payload.triggers, trigger => {

      let node = null;
      if (trigger.type === 'event') {

        trigger.selectedTrigger = trigger.event.code;
        node = new Trigger.NodeModel(trigger);

      } else if (trigger.type === 'before_event') {

        const timeUnit = minutesToTimeUnit(trigger.options.minutes);
        trigger.timeUnit = timeUnit.unit;
        trigger.time = timeUnit.time;
        trigger.selectedTrigger = trigger.event.code;

        node = new BeforeTrigger.NodeModel(trigger);
      }

      this.activeModel.addNode(node);
      node.setPosition(visual[trigger.id].x, visual[trigger.id].y);

      // link triggers with nodes
      trigger.elements.forEach(element => {
        this.linkNodes(node, this.activeModel.getNode(element));
      });
    });
  }

  linkNodes(sourceNode, targetNode, direction, position = 0) {
    if (direction){
      if (direction === 'positive') {
        let link;
        if (sourceNode.type === 'ab_test') {
          link = sourceNode.getPort('right.' + position).link(targetNode.getPort('left'));
        } else {
          link = sourceNode.getPort('right').link(targetNode.getPort('left'));
        }
        this.activeModel.addLink(link);
        return;
      } else if (direction === 'negative') {
        const link = sourceNode.getPort('bottom').link(targetNode.getPort('left'));
        this.activeModel.addLink(link);
        return;
      }
    }

    const link = sourceNode.getPort('right').link(targetNode.getPort('left'));
    this.activeModel.addLink(link);
  }
}
