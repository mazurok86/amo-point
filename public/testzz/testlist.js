/**
 * Dynamic field visibility for the "Тип" select on testlist.html.
 *
 * Target page: https://test.amopoint-dev.ru/testzz/testlist.html
 * Real markup: <select name="type_val"> with options 1..5, plain <input>s
 * named input_1..input_7, and <input type="button"> named button_1, button_12,
 * button_28, button_33, button_88. Each field is wrapped in a <p>.
 *
 * Algorithm:
 *   On change of <select name="type_val">, walk every <input name="..."> and
 *   show only those whose `name` attribute contains the selected value as a
 *   substring. Empty value falls back to "show all".
 *
 * Wrapper:
 *   The parent element of the field — on the target page that's the <p>
 *   that also contains the inline label text "Поле N" / "Кнопка N", so
 *   hiding the parent collapses both the input and its caption.
 *
 * Delivery: drop-in <script src="testlist.js"></script> OR paste the IIFE
 * body into DevTools Console (no globals leaked, no dependencies).
 *
 * See docs/test-task/02-dynamic-fields.md for rejected alternatives.
 */
(() => {
    'use strict';

    const SELECT_SELECTOR = 'select[name="type_val"]';
    const FIELD_SELECTOR = 'input[name]';

    const applyVisibility = (sourceSelect) => {
        const value = sourceSelect.value.trim().toLowerCase();
        for (const field of document.querySelectorAll(FIELD_SELECTOR)) {
            const visible = !value || field.name.toLowerCase().includes(value);
            field.parentElement.hidden = !visible;
        }
    };

    const init = () => {
        const select = document.querySelector(SELECT_SELECTOR);
        if (!select) {
            return;
        }
        select.addEventListener('change', () => applyVisibility(select));
        applyVisibility(select);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
