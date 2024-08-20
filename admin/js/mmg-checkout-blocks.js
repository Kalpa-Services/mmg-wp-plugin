const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
const { createElement } = window.wp.element;
const { decodeEntities } = window.wp.htmlEntities;
const { escapeHTML } = window.wp.escapeHtml;

const MMGCheckoutLabel = ({ title }) => {
    return createElement('span', {}, decodeEntities(escapeHTML(title)));
};

const MMGCheckoutContent = ({ description }) => {
    return createElement('p', {}, decodeEntities(escapeHTML(description)));
};

const mmgCheckoutData = {
    title: 'MMG Checkout',
    description: 'Pay with MMG Checkout',
    isEnabled: 'yes',
    supports: ['refunds', 'products']
};

registerPaymentMethod({
    name: 'mmg_checkout',
    label: createElement(MMGCheckoutLabel, { title: mmgCheckoutData.title }),
    content: createElement(MMGCheckoutContent, { description: mmgCheckoutData.description }),
    edit: createElement(MMGCheckoutContent, { description: mmgCheckoutData.description }),
    canMakePayment: () => mmgCheckoutData.isEnabled === 'yes',
    ariaLabel: decodeEntities(mmgCheckoutData.title),
    supports: {
        features: mmgCheckoutData.supports,
    },
});