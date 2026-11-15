/**
 * ApexSMM Panel Global Frontend Helpers
 */
window.SMM = {
    csrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    },

    async apiPost(url, payload) {
        const isFormData = payload instanceof FormData;
        const headers = {
            'X-CSRF-TOKEN': this.csrfToken(),
            'Accept': 'application/json'
        };
        if (!isFormData) {
            headers['Content-Type'] = 'application/json';
        }
        const res = await fetch(url, {
            method: 'POST',
            headers,
            body: isFormData ? payload : JSON.stringify(payload)
        });
        const data = await res.json().catch(() => ({ ok: false, error: 'Invalid server response' }));
        if (!res.ok && data.ok !== false) {
            data.ok = false;
            data.error = data.error || `HTTP ${res.status}`;
        }
        return data;
    },

    async apiGet(url) {
        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
        return res.json().catch(() => ({ ok: false, error: 'Invalid server response' }));
    },

    formatMoney(amount, symbol = '₹') {
        const n = Number(amount || 0);
        return symbol + n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },

    async copyText(text, btnEl) {
        try {
            await navigator.clipboard.writeText(text);
            if (btnEl) {
                const prev = btnEl.textContent;
                btnEl.textContent = 'Copied';
                setTimeout(() => { btnEl.textContent = prev; }, 1400);
            }
        } catch (e) {
            console.error(e);
        }
    },

    async markNotificationsRead() {
        const res = await this.apiPost('/api/notification.php', { action: 'mark_all_read' });
        if (res.ok) {
            const badge = document.getElementById('notif-badge-count');
            if (badge) badge.classList.add('hidden');
        }
    }
};
