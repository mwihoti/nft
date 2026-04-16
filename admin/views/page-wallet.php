<?php defined( 'ABSPATH' ) || exit;
$saved_address = get_option( 'bch_admin_wallet_address', '' );
$saved_pkh     = get_option( 'bch_admin_pkh', '' );
$network       = get_option( 'bch_network', 'preprod' );
$explorer_base = $network === 'mainnet' ? 'https://cardanoscan.io/address/' : 'https://preprod.cardanoscan.io/address/';
?>
<div class="wrap bch-wrap">
    <h1>&#128274; Admin Wallet</h1>
    <p class="description">
        Connect your Cardano wallet to view your address and payment key hash (PKH).
        The PKH is used by the Aiken smart contract to authorise minting and metadata updates.
    </p>

    <?php if ( isset( $_GET['wallet_saved'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p>Wallet address saved.</p></div>
    <?php endif; ?>

    <!-- Currently saved -->
    <?php if ( $saved_address ) : ?>
    <div class="bch-card bch-card--info" style="margin-bottom:1.5rem">
        <h3 style="margin-top:0">&#10003; Saved Admin Wallet</h3>
        <table class="form-table" style="margin:0">
            <tr>
                <th style="width:160px">Address</th>
                <td>
                    <code class="bch-addr-long"><?php echo esc_html( $saved_address ); ?></code>
                    <a href="<?php echo esc_url( $explorer_base . $saved_address ); ?>" target="_blank" style="margin-left:.5rem">
                        View on CardanoScan &#8599;
                    </a>
                </td>
            </tr>
            <?php if ( $saved_pkh ) : ?>
            <tr>
                <th>Payment Key Hash</th>
                <td><code><?php echo esc_html( $saved_pkh ); ?></code></td>
            </tr>
            <?php endif; ?>
            <tr>
                <th>Network</th>
                <td><?php echo esc_html( ucfirst( $network ) ); ?></td>
            </tr>
        </table>
    </div>
    <?php endif; ?>

    <!-- Connect wallet card -->
    <div class="bch-card" id="bch-wallet-connect-card">
        <h2 style="margin-top:0"><?php echo $saved_address ? 'Connect a Different Wallet' : 'Connect Your Wallet'; ?></h2>
        <p>Select your browser wallet extension below. No transaction will be sent — this only reads your address.</p>

        <!-- Wallet buttons -->
        <div id="bch-admin-wallet-buttons">
            <div class="bch-wallet-grid">
                <button type="button" class="bch-wallet-btn" data-wallet="eternl">
                    <img src="<?php echo esc_url( BCH_PLUGIN_URL . 'assets/img/eternl.svg' ); ?>" alt="">
                    Eternl
                </button>
                <button type="button" class="bch-wallet-btn" data-wallet="nami">
                    <img src="<?php echo esc_url( BCH_PLUGIN_URL . 'assets/img/nami.svg' ); ?>" alt="">
                    Nami
                </button>
                <button type="button" class="bch-wallet-btn" data-wallet="lace">
                    <img src="<?php echo esc_url( BCH_PLUGIN_URL . 'assets/img/lace.svg' ); ?>" alt="">
                    Lace
                </button>
                <button type="button" class="bch-wallet-btn" data-wallet="flint">
                    <img src="<?php echo esc_url( BCH_PLUGIN_URL . 'assets/img/flint.svg' ); ?>" alt="">
                    Flint
                </button>
            </div>
            <p id="bch-connect-status" class="bch-status-msg" style="min-height:1.4em;margin-top:.75rem"></p>
        </div>

        <!-- Connected state -->
        <div id="bch-admin-connected" style="display:none">
            <div class="bch-wallet-connected-box" style="margin-bottom:1.25rem">
                <span class="bch-connected-icon">&#10003;</span>
                <div>
                    <strong id="bch-admin-wallet-name"></strong> connected
                    <div class="bch-connected-addr" id="bch-admin-addr-short"></div>
                </div>
                <button type="button" id="bch-admin-disconnect" class="bch-btn-link">Change</button>
            </div>

            <table class="form-table" style="margin-bottom:1rem">
                <tr>
                    <th style="width:160px">Full Address</th>
                    <td>
                        <code id="bch-admin-addr-full" class="bch-addr-long"></code>
                        <button type="button" class="button button-small" id="bch-copy-addr" style="margin-left:.5rem">Copy</button>
                    </td>
                </tr>
                <tr>
                    <th>Payment Key Hash</th>
                    <td>
                        <code id="bch-admin-pkh"></code>
                        <button type="button" class="button button-small" id="bch-copy-pkh" style="margin-left:.5rem">Copy</button>
                        <p class="description" style="margin-top:.25rem">
                            Use this value in the Aiken <code>admin_pkh</code> parameter and in Settings.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th>Explorer</th>
                    <td><a id="bch-explorer-addr-link" href="#" target="_blank">View on CardanoScan &#8599;</a></td>
                </tr>
            </table>

            <button type="button" id="bch-save-wallet-btn" class="button button-primary button-large">
                &#128190; Save This Wallet to Settings
            </button>
            <span id="bch-save-status" style="margin-left:1rem;color:#166534;font-weight:600"></span>
        </div>
    </div>

    <!-- Info card -->
    <div class="bch-card bch-card--info" style="margin-top:1.5rem">
        <h3 style="margin-top:0">&#9432; How This Works</h3>
        <ul>
            <li>Connect the wallet that holds your <strong>admin signing key</strong> (used to authorise NFT mints)</li>
            <li>The <strong>Payment Key Hash (PKH)</strong> is embedded in the Aiken minting policy — only this key can mint tokens</li>
            <li>Your wallet address is displayed for verification — nothing is sent or signed here</li>
            <li>On <strong><?php echo esc_html( ucfirst( $network ) ); ?></strong> — make sure your wallet is set to the correct network</li>
        </ul>
    </div>
</div>

<script>
(function () {
    const ajaxUrl   = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
    const nonce     = <?php echo wp_json_encode( wp_create_nonce( 'bch_ajax' ) ); ?>;
    const explorer  = <?php echo wp_json_encode( $explorer_base ); ?>;
    const network   = <?php echo wp_json_encode( $network ); ?>;

    const buttons   = document.getElementById('bch-admin-wallet-buttons');
    const connected = document.getElementById('bch-admin-connected');
    const statusEl  = document.getElementById('bch-connect-status');

    let currentAddress = '';
    let currentPkh     = '';

    // ── Wallet connect ──────────────────────────────────────────────────────
    document.querySelectorAll('.bch-wallet-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
            const name = btn.dataset.wallet;
            setStatus(`Connecting to ${cap(name)}…`);

            try {
                if (!window.cardano?.[name]) {
                    throw new Error(`${cap(name)} extension not found. Install it and refresh.`);
                }

                const api     = await window.cardano[name].enable();
                const address = await resolveAddress(api);

                if (!address) throw new Error('Could not read wallet address.');

                const pkh = extractPkh(address);
                showConnected(address, pkh, name);

            } catch (err) {
                setStatus(err.message, true);
            }
        });
    });

    document.getElementById('bch-admin-disconnect')?.addEventListener('click', () => {
        currentAddress = '';
        currentPkh     = '';
        connected.style.display = 'none';
        buttons.style.display   = 'block';
        setStatus('');
    });

    // ── Copy buttons ────────────────────────────────────────────────────────
    document.getElementById('bch-copy-addr')?.addEventListener('click', () => {
        navigator.clipboard.writeText(currentAddress);
        flash('bch-copy-addr', 'Copied!');
    });

    document.getElementById('bch-copy-pkh')?.addEventListener('click', () => {
        navigator.clipboard.writeText(currentPkh);
        flash('bch-copy-pkh', 'Copied!');
    });

    // ── Save wallet ─────────────────────────────────────────────────────────
    document.getElementById('bch-save-wallet-btn')?.addEventListener('click', async () => {
        const btn  = document.getElementById('bch-save-wallet-btn');
        const stat = document.getElementById('bch-save-status');
        btn.disabled  = true;
        stat.textContent = 'Saving…';

        const body = new FormData();
        body.append('action',          'bch_save_admin_wallet');
        body.append('nonce',           nonce);
        body.append('wallet_address',  currentAddress);
        body.append('admin_pkh',       currentPkh);

        try {
            const res  = await fetch(ajaxUrl, { method: 'POST', body });
            const json = await res.json();
            if (!json.success) throw new Error(json.data || 'Save failed.');
            stat.textContent = '✓ Saved!';
            setTimeout(() => { stat.textContent = ''; btn.disabled = false; }, 3000);
        } catch (err) {
            stat.style.color  = '#c00';
            stat.textContent  = err.message;
            btn.disabled      = false;
        }
    });

    // ── Helpers ─────────────────────────────────────────────────────────────
    function showConnected(address, pkh, walletName) {
        currentAddress = address;
        currentPkh     = pkh;

        document.getElementById('bch-admin-wallet-name').textContent = cap(walletName);
        document.getElementById('bch-admin-addr-short').textContent  = truncate(address);
        document.getElementById('bch-admin-addr-full').textContent   = address;
        document.getElementById('bch-admin-pkh').textContent         = pkh;

        const explorerLink = document.getElementById('bch-explorer-addr-link');
        explorerLink.href  = explorer + address;

        buttons.style.display   = 'none';
        connected.style.display = 'block';
        setStatus('');
    }

    /**
     * Extract the 56-char payment key hash from a bech32 Cardano address.
     * Enterprise address (60 bytes): header(1) + pkh(28 bytes = 56 hex chars)
     * Base address (57 bytes):       header(1) + pkh(28) + stakeKeyHash(28)
     */
    function extractPkh(address) {
        try {
            const decoded = bech32Decode(address);
            if (!decoded) return '(could not derive PKH)';
            // Skip the 1-byte header; next 28 bytes = payment key hash
            const pkhBytes = decoded.slice(1, 29);
            return bytesToHex(pkhBytes);
        } catch {
            return '(could not derive PKH)';
        }
    }

    async function resolveAddress(api) {
        try {
            let addrs = await api.getUsedAddresses();
            if (!addrs || addrs.length === 0) addrs = [await api.getChangeAddress()];
            const hex = addrs[0];
            if (typeof hex === 'string' && hex.startsWith('addr')) return hex;
            return hexCborToBech32(hex);
        } catch { return null; }
    }

    function hexCborToBech32(hex) {
        let bytes = hexToBytes(hex);
        if (bytes[0] === 0x41) bytes = bytes.slice(2);
        else if (bytes[0] === 0x58) bytes = bytes.slice(2);
        else if (bytes[0] === 0x59) bytes = bytes.slice(3);
        const network = (bytes[0] & 0x01) === 1 ? 'addr' : 'addr_test';
        return bech32Encode(network, bytes);
    }

    // Decode bech32 → raw bytes
    function bech32Decode(str) {
        const CHARSET = 'qpzry9x8gf2tvdw0s3jn54khce6mua7l';
        str = str.toLowerCase();
        const sep = str.lastIndexOf('1');
        if (sep < 1) return null;
        const hrp   = str.slice(0, sep);
        const data5 = [];
        for (let i = sep + 1; i < str.length - 6; i++) {
            const idx = CHARSET.indexOf(str[i]);
            if (idx < 0) return null;
            data5.push(idx);
        }
        return convertBits(data5, 5, 8, false);
    }

    // Minimal bech32 encoder
    const CHARSET = 'qpzry9x8gf2tvdw0s3jn54khce6mua7l';

    function bech32Encode(hrp, bytes) {
        const data5 = convertBits(Array.from(bytes), 8, 5, true);
        const chk   = bech32Polymod(hrpExpand(hrp).concat(data5).concat([0,0,0,0,0,0])) ^ 1;
        const cs    = [0,1,2,3,4,5].map(i => (chk >> (5*(5-i))) & 31);
        return hrp + '1' + data5.concat(cs).map(d => CHARSET[d]).join('');
    }

    function hrpExpand(hrp) {
        const r = [];
        for (const c of hrp) r.push(c.charCodeAt(0) >> 5);
        r.push(0);
        for (const c of hrp) r.push(c.charCodeAt(0) & 31);
        return r;
    }

    function bech32Polymod(values) {
        const GEN = [0x3b6a57b2,0x26508e6d,0x1ea119fa,0x3d4233dd,0x2a1462b3];
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
        const b = [];
        for (let i = 0; i < hex.length; i += 2) b.push(parseInt(hex.slice(i,i+2),16));
        return b;
    }

    function bytesToHex(bytes) {
        return bytes.map(b => b.toString(16).padStart(2,'0')).join('');
    }

    function setStatus(msg, isError = false) {
        statusEl.textContent = msg;
        statusEl.style.color = isError ? '#c00' : '#166534';
    }

    function cap(s) { return s.charAt(0).toUpperCase() + s.slice(1); }

    function truncate(s, n = 20) {
        return s.length <= n*2 ? s : s.slice(0,n) + '\u2026' + s.slice(-8);
    }

    function flash(id, txt) {
        const el = document.getElementById(id);
        const orig = el.textContent;
        el.textContent = txt;
        setTimeout(() => el.textContent = orig, 2000);
    }
})();
</script>
