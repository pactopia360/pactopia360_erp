(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var root = document.querySelector('.page-facturacion-360');
        if (!root) {
            return;
        }

        root.classList.add('fx360-js-ready');

        var accordionItems = document.querySelectorAll('.sat-clean-accordion__item');

        accordionItems.forEach(function (item) {
            item.open = false;
            item.classList.remove('is-open');

            item.addEventListener('toggle', function () {
                item.classList.toggle('is-open', item.open);
            });
        });
    });
})();