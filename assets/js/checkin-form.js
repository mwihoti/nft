/**
 * Public event check-in form.
 * Supports CIP-30 wallet connect (Eternl, Nami, Lace, Flint)
 * with a manual address fallback.
 */
(function () {
    'use strict';

    const cfg     = window.bchCheckin || {};
    const form    = document.getElementById('bch-checkin-form');
    const errorEl = document.getElementById('bch-checkin-error');
    const success = document.getElementById('bch-checkin-success');
    const submitBtn  = document.getElementById('bch-checkin-submit');
    const submitHint = document.getElementById('bch-submit-hint');

    // Wallet address hidden input (what gets submitted)
    const walletInput      = document.getElementById('bch-wallet');
    const connectedBox     = document.getElementById('bch-wallet-connected');
    const connectSection   = document.getElementById('bch-wallet-connect-section');
    const connectedName    = document.getElementById('bch-connected-wallet-name');
    const connectedDisplay = document.getElementById('bch-connected-addr-display');
    const statusMsg        = document.getElementById('bch-wallet-connect-status');
    const disconnectBtn    = document.getElementById('bch-disconnect-wallet');
    const toggleManualBtn  = document.getElementById('bch-toggle-manual');
    const manualSection    = document.getElementById('bch-manual-wallet');
    const manualInput      = document.getElementById('bch-wallet-manual-input');

    if (!form) return;

    // -------------------------------------------------------------------------
    // Wallet connect (CIP-30)
    // -------------------------------------------------------------------------

    document.querySelectorAll('.bch-wallet-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
            const name = btn.dataset.wallet;
            setStatus(`Connecting to ${capitalize(name)}...`);

            try {
                if (!window.cardano?.[name]) {
                    throw new Error(
                        `${capitalize(name)} wallet not found. ` +
                        `Install the browser extension and refresh this page.`
                    );
                }

                const api      = await window.cardano[name].enable();
                const address  = await resolveAddress(api);

                if (!address) throw new Error('Could not read wallet address.');

                setWalletAddress(address, name);

            } catch (err) {
                setStatus(err.message, true);
            }
        });
    });

    disconnectBtn?.addEventListener('click', () => {
        clearWalletAddress();
    });

    // -------------------------------------------------------------------------
    // Manual entry fallback
    // -------------------------------------------------------------------------

    toggleManualBtn?.addEventListener('click', () => {
        const isShown = manualSection.style.display !== 'none';
        manualSection.style.display = isShown ? 'none' : 'block';
        toggleManualBtn.textContent = isShown
            ? '✏ Enter address manually instead'
            : '✕ Hide manual entry';
    });

    manualInput?.addEventListener('input', () => {
        const val = manualInput.value.trim();
        if (val.startsWith('addr')) {
            walletInput.value = val;
            enableSubmit();
        } else {
            walletInput.value = '';
            disableSubmit();
        }
    });

    // -------------------------------------------------------------------------
    // Form submit
    // -------------------------------------------------------------------------

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        errorEl.style.display = 'none';

        const wallet = walletInput.value.trim();
        if (!wallet || !wallet.startsWith('addr')) {
            showError('Please connect your wallet or enter a valid Cardano address.');
            return;
        }

        submitBtn.disabled    = true;
        submitBtn.textContent = 'Submitting…';

        const data = new FormData(form);
        data.append('action', 'bch_public_checkin');
        data.append('nonce',  cfg.nonce);

        try {
            const res  = await fetch(cfg.ajaxUrl, { method: 'POST', body: data });
            const json = await res.json();

            if (json.success) {
                document.getElementById('bch-success-message').textContent = json.data.message;
                form.style.display    = 'none';
                success.style.display = 'block';
                success.scrollIntoView({ behavior: 'smooth', block: 'center' });
            } else {
                showError(json.data || 'Something went wrong. Please try again.');
                submitBtn.disabled    = false;
                submitBtn.textContent = '📋 Check In & Request NFT';
            }
        } catch {
            showError('Network error. Please check your connection and try again.');
            submitBtn.disabled    = false;
            submitBtn.textContent = '📋 Check In & Request NFT';
        }
    });

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    function setWalletAddress(address, walletName) {
        walletInput.value = address;

        // Show connected box, hide buttons
        connectedName.textContent    = capitalize(walletName);
        connectedDisplay.textContent = truncate(address, 24);
        connectedBox.style.display   = 'flex';

        // Hide the wallet buttons row
        document.querySelector('.bch-wallet-grid--checkin').style.display = 'none';

        setStatus('');
        enableSubmit();
    }

    function clearWalletAddress() {
        walletInput.value = '';
        connectedBox.style.display = 'none';
        document.querySelector('.bch-wallet-grid--checkin').style.display = 'flex';
        manualSection.style.display = 'none';
        manualInput.value = '';
        setStatus('');
        disableSubmit();
    }

    function enableSubmit() {
        submitBtn.disabled        = false;
        submitHint.style.display  = 'none';
    }

    function disableSubmit() {
        submitBtn.disabled        = true;
        submitHint.style.display  = 'block';
    }

    function setStatus(msg, isError = false) {
        if (!statusMsg) return;
        statusMsg.textContent = msg;
        statusMsg.style.color = isError ? '#c00' : '#166534';
    }

    function showError(msg) {
        errorEl.textContent   = msg;
        errorEl.style.display = 'block';
        errorEl.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function capitalize(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    function truncate(str, len) {
        if (str.length <= len * 2) return str;
        return str.slice(0, len) + '…' + str.slice(-8);
    }

    /**
     * Extract bech32 address from a CIP-30 wallet API.
     * CIP-30 returns addresses as hex-encoded CBOR bytes.
     */
    async function resolveAddress(api) {
        try {
            // Try used addresses first, fall back to change address
            let addresses = await api.getUsedAddresses();
            if (!addresses || addresses.length === 0) {
                addresses = [await api.getChangeAddress()];
            }
            const hex = addresses[0];

            // If it looks like a bech32 address already, return as-is
            if (typeof hex === 'string' && hex.startsWith('addr')) return hex;

            // Decode hex CBOR → bech32 using our minimal decoder
            return hexCborToBech32(hex);
        } catch {
            return null;
        }
    }

    /**
     * Decode a Cardano address from hex CBOR bytes to bech32.
     * Supports mainnet (addr1) and testnet (addr_test1) enterprise addresses.
     */
    function hexCborToBech32(hex) {
        // Strip CBOR list wrapper if present (starts with 41/58/59)
        let bytes = hexToBytes(hex);

        // CBOR byte string: 0x41 = 1-byte string, 0x58 = 2-byte len, 0x59 = 3-byte len
        if (bytes[0] === 0x41) bytes = bytes.slice(2);
        else if (bytes[0] === 0x58) bytes = bytes.slice(2);
        else if (bytes[0] === 0x59) bytes = bytes.slice(3);
        // else assume raw address bytes

        const header  = bytes[0];
        const isTest  = (header & 0x0f) === 0;  // low nibble 0 = mainnet, 1-... = testnet
        // Actually: bit 0 of header = network (0=testnet, 1=mainnet for enterprise)
        const network = (header & 0x01) === 1 ? 'addr' : 'addr_test';

        return bech32Encode(network, bytes);
    }

    // ── Minimal bech32 encoder ──────────────────────────────────────────────
    const CHARSET = 'qpzry9x8gf2tvdw0s3jn54khce6mua7l';

    function bech32Encode(hrp, bytes) {
        const data5 = convertBits(Array.from(bytes), 8, 5, true);
        const chk   = bech32Polymod(hrpExpand(hrp).concat(data5).concat([0,0,0,0,0,0])) ^ 1;
        const checksum = [0,1,2,3,4,5].map(i => (chk >> (5 * (5 - i))) & 31);
        return hrp + '1' + data5.concat(checksum).map(d => CHARSET[d]).join('');
    }

    function hrpExpand(hrp) {
        const r = [];
        for (const c of hrp) r.push(c.charCodeAt(0) >> 5);
        r.push(0);
        for (const c of hrp) r.push(c.charCodeAt(0) & 31);
        return r;
    }

    function bech32Polymod(values) {
        const GEN = [0x3b6a57b2, 0x26508e6d, 0x1ea119fa, 0x3d4233dd, 0x2a1462b3];
        let chk = 1;
        for (const v of values) {
            const b = chk >> 25;
            chk = ((chk & 0x1ffffff) << 5) ^ v;
            for (let i = 0; i < 5; i++) if ((b >> i) & 1) chk ^= GEN[i];
        }
        return chk;
    }

    function convertBits(data, from, to, pad = true) {
        let acc = 0, bits = 0;
        const result = [], maxv = (1 << to) - 1;
        for (const val of data) {
            acc = ((acc << from) | val) & 0xffffffff;
            bits += from;
            while (bits >= to) { bits -= to; result.push((acc >> bits) & maxv); }
        }
        if (pad && bits) result.push((acc << (to - bits)) & maxv);
        return result;
    }

    function hexToBytes(hex) {
        const bytes = [];
        for (let i = 0; i < hex.length; i += 2) bytes.push(parseInt(hex.slice(i, i + 2), 16));
        return bytes;
    }

})();
