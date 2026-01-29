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
    var amount = parsePrice(el.textContent);
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

  function enhanceProductPrices() {
    if (!vipdpConfig.enableProduct) {
      return;
    }
    enhanceSelector('.product-prices .current-price span[itemprop=\"price\"]');
    enhanceSelector('.product-prices .current-price span');
    enhanceSelector('.product-prices .price');
    enhanceSelector('.product-price');
    enhanceSelector('.product-miniature .price');
  }

  function enhanceCartPrices() {
    if (!vipdpConfig.enableCart) {
      return;
    }
    enhanceSelector('.cart-item .product-price');
    enhanceSelector('.cart-item .price');
    enhanceSelector('.cart-summary .cart-total .value');
    enhanceSelector('.cart-summary .cart-total .price');
    enhanceSelector('.cart-summary .cart-total .cart-value');
  }

  function enhanceCartModal() {
    if (!vipdpConfig.enableCart) {
      return;
    }
    enhanceSelector('#blockcart-modal .product-price');
    enhanceSelector('#blockcart-modal .price');
    enhanceSelector('#blockcart-modal .cart-content .value');
  }

  function enhanceOrderConfirmation() {
    if (!vipdpConfig.enableCart) {
      return;
    }
    enhanceSelector('.order-confirmation .order-confirmation-table .value');
    enhanceSelector('.order-confirmation .total-value');
    enhanceSelector('.order-confirmation .value');
  }

  function refreshAll() {
    enhanceProductPrices();
    enhanceCartPrices();
    enhanceCartModal();
    enhanceOrderConfirmation();
  }

  document.addEventListener('DOMContentLoaded', refreshAll);

  if (typeof prestashop !== 'undefined' && prestashop.on) {
    prestashop.on('updatedCart', refreshAll);
    prestashop.on('updateProduct', refreshAll);
    prestashop.on('updatedProduct', refreshAll);
  }
})();
