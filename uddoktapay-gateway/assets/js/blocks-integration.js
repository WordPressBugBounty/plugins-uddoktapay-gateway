const uddoktapay_settings = window.wc.wcSettings.getSetting('uddoktapay_data', {});

const uddoktapay_label = window.wp.htmlEntities.decodeEntities(uddoktapay_settings.title) || 'Mobile Banking';
const UddoktaPayContent = () => {
    return window.wp.htmlEntities.decodeEntities(uddoktapay_settings.description || '');
};

const UddoktaPayBlock = {
    name: 'uddoktapay',
    label: uddoktapay_label,
    content: Object(window.wp.element.createElement)(UddoktaPayContent, null),
    edit: Object(window.wp.element.createElement)(UddoktaPayContent, null),
    canMakePayment: () => true,
    ariaLabel: uddoktapay_label,
    supports: uddoktapay_settings.supports,
};

window.wc.wcBlocksRegistry.registerPaymentMethod(UddoktaPayBlock);