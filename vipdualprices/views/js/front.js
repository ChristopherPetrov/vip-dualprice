/* global prestashop, vipdpConfig */
(function () {
  'use strict';

  if (typeof vipdpConfig === 'undefined' || !vipdpConfig.showSecondary) {
    return;
  }

  var primary = vipdpConfig.primary || 'BGN';
  var rate = parseFloat(vipdpConfig.rate || 0);
  var tagStyle = vipdpConfig.tagStyle || 'symbol';
  var symbols = vipdpConfig.currencySymbols || { BGN: 'лв', EUR: '€' };
  var codes = vipdpConfig.currencyCodes || { BGN: 'BGN', EUR: 'EUR' };
  var formatStyle = vipdpConfig.format || 'paren';

  function getSecondaryIso() {
    return primary === 'BGN' ? 'EUR' : 'BGN';
  }

  function parsePrice(rawText) {
    if (!rawText) {
      return null;
    }
    var cleaned = rawText.replace(/[^0-9,\\.]/g, '');
    if (!cleaned) {
      return null;
    }
    if (cleaned.indexOf(',') !== -1 && cleaned.indexOf('.') !== -1) {
      if (cleaned.lastIndexOf(',') > cleaned.lastIndexOf('.')) {
        cleaned = cleaned.replace(/\\./g, '').replace(',', '.');
      } else {
        cleaned = cleaned.replace(/,/g, '');
      }
    } else if (cleaned.indexOf(',') !== -1) {
      cleaned = cleaned.replace(',', '.');
    }
    var value = parseFloat(cleaned);
    return isNaN(value) ? null : value;
  }

  function formatAmount(amount, iso) {
    var formatted = new Intl.NumberFormat(document.documentElement.lang || 'en', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(amount);
    if (tagStyle === 'code') {
      return formatted + ' ' + (codes[iso] || iso);
    }
    var symbol = symbols[iso] || iso;
    if (iso === 'EUR') {
      return symbol + formatted;
    }
    return formatted + ' ' + symbol;
  }

  function computeSecondary(primaryAmount) {
    if (!rate || !primaryAmount) {
      return null;
    }
    if (primary === 'BGN') {
      return primaryAmount / rate;
    }
    return primaryAmount * rate;
  }

  function extractAmount(el) {
    if (!el) {
      return null;
    }
    var textAmount = parsePrice(el.textContent);
    if (textAmount) {
      return textAmount;
    }
    if (el.hasAttribute('content')) {
      var contentAmount = parsePrice(String(el.getAttribute('content')));
      if (contentAmount) {
        return contentAmount;
      }
    }
    var dataValue = el.getAttribute('data-value') || el.getAttribute('data-raw-value') || el.getAttribute('data-price');
    if (dataValue) {
      var dataAmount = parsePrice(String(dataValue));
      if (dataAmount) {
        return dataAmount;
      }
    }
    return null;
  }

  function ensureSecondary(el) {
    if (!el) {
      return;
    }
    if (el.querySelector('.vipdp-secondary')) {
      return;
    }
    if (el.nextElementSibling && el.nextElementSibling.classList.contains('vipdp-secondary')) {
      return;
    }
    if (el.parentNode && el.parentNode.querySelector('.vipdp-secondary')) {
      return;
    }
    var amount = extractAmount(el);
    if (!amount) {
      return;
    }
    var secondaryAmount = computeSecondary(amount);
    if (!secondaryAmount) {
      return;
    }
    var secondaryIso = getSecondaryIso();
    var label = formatAmount(secondaryAmount, secondaryIso);
    var span = document.createElement('span');
    span.className = 'vipdp-secondary';
    if (formatStyle === 'pipe') {
      span.textContent = '| ' + label;
      span.classList.add('vipdp-secondary--pipe');
    } else {
      span.textContent = '(' + label + ')';
    }
    el.appendChild(span);
  }

  function enhanceSelector(selector) {
    var elements = document.querySelectorAll(selector);
    if (!elements.length) {
      return;
    }
    elements.forEach(function (el) {
      ensureSecondary(el);
    });
  }

  function enhanceWithin(containerSelector, priceSelectors) {
    var container = document.querySelector(containerSelector);
    if (!container) {
      return;
    }
    var selector = priceSelectors.join(', ');
    container.querySelectorAll(selector).forEach(function (el) {
      ensureSecondary(el);
    });
  }

  function enhanceCartPrices() {
    if (!vipdpConfig.enableCart) {
      return;
    }
    enhanceWithin('.cart-items', [
      '.product-price',
      '.price',
      '.product-line-info .value',
      '.product-line-info .product-price',
    ]);
    enhanceWithin('.cart-summary', [
      '.cart-total .value',
      '.cart-total .price',
      '.cart-total .cart-value',
      '.value',
      '.amount',
    ]);
    enhanceWithin('.cart-detailed-totals', [
      '.value',
      '.price',
    ]);
    enhanceWithin('.cart-summary-products', [
      '.value',
      '.price',
    ]);
  }

  function enhanceCartModal() {
    if (!vipdpConfig.enableCart) {
      return;
    }
    enhanceWithin('#blockcart-modal', [
      '.product-price',
      '.price',
      '.cart-content .value',
      '.cart-content .amount',
    ]);
  }

  function enhanceOrderConfirmation() {
    if (!vipdpConfig.enableCart) {
      return;
    }
    enhanceWithin('.order-confirmation', [
      '.order-confirmation-table .value',
      '.order-confirmation-table .price',
      '.total-value',
      '.value',
    ]);
    enhanceWithin('.order-confirmation .cart-summary', [
      '.value',
      '.price',
    ]);
  }

  function enhanceCheckoutPaymentStep() {
    if (!vipdpConfig.enableCart) {
      return;
    }
    enhanceWithin('#checkout-payment-step', [
      '.order-summary-content .value',
      '.order-summary-content .price',
      '.order-summary-content .amount',
      '.order-confirmation-table .value',
    ]);
    enhanceWithin('#checkout-summary', [
      '.value',
      '.price',
      '.amount',
    ]);
  }

  function enhanceHeaderCart() {
    if (!vipdpConfig.enableCart) {
      return;
    }
    enhanceSelector('.blockcart .cart-price');
    enhanceSelector('.blockcart .cart-total');
    enhanceSelector('.blockcart .price');
    enhanceWithin('#_desktop_cart', [
      '.cart-price',
      '.cart-total',
      '.price',
    ]);
    enhanceWithin('#_mobile_cart', [
      '.cart-price',
      '.cart-total',
      '.price',
    ]);
  }

  function refreshAll() {
    enhanceCartPrices();
    enhanceCartModal();
    enhanceOrderConfirmation();
    enhanceCheckoutPaymentStep();
    enhanceHeaderCart();
  }

  document.addEventListener('DOMContentLoaded', refreshAll);

  if (typeof prestashop !== 'undefined' && prestashop.on) {
    prestashop.on('updatedCart', refreshAll);
    prestashop.on('updateProduct', refreshAll);
    prestashop.on('updatedProduct', refreshAll);
  }
})();
