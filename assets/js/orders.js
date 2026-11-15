/**
 * Order placement & live status sync handler
 */
window.SMMOrders = {
    async checkStatus(orderId, btn) {
        if (!btn) return;
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = 'Syncing...';
        try {
            const res = await window.SMM.apiGet(`/api/order-status.php?order_id=${encodeURIComponent(orderId)}`);
            if (res.ok) {
                window.location.reload();
            } else {
                alert(res.error || 'Could not sync status from provider.');
            }
        } finally {
            btn.disabled = false;
            btn.innerHTML = orig;
        }
    }
};
