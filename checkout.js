// Ensure React and ReactDOM are available
const { createElement, useEffect, useRef } = window.wp.element;

// Get settings
const settings = window.wc.wcSettings.getSetting('maib_data', {});
const label = window.wp.htmlEntities.decodeEntities(settings.title) || window.wp.i18n.__('Maib Payment Gateway', 'maib');

// Helper function to inject HTML content
const injectHTML = (container, html) => {
    container.innerHTML = html;
};

// Content Component
const Content = () => {
    const description = window.wp.htmlEntities.decodeEntities(settings.description || '');
    const iconUrl = window.wp.htmlEntities.decodeEntities(settings.icon || '');

    const contentHtml = `
        ${description}
        ${iconUrl ? `<img src="${iconUrl}" alt="Payment Gateway Icon" style="display: block; margin: 10px 0 0;" />` : ''}
    `;

    // Use a reference to inject HTML after rendering
    const contentRef = useRef(null);

    useEffect(() => {
        if (contentRef.current) {
            injectHTML(contentRef.current, contentHtml);
        }
    }, [contentHtml]);

    return createElement('span', { ref: contentRef });
};

const Block_Gateway = {
    name: 'maib',
    label: label,
    content: createElement(Content),
    edit: createElement(Content),
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
        features: settings.supports,
    },
};

window.wc.wcBlocksRegistry.registerPaymentMethod(Block_Gateway);
