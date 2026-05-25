import './bootstrap';

import Alpine from 'alpinejs';
import TomSelect from 'tom-select';
import 'tom-select/dist/css/tom-select.default.min.css';

window.Alpine = Alpine;
window.TomSelect = TomSelect;

/**
 * x-ts — wrap any <select> with Tom Select for searchable dropdown.
 *
 * Usage:
 *   <select x-ts>                          — defaults only
 *   <select x-ts="{ placeholder: '...' }"> — with overrides
 *   <select x-ts="{ options: [...] }">     — JS-provided options (skips DOM options)
 *
 * Cleanup is automatic when element is removed (x-if / x-for).
 */
Alpine.directive('ts', (el, { expression }, { evaluate, cleanup }) => {
    const overrides = expression ? evaluate(expression) : {};
    const ts = new TomSelect(el, {
        allowEmptyOption: true,
        ...overrides,
    });
    el._ts = ts;
    cleanup(() => { delete el._ts; ts.destroy(); });
});

Alpine.start();
