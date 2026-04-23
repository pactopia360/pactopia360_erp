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
            item.addEventListener('toggle', function () {
                if (item.open) {
                    item.classList.add('is-open');
                } else {
                    item.classList.remove('is-open');
                }
            });

            if (item.open) {
                item.classList.add('is-open');
            }
        });
    });
})();