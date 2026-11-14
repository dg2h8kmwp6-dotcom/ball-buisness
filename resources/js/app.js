import { initOrderBuilder } from './order-builder.js';

const orderBuilder = document.querySelector('[data-order-builder]');

if (orderBuilder) {
    initOrderBuilder(orderBuilder);
}
