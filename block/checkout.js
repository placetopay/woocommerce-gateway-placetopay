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

const Label = () => {
    const title = label
    const imagePath = settings.image;

    const paymentImage = React.createElement("img", {
        src: imagePath,
        alt: `${title} logo`,
        style: {
            width: '100px',
            height: 'auto',
            maxWidth: '100%',
            maxHeight: '100%'
        }
    });

    return React.createElement("div", {
            style: {
                display: 'flex',
                justifyContent: 'space-between',
                alignItems: 'center',
                width: '100%'
            }
        },
        React.createElement("span", {}, title),
        paymentImage
    );
};


const Block_Gateway = {
    name: 'placetopay',
    label: Object(window.wp.element.createElement)(Label),
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
