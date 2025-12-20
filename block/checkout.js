(function() {
    'use strict';

    let gatewayData = null;
    let gatewayId = null;
    
    const scriptSrc = document.currentScript ? document.currentScript.src : '';
    
    const clientIdMatch = scriptSrc.match(/checkout[_-]([^\.]+)\.js/);
    
    if (clientIdMatch && clientIdMatch[1]) {
        const expectedClientId = clientIdMatch[1];

        const camelCaseVarName = expectedClientId.split('-').map((word, index) => {
            if (index === 0) {
                return word.toLowerCase();
            } else {
                return word.charAt(0).toUpperCase() + word.slice(1).toLowerCase();
            }
        }).join('');

        const pascalCaseVarName = expectedClientId.split('-').map((word) => {
            return word.charAt(0).toUpperCase() + word.slice(1).toLowerCase();
        }).join('');
        
        const expectedVarName = 'gatewayData' + pascalCaseVarName;
        
        if (window[expectedVarName] && window[expectedVarName].id === expectedClientId) {
            gatewayData = window[expectedVarName];
            gatewayId = expectedClientId;
        }
    }
    
    if (!gatewayData || !gatewayId) {
        return;
    }
    
    const GLOBAL_SET_KEY = '_registeredGatewayIds_v2';
    if (!window[GLOBAL_SET_KEY]) {
        window[GLOBAL_SET_KEY] = new Set();
    }
    const registeredGatewayIds = window[GLOBAL_SET_KEY];
    
    function registerPaymentMethod(settings, gatewayId) {
        if (registeredGatewayIds.has(gatewayId)) {
            return;
        }
        
        if (window.wc && window.wc.wcBlocksRegistry && window.wc.wcBlocksRegistry.getPaymentMethods && window.wc.wcBlocksRegistry.getPaymentMethods()[gatewayId]) {
            registeredGatewayIds.add(gatewayId);
            return;
        }

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
            name: gatewayId,
            label: Object(window.wp.element.createElement)(Label),
            content: Object(window.wp.element.createElement)(() => (
                React.createElement("div", {
                        style: { display: 'flex', justifyContent: 'center', alignItems: 'center', width: '100%' }
                    },
                    React.createElement(Content, null),
                )
            )),
            edit: Object(window.wp.element.createElement)(() => (
                React.createElement("div", {
                        style: { display: 'flex', justifyContent: 'center',alignItems: 'center', width: '150%' }
                    },
                    React.createElement(Content, null),
                )
            )),
            canMakePayment: () => true,
            ariaLabel: label,
        };

        if (window.wc && window.wc.wcBlocksRegistry) {
            try {
                window.wc.wcBlocksRegistry.registerPaymentMethod(Block_Gateway);
                registeredGatewayIds.add(gatewayId);
            } catch (e) {

            }
        }
    }

    function registerThisPaymentMethod() {
        if (registeredGatewayIds.has(gatewayId)) {
            return;
        }
        
        if (window.wc && window.wc.wcBlocksRegistry && window.wc.wcBlocksRegistry.getPaymentMethods && window.wc.wcBlocksRegistry.getPaymentMethods()[gatewayId]) {
            registeredGatewayIds.add(gatewayId);
            return;
        }
        
        registerPaymentMethod(gatewayData, gatewayId);
    }

    registerThisPaymentMethod();

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', registerThisPaymentMethod);
    }
    
    if (window.wc && window.wc.wcBlocksRegistry) {
        setTimeout(registerThisPaymentMethod, 50);
    }
    
    window.addEventListener('woocommerceBlocksReady', registerThisPaymentMethod);
})();
