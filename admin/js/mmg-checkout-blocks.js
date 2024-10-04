const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
const { createElement, useEffect } = window.wp.element;
const { decodeEntities } = window.wp.htmlEntities;
const { escapeHTML } = window.wp.escapeHtml;

const MMGCheckoutLabel = ({ title }) => {
  return createElement(
    "span",
    { className: "mmg-checkout-label" },
    decodeEntities(escapeHTML(title))
  );
};

const MMGCheckoutContent = ({ description }) => {
  return createElement(
    "p",
    { className: "mmg-checkout-content" },
    decodeEntities(escapeHTML(description))
  );
};

registerPaymentMethod({
  name: "mmg_checkout",
  label: createElement(MMGCheckoutLabel, { title: mmgCheckoutData.title }),
  content: createElement(MMGCheckoutContent, {
    description: mmgCheckoutData.description,
  }),
  edit: createElement(MMGCheckoutContent, {
    description: mmgCheckoutData.description,
  }),
  canMakePayment: () => mmgCheckoutData.isEnabled === "yes",
  ariaLabel: decodeEntities(mmgCheckoutData.title),
  supports: {
    features: mmgCheckoutData.supports,
  },
});
