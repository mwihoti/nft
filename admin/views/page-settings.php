<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap bch-wrap">
    <h1>BCH Attendance NFT — Settings</h1>
    <form method="post" action="options.php">
        <?php settings_fields( 'bch_settings_group' ); ?>

        <h2>Cardano Network</h2>
        <table class="form-table">
            <tr>
                <th><label for="bch_network">Network</label></th>
                <td>
                    <select id="bch_network" name="bch_network">
                        <option value="preprod" <?php selected( get_option( 'bch_network' ), 'preprod' ); ?>>Preprod Testnet</option>
                        <option value="mainnet" <?php selected( get_option( 'bch_network' ), 'mainnet' ); ?>>Mainnet</option>
                    </select>
                    <p class="description">Use <strong>Preprod</strong> for development and testing.</p>
                </td>
            </tr>
        </table>

        <h2>Blockfrost (Mesh.js Minting)</h2>
        <p>Required for the Mesh.js minting flow — provides UTXO data and transaction submission.</p>
        <table class="form-table">
            <tr>
                <th><label for="bch_blockfrost_api_key">Blockfrost API Key</label></th>
                <td>
                    <input type="password" id="bch_blockfrost_api_key" name="bch_blockfrost_api_key" class="large-text"
                        value="<?php echo esc_attr( get_option( 'bch_blockfrost_api_key', '' ) ); ?>"
                        placeholder="preprodXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX">
                    <p class="description">
                        Get a free key at <a href="https://blockfrost.io" target="_blank">blockfrost.io</a> → New Project → Cardano Preprod.
                        Key starts with <code>preprod</code> for testnet.
                    </p>
                </td>
            </tr>
        </table>

        <h2>NMKR Studio (Legacy)</h2>
        <p>NMKR handles NFT minting on Cardano — no wallet or signing key required on this server.
           Create a free account at <a href="https://studio.nmkr.io" target="_blank">studio.nmkr.io</a>.</p>
        <table class="form-table">
            <tr>
                <th><label for="bch_nmkr_api_key">NMKR API Key</label></th>
                <td>
                    <input type="password" id="bch_nmkr_api_key" name="bch_nmkr_api_key" class="large-text"
                        value="<?php echo esc_attr( get_option( 'bch_nmkr_api_key' ) ); ?>">
                    <p class="description">
                        Generate in NMKR Studio → <em>API Keys</em>. Use a <strong>Preprod</strong> key for testing,
                        <strong>Mainnet</strong> key for production. Never share this key.
                    </p>
                </td>
            </tr>
            <tr>
                <th><label for="bch_nmkr_project_uid">NMKR Project UID</label></th>
                <td>
                    <input type="text" id="bch_nmkr_project_uid" name="bch_nmkr_project_uid" class="large-text"
                        value="<?php echo esc_attr( get_option( 'bch_nmkr_project_uid' ) ); ?>"
                        placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx">
                    <p class="description">
                        Create a project in NMKR Studio → copy the <em>Project UID</em> (UUID format).
                        Set the project's token standard to <strong>CIP-68</strong> in the project settings.
                    </p>
                </td>
            </tr>
        </table>

        <h2>Cardano Policy</h2>
        <table class="form-table">
            <tr>
                <th><label for="bch_policy_id">NMKR Policy ID</label></th>
                <td>
                    <input type="text" id="bch_policy_id" name="bch_policy_id" class="large-text"
                        value="<?php echo esc_attr( get_option( 'bch_policy_id', '' ) ); ?>"
                        placeholder="64-char hex policy ID from NMKR Studio">
                    <p class="description">
                        In NMKR Studio → your project → <em>Policy</em> tab → copy the Policy ID.
                        Used to generate CardanoScan links for minted NFTs.
                    </p>
                </td>
            </tr>
        </table>

        <h2>Claim Link Settings</h2>
        <table class="form-table">
            <tr>
                <th><label for="bch_claim_expiry_hours">Claim Link Expiry (hours)</label></th>
                <td>
                    <input type="number" id="bch_claim_expiry_hours" name="bch_claim_expiry_hours" min="1" max="720"
                        value="<?php echo esc_attr( get_option( 'bch_claim_expiry_hours', 72 ) ); ?>">
                    <p class="description">How long QR claim links stay valid (default: 72 hours).</p>
                </td>
            </tr>
        </table>

        <div class="bch-card" style="margin-top:1.5rem">
            <h3>&#9432; NMKR Setup Checklist</h3>
            <ol>
                <li>Sign up at <a href="https://studio.nmkr.io" target="_blank">studio.nmkr.io</a></li>
                <li>Create a new <strong>Preprod</strong> project → set token standard to <strong>CIP-68</strong></li>
                <li>Copy the <em>Project UID</em> → paste above</li>
                <li>Go to <em>Settings → API Keys</em> → generate a Preprod key → paste above</li>
                <li>Fund the NMKR minting wallet shown in Studio with Preprod tADA (<a href="https://docs.cardano.org/cardano-testnets/tools/faucet" target="_blank">faucet</a>)</li>
                <li>Save settings → create an event → check in an attendee → click <strong>Issue NFT</strong></li>
            </ol>
        </div>

        <?php submit_button(); ?>
    </form>
</div>
