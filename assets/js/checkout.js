/**
 * SalesRender Checkout JavaScript
 */
jQuery(document).ready(function($) {
  // Проверяем наличие необходимых параметров
  if (typeof sr_checkout_params === 'undefined') {
      console.error('Checkout parameters not found');
      return false;
  }

  const SRCheckout = {
      $form: $('#sr-checkout-form'),
      $submitButton: $('.sr-submit-button'),
      selectedPackage: '1x',
      priceData: {},
      
      init: function() {
          // Инициализация OPN
          if (typeof OmiseCard !== 'undefined') {
              OmiseCard.configure({
                  publicKey: sr_checkout_params.opn_public_key,
                  image: sr_checkout_params.shop_logo,
                  frameLabel: sr_checkout_params.shop_name,
                  submitLabel: sr_checkout_params.i18n.pay_button,
                  currency: sr_checkout_params.currency
              });
          }

          this.initializeEventHandlers();
          this.initializePackages();
          this.updateOrderSummary();
          this.initializeValidation();
      },

      initializeEventHandlers: function() {
          // Выбор пакета
          $('.sr-package-option').on('click', this.handlePackageSelection.bind(this));

          // Валидация при вводе
          this.$form.on('input', 'input, select, textarea', this.validateField.bind(this));

          // Форматирование телефона
          $('input[name="phone"]').on('input', this.formatPhoneNumber.bind(this));

          // Отправка формы
          this.$form.on('submit', this.handleSubmission.bind(this));

          // Переключение способа оплаты
          $('.sr-payment-method').on('click', this.handlePaymentMethodChange.bind(this));

          // Обработка выбора рассрочки
          $('#sr-installment-terms').on('change', this.updateInstallmentDetails.bind(this));
      },

      handlePaymentMethodChange: function(e) {
          const $method = $(e.currentTarget);
          const method = $method.data('method');

          $('.sr-payment-method').removeClass('active');
          $method.addClass('active');

          $('.sr-payment-method-form').removeClass('active');
          $(`#sr-${method}-form`).addClass('active');

          $('#sr-payment-method').val(method);

          // Очищаем предыдущие токены
          $('#sr-opn-token, #sr-opn-source').val('');
      },

      initializePackages: function() {
          if (sr_checkout_params.packages) {
              this.priceData = sr_checkout_params.packages;
              this.updatePackagePrices();
          }
      },

      handlePackageSelection: function(e) {
          const $package = $(e.currentTarget);
          this.selectedPackage = $package.data('package');
          
          $('.sr-package-option').removeClass('selected');
          $package.addClass('selected');
          
          this.updateOrderSummary();
      },

      validateField: function(e) {
          const $field = $(e.target);
          const value = $field.val();
          const fieldName = $field.attr('name');
          
          let isValid = true;
          let errorMessage = '';

          switch(fieldName) {
              case 'email':
                  isValid = this.validateEmail(value);
                  errorMessage = sr_checkout_params.i18n.invalid_email;
                  break;
              
              case 'phone':
                  isValid = this.validatePhone(value);
                  errorMessage = sr_checkout_params.i18n.invalid_phone;
                  break;
              
              default:
                  isValid = value.length > 0;
                  errorMessage = sr_checkout_params.i18n.required_field;
          }

          this.updateFieldValidation($field, isValid, errorMessage);
          this.updateSubmitButton();
      },

      initializeValidation: function() {
          // Добавляем правила валидации
          $.validator.addMethod('phone_format', function(value, element) {
              return this.optional(element) || /^\+?[0-9]{9,}$/.test(value.replace(/[\s-]/g, ''));
          }, sr_checkout_params.i18n.invalid_phone);

          // Инициализация валидатора формы
          this.$form.validate({
              errorClass: 'sr-field-error',
              errorElement: 'div',
              highlight: function(element) {
                  $(element).addClass('error');
              },
              unhighlight: function(element) {
                  $(element).removeClass('error');
              }
          });
      },

      validateEmail: function(email) {
          return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
      },

      validatePhone: function(phone) {
          return /^\+?[0-9]{9,}$/.test(phone.replace(/[\s-]/g, ''));
      },

      formatPhoneNumber: function(e) {
          const $field = $(e.target);
          let value = $field.val().replace(/[^0-9]/g, '');
          
          if (value.length > 3 && value.length <= 6) {
              value = value.slice(0, 3) + '-' + value.slice(3);
          } else if (value.length > 6) {
              value = value.slice(0, 3) + '-' + value.slice(3, 6) + '-' + value.slice(6, 10);
          }
          
          $field.val(value);
      },

      updateFieldValidation: function($field, isValid, errorMessage) {
          const $wrapper = $field.closest('.sr-form-group');
          const $error = $wrapper.find('.sr-field-error');
          
          if (!isValid) {
              if ($error.length === 0) {
                  $wrapper.append(`<div class="sr-field-error">${errorMessage}</div>`);
              }
              $field.addClass('error');
          } else {
              $error.remove();
              $field.removeClass('error');
          }
      },

      updateSubmitButton: function() {
          const isValid = this.$form.valid();
          this.$submitButton.prop('disabled', !isValid);
      },

      handleSubmission: function(e) {
          e.preventDefault();

          if (!this.$form.valid()) {
              return false;
          }

          this.$submitButton.prop('disabled', true)
              .text(sr_checkout_params.i18n.processing);

          const paymentMethod = $('#sr-payment-method').val();

          switch(paymentMethod) {
              case 'credit_card':
                  this.processCreditCardPayment();
                  break;

              case 'installment':
                  this.processInstallmentPayment();
                  break;

              case 'promptpay':
                  this.processPromptPayPayment();
                  break;

              default:
                  console.error('Invalid payment method');
                  this.handlePaymentError(sr_checkout_params.i18n.invalid_payment_method);
          }
      },

      processCreditCardPayment: function() {
          OmiseCard.open({
              amount: this.getCurrentPrice() * 100,
              currency: sr_checkout_params.currency,
              defaultPaymentMethod: 'credit_card',
              onCreateTokenSuccess: this.handlePaymentSuccess.bind(this),
              onFormClosed: () => {
                  this.$submitButton.prop('disabled', false)
                      .text(sr_checkout_params.i18n.pay_button);
              }
          });
      },

      processInstallmentPayment: function() {
          const terms = $('#sr-installment-terms').val();
          if (!terms) {
              this.handlePaymentError(sr_checkout_params.i18n.select_installment_terms);
              return;
          }

          $.ajax({
              url: sr_checkout_params.ajax_url,
              type: 'POST',
              data: {
                  action: 'sr_create_source',
                  nonce: sr_checkout_params.nonce,
                  amount: this.getCurrentPrice() * 100,
                  currency: sr_checkout_params.currency,
                  type: 'installment',
                  terms: terms
              },
              success: this.handleSourceCreated.bind(this),
              error: this.handlePaymentError.bind(this)
          });
      },

      processPromptPayPayment: function() {
          $.ajax({
              url: sr_checkout_params.ajax_url,
              type: 'POST',
              data: {
                  action: 'sr_create_source',
                  nonce: sr_checkout_params.nonce,
                  amount: this.getCurrentPrice() * 100,
                  currency: sr_checkout_params.currency,
                  type: 'promptpay'
              },
              success: this.handleSourceCreated.bind(this),
              error: this.handlePaymentError.bind(this)
          });
      },

      handlePaymentSuccess: function(token) {
          // Добавляем токен к форме
          $('#sr-opn-token').val(token);

          // Отправляем форму
          this.$form.off('submit').submit();
      },

      handleSourceCreated: function(response) {
          if (response.success && response.data.source) {
              $('#sr-opn-source').val(response.data.source);
              
              if (response.data.authorize_uri) {
                  // Редирект на страницу оплаты
                  window.location.href = response.data.authorize_uri;
              } else {
                  // Отправляем форму
                  this.$form.off('submit').submit();
              }
          } else {
              this.handlePaymentError(response.data.message || sr_checkout_params.i18n.payment_error);
          }
      },

      handlePaymentError: function(error) {
          this.$submitButton.prop('disabled', false)
              .text(sr_checkout_params.i18n.pay_button);

          // Показываем ошибку
          const $errorContainer = $('#sr-payment-errors');
          $errorContainer.html(`<div class="sr-error">${error}</div>`);
          
          // Прокручиваем к ошибке
          $('html, body').animate({
              scrollTop: $errorContainer.offset().top - 100
          }, 500);
      },

      getCurrentPrice: function() {
          return this.priceData[this.selectedPackage] 
              ? parseFloat(this.priceData[this.selectedPackage].price) 
              : 0;
      },

      updatePackagePrices: function() {
          Object.entries(this.priceData).forEach(([key, data]) => {
              $(`.sr-package-option[data-package="${key}"] .sr-package-price`)
                  .text(data.formatted_price);
          });
      },

      updateOrderSummary: function() {
          const packageData = this.priceData[this.selectedPackage] || {};
          
          $('.sr-order-summary').html(`
              <div class="sr-summary-item">
                  <span>${sr_checkout_params.i18n.package}</span>
                  <span>${this.selectedPackage}</span>
              </div>
              <div class="sr-summary-item">
                  <span>${sr_checkout_params.i18n.quantity}</span>
                  <span>${packageData.quantity || 0}</span>
              </div>
              <div class="sr-summary-item total">
                  <span>${sr_checkout_params.i18n.total}</span>
                  <span>${packageData.formatted_price || 0}</span>
              </div>
          `);
      },

      updateInstallmentDetails: function() {
          const terms = $('#sr-installment-terms').val();
          if (!terms) return;

          const amount = this.getCurrentPrice();
          const monthlyAmount = (amount / terms).toFixed(2);

          $('#sr-installment-details').html(`
              <div class="sr-installment-info">
                  <p>${sr_checkout_params.i18n.monthly_payment}: ${monthlyAmount} ${sr_checkout_params.currency}</p>
                  <p>${sr_checkout_params.i18n.total_amount}: ${amount} ${sr_checkout_params.currency}</p>
              </div>
          `);
      }
  };

  // Инициализация
  SRCheckout.init();
});