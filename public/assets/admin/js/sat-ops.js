document.addEventListener('DOMContentLoaded', function () {
    const interactiveCards = document.querySelectorAll('.satOpsCard:not(.is-disabled)');
    const quickActions = document.querySelectorAll('.satOpsMiniAction');

    interactiveCards.forEach(function (card) {
        card.addEventListener('mouseenter', function () {
            card.style.willChange = 'transform';
        });

        card.addEventListener('mouseleave', function () {
            card.style.willChange = 'auto';
        });
    });

    quickActions.forEach(function (item) {
        item.addEventListener('keydown', function (event) {
            if ((event.key === 'Enter' || event.key === ' ') && item.tagName.toLowerCase() === 'button') {
                event.preventDefault();
                item.click();
            }
        });
    });
});