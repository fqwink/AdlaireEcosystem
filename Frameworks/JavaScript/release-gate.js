(function (global, document) {
    'use strict';

    var ui = global.AdlaireUI;
    if (!ui || !document) {
        return;
    }

    function passed(value) {
        return ['ok', 'pass', 'passed', 'ready', 'true'].indexOf(String(value || '').toLowerCase()) !== -1;
    }

    ui.releaseGate = function (scope) {
        var checks = ui.all('[data-release-check]', scope).map(function (item) {
            var state = item.getAttribute('data-state') || ui.text(item);
            return {
                name: item.getAttribute('data-release-check') || ui.text(item),
                state: state,
                passed: passed(state)
            };
        });

        return {
            ready: checks.length > 0 && checks.every(function (check) {
                return check.passed;
            }),
            checks: checks
        };
    };

    ui.ready(function () {
        ui.emit('release-gate', ui.releaseGate(document));
    });
}(window, document));
