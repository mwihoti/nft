/**
 * Admin Mint Flow — Mesh.js + CIP-68 + NativeScript
 *
 * 1. Admin connects CIP-30 wallet (Eternl / Nami / Lace / Flint)
 * 2. NativeScript forging script derived from admin address (sig policy)
 * 3. Builds CIP-68 tx:
 *      - Label 100 ref NFT  → admin wallet, with inline datum
 *      - Label 222 user NFT → attendee wallet
 * 4. Admin signs → submitted via Blockfrost
 * 5. Real tx hash saved to WordPress via AJAX → status = minted
 */
(function () {
    'use strict';

    const cfg = window.bchMint || {};
    let walletApi = null;

    // ── Wallet connect buttons ────────────────────────────────────────────────
    document.querySelectorAll('.bch-wallet-btn').forEach(btn => {
        btn.addEventListener('click', () => connectWallet(btn.dataset.wallet));
    });

    async function connectWallet(name) {
        setStatus(`Connecting to ${cap(name)}…`);
        try {
            if (!window.cardano?.[name]) throw new Error(`${cap(name)} extension not found. Install it and refresh.`);
            walletApi = await window.cardano[name].enable();
            setStatus(`\u2713 ${cap(name)} connected`, false);

            // Show connected state, hide buttons
            document.getElementById('bch-wallet-btns').style.display = 'none';
            document.getElementById('bch-wallet-connected-row').style.display = 'flex';
            document.getElementById('bch-connected-wallet-label').textContent = cap(name) + ' connected';

            document.getElementById('bch-mint-btn').disabled = false;
        } catch (err) {
            setStatus(err.message, true);
        }
    }

    document.getElementById('bch-disconnect-wallet-btn')?.addEventListener('click', () => {
        walletApi = null;
        document.getElementById('bch-wallet-btns').style.display = 'flex';
        document.getElementById('bch-wallet-connected-row').style.display = 'none';
        document.getElementById('bch-mint-btn').disabled = true;
        setStatus('');
    });

    // ── Mint button ───────────────────────────────────────────────────────────
    document.getElementById('bch-mint-btn')?.addEventListener('click', doMint);

    async function doMint() {
        const btn = document.getElementById('bch-mint-btn');
        btn.disabled    = true;
        btn.textContent = 'Building transaction…';
        hideError();

        try {
            const { ForgeScript, Transaction, BlockfrostProvider } = window.MeshSDK;

            if (!ForgeScript || !Transaction) throw new Error('Mesh.js not loaded. Check your internet connection and refresh.');

            // 1. Get admin address from wallet
            const addrs   = await walletApi.getUsedAddresses();
            const addrHex = addrs.length ? addrs[0] : await walletApi.getChangeAddress();
            const adminAddr = addrHex.startsWith('addr') ? addrHex : hexCborToBech32(addrHex);

            // 2. Create NativeScript forging script (sig policy)
            const forgingScript = ForgeScript.withOneSignature(adminAddr);

            // 3. Compute CIP-68 asset names
            const baseName   = `${cfg.eventId}ATT${cfg.attendeeId}`;
            const baseHex    = strToHex(baseName);
            const refName    = '000643b0' + baseHex;  // label 100
            const userName   = '001bc280' + baseHex;  // label 222

            // 4. Build CIP-68 datum (Mesh Data format)
            const ipfsUri = cfg.bannerIpfs || 'ipfs://QmUFPkMQMiCCRMoSUCDFkJKnfv8nfCwRMh3RrDaZQiQkv4';
            const datum = {
                alternative: 0,
                fields: [
                    {
                        map: [
                            { k: { bytes: strToHex('name') },          v: { bytes: strToHex(`Attendance \u2013 ${cfg.eventTitle}`) } },
                            { k: { bytes: strToHex('image') },         v: { bytes: strToHex(ipfsUri) } },
                            { k: { bytes: strToHex('event_name') },    v: { bytes: strToHex(cfg.eventTitle) } },
                            { k: { bytes: strToHex('event_date') },    v: { bytes: strToHex(cfg.eventDate) } },
                            { k: { bytes: strToHex('venue') },         v: { bytes: strToHex(cfg.venue) } },
                            { k: { bytes: strToHex('attendee_name') }, v: { bytes: strToHex(cfg.attendeeName) } },
                            { k: { bytes: strToHex('attendee_id') },   v: { bytes: strToHex(String(cfg.attendeeId)) } },
                            { k: { bytes: strToHex('issued_by') },     v: { bytes: strToHex('Blockchain Centre Hub') } },
                        ]
                    },
                    { int: 1 }, // version
                ]
            };

            btn.textContent = 'Minting…';

            // 5. Build transaction
            const tx = new Transaction({ initiator: walletApi });

            // Ref NFT (100) → admin wallet with inline CIP-68 datum
            tx.mintAsset(forgingScript, {
                assetName: refName,
                assetQuantity: '1',
                recipient: {
                    address: adminAddr,
                    datum: { value: datum, inline: true },
                },
            });

            // User NFT (222) → attendee wallet
            tx.mintAsset(forgingScript, {
                assetName: userName,
                assetQuantity: '1',
                recipient: cfg.attendeeWallet,
            });

            btn.textContent = 'Awaiting signature…';
            const unsignedTx = await tx.build();
            const signedTx   = await walletApi.signTx(unsignedTx, true);

            btn.textContent = 'Submitting…';
            const txHash = await walletApi.submitTx(signedTx);

            // 6. Save tx hash + policy ID to WordPress
            const policyId = resolveForgeScriptPolicyId(forgingScript);
            await wpAjax('bch_save_tx_hash', {
                attendee_id: cfg.attendeeId,
                tx_hash:     txHash,
                asset_name:  userName,
                policy_id:   policyId || '',
            });

            // 7. Show success
            showSuccess(txHash);

        } catch (err) {
            showError(err.message);
            btn.disabled    = false;
            btn.textContent = 'Sign & Mint NFT';
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    function showSuccess(txHash) {
        const panel = document.getElementById('bch-mint-success');
        const link  = document.getElementById('bch-mint-tx-link');
        if (panel) panel.style.display = 'block';
        if (link)  {
            link.href        = cfg.explorerBase + txHash;
            link.textContent = txHash.slice(0, 16) + '\u2026';
        }
        document.getElementById('bch-mint-btn').textContent = '\u2713 Minted';
    }

    function showError(msg) {
        const el = document.getElementById('bch-mint-error');
        if (!el) return;
        el.textContent   = 'Error: ' + msg;
        el.style.display = 'block';
    }

    function hideError() {
        const el = document.getElementById('bch-mint-error');
        if (el) el.style.display = 'none';
    }

    function setStatus(msg, isError = false) {
        const el = document.getElementById('bch-wallet-status');
        if (!el) return;
        el.textContent = msg;
        el.style.color = isError ? '#c00' : '#166534';
    }

    async function wpAjax(action, data = {}) {
        const body = new FormData();
        body.append('action', action);
        body.append('nonce', cfg.nonce);
        Object.entries(data).forEach(([k, v]) => body.append(k, v));
        const res  = await fetch(cfg.ajaxUrl, { method: 'POST', body });
        const json = await res.json();
        if (!json.success) throw new Error(json.data || 'Server error');
        return json.data;
    }

    /** Try to extract the policy ID from a ForgeScript value */
    function resolveForgeScriptPolicyId(script) {
        try {
            // Mesh.js ForgeScript is a hex string (CBOR of native script)
            // resolveNativeScriptHash may be available
            const { resolveNativeScriptHash } = window.MeshSDK;
            if (resolveNativeScriptHash) return resolveNativeScriptHash(script);
        } catch { /* ignore */ }
        return '';
    }

    function strToHex(str) {
        return Array.from(new TextEncoder().encode(str))
            .map(b => b.toString(16).padStart(2, '0')).join('');
    }

    function cap(s) { return s.charAt(0).toUpperCase() + s.slice(1); }

    // Bech32 decoder (same as checkin-form.js) for hex CBOR addresses
    function hexCborToBech32(hex) {
        let bytes = hexToBytes(hex);
        if (bytes[0] === 0x41) bytes = bytes.slice(2);
        else if (bytes[0] === 0x58) bytes = bytes.slice(2);
        else if (bytes[0] === 0x59) bytes = bytes.slice(3);
        const network = (bytes[0] & 0x01) === 1 ? 'addr' : 'addr_test';
        return bech32Encode(network, bytes);
    }
    const CHARSET = 'qpzry9x8gf2tvdw0s3jn54khce6mua7l';
    function bech32Encode(hrp, bytes) {
        const d5 = cvBits(Array.from(bytes), 8, 5, true);
        const chk = polymod(hrpExpand(hrp).concat(d5).concat([0,0,0,0,0,0])) ^ 1;
        const cs  = [0,1,2,3,4,5].map(i => (chk >> (5*(5-i))) & 31);
        return hrp + '1' + d5.concat(cs).map(d => CHARSET[d]).join('');
    }
    function hrpExpand(hrp) { const r=[]; for(const c of hrp) r.push(c.charCodeAt(0)>>5); r.push(0); for(const c of hrp) r.push(c.charCodeAt(0)&31); return r; }
    function polymod(v) { const G=[0x3b6a57b2,0x26508e6d,0x1ea119fa,0x3d4233dd,0x2a1462b3]; let c=1; for(const x of v){const b=c>>25;c=((c&0x1ffffff)<<5)^x;for(let i=0;i<5;i++)if((b>>i)&1)c^=G[i];} return c; }
    function cvBits(data,fr,to,pad=true){let a=0,b=0;const r=[],m=(1<<to)-1;for(const v of data){a=((a<<fr)|v)&0xffffffff;b+=fr;while(b>=to){b-=to;r.push((a>>b)&m);}}if(pad&&b)r.push((a<<(to-b))&m);return r;}
    function hexToBytes(hex){const b=[];for(let i=0;i<hex.length;i+=2)b.push(parseInt(hex.slice(i,i+2),16));return b;}

})();
