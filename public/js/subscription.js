(() => {
    const root = document.getElementById('subscription-content');
    if (!root) return;
    const authenticated = root.dataset.authenticated === '1';
    const configured = root.dataset.configured === '1';
    const find = selector => root.querySelector(selector);
    const money = amount => 'Rp' + Number(amount).toLocaleString('id-ID');
    const statusLabels = { pending: 'Menunggu pembayaran', paid: 'Lunas', failed: 'Gagal', expired: 'Kedaluwarsa', cancelled: 'Dibatalkan', refunded: 'Dikembalikan' };
    const statusMessages = { pending: 'Menunggu pembayaran atau konfirmasi Midtrans.', paid: 'Pembayaran terverifikasi. Masa paket sudah dicatat.', failed: 'Pembayaran belum berhasil.', expired: 'Batas waktu pembayaran habis. Pilih paket untuk mencoba lagi.', cancelled: 'Pembayaran dibatalkan.', refunded: 'Pembayaran telah dikembalikan.' };
    let duration = root.dataset.initialPlan;
    let tab = root.dataset.initialTab;
    let page = 1;
    let lastPage = 1;
    let busy = false;
    let loadVersion = 0;
    const checkouts = new Map();
    const modal = root.closest('[role="dialog"]').parentElement;
    const previousInert = new Map();
    let returnFocus = null;
    window.addEventListener('open-modal', event => {
        if (event.detail === 'subscription' && !root.contains(document.activeElement) && document.activeElement !== document.body) returnFocus = document.activeElement;
    });
    const syncBackground = () => {
        if (modal.style.display !== 'none') {
            for (const element of document.body.children) {
                if (element.contains(root) || ['SCRIPT', 'STYLE', 'LINK'].includes(element.tagName) || previousInert.has(element)) continue;
                previousInert.set(element, element.inert);
                element.inert = true;
            }
        } else {
            previousInert.forEach((value, element) => { element.inert = value; });
            previousInert.clear();
            if (returnFocus?.isConnected && returnFocus.getClientRects().length) returnFocus.focus();
        }
    };
    new MutationObserver(syncBackground).observe(modal, { attributes: true, attributeFilter: ['style'] });
    syncBackground();

    const feedback = (text, error = false) => {
        const element = find('[data-sub-feedback]');
        element.textContent = text;
        element.hidden = !text;
        element.dataset.error = String(error);
    };
    const request = async (url, body) => {
        const options = { credentials: 'same-origin', headers: { Accept: 'application/json' } };
        if (body !== undefined) {
            options.method = 'POST';
            options.headers['Content-Type'] = 'application/json';
            options.headers['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]').content;
            options.body = JSON.stringify(body);
        }
        const response = await fetch(url, options);
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(response.status === 401 || response.status === 419 ? 'Sesi berakhir. Masuk kembali untuk melanjutkan.' : data.message || 'Permintaan belum berhasil. Coba lagi sebentar.');
        return data;
    };
    const setDuration = value => {
        if (busy) return;
        duration = value === 'annual' ? 'annual' : 'monthly';
        root.querySelectorAll('[data-sub-duration]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.subDuration === duration)));
        const annual = duration === 'annual';
        find('[data-sub-price]').textContent = money(annual ? root.dataset.annual : root.dataset.monthly);
        find('[data-sub-unit]').textContent = annual ? '/ tahun' : '/ bulan';
        find('[data-sub-price-detail]').textContent = annual ? `Dibayar ${money(root.dataset.annual)} untuk 12 bulan.` : `Dibayar ${money(root.dataset.monthly)} untuk 1 bulan.`;
        const label = find('[data-sub-pay-label]');
        if (label) label.textContent = annual ? 'Pilih Pro Tahunan' : 'Pilih Pro Bulanan';
        const guest = find('[data-sub-guest-checkout]');
        if (guest) guest.href = annual ? root.dataset.annualLogin : root.dataset.monthlyLogin;
    };
    const setTab = value => {
        tab = authenticated && value === 'billing' ? 'billing' : 'plans';
        root.querySelectorAll('[data-sub-tab]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.subTab === tab)));
        root.querySelectorAll('[data-sub-panel]').forEach(panel => { panel.hidden = panel.dataset.subPanel !== tab; });
        find('.sub-scroll').scrollTop = 0;
    };
    const createElement = (tag, className, text) => {
        const element = document.createElement(tag);
        element.className = className;
        if (text !== undefined) element.textContent = text;
        return element;
    };
    const renderOrders = payments => {
        const list = find('[data-sub-orders]');
        list.replaceChildren();
        for (const payment of payments) {
            const article = createElement('article', 'sub-order');
            const heading = createElement('div', 'sub-order-heading');
            heading.append(createElement('h4', '', `Pro ${payment.plan === 'annual' ? 'Tahunan' : 'Bulanan'} · ${money(payment.amount)}`), createElement('span', '', statusLabels[payment.status] || payment.status));
            article.append(heading, createElement('p', '', `${payment.order_id} · ${payment.created_at}`));
            if (payment.starts_at && payment.ends_at) article.append(createElement('p', '', `Periode ${payment.starts_at} sampai ${payment.ends_at}`));
            const actions = createElement('div', 'sub-order-actions');
            const refresh = createElement('button', '', 'Cek status');
            refresh.type = 'button';
            refresh.addEventListener('click', async () => {
                if (busy) return;
                refresh.disabled = true;
                refresh.textContent = 'Memeriksa…';
                try {
                    const data = await request(payment.refresh_url, {});
                    feedback(statusMessages[data.status] || 'Status diperbarui.');
                    await loadPayments(page);
                } catch (error) {
                    feedback(error.message, true);
                    refresh.disabled = false;
                    refresh.textContent = 'Cek status';
                }
            });
            actions.append(refresh);
            if (payment.resumable && configured) {
                const resume = createElement('button', '', 'Lanjutkan pembayaran');
                resume.type = 'button';
                resume.addEventListener('click', () => beginCheckout(payment.resume_url, {}, payment.order_id));
                actions.append(resume);
            }
            article.append(actions);
            list.append(article);
        }
    };
    const loadPayments = async (requestedPage = 1) => {
        if (!authenticated) return;
        const version = ++loadVersion;
        const skeleton = find('[data-sub-skeleton]');
        skeleton.hidden = false;
        find('[data-sub-orders]').replaceChildren();
        find('[data-sub-empty]').hidden = true;
        find('[data-sub-pagination]').hidden = true;
        find('[data-sub-retry]').hidden = true;
        find('[data-sub-active]').textContent = 'Memeriksa paket…';
        try {
            const url = new URL(root.dataset.summary);
            url.searchParams.set('page', String(requestedPage));
            const data = await request(url);
            if (version !== loadVersion) return;
            page = data.pagination.current;
            lastPage = data.pagination.last;
            find('[data-sub-active]').textContent = data.active ? `Pro ${data.active.plan === 'annual' ? 'Tahunan' : 'Bulanan'} aktif sampai ${data.active.ends_at}. Perpanjangan dilakukan manual.` : 'Belum ada paket berbayar yang aktif.';
            renderOrders(data.payments);
            find('[data-sub-empty]').hidden = data.payments.length !== 0;
            find('[data-sub-pagination]').hidden = lastPage <= 1;
            find('[data-sub-page]').textContent = `${page} / ${lastPage}`;
            find('[data-sub-prev]').disabled = page <= 1;
            find('[data-sub-next]').disabled = page >= lastPage;
        } catch (error) {
            if (version !== loadVersion) return;
            find('[data-sub-active]').textContent = 'Status paket belum dapat dimuat.';
            find('[data-sub-retry]').hidden = false;
            feedback(error.message, true);
        } finally {
            if (version === loadVersion) skeleton.hidden = true;
        }
    };
    const open = (targetTab = 'plans', clearFeedback = true) => {
        setTab(targetTab);
        if (clearFeedback) feedback('');
        window.dispatchEvent(new CustomEvent('open-modal', { detail: 'subscription' }));
        if (tab === 'billing') loadPayments(page);
    };
    const setBusy = value => {
        busy = value;
        root.querySelectorAll('[data-sub-duration]').forEach(button => { button.disabled = value; });
        const button = find('[data-sub-checkout]');
        if (button) button.disabled = value || !configured;
        find('[data-sub-pay-loader]')?.classList.toggle('hidden', !value);
    };
    const beginCheckout = async (url, body, cacheKey) => {
        if (busy || !authenticated || !configured) return;
        setBusy(true);
        feedback('Membuka pembayaran…');
        try {
            if (!window.snap) throw new Error('Layanan pembayaran belum termuat. Muat ulang halaman dan coba lagi.');
            const checkout = checkouts.get(cacheKey) || await request(url, body);
            checkouts.set(cacheKey, checkout);
            // Snap owns its own modal and focus. Close our dialog before opening it.
            window.dispatchEvent(new CustomEvent('close-modal', { detail: 'subscription' }));
            const finish = async () => {
                setBusy(false);
                setTab('billing');
                window.dispatchEvent(new CustomEvent('open-modal', { detail: 'subscription' }));
                feedback('Memeriksa konfirmasi pembayaran…');
                try {
                    const data = await request(checkout.refresh_url, {});
                    feedback(statusMessages[data.status] || 'Status diperbarui.');
                    if (data.status !== 'pending') checkouts.delete(cacheKey);
                } catch (error) { feedback(error.message, true); }
                await loadPayments(1);
            };
            window.snap.pay(checkout.token, {
                onSuccess: finish, onPending: finish,
                onError: finish,
                onClose: () => {
                    setBusy(false);
                    feedback('Pembayaran belum selesai. Lanjutkan order yang sama dari riwayat.');
                    open('billing', false);
                },
            });
        } catch (error) {
            setBusy(false);
            feedback(error.message, true);
            window.dispatchEvent(new CustomEvent('open-modal', { detail: 'subscription' }));
        }
    };
    root.querySelectorAll('[data-sub-duration]').forEach(button => button.addEventListener('click', () => setDuration(button.dataset.subDuration)));
    root.querySelectorAll('[data-sub-tab]').forEach(button => button.addEventListener('click', () => { feedback(''); setTab(button.dataset.subTab); if (tab === 'billing') loadPayments(page); }));
    find('[data-sub-checkout]')?.addEventListener('click', () => beginCheckout(root.dataset.pay, { plan: duration }, duration));
    find('[data-sub-prev]')?.addEventListener('click', () => loadPayments(Math.max(1, page - 1)));
    find('[data-sub-next]')?.addEventListener('click', () => loadPayments(Math.min(lastPage, page + 1)));
    find('[data-sub-retry]')?.addEventListener('click', () => { feedback(''); loadPayments(page); });
    document.addEventListener('click', event => {
        const target = event.target.closest('[data-open-subscription], a[href]');
        if (!target) return;
        const href = target.getAttribute('href');
        const url = href ? new URL(href, window.location.href) : null;
        if (target.hasAttribute('data-open-subscription') || (url?.origin === window.location.origin && url.pathname === '/langganan')) {
            event.preventDefault();
            if (target.hasAttribute('data-open-subscription')) document.querySelector('[aria-label="Menu akun"]')?.focus();
            open(target.dataset.subscriptionTab);
        }
    });
    setDuration(duration);
    setTab(tab);
    if (new URLSearchParams(window.location.search).get('upgrade') === '1' && tab === 'billing') loadPayments(1);
})();
