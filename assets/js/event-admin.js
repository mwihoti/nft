/**
 * Frontend Event Admin Dashboard JS
 * Handles: Issue NFT buttons, copy buttons, search/filter, add attendee form, tx hash polling.
 */
(function () {
    'use strict';

    const cfg     = window.bchEventAdmin || {};
    const ajaxUrl = cfg.ajaxUrl;
    const nonce   = cfg.nonce;
    const network = cfg.network || 'preprod';
    const explorer = network === 'mainnet'
        ? 'https://cardanoscan.io'
        : 'https://preprod.cardanoscan.io';

    // ── Issue NFT buttons ──────────────────────────────────────────────────
    document.querySelectorAll('.bch-ea-btn--mint').forEach(btn => {
        btn.addEventListener('click', () => issueNft(btn));
    });

    async function issueNft(btn) {
        btn.disabled    = true;
        btn.textContent = 'Minting…';

        const body = new FormData();
        body.append('action',      'bch_issue_nft');
        body.append('nonce',       nonce);
        body.append('attendee_id', btn.dataset.attendeeId);

        try {
            const res  = await fetch(ajaxUrl, { method: 'POST', body });
            const json = await res.json();
            if (!json.success) throw new Error(json.data || 'Mint failed.');

            // Update row status badge
            const row   = btn.closest('tr');
            const badge = row?.querySelector('.bch-status');
            if (badge) {
                badge.className   = 'bch-status bch-status--minted';
                badge.textContent = 'minted';
            }
            // Update tx cell
            const txCell = row?.querySelector('.bch-ea-tx');
            if (txCell) {
                txCell.innerHTML = '<span style="color:#166534;font-size:.8rem">Confirming…</span>';
            }

            btn.textContent      = '✓ Issued';
            btn.style.background = '#059669';
            showResult(`✓ NFT issued — NMKR Mint ID: <strong>${json.data.mint_id}</strong>. Token will appear in the wallet once confirmed.`, false);

        } catch (err) {
            btn.disabled    = false;
            btn.textContent = '🎉 Issue NFT';
            showResult('Error: ' + err.message, true);
        }
    }

    // ── Copy buttons ───────────────────────────────────────────────────────
    document.querySelectorAll('.bch-ea-copy').forEach(btn => {
        btn.addEventListener('click', () => {
            navigator.clipboard.writeText(btn.dataset.copy).then(() => {
                const orig = btn.textContent;
                btn.textContent = '✓';
                setTimeout(() => btn.textContent = orig, 1500);
            });
        });
    });

    // ── Search & filter ────────────────────────────────────────────────────
    const searchInput = document.getElementById('bch-ea-search');
    const filterSel   = document.getElementById('bch-ea-filter');

    function applyFilter() {
        const q      = (searchInput?.value || '').toLowerCase();
        const status = filterSel?.value || '';

        document.querySelectorAll('.bch-ea-row').forEach(row => {
            const nameMatch   = (row.dataset.name  || '').includes(q);
            const emailMatch  = (row.dataset.email || '').includes(q);
            const statusMatch = !status || row.dataset.status === status;
            row.style.display = (nameMatch || emailMatch) && statusMatch ? '' : 'none';
        });
    }

    searchInput?.addEventListener('input', applyFilter);
    filterSel?.addEventListener('change', applyFilter);

    // ── Add attendee form ──────────────────────────────────────────────────
    const addToggle = document.getElementById('bch-ea-add-toggle');
    const addForm   = document.getElementById('bch-ea-add-form');
    const addCancel = document.getElementById('bch-ea-add-cancel');
    const addSubmit = document.getElementById('bch-ea-add-submit');
    const addError  = document.getElementById('bch-ea-add-error');

    addToggle?.addEventListener('click', () => {
        addForm.style.display = 'block';
        addToggle.style.display = 'none';
        document.getElementById('bch-ea-add-name')?.focus();
    });

    addCancel?.addEventListener('click', () => {
        addForm.style.display   = 'none';
        addToggle.style.display = 'inline-block';
        addError.style.display  = 'none';
    });

    addSubmit?.addEventListener('click', async () => {
        addError.style.display = 'none';
        const name    = document.getElementById('bch-ea-add-name')?.value.trim();
        const email   = document.getElementById('bch-ea-add-email')?.value.trim();
        const wallet  = document.getElementById('bch-ea-add-wallet')?.value.trim();
        const eventId = document.getElementById('bch-ea-add-event')?.value;

        if (!name) { showAddError('Name is required.'); return; }

        addSubmit.disabled    = true;
        addSubmit.textContent = 'Saving…';

        const body = new FormData();
        body.append('action',         'bch_admin_add_attendee');
        body.append('nonce',          nonce);
        body.append('event_id',       eventId);
        body.append('name',           name);
        body.append('email',          email);
        body.append('wallet_address', wallet);

        try {
            const res  = await fetch(ajaxUrl, { method: 'POST', body });
            const json = await res.json();
            if (!json.success) throw new Error(json.data || 'Failed to add attendee.');

            // Reload page to show new row
            window.location.reload();

        } catch (err) {
            showAddError(err.message);
            addSubmit.disabled    = false;
            addSubmit.textContent = 'Save';
        }
    });

    function showAddError(msg) {
        addError.textContent   = msg;
        addError.style.display = 'block';
    }

    // ── Poll for real tx hash on confirming rows ───────────────────────────
    document.querySelectorAll('.bch-ea-tx-polling').forEach(el => {
        const attendeeId = el.dataset.attendeeId;
        let attempts = 0;
        const MAX = 5; // ~30s — NMKR Preprod rarely returns tx hash

        const poll = async () => {
            const body = new FormData();
            body.append('action',      'bch_refresh_tx_hash');
            body.append('nonce',       nonce);
            body.append('attendee_id', attendeeId);

            try {
                const res  = await fetch(ajaxUrl, { method: 'POST', body });
                const json = await res.json();

                if (json.success && json.data.confirmed) {
                    const tx = json.data.tx_hash;
                    el.outerHTML = `<a href="${explorer}/transaction/${tx}" target="_blank"
                        class="bch-ea-tx-link" style="font-size:.8rem">
                        ${tx.slice(0, 8)}… ↗
                    </a>`;
                    return;
                }
            } catch { /* ignore */ }

            if (++attempts < MAX) {
                setTimeout(poll, 6000);
            } else {
                // NMKR Preprod doesn't return tx hashes — Policy link above is enough
                el.remove();
            }
        };

        setTimeout(poll, 6000);
    });

    // ── Global result banner ───────────────────────────────────────────────
    function showResult(html, isError = false) {
        const el = document.getElementById('bch-ea-result');
        if (!el) return;
        el.style.display = 'block';
        el.innerHTML = `<div class="bch-alert bch-alert--${isError ? 'error' : 'success'}">${html}</div>`;
        el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        if (!isError) setTimeout(() => el.style.display = 'none', 6000);
    }

})();
