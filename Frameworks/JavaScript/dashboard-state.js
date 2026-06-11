(function (global, document) {
    'use strict';

    var ui = global.AdlaireUI;
    if (!ui || !document) {
        return;
    }

    ui.dashboardState = function () {
        return {
            title: ui.text(document.querySelector('h1')),
            status: ui.text(document.querySelector('.badge')),
            sections: ui.all('main section').map(function (section) {
                return ui.text(section.querySelector('h2'));
            }),
            releaseGate: typeof ui.releaseGate === 'function' ? ui.releaseGate(document) : null
        };
    };

    ui.ready(function () {
        ui.emit('dashboard-state', ui.dashboardState());
    });
}(window, document));
