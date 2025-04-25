import { useRef, useState } from 'react';
import { setCanvasZoomingAndPanning } from '../store/canvasSlice';
import { store } from '../store';
import { bemClassName } from '../utils/bem';

export const useNode = (nodeProps) => {
  const clickTimeout = useRef(null);
  const [nodeFormName, setNodeFormName] = useState(nodeProps.data.node.name);
  const [dialogOpened, setDialogOpened] = useState(false);
  const [anchorElForPopover, setAnchorElForPopover] = useState(null);
  const [anchorElementForTooltip, setAnchorElementForTooltip] = useState(null);
  const dialogOpenCallbacks = []
  const dialogCloseCallbacks = []

  const bem = (selector) => bemClassName(
    selector,
    nodeProps.data.node.classBaseName,
    nodeProps.data.node.className
  )

  const getClassName = () => {
    return nodeProps.data.node.classBaseName + ' ' + nodeProps.data.node.className;
  };

  const openDialog = () => {
    if (dialogOpened) {
      return;
    }

    setDialogOpened(true);
    setAnchorElementForTooltip(null);
    setAnchorElForPopover(null);
    store.dispatch(setCanvasZoomingAndPanning(false));
    setNodeFormName(nodeProps.data.node.name)

    dialogOpenCallbacks.map(callback => callback())
  };

  const closeDialog = () => {
    setDialogOpened(false);
    store.dispatch(setCanvasZoomingAndPanning(true));
    dialogCloseCallbacks.map(callback => callback())
  };

  const handleNodeMouseEnter = event => {
    if (!dialogOpened && !anchorElForPopover) {
      setAnchorElementForTooltip(event.currentTarget);
    }
  };

  const handleNodeMouseLeave = () => {
    setAnchorElementForTooltip(null);
  };

  const onNodeClick = (event) => {
    if (!clickTimeout.current) {
      const target = event.currentTarget;
      clickTimeout.current = setTimeout(() => {
        if (!anchorElForPopover && !dialogOpened) {
          setAnchorElForPopover(target.querySelector('.node-container') || target);
          setAnchorElementForTooltip(null);
        }
        clickTimeout.current = null;
      }, 200);
    }
  };

  const onNodeDoubleClick = () => {
    clearTimeout(clickTimeout.current);
    clickTimeout.current = null;
    openDialog();
  };

  const deleteNode = () => {
    nodeProps.data.deleteNode(nodeProps.id);
  };

  const closePopover = () => {
    setAnchorElForPopover(null);
  };

  const onDialogOpen = (callback) => {
    dialogOpenCallbacks.push(callback)
  }

  const onDialogClose = (callback) => {
    dialogCloseCallbacks.push(callback)
  }

  return {
    bem,
    getClassName,
    anchorElForPopover,
    anchorElementForTooltip,
    dialogOpened,
    openDialog,
    closeDialog,
    onNodeClick,
    onNodeDoubleClick,
    deleteNode,
    closePopover,
    setAnchorElementForTooltip,
    onDialogOpen,
    onDialogClose,
    nodeFormName,
    setNodeFormName,
    handleNodeMouseEnter,
    handleNodeMouseLeave
  };
};
