/**
 * Admin Bulk Selection & Provider Operations
 */
window.SMMAdmin = {
    toggleAll(masterCheckbox, selector = '.row-checkbox') {
        document.querySelectorAll(selector).forEach(cb => {
            cb.checked = masterCheckbox.checked;
        });
        this.updateSelectedCount(selector);
    },

    selectAll(state, selector = '.row-checkbox') {
        document.querySelectorAll(selector).forEach(cb => {
            cb.checked = state;
        });
        const master = document.getElementById('selectAllCheckbox');
        if (master) master.checked = state;
        this.updateSelectedCount(selector);
    },

    getSelectedIds(selector = '.row-checkbox') {
        return Array.from(document.querySelectorAll(`${selector}:checked`)).map(cb => Number(cb.value));
    },

    updateSelectedCount(selector = '.row-checkbox') {
        const countEl = document.getElementById('bulkSelectedCount');
        if (countEl) {
            countEl.textContent = String(this.getSelectedIds(selector).length);
        }
    }
};
