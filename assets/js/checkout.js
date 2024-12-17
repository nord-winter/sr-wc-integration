class SRCheckout {
  constructor(config) {
    if (!config) {
      console.error("Configuration not provided");
      return;
    }

    if (!config.opn || !config.opn.public_key) {
      console.error("OPN configuration is missing");
      return;
    }

    if (!config.package_data) {
      console.error("Package data is missing");
      return;
    }

    this.config = config;
    this.form = document.getElementById("sr-checkout-form");
    if (!this.form) {
      console.error("Checkout form not found");
      return;
    }

    this.packageOptions = document.querySelectorAll(".sr-package-option");
    if (this.packageOptions.length === 0) {
      console.error("No package options found");
    }

    this.paymentMethods = document.querySelectorAll(".sr-payment-method");
    if (this.paymentMethods.length === 0) {
      console.error("No payment methods found");
    }

    this.submitButton = this.form.querySelector(".sr-submit-button");
    if (!this.submitButton) {
      console.error("Submit button not found");
    }

    this.selectedPackage = null;
    this.currentAmount = 0;
    this.cardForm = null;

    this.init();
  }

  init() {
    console.log("Initializing checkout...");
    this.initializePackages();
    this.initializePayment();
    this.initializeValidation();
    this.initializeEventHandlers();
  }

  initializePackages() {
    this.packageOptions.forEach((option) => {
      option.addEventListener("click", (e) => this.handlePackageSelection(e));
    });

    // Выбираем первый пакет по умолчанию
    if (this.packageOptions.length > 0) {
      this.packageOptions[0].click();
    }
  }

  handlePackageSelection(e) {
    const packageOption = e.currentTarget;
    const packageType = packageOption.dataset.package;

    if (!this.config.package_data || !this.config.package_data[packageType]) {
      console.error("Package data not found:", {
        packageType,
        availableData: this.config.package_data,
      });
      return;
    }

    const packageData = this.config.package_data[packageType];

    this.packageOptions.forEach((opt) => opt.classList.remove("selected"));
    packageOption.classList.add("selected");

    this.selectedPackage = {
      type: packageType,
      price: packageData.price,
      quantity: packageData.quantity,
    };

    this.currentAmount = packageData.price * 100;
    this.updateOrderSummary();
  }

  updateOrderSummary() {
    const summary = document.querySelector(".sr-order-summary");
    if (!summary || !this.selectedPackage) return;

    const formattedPrice = new Intl.NumberFormat(this.config.locale, {
      style: "currency",
      currency: this.config.currency,
    }).format(this.selectedPackage.price);

    summary.innerHTML = `
        <div class="sr-summary-item">
            <span>Package ${this.selectedPackage.type}</span>
            <span>${this.selectedPackage.quantity} units</span>
        </div>
        <div class="sr-summary-item total">
            <span>Total:</span>
            <span>${formattedPrice}</span>
        </div>
    `;
  }

  initializePayment() {
    console.log("Initializing payment...");

    if (!this.config.opn) {
      console.error("OPN configuration not found");
      return;
    }

    if (window.Omise) {
      console.log("Configuring Omise...");
      try {
        window.Omise.setPublicKey(this.config.opn.public_key);

        const cardElement = document.getElementById("card-element");
        if (cardElement) {
          this.cardForm = window.Omise.createElements({
            style: {
              base: {
                fontSize: "16px",
                color: "#32325d",
                "::placeholder": {
                  color: "#aab7c4",
                },
              },
              invalid: {
                color: "#dc3545",
              },
            },
          }).create("card");

          this.cardForm.on("change", (event) => {
            const errorElement = document.getElementById("card-errors");
            if (event.error) {
              errorElement.textContent = event.error.message;
            } else {
              errorElement.textContent = "";
            }
          });

          this.cardForm.mount(cardElement);
          console.log("Card form mounted");
        } else {
          console.error("Card element not found");
        }
      } catch (error) {
        console.error("Error configuring Omise:", error);
      }
    } else {
      console.warn("Omise not loaded");
    }

    // Initialize payment method selection
    if (this.paymentMethods.length > 0) {
      this.paymentMethods.forEach((method) => {
        method.addEventListener("click", (e) =>
          this.handlePaymentMethodChange(e)
        );
      });

      // Select first payment method by default
      this.paymentMethods[0].click();
    }
  }

  handlePaymentMethodChange(e) {
    const methodElement = e.currentTarget;
    if (
      !methodElement ||
      !methodElement.dataset ||
      !methodElement.dataset.method
    ) {
      console.error("Invalid payment method element:", methodElement);
      return;
    }

    const method = methodElement.dataset.method;
    const methodsContainer = methodElement.closest(".sr-payment-methods");

    if (methodsContainer) {
      methodsContainer.querySelectorAll(".sr-payment-method").forEach((el) => {
        el.classList.remove("active");
      });
      methodElement.classList.add("active");

      // Show corresponding form
      const forms = document.querySelectorAll(".sr-payment-form");
      forms.forEach((form) => {
        form.classList.remove("active");
      });

      const selectedForm = document.getElementById(`${method}-form`);
      if (selectedForm) {
        selectedForm.classList.add("active");
      }

      // Update hidden input
      const hiddenInput = document.getElementById("selected-payment-method");
      if (hiddenInput) {
        hiddenInput.value = method;
      }
    }

    document.getElementById("card-errors").textContent = "";
    document.getElementById("opn_token").value = "";
    document.getElementById("opn_source").value = "";
  }

  initializeValidation() {
    if (this.form && jQuery().validate) {
      jQuery.validator.addMethod(
        "minimum_amount",
        function (value, element) {
          return parseFloat(value) >= 20; // минимальная сумма для OPN
        },
        "Minimum amount is required"
      );
      jQuery(this.form).validate({
        rules: {
          first_name: "required",
          last_name: "required",
          email: {
            required: true,
            email: true,
          },
          phone: {
            required: true,
            minlength: 10,
            pattern: /^[0-9]{10}$/,
          },
          address: "required",
          city: "required",
          postcode: {
            required: true,
            pattern: /^[0-9]{5}$/,
          },
        },
        messages: this.config.i18n,
        errorPlacement: (error, element) => {
          error.addClass("sr-field-error");
          element.after(error);
        },
        highlight: (element) => {
          jQuery(element).addClass("error");
        },
        unhighlight: (element) => {
          jQuery(element).removeClass("error");
        },
      });
    }
  }

  initializeEventHandlers() {
    if (this.form) {
      this.form.addEventListener("submit", async (e) => {
        e.preventDefault();
        console.log("Form submitted");

        if (!jQuery(this.form).valid()) {
          return false;
        }

        try {
          await this.processPayment();
        } catch (error) {
          this.handleError(error);
        }
      });
    }
  }

  async processPayment() {
    if (this.currentAmount <= 0) {
      this.handleError(new Error("Invalid payment amount"));
      return;
    }

    const paymentMethod = document.getElementById(
      "selected-payment-method"
    ).value;
    this.setLoading(true);
    this.processingPayment = true; // Добавляем флаг

    // Добавляем обработчик закрытия страницы
    const handleBeforeUnload = (e) => {
      if (this.processingPayment) {
        e.preventDefault();
        e.returnValue = "Payment in progress. Are you sure you want to leave?";
        return e.returnValue;
      }
    };

    window.addEventListener("beforeunload", handleBeforeUnload);

    try {
      switch (paymentMethod) {
        case "credit_card":
          await this.processCreditCardPayment();
          break;
        case "promptpay":
          await this.processPromptPayPayment();
          break;
        default:
          throw new Error("Invalid payment method");
      }
    } catch (error) {
      this.handleError(error);
    } finally {
      this.processingPayment = false; // Сбрасываем флаг
      this.setLoading(false);
      // Удаляем обработчик после завершения
      window.removeEventListener("beforeunload", handleBeforeUnload);
    }
  }

  processCreditCardPayment() {
    return new Promise((resolve, reject) => {
      console.log("Processing credit card payment...");

      if (!this.cardForm) {
        reject(new Error("Card form not initialized"));
        return;
      }

      window.Omise.createToken("card", this.cardForm)
        .then((result) => {
          if (result.error) {
            console.error("Card error:", result.error);
            reject(new Error(result.error.message));
            return;
          }

          console.log("Token created");
          document.getElementById("opn_token").value = result.id;
          this.form.submit();
          resolve();
        })
        .catch((error) => {
          console.error("Token creation error:", error);
          reject(error);
        });
    });
  }

  processPromptPayPayment() {
    return new Promise((resolve, reject) => {
      const qrContainer = document.getElementById("sr-qr-code");
      if (!qrContainer) {
        reject(new Error("QR container not found"));
        return;
      }

      // Create PromptPay source
      fetch(this.config.ajax_url, {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: new URLSearchParams({
          action: "sr_create_promptpay_source",
          nonce: this.config.nonce,
          amount: this.currentAmount,
          currency: this.config.currency,
        }),
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success && data.qr_code) {
            // Display QR code
            qrContainer.innerHTML = "";
            new QRCode(qrContainer, {
              text: data.qr_code,
              width: 256,
              height: 256,
            });

            document.getElementById("opn_source").value = data.source_id;
            this.startPromptPayStatusCheck(data.source_id);
            resolve();
          } else {
            reject(new Error(data.message || "Failed to create PromptPay QR"));
          }
        })
        .catch(reject);
    });
  }

  startPromptPayStatusCheck(sourceId) {
    let attempts = 0;
    const maxAttempts = 20; // Максимальное количество попыток (20 * 3 секунды = 60 секунд)

    const checkStatus = () => {
      // Проверяем количество попыток
      if (attempts >= maxAttempts) {
        this.handleError(
          new Error("Payment timeout: transaction took too long")
        );
        return;
      }
      attempts++; // Увеличиваем счетчик попыток

      fetch(this.config.ajax_url, {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: new URLSearchParams({
          action: "sr_check_promptpay_status",
          nonce: this.config.nonce,
          source_id: sourceId,
        }),
      })
        .then((response) => {
          if (!response.ok) {
            throw new Error("Network response was not ok");
          }
          return response.json();
        })
        .then((data) => {
          if (data.success) {
            switch (data.status) {
              case "successful":
                this.form.submit();
                break;
              case "pending":
                // Если статус все еще pending и не превышен лимит попыток
                if (attempts < maxAttempts) {
                  setTimeout(checkStatus, 3000); // Проверяем каждые 3 секунды
                }
                break;
              case "failed":
                throw new Error("Payment was declined");
              case "expired":
                throw new Error("Payment QR code has expired");
              default:
                throw new Error(`Unknown payment status: ${data.status}`);
            }
          } else {
            throw new Error(data.message || "Failed to check payment status");
          }
        })
        .catch((error) => {
          this.handleError(error);
          // Продолжаем проверять статус при ошибках сети, если не превышен лимит
          if (error.name === "NetworkError" && attempts < maxAttempts) {
            setTimeout(checkStatus, 3000);
          }
        });
    };

    // Начинаем проверку статуса
    checkStatus();
  }

  setLoading(isLoading) {
    if (this.submitButton) {
      this.submitButton.disabled = isLoading;
    }
    if (this.form) {
      if (isLoading) {
        this.form.classList.add("sr-loading");
      } else {
        this.form.classList.remove("sr-loading");
      }
    }
  }

  handleError(error) {
    console.error("Payment error:", error);
    const errorContainer = document.getElementById("sr-payment-errors");
    if (errorContainer) {
      errorContainer.innerHTML = `
                <div class="sr-error-message">
                    ${
                      error.message ||
                      "An error occurred during payment processing"
                    }
                </div>
            `;
    }
  }
}

// Initialize on document load
jQuery(document).ready(() => {
  if (typeof sr_checkout_params !== "undefined") {
    window.srCheckout = new SRCheckout(sr_checkout_params);
  } else {
    console.error("Checkout parameters not found");
  }
});
