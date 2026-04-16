/**
 * Attendee QR Claim Flow (Flow B)
 *
 * 1. Attendee connects CIP-30 wallet → address extracted client-side
 * 2. Address saved to backend via bch_save_wallet
 * 3. "Claim NFT" button triggers bch_claim_mint (server-side NMKR mint)
 * 4. Success confirmation shown
 *
 * No Mesh.js dependency — uses the same minimal bech32 decoder as checkin-form.js
 */
(function () {
    'use strict';

    const cfg   = window.bchClaim || {};
    const token = cfg.claimToken || '';

    // -------------------------------------------------------------------------
    // Step 1 — Wallet connect
    // -------------------------------------------------------------------------
    document.querySelectorAll('.bch-wallet-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
            const walletName = btn.dataset.wallet;
            setStatus('bch-connect-status', `Connecting to ${capitalize(walletName)}…`);

            try {
                if (!window.cardano?.[walletName]) {
                    throw new Error(
                        `${capitalize(walletName)} wallet not found. ` +
                        `Install the browser extension and refresh.`
                    );
                }

                const api     = await window.cardano[walletName].enable();
                const address = await resolveAddress(api);

                if (!address) throw new Error('Could not read wallet address.');

                document.getElementById('bch-connected-addr').textContent = truncate(address);
                setStatus('bch-connect-status', '\u2713 Connected');

                // Save wallet address to backend
                await wpAjax('bch_save_wallet', { claim_token: token, wallet_address: address });

                // Advance to step 2
                document.getElementById('bch-step-connect').style.display = 'none';
                document.getElementById('bch-step-claim').style.display   = 'block';

            } catch (err) {
                setStatus('bch-connect-status', err.message, true);
            }
        });
    });

    // -------------------------------------------------------------------------
    // Step 2 — Claim: trigger server-side NMKR mint
    // -------------------------------------------------------------------------
    const claimBtn = document.getElementById('bch-claim-btn');
    if (claimBtn) {
        claimBtn.addEventListener('click', async () => {
            claimBtn.disabled = true;
            setStatus('bch-claim-status', 'Minting your NFT… this may take a moment.');

            try {
                const result = await wpAjax('bch_claim_mint', { claim_token: token });

                // Show success
                const mintId   = result.mint_id || '';
                const policyId = cfg.policyId   || '';

                document.getElementById('bch-claim-tx-link').href        = `${cfg.explorerBase || ''}${mintId}`;
                document.getElementById('bch-claim-tx-link').textContent = mintId || 'View on CardanoScan';

                if (policyId) {
                    document.getElementById('bch-explorer-link').href =
                        `https://preprod.cardanoscan.io/tokenPolicy/${policyId}`;
                }

                document.getElementById('bch-step-claim').style.display   = 'none';
                document.getElementById('bch-step-success').style.display = 'block';

            } catch (err) {
                const errEl = document.getElementById('bch-claim-error');
                errEl.textContent   = `Error: ${err.message}`;
                errEl.style.display = 'block';
                claimBtn.disabled   = false;
                setStatus('bch-claim-status', '');
            }
        });
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    async function resolveAddress(api) {
        try {
            let addresses = await api.getUsedAddresses();
            if (!addresses || addresses.length === 0) {
                addresses = [await api.getChangeAddress()];
            }
            const hex = addresses[0];
            if (typeof hex === 'string' && hex.startsWith('addr')) return hex;
            return hexCborToBech32(hex);
        } catch {
            return null;
        }
    }

    function hexCborToBech32(hex) {
        let bytes = hexToBytes(hex);
        if (bytes[0] === 0x41) bytes = bytes.slice(2);
        else if (bytes[0] === 0x58) bytes = bytes.slice(2);
        else if (bytes[0] === 0x59) bytes = bytes.slice(3);

        const header  = bytes[0];
        const network = (header & 0x01) === 1 ? 'addr' : 'addr_test';
        return bech32Encode(network, bytes);
    }

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

    async function wpAjax(action, data = {}) {
        const body = new FormData();
        body.append('action', action);
        body.append('nonce',  cfg.nonce);
        Object.entries(data).forEach(([k, v]) => body.append(k, v));

        const res  = await fetch(cfg.ajaxUrl, { method: 'POST', body });
        const json = await res.json();
        if (!json.success) throw new Error(json.data || 'Server error');
        return json.data;
    }

    function setStatus(id, msg, isError = false) {
        const el = document.getElementById(id);
        if (!el) return;
        el.textContent = msg;
        el.style.color = isError ? '#c00' : '#166534';
    }

    function capitalize(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    function truncate(addr, len = 20) {
        if (!addr || addr.length <= len * 2) return addr;
        return addr.slice(0, len) + '\u2026' + addr.slice(-8);
    }

})();
