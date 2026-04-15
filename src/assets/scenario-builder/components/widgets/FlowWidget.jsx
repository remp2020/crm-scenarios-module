import React, { useCallback, useEffect, useRef } from 'react';
import ReactFlow, { useNodesState, useEdgesState, addEdge, useReactFlow } from 'reactflow';
import { store } from '../../store';
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
  cKey = 67,
  zKey = 90;

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

const HISTORY_LIMIT = 100;

export default function FlowWidget(props) {
  const [nodes, setNodes, onNodesChange] = useNodesState([]);
  const [edges, setEdges, onEdgesChange] = useEdgesState([]);
  const {screenToFlowPosition} = useReactFlow();

  // Keep refs to latest nodes/edges so we can snapshot the pre-change state
  // without the snapshot closure getting stale across renders.
  const nodesRef = useRef(nodes);
  const edgesRef = useRef(edges);
  useEffect(() => { nodesRef.current = nodes; }, [nodes]);
  useEffect(() => { edgesRef.current = edges; }, [edges]);

  const historyRef = useRef([]);
  // Dedupe multiple snapshot() calls fired within the same synchronous burst
  // (e.g. delete of a node cascades into edge removal — still one undo step).
  const snapshotPendingRef = useRef(false);

  const snapshot = useCallback(() => {
    if (snapshotPendingRef.current) {
      return;
    }
    snapshotPendingRef.current = true;
    Promise.resolve().then(() => { snapshotPendingRef.current = false; });

    historyRef.current.push({
      nodes: nodesRef.current.map(n => JSON.parse(JSON.stringify(n))),
      edges: edgesRef.current.map(e => ({ ...e }))
    });
    if (historyRef.current.length > HISTORY_LIMIT) {
      historyRef.current.shift();
    }
  }, []);

  const undo = useCallback(() => {
    const previous = historyRef.current.pop();
    if (!previous) {
      return;
    }
    setNodes(previous.nodes);
    setEdges(previous.edges);
  }, [setNodes, setEdges]);

  // Intercept node changes to capture an undo point before destructive edits.
  const handleNodesChange = useCallback((changes) => {
    if (changes.some(c => c.type === 'remove')) {
      snapshot();
    }
    onNodesChange(changes);
  }, [onNodesChange, snapshot]);

  const handleEdgesChange = useCallback((changes) => {
    if (changes.some(c => c.type === 'remove')) {
      snapshot();
    }
    onEdgesChange(changes);
  }, [onEdgesChange, snapshot]);

  // Snapshot before a drag begins — restore will bring the node back to its
  // original position if the user regrets the move.
  // On drag stop we check if the position actually changed; if not, we pop the
  // snapshot so no-op drags (click without moving) don't pollute the stack.
  const dragStartPositionsRef = useRef(null);

  const onNodeDragStart = useCallback((_event, _node, draggedNodes) => {
    snapshot();
    dragStartPositionsRef.current = new Map(
      draggedNodes.map(n => [n.id, { x: n.position.x, y: n.position.y }])
    );
  }, [snapshot]);

  const onNodeDragStop = useCallback((_event, _node, draggedNodes) => {
    const startPositions = dragStartPositionsRef.current;
    if (!startPositions) return;

    const moved = draggedNodes.some(n => {
      const start = startPositions.get(n.id);
      return start && (start.x !== n.position.x || start.y !== n.position.y);
    });

    if (!moved) {
      // Nothing actually moved — discard the snapshot we pushed on drag start
      historyRef.current.pop();
    }
    dragStartPositionsRef.current = null;
  }, []);

  const keydownHandler = (e) => {
    if (e.keyCode === ctrlKey || e.keyCode === cmdKey) {
      ctrlDown = true;
    }

    // CTRL/CMD + C
    if (ctrlDown && (e.keyCode === cKey)) {
      if (store.getState().canvas.nodeDetailOpened) {
        return;
      }
      nodesToCopy = props.app.diagramService.getSelectedNodes();
    }

    // CTRL/CMD + V
    if (ctrlDown && (e.keyCode === vKey)) {
      if (store.getState().canvas.nodeDetailOpened) {
        return;
      }
      const {nodes: copiedNodes, edges: copiedEdges} = props.app.diagramService.copyNodes(nodesToCopy);

      snapshot();

      setNodes((nds) =>
        nds.map((node) => ({
          ...node,
          selected: false
        })).concat(copiedNodes.map(node => ({...node, selected: true})))
      );
      setEdges((eds) =>
        eds.map((edge) => ({
          ...edge,
          selected: false
        })).concat(copiedEdges.map(edge => ({...edge, selected: true})))
      );

      nodesToCopy = [];
    }

    // CTRL/CMD + Z — universal undo across delete/connect/move/drop/paste
    if (ctrlDown && (e.keyCode === zKey)) {
      if (store.getState().canvas.nodeDetailOpened) {
        return;
      }
      e.preventDefault();
      undo();
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
    (params) => {
      snapshot();
      setEdges((eds) => addEdge(params, eds));
    },
    [setEdges, snapshot]
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

      node.position = screenToFlowPosition({
        x: event.clientX,
        y: event.clientY
      });

      snapshot();
      setNodes((nds) => nds.concat(node));
    },
    [screenToFlowPosition, setNodes, snapshot]
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
      onNodesChange={handleNodesChange}
      onEdgesChange={handleEdgesChange}
      onNodeDragStart={onNodeDragStart}
      onNodeDragStop={onNodeDragStop}
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
