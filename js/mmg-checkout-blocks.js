const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
const { createElement } = window.wp.element;
const { decodeEntities } = window.wp.htmlEntities;

const MMGCheckoutLabel = ({ title }) => {
    return createElement('span', {}, decodeEntities(title));
};

const MMGCheckoutContent = ({ description }) => {
    return createElement('p', {}, decodeEntities(description));
};

registerPaymentMethod({
    name: 'mmg_checkout',
    label: createElement(MMGCheckoutLabel, { title: mmgCheckoutData.title }),
    content: createElement(MMGCheckoutContent, { description: mmgCheckoutData.description }),
    edit: createElement(MMGCheckoutContent, { description: mmgCheckoutData.description }),
    canMakePayment: () => mmgCheckoutData.enabled === 'yes',
    ariaLabel: decodeEntities(mmgCheckoutData.title),
    supports: {
        features: mmgCheckoutData.supports,
    },
});