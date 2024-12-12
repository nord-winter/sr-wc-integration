import { scriptUrl } from './constants';

const SR_WC_Integration = require('./SR_WC_Integration');

function createForm() {
  const form = document.createElement('form');
  form.setAttribute('id', 'checkout-form');
  // ...
  return form;
}

function processPayment(form) {
  try {
    const formData = new FormData(form);
    fetch(scriptUrl, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded'
      },
      body: formData
    })
    .then(response => response.json())
    .then(data => console.log(data))
    .catch(error => console.error('Error:', error));
  } catch (error) {
    console.error('Error processing payment:', error);
  }
}

document.addEventListener('DOMContentLoaded', () => {
  const form = createForm();
  form.addEventListener('submit', event => {
    processPayment(form);
    event.preventDefault();
  });
});