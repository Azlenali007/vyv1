/**
 * Live service search and filtering helper
 */
document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('liveServiceSearch');
    const categorySelect = document.getElementById('liveCategoryFilter');
    const tableBody = document.getElementById('servicesTableBody');
    if (!searchInput || !tableBody) return;

    let timer = null;
    const runFilter = async () => {
        const q = searchInput.value.trim();
        const cat = categorySelect ? categorySelect.value : '0';
        const res = await window.SMM.apiGet(`/api/service-search.php?q=${encodeURIComponent(q)}&category_id=${encodeURIComponent(cat)}`);
        if (!res.ok) return;

        if (!res.services || res.services.length === 0) {
            tableBody.innerHTML = `<tr><td colspan="6" class="px-5 py-10 text-center text-sm text-slate-500">No services match your current filter.</td></tr>`;
            return;
        }

        tableBody.innerHTML = res.services.map(s => `
            <tr class="border-b border-slate-100 table-row-hover">
                <td class="px-4 py-3.5 text-xs font-mono-code text-slate-500">#${s.id}</td>
                <td class="px-4 py-3.5">
                    <div class="font-medium text-slate-900 text-sm">${escapeHtml(s.name)}</div>
                    <div class="text-xs text-slate-500 mt-0.5">${escapeHtml(s.category_name)}</div>
                </td>
                <td class="px-4 py-3.5 text-sm font-semibold text-[#151515] tabular-nums">${escapeHtml(res.currency)}${Number(s.rate_per_1000).toFixed(2)}</td>
                <td class="px-4 py-3.5 text-xs text-slate-600 tabular-nums">${Number(s.min_qty).toLocaleString()} – ${Number(s.max_qty).toLocaleString()}</td>
                <td class="px-4 py-3.5 text-xs text-slate-500">${escapeHtml(s.average_time || '—')}</td>
                <td class="px-4 py-3.5 text-right">
                    <a href="/new-order.php?service_id=${s.id}" class="inline-flex items-center px-3 py-1.5 rounded-lg bg-[#7138D8] hover:bg-[#5E28C2] text-white text-xs font-medium whitespace-nowrap">Order</a>
                </td>
            </tr>
        `).join('');
    };

    const escapeHtml = (str) => String(str ?? '').replace(/[&<>"']/g, m => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[m]));

    searchInput.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(runFilter, 220);
    });
    if (categorySelect) {
        categorySelect.addEventListener('change', runFilter);
    }
});
