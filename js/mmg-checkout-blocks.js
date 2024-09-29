const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
const { createElement, useEffect } = window.wp.element;
const { decodeEntities } = window.wp.htmlEntities;
const { escapeHTML } = window.wp.escapeHtml;

const MMGCheckoutLabel = ({ title }) => {
    return createElement('span', { className: 'mmg-checkout-label' }, decodeEntities(escapeHTML(title)));
};

const MMGCheckoutContent = ({ description }) => {
    return createElement('p', { className: 'mmg-checkout-content' }, decodeEntities(escapeHTML(description)));
};

const addCustomStyles = () => {
    const style = document.createElement('style');
    style.innerHTML = `
        .mmg-checkout-label {
            background-color: #147047;
            padding: 10px;
            border-radius: 5px;
            color: white;
            font-weight: bold;
            font-size: 16px;
        }
        .mmg-checkout-content {
            background-color: #d5c21c;
            padding: 15px;
            border-radius: 5px;
            color: black;
            font-size: 14px;
        }
    `;
    document.head.appendChild(style);
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

// Ensure custom styles are added when the document is ready.
document.addEventListener('DOMContentLoaded', addCustomStyles);
