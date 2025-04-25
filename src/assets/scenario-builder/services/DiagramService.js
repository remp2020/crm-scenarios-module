import flatMap from 'lodash/flatMap';
import { BANNER_ENABLED } from '../config';
import { v4 as uuid } from 'uuid';
import * as Goal from '../components/elements/Goal/NodeFactory';
import * as Wait from '../components/elements/Wait/NodeFactory';
import * as Email from '../components/elements/Email/NodeFactory';
import * as ABTest from '../components/elements/ABTest/NodeFactory';
import * as Banner from '../components/elements/Banner/NodeFactory';
import * as Generic from '../components/elements/Generic/NodeFactory';
import * as Segment from '../components/elements/Segment/NodeFactory';
import * as Trigger from '../components/elements/Trigger/NodeFactory';
import * as BeforeTrigger from '../components/elements/BeforeTrigger/NodeFactory';
import * as PushNotification from '../components/elements/PushNotification/NodeFactory';
import * as Condition from '../components/elements/Condition/NodeFactory';

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

export class DiagramService {
  instance;

  setInstance(diagramInstance) {
    this.instance = diagramInstance;
  }

  getData() {
    return this.instance.toObject();
  }

  getNodes() {
    return this.instance.getNodes();
  }

  getSelectedNodes() {
    return this.getNodes().filter(node => node.selected);
  }

  fitView() {
    this.instance.fitView()
  }

  restore(payload) {
    const edges = [];
    const nodes = [];

    const linkNodes = (sourceNode, targetNode, direction, position = 0) => {
      if (direction) {
        if (direction === 'positive') {
          const link = {
            id: uuid(),
            source: sourceNode.id,
            sourceHandle: 'right',
            target: targetNode.id,
            targetHandle: 'left'
          };

          if (sourceNode.type === 'ab_test') {
            link.sourceHandle = `right.${position}`;
          }

          edges.push(link);
          return;
        } else if (direction === 'negative') {
          edges.push({
            id: uuid(),
            source: sourceNode.id,
            sourceHandle: 'bottom',
            target: targetNode.id,
            targetHandle: 'left'
          });
          return;
        }
      }

      edges.push({
        id: uuid(),
        source: sourceNode.id,
        sourceHandle: 'right',
        target: targetNode.id,
        targetHandle: 'left'
      });
    };

    // Format nodes
    flatMap(payload.elements, element => {
      const node = this.createNode(element.type, element, payload.name);
      node.position = payload.visual[element.id];
      nodes.push(node);
    });

    // Link nodes
    flatMap(payload.elements, element => {
      element[element.type].descendants.forEach(item => {
        linkNodes(element, {id: item.uuid}, item.direction, item.position);
      });
    });

    // Format triggers
    flatMap(payload.triggers, data => {
      let node;

      if (data.type === 'event') {
        data.selectedTrigger = data.event.code;
        node = Trigger.createNode(data);

      } else if (data.type === 'before_event') {

        const timeUnit = minutesToTimeUnit(data.options.minutes);
        data.timeUnit = timeUnit.unit;
        data.time = timeUnit.time;
        data.selectedTrigger = data.event.code;

        node = BeforeTrigger.createNode(data);
      }

      node.data = {
        ...node.data,
        deleteNode: (id) => this.deleteNode(id)
      }

      node.position = payload.visual[data.id];

      // link triggers with nodes
      data.elements.forEach(elementId => {
        linkNodes(node, {id: elementId});
      });

      nodes.push(node);
    });

    this.instance.setNodes(nodes);
    this.instance.setEdges(edges);
  }

  createNode(type, data, scenarioName) {
    let node;

    if (type === 'segment') {
      data.selectedSegment = data.segment.code;
      node = Segment.createNode(data);

    } else if (type === 'condition') {
      node = Condition.createNode({
        id: data.id,
        conditions: data.condition.conditions,
        name: data.name
      });

    } else if (type === 'email') {
      data.selectedMail = data.email.code;
      node = Email.createNode(data);

    } else if (type === 'generic') {

      data.selectedGeneric = data.generic.code;
      data.options = data.generic.options;
      node = Generic.createNode(data);

    } else if (type === 'wait') {

      const timeUnit = minutesToTimeUnit(data.wait.minutes);
      data.waitingUnit = timeUnit.unit;
      data.waitingTime = timeUnit.time;

      node = Wait.createNode(data);

    } else if (type === 'banner') {

      if (!BANNER_ENABLED) {
        throw Error('BANNER_ENABLED configuration is false, but loaded scenario contains banner data.');
      }

      const timeUnit = minutesToTimeUnit(data.banner.expiresInMinutes);
      data.expiresInUnit = timeUnit.unit;
      data.expiresInTime = timeUnit.time;

      data.selectedBanner = data.banner.id;

      node = Banner.createNode(data);

    } else if (type === 'goal') {

      if (data.goal.hasOwnProperty('timeoutMinutes')) {
        const timeUnit = minutesToTimeUnit(data.goal.timeoutMinutes);
        data.timeoutUnit = timeUnit.unit;
        data.timeoutTime = timeUnit.time;
      }

      const recheckPeriodTimeUnit = minutesToTimeUnit(data.goal.recheckPeriodMinutes);
      data.recheckPeriodUnit = recheckPeriodTimeUnit.unit;
      data.recheckPeriodTime = recheckPeriodTimeUnit.time;

      data.selectedGoals = data.goal.codes;

      node = Goal.createNode(data);

    } else if (type === 'push_notification') {

      data.selectedTemplate = data.push_notification.template;
      data.selectedApplication = data.push_notification.application;

      node = PushNotification.createNode(data);

    } else if (type === 'ab_test') {
      node = ABTest.createNode({
        id: data.id,
        scenarioName: scenarioName,
        name: data.name,
        variants: data.ab_test.variants
      });
    }

    node.data = {
      ...node.data,
      deleteNode: (id) => this.deleteNode(id)
    }

    return node;
  }

  getDiagram() {
    return this.instance;
  }

  copyNodes(nodesToCopy) {
    const newNodes = nodesToCopy.map((node) => this.copyNode(node));
    const newEdges = this.instance.getEdges()
      .filter(edge => nodesToCopy.find(node => node.id === edge.source) || nodesToCopy.find(node => node.id === edge.target))
      .map(edge => ({
        ...edge,
        id: uuid(),
        source: newNodes.find(node => node.originalId === edge.source)?.id || edge.source,
        target: newNodes.find(node => node.originalId === edge.target)?.id || edge.target
      }))
      .filter(edge => newNodes.find(node => node.id === edge.source) && newNodes.find(node => node.id === edge.target));

    this.instance.addNodes(newNodes);
    this.instance.addEdges(newEdges);

    return {
      nodes: newNodes,
      edges: newEdges
    }
  }

  copyNode(node) {
    let offset = {x: 75, y: 75};
    let newNode = JSON.parse(JSON.stringify(node));
    newNode.position = {
      x: newNode.position.x + offset.x,
      y: newNode.position.y + offset.y
    };
    newNode.selected = false;
    newNode.originalId = node.id;
    newNode.id = uuid();
    return newNode;
  }

  deleteNode(nodeId) {
    this.instance.deleteElements({
      nodes: [{ id: nodeId }]
    })
  }
}
