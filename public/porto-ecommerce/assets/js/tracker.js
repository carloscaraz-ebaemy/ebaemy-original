/**
 * EcommerceTracker — unified tracking for Meta Pixel, TikTok Pixel, and GA4
 * Usage:
 *   EcommerceTracker.viewContent({ id, name, price, currency })
 *   EcommerceTracker.addToCart({ id, name, price, currency, quantity })
 *   EcommerceTracker.initiateCheckout({ items, total, currency })
 *   EcommerceTracker.purchase({ orderId, items, total, currency })
 */
(function (window) {
    'use strict';

    var EcommerceTracker = {

        // ── Helpers ──────────────────────────────────────────────────────────

        _hasFbq: function () { return typeof window.fbq === 'function'; },
        _hasTtq: function () { return typeof window.ttq !== 'undefined' && typeof window.ttq.track === 'function'; },
        _hasGtag: function () { return typeof window.gtag === 'function'; },

        _currency: function (val) { return (val || 'PEN').toUpperCase(); },

        // ── ViewContent ──────────────────────────────────────────────────────

        viewContent: function (data) {
            var id       = String(data.id || '');
            var name     = String(data.name || '');
            var price    = parseFloat(data.price) || 0;
            var currency = this._currency(data.currency);

            if (this._hasFbq()) {
                fbq('track', 'ViewContent', {
                    content_ids:  [id],
                    content_name: name,
                    content_type: 'product',
                    value:        price,
                    currency:     currency
                });
            }

            if (this._hasTtq()) {
                ttq.track('ViewContent', {
                    content_id:   id,
                    content_name: name,
                    content_type: 'product',
                    value:        price,
                    currency:     currency
                });
            }

            if (this._hasGtag()) {
                gtag('event', 'view_item', {
                    currency: currency,
                    value:    price,
                    items: [{
                        item_id:   id,
                        item_name: name,
                        price:     price,
                        quantity:  1
                    }]
                });
            }
        },

        // ── AddToCart ────────────────────────────────────────────────────────

        addToCart: function (data) {
            var id       = String(data.id || '');
            var name     = String(data.name || '');
            var price    = parseFloat(data.price) || 0;
            var qty      = parseInt(data.quantity) || 1;
            var currency = this._currency(data.currency);
            var value    = price * qty;

            if (this._hasFbq()) {
                fbq('track', 'AddToCart', {
                    content_ids:  [id],
                    content_name: name,
                    content_type: 'product',
                    value:        value,
                    currency:     currency
                });
            }

            if (this._hasTtq()) {
                ttq.track('AddToCart', {
                    content_id:   id,
                    content_name: name,
                    content_type: 'product',
                    quantity:     qty,
                    price:        price,
                    value:        value,
                    currency:     currency
                });
            }

            if (this._hasGtag()) {
                gtag('event', 'add_to_cart', {
                    currency: currency,
                    value:    value,
                    items: [{
                        item_id:   id,
                        item_name: name,
                        price:     price,
                        quantity:  qty
                    }]
                });
            }
        },

        // ── InitiateCheckout ─────────────────────────────────────────────────

        initiateCheckout: function (data) {
            var items    = data.items || [];
            var total    = parseFloat(data.total) || 0;
            var currency = this._currency(data.currency);
            var ids      = items.map(function (i) { return String(i.id || ''); });
            var count    = items.reduce(function (acc, i) { return acc + (parseInt(i.quantity) || 1); }, 0);

            if (this._hasFbq()) {
                fbq('track', 'InitiateCheckout', {
                    content_ids:   ids,
                    content_type:  'product',
                    num_items:     count,
                    value:         total,
                    currency:      currency
                });
            }

            if (this._hasTtq()) {
                ttq.track('InitiateCheckout', {
                    value:    total,
                    currency: currency
                });
            }

            if (this._hasGtag()) {
                gtag('event', 'begin_checkout', {
                    currency: currency,
                    value:    total,
                    items:    items.map(function (i) {
                        return {
                            item_id:   String(i.id || ''),
                            item_name: String(i.name || ''),
                            price:     parseFloat(i.price) || 0,
                            quantity:  parseInt(i.quantity) || 1
                        };
                    })
                });
            }
        },

        // ── Purchase ─────────────────────────────────────────────────────────

        purchase: function (data) {
            var orderId  = String(data.orderId || '');
            var items    = data.items || [];
            var total    = parseFloat(data.total) || 0;
            var currency = this._currency(data.currency);
            var ids      = items.map(function (i) { return String(i.id || ''); });

            if (this._hasFbq()) {
                fbq('track', 'Purchase', {
                    content_ids:  ids,
                    content_type: 'product',
                    value:        total,
                    currency:     currency
                });
            }

            if (this._hasTtq()) {
                ttq.track('CompletePayment', {
                    value:    total,
                    currency: currency
                });
            }

            if (this._hasGtag()) {
                gtag('event', 'purchase', {
                    transaction_id: orderId,
                    currency:       currency,
                    value:          total,
                    items:          items.map(function (i) {
                        return {
                            item_id:   String(i.id || ''),
                            item_name: String(i.name || ''),
                            price:     parseFloat(i.price) || 0,
                            quantity:  parseInt(i.quantity) || 1
                        };
                    })
                });
            }
        }
    };

    window.EcommerceTracker = EcommerceTracker;

}(window));
