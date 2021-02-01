// Require main style file here for concatenation.
require( __dirname + '/../scss/payment_fields.scss' );
const handleSize = function(elem, size) {
    if (size < 600) {
        elem.classList.remove('col-wide');
        elem.classList.add('col-narrow');
    } else {
        elem.classList.remove('col-narrow');
        elem.classList.add('col-wide');
    }
};
// handleSize for resize event
const delta = 300;
let startTime;
let timeout = false;
const handleResize = function() {
    const container = document.getElementById('payment');
    const checkoutContainer = document.getElementsByClassName('payment_method_checkout_finland');
    if (new Date() - startTime < delta) {
        setTimeout(handleResize, delta)
    } else {
        timeout = false;
        handleSize(checkoutContainer[0], Math.round(container.offsetWidth));
    }
};
window.initOpcheckout = () => {
    const providerGroups = document.getElementsByClassName('provider-group');
    if (providerGroups.length === 1) {
        Array.from(providerGroups).map(providerGroup => {
            providerGroup.style = 'display: none;'
            const fields = document.getElementsByClassName('op-payment-service-woocommerce-payment-fields');
            Array.from(fields).map(field => field.classList.remove('hidden'))
        })
    } else if (providerGroups.length > 1) {
        Array.from(providerGroups).map(providerGroup => {
            providerGroup.addEventListener('click', function(e) {
                e.preventDefault();
                if (this.classList.contains('selected')) {
                    this.classList.remove('selected');
                    this.nextSibling.classList.add('hidden');
                    return;
                }
                // Clear active state
                const active = document.getElementsByClassName('provider-group selected');
                if (active.length !== 0) {
                    active[0].classList.remove('selected');
                }
                // Hide payment fields
                const fields = document.getElementsByClassName('op-payment-service-woocommerce-payment-fields');
                Array.from(fields).map(field => field.classList.add('hidden'))
                // Show current group
                this.classList.add('selected');
                this.nextSibling.classList.remove('hidden');
                // Use scrolIntoView(alignTo) method
                const closestUl = this.nextSibling.closest('ul');
                closestUl.scrollIntoView(false); // align to the bottom of the scrollable element
            });
        })
    }
    const methods = document.getElementsByClassName('op-payment-service-woocommerce-payment-fields--list-item op-payment-service-woocommerce-tokenized-payment-method');
    if (methods.length > 0) {
        Array.from(methods).map(method => {
            method.addEventListener('click', function (e) {
                e.preventDefault();
                Array.from(methods).map(method => method.classList.remove('selected'))
                let radio = this.childNodes[0].childNodes[0];
                if (typeof radio !== 'undefined') {
                    radio.checked = true;
                    this.classList.add('selected');
                }
            });
        })
    }
    handleResize()
}
window.addEventListener('resize', function() {
    startTime = new Date();
    if (timeout === false) {
        timeout = true;
        setTimeout(handleResize, delta);
    }
});