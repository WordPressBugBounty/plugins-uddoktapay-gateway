const uddoktapay_international_settings = window.wc.wcSettings.getSetting('uddoktapayinternational_data', {});

const uddoktapay_international_label = window.wp.htmlEntities.decodeEntities(uddoktapay_international_settings.title) || 'International Payment';
const UddoktaPayInternationalContent = () => {
    return window.wp.htmlEntities.decodeEntities(uddoktapay_international_settings.description || '');
};

const UddoktaPayInternationalBlock = {
    name: 'uddoktapayinternational',
    label: uddoktapay_international_label,
    content: Object(window.wp.element.createElement)(UddoktaPayInternationalContent, null),
    edit: Object(window.wp.element.createElement)(UddoktaPayInternationalContent, null),
    canMakePayment: () => true,
    ariaLabel: uddoktapay_international_label,
    supports: uddoktapay_international_settings.supports,
};

window.wc.wcBlocksRegistry.registerPaymentMethod(UddoktaPayInternationalBlock);