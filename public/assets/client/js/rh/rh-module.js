document.addEventListener('DOMContentLoaded', function () {
    const accordionTriggers = document.querySelectorAll('[data-rh-accordion-trigger]');
    const expandAllBtn = document.querySelector('[data-rh-expand="all"]');
    const collapseAllBtn = document.querySelector('[data-rh-collapse="all"]');
    const filterButtons = document.querySelectorAll('[data-rh-filter]');
    const accordionItems = document.querySelectorAll('.rh-accordion');

    function getAccordionBody(trigger) {
        const accordion = trigger.closest('.rh-accordion');
        return accordion ? accordion.querySelector('[data-rh-accordion-body]') : null;
    }

    function setAccordionState(trigger, open) {
        const body = getAccordionBody(trigger);
        trigger.classList.toggle('is-open', open);
        trigger.setAttribute('aria-expanded', open ? 'true' : 'false');

        if (body) {
            body.classList.toggle('is-open', open);
        }
    }

    function toggleAccordion(trigger, forceOpen = null) {
        const isOpen = trigger.classList.contains('is-open');
        const shouldOpen = forceOpen === null ? !isOpen : forceOpen;
        setAccordionState(trigger, shouldOpen);
    }

    accordionTriggers.forEach((trigger) => {
        trigger.addEventListener('click', function (event) {
            event.preventDefault();
            toggleAccordion(trigger);
        });
    });

    if (expandAllBtn) {
        expandAllBtn.addEventListener('click', function () {
            accordionTriggers.forEach((trigger) => setAccordionState(trigger, true));
        });
    }

    if (collapseAllBtn) {
        collapseAllBtn.addEventListener('click', function () {
            accordionTriggers.forEach((trigger) => setAccordionState(trigger, false));
        });
    }

    filterButtons.forEach((button) => {
        button.addEventListener('click', function () {
            const filter = button.getAttribute('data-rh-filter');

            filterButtons.forEach((btn) => btn.classList.remove('is-active'));
            button.classList.add('is-active');

            accordionItems.forEach((item) => {
                const group = item.getAttribute('data-rh-filter-group');
                const shouldShow = filter === 'all' || group === filter;
                item.classList.toggle('is-hidden', !shouldShow);
            });
        });
    });
});