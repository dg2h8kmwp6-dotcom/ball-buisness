import { initOrderBuilder } from './order-builder.js';
import { initBallPhotoCropper } from './ball-photo-cropper.js';

const orderBuilder = document.querySelector('[data-order-builder]');

if (orderBuilder) {
    initOrderBuilder(orderBuilder);
}

for (const form of document.querySelectorAll('[data-ball-photo-form]')) {
    initBallPhotoCropper(form);
}
