jQuery(document).ready(
    function ($) {
        $(document.body).on(
            'change', 'input[name="payment_method"]', function () {
                $('body').trigger('update_checkout');
            }
        );
    }
);

const { createElement } = window.wp.element;
const { decodeEntities } = window.wp.htmlEntities;

// === UddoktaPay ===
const uddoktapay_settings = window.wc.wcSettings.getSetting('uddoktapay_data', {});
const uddoktapay_label = decodeEntities(uddoktapay_settings.title) || 'Mobile Banking';
const uddoktapay_icon = decodeEntities(uddoktapay_settings.icon) || '';
const uddoktapay_show_icon = uddoktapay_settings.show_icon ?? true;

const UddoktaPayContent = () => decodeEntities(uddoktapay_settings.description || '');

const UddoktaPayBlock = {
    name: 'uddoktapay',
    label: uddoktapay_show_icon
        ? createElement(
            'span',
            { style: { display: 'flex', alignItems: 'center', gap: '8px' } },
            createElement('span', null, uddoktapay_label),
            createElement('img', {
                src: uddoktapay_icon,
                alt: uddoktapay_label
            })
        )
        : uddoktapay_label,
    placeOrderButtonLabel: 'Pay with UddoktaPay',
    content: createElement(UddoktaPayContent, null),
    edit: createElement(UddoktaPayContent, null),
    canMakePayment: () => true,
    ariaLabel: uddoktapay_label,
    supports: uddoktapay_settings.supports || { features: [] },
};

window.wc.wcBlocksRegistry.registerPaymentMethod(UddoktaPayBlock);


// === UddoktaPay International ===
const uddoktapay_international_settings = window.wc.wcSettings.getSetting('uddoktapayinternational_data', {});
const uddoktapay_international_label = decodeEntities(uddoktapay_international_settings.title) || 'International Payment';
const uddoktapay_international_icon = decodeEntities(uddoktapay_international_settings.icon) || '';
const uddoktapay_international_show_icon = uddoktapay_international_settings.show_icon ?? true;

const UddoktaPayInternationalContent = () => decodeEntities(uddoktapay_international_settings.description || '');

const UddoktaPayInternationalBlock = {
    name: 'uddoktapayinternational',
    label: uddoktapay_international_show_icon
        ? createElement(
            'span',
            { style: { display: 'flex', alignItems: 'center', gap: '8px' } },
            createElement('span', null, uddoktapay_international_label),
            createElement('img', {
                src: uddoktapay_international_icon,
                alt: uddoktapay_international_label
            })
        )
        : uddoktapay_international_label,
    placeOrderButtonLabel: 'Pay Internationally',
    content: createElement(UddoktaPayInternationalContent, null),
    edit: createElement(UddoktaPayInternationalContent, null),
    canMakePayment: () => true,
    ariaLabel: uddoktapay_international_label,
    supports: uddoktapay_international_settings.supports || { features: [] },
};

window.wc.wcBlocksRegistry.registerPaymentMethod(UddoktaPayInternationalBlock);