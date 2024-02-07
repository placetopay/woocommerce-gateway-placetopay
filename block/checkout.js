const settings = window.myCustomGatewayData || {};
const label = window.wp.htmlEntities.decodeEntities(settings.title) || 'Placetopay';

const Content = () => (
    React.createElement("div", {},
        React.createElement("p", {}, settings.description || '')
    )
);

const ImageContent = () => (
    React.createElement("img", {
        src: settings.image,
        alt: label,
        style: { width: '150px', height: 'auto' }
    })
);

const Block_Gateway = {
    name: 'placetopay',
    label: label,
    content: Object(window.wp.element.createElement)(() => (
        React.createElement("div", {
                style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', width: '100%' }
            },
            React.createElement(Content, null),
            React.createElement(ImageContent, null)
        )
    )),
    edit: Object(window.wp.element.createElement)(() => (
        React.createElement("div", {
                style: { display: 'flex', justifyContent: 'space-between', alignItems: 'center', width: '100%' }
            },
            React.createElement(Content, null),
            React.createElement(ImageContent, null)
        )
    )),
    canMakePayment: () => true,
    ariaLabel: label,
};

window.wc.wcBlocksRegistry.registerPaymentMethod(Block_Gateway);
