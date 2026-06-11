(function (global, document) {
    'use strict';

    if (!global || !document) {
        return;
    }

    var ui = global.AdlaireUI || {};
    ui.version = 'v0.284';

    ui.ready = function (callback) {
        if (typeof callback !== 'function') {
            return;
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback, { once: true });
            return;
        }

        callback();
    };

    ui.all = function (selector, scope) {
        return Array.prototype.slice.call((scope || document).querySelectorAll(selector));
    };

    ui.text = function (element) {
        return element ? String(element.textContent || '').trim() : '';
    };

    ui.emit = function (name, detail) {
        document.dispatchEvent(new CustomEvent('adlaire:' + name, { detail: detail || {} }));
    };

    global.AdlaireUI = ui;
}(window, document));
