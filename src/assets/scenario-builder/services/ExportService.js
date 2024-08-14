function unitTimeToMinutes(time, unit) {
  switch (unit) {
    case 'minutes':
      return parseInt(time);
    case 'hours':
      return time * 60;
    case 'days':
      return time * 60 * 24;
    default:
      return parseInt(time);
  }
}

export class ExportService {
  constructor(model) {
    this.model = model;
    this.serializedModel = model.getData()
  }

  exportPayload() {
    const payload = {};
    const triggers = ['trigger', 'before_trigger'];

    payload.triggers = {};
    payload.elements = {};
    payload.visual = {}

    for (const node of this.serializedModel.nodes) {
      payload.visual[node.id] = node.position
    }

    this.serializedModel.nodes
      .filter(node => triggers.includes(node.type))
      .map(node => (payload.triggers[node.id] = this.formatNode(node)));


    Object.entries(this.model.getNodes()).forEach(node => {
      if (!triggers.includes(node[1].type)) {
        payload.elements[node[1].id] = this.formatNode(node[1]);
      }
    });

    return payload;
  }

  getAllChildrenNodes(node, edgeName = 'right') {
    return this.serializedModel.edges
        .filter(edge => edge.source === node.id && edge.sourceHandle === edgeName)
        .map(edge => {
          const nextNode = this.serializedModel.nodes.find(n => n.id === edge.target)
          return { ...nextNode, edgeName}
        });
  }

  getPositiveAndNegativeDescendants(node) {
    const descendantsPositive = this.getAllChildrenNodes(node, 'right').map(
      descendantNode => this.formatDescendant(descendantNode, node)
    );
    const descendantsNegative = this.getAllChildrenNodes(node, 'bottom').map(
      descendantNode => this.formatDescendant(descendantNode, node)
    );
    return [...descendantsPositive, ...descendantsNegative];
  }

  formatNode(node) {
    if (node.type === 'email') {
      return {
        id: node.id,
        name: node.data.node.name || '',
        type: 'email',
        email: {
          code: node.data.node.selectedMail,
          descendants: this.getAllChildrenNodes(node).map(descendantNode =>
            this.formatDescendant(descendantNode, node)
          )
        }
      };
    } else if (node.type === 'banner') {
      return {
        id: node.id,
        name: node.data.node.name || '',
        type: 'banner',
        banner: {
          id: node.data.node.selectedBanner,
          expiresInMinutes: unitTimeToMinutes(node.data.node.expiresInTime, node.data.node.expiresInUnit),
          descendants: this.getAllChildrenNodes(node).map(descendantNode =>
            this.formatDescendant(descendantNode, node)
          )
        }
      };
    } else if (node.type === 'generic') {
      return {
        id: node.id,
        name: node.data.node.name || '',
        type: 'generic',
        generic: {
          code: node.data.node.selectedGeneric,
          options: node.data.node.options,
          descendants: this.getAllChildrenNodes(node).map(descendantNode =>
            this.formatDescendant(descendantNode, node)
          )
        }
      };
    } else if (node.type === 'condition') {
      return {
        id: node.id,
        name: node.data.node.name || '',
        type: 'condition',
        condition: {
          conditions: node.data.node.conditions,
          descendants: this.getPositiveAndNegativeDescendants(node),
        }
      };
    } else if (node.type === 'segment') {
      return {
        id: node.id,
        name: node.data.node.name || '',
        type: 'segment',
        segment: {
          code: node.data.node.selectedSegment ?? null,
          descendants: this.getPositiveAndNegativeDescendants(node),
        }
      };
    } else if (node.type === 'trigger') {
      return {
        id: node.id,
        name: node.data.node.name || '',
        type: 'event',
        event: {
          code: node.data.node.selectedTrigger ?? null
        },
        elements: this.getAllChildrenNodes(node).map(
          descendantNode => descendantNode.id
        )
      };
    } else if (node.type === 'before_trigger') {
      return {
        id: node.id,
        name: node.data.node.name || '',
        type: 'before_event',
        event: {
          code: node.data.node.selectedTrigger ?? null
        },
        elements: this.getAllChildrenNodes(node).map(
          descendantNode => descendantNode.id
        ),
        options: {
          minutes: unitTimeToMinutes(node.data.node.time, node.data.node.timeUnit)
        }
      };
    } else if (node.type === 'wait') {
      return {
        id: node.id,
        name: node.data.node.name || '',
        type: 'wait',
        wait: {
          minutes: unitTimeToMinutes(node.data.node.waitingTime, node.data.node.waitingUnit),
          descendants: this.getAllChildrenNodes(node).map(descendantNode =>
            this.formatDescendant(descendantNode, node)
          )
        }
      };
    } else if (node.type === 'goal') {
      let goalProperties = {
        codes: node.data.node.selectedGoals ? node.data.node.selectedGoals : [],
        descendants: this.getPositiveAndNegativeDescendants(node),
        recheckPeriodMinutes: unitTimeToMinutes(node.data.node.recheckPeriodTime, node.data.node.recheckPeriodUnit)
      };

      if (node.data.node.timeoutTime && node.data.node.timeoutUnit) {
        goalProperties.timeoutMinutes = unitTimeToMinutes(node.data.node.timeoutTime, node.data.node.timeoutUnit);
      }

      return {
        id: node.id,
        name: node.data.node.name || '',
        type: 'goal',
        goal: goalProperties,
      };
    } else if (node.type === 'push_notification') {
      return {
        id: node.id,
        name: node.data.node.name || '',
        type: 'push_notification',
        push_notification: {
          template: node.data.node.selectedTemplate,
          application: node.data.node.selectedApplication,
          descendants: this.getAllChildrenNodes(node).map(descendantNode =>
            this.formatDescendant(descendantNode, node)
          )
        }
      }
    } else if (node.type === 'ab_test') {
      let descendants = this.serializedModel.edges
        .filter(edge => edge.sourceHandle.startsWith('right.'))
        .flatMap(edge => this.getAllChildrenNodes(node, edge.sourceHandle)
          .map(descendantNode => this.formatDescendant(descendantNode, node)
          )
        );

      return {
        id: node.id,
        name: node.data.node.name || '',
        type: 'ab_test',
        ab_test: {
          variants: node.data.node.variants,
          descendants: descendants
        }
      }
    }
  }

  formatDescendant = (node, parentNode, index = 0) => {
    let descendant = {
      uuid: node.id
    };

    if (parentNode.type === 'segment') {
      descendant.direction = node.edgeName === 'right' ? 'positive' : 'negative';
    } else if (parentNode.type === 'goal') {
      descendant.direction = node.edgeName === 'right' ? 'positive' : 'negative';
    } else if (parentNode.type === 'condition') {
      descendant.direction = node.edgeName === 'right' ? 'positive' : 'negative';
    } else if (parentNode.type === 'ab_test') {
      descendant.direction = 'positive';
      descendant.position = node.edgeName.split('.')[1];
    }

    return descendant;
  };
}
