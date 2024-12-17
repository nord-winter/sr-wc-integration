/**
 * OPN Payment Integration
 */
window.SRPayment = {
    config: {},
    card: null,
    qrSource: null,
    
    init: function(config) {
        this.config = config;
        
        // Initialize OPN.js
        if (typeof OmiseCard !== 'undefined') {
            OmiseCard.configure({
                publicKey: this.config.publicKey,
                image: this.config.shopLogo,
                frameLabel: this.config.shopName,
                submitLabel: this.config.submitButtonText,
                buttonLabel: this.config.submitButtonText,
                currency: this.config.currency,
                defaultPaymentMethod: this.config.defaultPaymentMethod,
                location: this.config.location
            });
        }

        this.initializeEventHandlers();
    },

    initializeEventHandlers: function() {
        // Payment method selection
        document.querySelectorAll('.sr-payment-method').forEach(method => {
            method.addEventListener('click', this.handlePaymentMethodChange.bind(this));
        });

        // Installment terms change
        const termsSelect = document.getElementById('sr-installment-terms');
        if (termsSelect) {
            termsSelect.addEventListener('change', this.updateInstallmentDetails.bind(this));
        }
    },

    handlePaymentMethodChange: function(e) {
        const methodElement = e.currentTarget;
        const method = methodElement.dataset.method;

        // Update active states
        document.querySelectorAll('.sr-payment-method').forEach(el => {
            el.classList.remove('active');
        });
        methodElement.classList.add('active');

        // Show relevant form
        document.querySelectorAll('.sr-payment-method-form').forEach(form => {
            form.classList.remove('active');
        });
        document.getElementById(`sr-${method}-form`).classList.add('active');

        // Update hidden input
        document.getElementById('sr-payment-method').value = method;

        // Clear previous tokens/sources
        document.getElementById('sr-opn-token').value = '';
        document.getElementById('sr-opn-source').value = '';
    },

    processPayment: function(amount) {
        return new Promise((resolve, reject) => {
            const method = document.getElementById('sr-payment-method').value;

            switch (method) {
                case 'credit_card':
                    this.processCreditCardPayment(amount).then(resolve).catch(reject);
                    break;

                case 'promptpay':
                    this.processPromptPayPayment(amount).then(resolve).catch(reject);
                    break;

                case 'installment':
                    this.processInstallmentPayment(amount).then(resolve).catch(reject);
                    break;

                default:
                    reject(new Error('Invalid payment method'));
            }
        });
    },

    processCreditCardPayment: function(amount) {
        return new Promise((resolve, reject) => {
            OmiseCard.open({
                amount: amount,
                currency: this.config.currency,
                defaultPaymentMethod: 'credit_card',
                onCreateTokenSuccess: (token) => {
                    document.getElementById('sr-opn-token').value = token;
                    resolve({ type: 'token', value: token });
                },
                onFormClosed: () => {
                    reject(new Error('Payment cancelled'));
                },
                onError: (err) => {
                    reject(new Error(err.message));
                }
            });
        });
    },

    processPromptPayPayment: function(amount) {
        return new Promise((resolve, reject) => {
            fetch(this.config.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'sr_create_source',
                    nonce: this.config.nonce,
                    amount: amount,
                    currency: this.config.currency,
                    type: 'promptpay'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data.source) {
                    document.getElementById('sr-opn-source').value = data.data.source;
                    
                    // Display QR code if provided
                    if (data.data.qr) {
                        this.displayQRCode(data.data.qr);
                    }
                    
                    resolve({ type: 'source', value: data.data.source });
                } else {
                    reject(new Error(data.data.message || 'Failed to create payment source'));
                }
            })
            .catch(reject);
        });
    },

    processInstallmentPayment: function(amount) {
        return new Promise((resolve, reject) => {
            const terms = document.getElementById('sr-installment-terms').value;
            if (!terms) {
                reject(new Error('Please select installment terms'));
                return;
            }

            fetch(this.config.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'sr_create_source',
                    nonce: this.config.nonce,
                    amount: amount,
                    currency: this.config.currency,
                    type: 'installment',
                    terms: terms
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.data.source) {
                    document.getElementById('sr-opn-source').value = data.data.source;
                    
                    if (data.data.authorize_uri) {
                        // Redirect to installment provider
                        window.location.href = data.data.authorize_uri;
                    } else {
                        resolve({ type: 'source', value: data.data.source });
                    }
                } else {
                    reject(new Error(data.data.message || 'Failed to create installment'));
                }
            })
            .catch(reject);
        });
    },

    updateInstallmentDetails: function(e) {
        const terms = e.target.value;
        if (!terms) return;

        const amount = this.config.amount;
        const monthlyAmount = (amount / terms).toFixed(2);
        
        document.getElementById('sr-installment-details').innerHTML = `
            <div class="sr-installment-info">
                <p>Monthly payment: ${monthlyAmount} ${this.config.currency}</p>
                <p>Total amount: ${amount} ${this.config.currency}</p>
            </div>
        `;
    },

    displayQRCode: function(qrData) {
        const container = document.getElementById('sr-qr-code');
        if (!container) return;

        // Clear previous QR code
        container.innerHTML = '';

        // Create new QR code
        new QRCode(container, {
            text: qrData,
            width: 256,
            height: 256
        });
    },

    handleError: function(error) {
        const errorContainer = document.getElementById('sr-payment-errors');
        if (errorContainer) {
            errorContainer.innerHTML = `<div class="sr-error">${error.message}</div>`;
            errorContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
};

jQuery(document).ready(function($) {
    if (typeof srPaymentConfig !== 'undefined') {
        SRPayment.init(srPaymentConfig);
    }
});
