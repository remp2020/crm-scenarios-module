import { LeftRightBottomPort } from './../Ports';

export class PortModel extends LeftRightBottomPort {
  constructor(position = 'right') {
    super(position, 'ab_test');
  }
}
