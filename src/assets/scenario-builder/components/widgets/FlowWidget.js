import React, { useCallback, useEffect } from 'react';
import ReactFlow, { useNodesState, useEdgesState, addEdge, useReactFlow } from 'reactflow';
import 'reactflow/dist/style.css';
import GoalNodeWidget from '../elements/Goal/NodeWidget';
import WaitNodeWidget from '../elements/Wait/NodeWidget';
import EmailNodeWidget from '../elements/Email/NodeWidget';
import ABTestNodeWidget from '../elements/ABTest/NodeWidget';
import BannerNodeWidget from '../elements/Banner/NodeWidget';
import GenericNodeWidget from '../elements/Generic/NodeWidget';
import SegmentNodeWidget from '../elements/Segment/NodeWidget';
import TriggerNodeWidget from '../elements/Trigger/NodeWidget';
import BeforeTriggerNodeWidget from '../elements/BeforeTrigger/NodeWidget';
import PushNotificationNodeWidget from '../elements/PushNotification/NodeWidget';
import ConditionNodeWidget from '../elements/Condition/NodeWidget';
import * as Goal from '../elements/Goal/NodeFactory';
import * as Wait from '../elements/Wait/NodeFactory';
import * as Email from '../elements/Email/NodeFactory';
import * as ABTest from '../elements/ABTest/NodeFactory';
import * as Banner from '../elements/Banner/NodeFactory';
import * as Generic from '../elements/Generic/NodeFactory';
import * as Segment from '../elements/Segment/NodeFactory';
import * as Trigger from '../elements/Trigger/NodeFactory';
import * as BeforeTrigger from '../elements/BeforeTrigger/NodeFactory';
import * as PushNotification from '../elements/PushNotification/NodeFactory';
import * as Condition from '../elements/Condition/NodeFactory';

const nodeTypes = {
  email: EmailNodeWidget,
  generic: GenericNodeWidget,
  banner: BannerNodeWidget,
  push_notification: PushNotificationNodeWidget,
  segment: SegmentNodeWidget,
  condition: ConditionNodeWidget,
  trigger: TriggerNodeWidget,
  before_trigger: BeforeTriggerNodeWidget,
  wait: WaitNodeWidget,
  goal: GoalNodeWidget,
  ab_test: ABTestNodeWidget
};

const ctrlKey = 17,
  cmdKey = 91,
  vKey = 86,
  cKey = 67;

const defaultEdgeOptions = {
  style: {
    strokeWidth: '2px'
  }
};

const connectionLineStyle = {
  strokeWidth: '2px'
};

let ctrlDown = false;
let nodesToCopy = false;

export default function FlowWidget(props) {
  const [nodes, setNodes, onNodesChange] = useNodesState([]);
  const [edges, setEdges, onEdgesChange] = useEdgesState([]);
  const {screenToFlowPosition} = useReactFlow();

  const keydownHandler = (e) => {
    if (e.keyCode === ctrlKey || e.keyCode === cmdKey) {
      ctrlDown = true;
    }

    // CTRL/CMD + C
    if (ctrlDown && (e.keyCode === cKey)) {
      nodesToCopy = props.app.diagramService.getSelectedNodes();
    }

    // CTRL/CMD + V
    if (ctrlDown && (e.keyCode === vKey)) {
      const {nodes, edges} = props.app.diagramService.copyNodes(nodesToCopy);

      setNodes((nds) =>
        nds.map((node) => ({
          ...node,
          selected: nodes.find(n => n.id === node.id)
        }))
      );
      setEdges((eds) =>
        eds.map((edge) => ({
          ...edge,
          selected: edges.find(e => e.id === edge.id)
        }))
      );

      nodesToCopy = [];
    }
  };

  const keyupHandler = (e) => {
    if (e.keyCode === ctrlKey || e.keyCode === cmdKey) {
      ctrlDown = false;
    }
  };

  const onInit = (diagramInstance) => {
    props.app.getDiagramService().setInstance(diagramInstance);
  };

  const onConnect = useCallback(
    (params) => setEdges((eds) => addEdge(params, eds)),
    [setEdges]
  );

  const onDragOver = useCallback((event) => {
    event.preventDefault();
    event.dataTransfer.dropEffect = 'move';
  }, []);

  const onDrop = useCallback(
    (event) => {
      event.preventDefault();

      const type = event.dataTransfer.getData('application/reactflow');

      // check if the dropped element is valid
      if (typeof type === 'undefined' || !type) {
        return;
      }

      let node;

      if (type === 'banner') {
        node = Banner.createNode({
          expiresInUnit: 'days',
          expiresInTime: 1
        });
      } else if (type === 'goal') {
        node = Goal.createNode({
          recheckPeriodUnit: 'hours',
          recheckPeriodTime: 1
        });
      } else if (type === 'ab_test') {
        node = ABTest.createNode({
          name: 'AB Test',
          scenarioName: props.scenario.name
        });
      } else if (type === 'trigger') {
        node = Trigger.createNode();
      } else if (type === 'before_trigger') {
        node = BeforeTrigger.createNode();
      } else if (type === 'segment') {
        node = Segment.createNode();
      } else if (type === 'condition') {
        node = Condition.createNode();
      } else if (type === 'generic') {
        node = Generic.createNode();
      } else if (type === 'push_notification') {
        node = PushNotification.createNode();
      } else if (type === 'wait') {
        node = Wait.createNode();
      } else if (type === 'email') {
        node = Email.createNode();
      }

      node.data = {
        ...node.data,
        deleteNode: (id) => props.app.diagramService.deleteNode(id)
      }

      node.position = screenToFlowPosition({
        x: event.clientX,
        y: event.clientY
      });

      setNodes((nds) => nds.concat(node));
    },
    [screenToFlowPosition]
  );

  useEffect(() => {
    document.addEventListener('keydown', keydownHandler);
    document.addEventListener('keyup', keyupHandler);

    return () => {
      document.removeEventListener('keydown', keydownHandler);
      document.removeEventListener('keyup', keyupHandler);
    };
  }, []);

  return (
    <ReactFlow
      nodes={nodes}
      edges={edges}
      onNodesChange={onNodesChange}
      onEdgesChange={onEdgesChange}
      onConnect={onConnect}
      nodeTypes={nodeTypes}
      onDrop={onDrop}
      onDragOver={onDragOver}
      defaultEdgeOptions={defaultEdgeOptions}
      connectionLineStyle={connectionLineStyle}
      deleteKeyCode="Delete"
      onInit={onInit}
      minZoom={0.1}
    />
  );
}
