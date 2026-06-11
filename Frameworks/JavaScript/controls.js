(function (global, document) {
    'use strict';

    var ui = global.AdlaireUI;
    if (!ui || !document) {
        return;
    }

    function toggleTarget(button) {
        var selector = button.getAttribute('data-control-target');
        var target = selector ? document.querySelector(selector) : null;
        if (!target) {
            return;
        }

        var expanded = button.getAttribute('aria-expanded') === 'true';
        button.setAttribute('aria-expanded', expanded ? 'false' : 'true');
        target.hidden = expanded;
        ui.emit('control', {
            action: 'toggle',
            target: selector,
            expanded: !expanded
        });
    }

    ui.bindControls = function (scope) {
        ui.all('[data-control-target]', scope).forEach(function (button) {
            if (button.dataset.adlaireBound === 'control') {
                return;
            }

            button.dataset.adlaireBound = 'control';
            button.addEventListener('click', function () {
                toggleTarget(button);
            });
        });
    };

    ui.ready(function () {
        ui.bindControls(document);
    });
}(window, document));
