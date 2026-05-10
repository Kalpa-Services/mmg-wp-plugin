const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
const { createElement } = window.wp.element;
const { decodeEntities } = window.wp.htmlEntities;
const { useSelect } = window.wp.data;

const settings = window.wc.wcSettings.getPaymentMethodData("mmg_checkout") || {};

const MMGCheckoutLabel = ({ title }) => {
  return createElement(
    "span",
    { className: "mmg-checkout-label" },
    decodeEntities(title)
  );
};

const MMGCheckoutContent = () => {
  const { description, has_conversion, currency, currency_symbol, rate } = settings;

  const cartTotals = useSelect((select) => {
    return select("wc/store/cart").getCartTotals();
  }, []);

  const content = [
    createElement(
      "p",
      { className: "mmg-checkout-content", key: "desc" },
      decodeEntities(description || "")
    ),
  ];

  if (has_conversion && cartTotals && parseFloat(rate) > 0) {
    const total = parseFloat(cartTotals.total_price) / 100;
    const converted_total = Math.round(total * parseFloat(rate));
    const price_formatted = `${decodeEntities(currency_symbol || "")}${total.toFixed(2)}`;

    content.push(
      createElement(
        "div",
        {
          className: "mmg-checkout-conversion-notice",
          key: "conversion",
          style: {
            marginTop: "15px",
            padding: "15px",
            background: "#fffbeb",
            border: "1px solid #f59e0b",
            borderRadius: "8px",
            fontSize: "13px",
            color: "#92400e",
            lineHeight: "1.6",
          },
        },
        [
          createElement(
            "div",
            { style: { fontWeight: "700", marginBottom: "4px" }, key: "title" },
            "Currency Conversion"
          ),
          createElement(
            "div",
            { key: "rate" },
            `Your total of ${price_formatted} will be converted to GYD at a rate of 1 ${decodeEntities(currency || "")} = ${rate} GYD.`
          ),
          createElement(
            "div",
            {
              style: { marginTop: "8px", fontSize: "15px", fontWeight: "700", color: "#1e2a3a" },
              key: "total",
            },
            `Total to Pay: ${new Intl.NumberFormat('en-US').format(converted_total)} GYD`
          ),
        ]
      )
    );
  }

  return createElement("div", null, content);
};

registerPaymentMethod({
  name: "mmg_checkout",
  label: createElement(MMGCheckoutLabel, { title: settings.title || "MMG Checkout" }),
  content: createElement(MMGCheckoutContent),
  edit: createElement(MMGCheckoutContent),
  canMakePayment: () => true,
  ariaLabel: decodeEntities(settings.title || "MMG Checkout"),
  supports: {
    features: settings.supports || [],
  },
});
