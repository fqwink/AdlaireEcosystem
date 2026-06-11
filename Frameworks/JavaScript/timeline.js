(function (global, document) {
    'use strict';

    var ui = global.AdlaireUI;
    if (!ui || !document) {
        return;
    }

    ui.timelineItems = function (scope) {
        return ui.all('[data-timeline-item]', scope).map(function (item, index) {
            return {
                index: index,
                label: ui.text(item),
                state: item.getAttribute('data-state') || 'unknown',
                time: item.getAttribute('datetime') || item.getAttribute('data-time') || ''
            };
        });
    };

    ui.markTimelineState = function (scope) {
        ui.all('[data-timeline-item]', scope).forEach(function (item) {
            var state = item.getAttribute('data-state') || 'unknown';
            item.setAttribute('data-adlaire-state', state);
        });
    };

    ui.ready(function () {
        ui.markTimelineState(document);
    });
}(window, document));
