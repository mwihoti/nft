<?php defined( 'ABSPATH' ) || exit;
if ( ! $attendee || ! $event ) {
    echo '<div class="wrap"><p>Attendee or event not found.</p></div>';
    return;
}

$base_name  = $event->id . 'ATT' . $attendee->id;
$base_hex   = bin2hex( $base_name );
$ref_name   = '000643b0' . $base_hex;
$user_name  = '001bc280' . $base_hex;
$network    = get_option( 'bch_network', 'preprod' );
$explorer   = $network === 'mainnet' ? 'https://cardanoscan.io' : 'https://preprod.cardanoscan.io';
$has_bf_key = (bool) get_option( 'bch_blockfrost_api_key', '' );
$has_adm_pkh = (bool) get_option( 'bch_admin_pkh', '' );
?>
<div class="wrap bch-wrap">
    <h1>Issue NFT — <?php echo esc_html( $attendee->name ); ?></h1>
    <p>
        <strong>Event:</strong> <?php echo esc_html( $event->title ); ?> &nbsp;|&nbsp;
        <strong>Date:</strong> <?php echo esc_html( $event->event_date ); ?>
        &nbsp;&mdash;&nbsp;
        <a href="<?php echo esc_url( admin_url( "admin.php?page=bch-events&bch_action=detail&event_id={$event->id}" ) ); ?>">&larr; Back</a>
    </p>

    <?php if ( $attendee->nft_status === 'minted' ) : ?>
        <div class="notice notice-success">
            <p>&#10003; NFT already minted.
                <?php
                $tx = $attendee->tx_hash ?? '';
                if ( strlen( $tx ) === 64 && ctype_xdigit( $tx ) ) :
                ?>
                    <a href="<?php echo esc_url( "{$explorer}/transaction/{$tx}" ); ?>" target="_blank">
                        View transaction &#8599;
                    </a>
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>

    <?php if ( ! $has_bf_key || ! $has_adm_pkh ) : ?>
        <div class="notice notice-warning">
            <p>
                <?php if ( ! $has_bf_key ) : ?>
                    &#9888; <strong>Blockfrost API key not set.</strong>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=bch-settings' ) ); ?>">Add it in Settings &rarr;</a><br>
                <?php endif; ?>
                <?php if ( ! $has_adm_pkh ) : ?>
                    &#9888; <strong>Admin PKH not saved.</strong>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=bch-wallet' ) ); ?>">Connect your wallet to save it &rarr;</a>
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>

    <!-- NFT preview -->
    <div class="bch-card">
        <h2 style="margin-top:0">NFT Details</h2>
        <table class="form-table">
            <tr><th>Display Name</th><td><strong><?php echo esc_html( "Attendance \u{2013} {$event->title}" ); ?></strong></td></tr>
            <tr><th>Attendee</th><td><?php echo esc_html( $attendee->name ); ?></td></tr>
            <tr><th>Recipient Wallet</th><td><code><?php echo esc_html( $attendee->wallet_address ); ?></code></td></tr>
            <tr><th>Ref NFT asset name</th><td><code><?php echo esc_html( $ref_name ); ?></code></td></tr>
            <tr><th>User NFT asset name</th><td><code><?php echo esc_html( $user_name ); ?></code></td></tr>
            <tr><th>Event Date</th><td><?php echo esc_html( date( 'Y-m-d', strtotime( $event->event_date ) ) ); ?></td></tr>
            <tr><th>Venue</th><td><?php echo esc_html( $event->venue ); ?></td></tr>
            <tr><th>Network</th><td><?php echo esc_html( ucfirst( $network ) ); ?></td></tr>
        </table>
    </div>

    <!-- Mint card -->
    <div class="bch-card" id="bch-mint-card">
        <h2 style="margin-top:0">&#127381; Mint with Mesh.js</h2>
        <p>Connect your admin Cardano wallet to sign and submit the minting transaction directly on-chain. No third-party service required.</p>

        <?php if ( $attendee->nft_status !== 'minted' ) : ?>

        <!-- Step 1: Wallet connect -->
        <div id="bch-wallet-btns" style="margin-bottom:1rem">
            <p style="margin-bottom:.5rem;font-weight:600">Step 1 — Connect your admin wallet:</p>
            <div class="bch-wallet-grid">
                <button type="button" class="bch-wallet-btn" data-wallet="eternl">
                    <img src="<?php echo esc_url( BCH_PLUGIN_URL . 'assets/img/eternl.svg' ); ?>" alt=""> Eternl
                </button>
                <button type="button" class="bch-wallet-btn" data-wallet="nami">
                    <img src="<?php echo esc_url( BCH_PLUGIN_URL . 'assets/img/nami.svg' ); ?>" alt=""> Nami
                </button>
                <button type="button" class="bch-wallet-btn" data-wallet="lace">
                    <img src="<?php echo esc_url( BCH_PLUGIN_URL . 'assets/img/lace.svg' ); ?>" alt=""> Lace
                </button>
                <button type="button" class="bch-wallet-btn" data-wallet="flint">
                    <img src="<?php echo esc_url( BCH_PLUGIN_URL . 'assets/img/flint.svg' ); ?>" alt=""> Flint
                </button>
            </div>
            <p id="bch-wallet-status" style="min-height:1.4em;margin-top:.5rem;font-weight:500"></p>
        </div>

        <!-- Connected state -->
        <div id="bch-wallet-connected-row" style="display:none;align-items:center;gap:1rem;margin-bottom:1rem;background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:.75rem 1rem">
            <span style="width:28px;height:28px;line-height:28px;background:#22c55e;color:#fff;border-radius:50%;text-align:center;font-weight:700;flex-shrink:0">&#10003;</span>
            <strong id="bch-connected-wallet-label"></strong>
            <button type="button" id="bch-disconnect-wallet-btn" style="margin-left:auto;background:none;border:none;color:#6b7280;cursor:pointer;text-decoration:underline;font-size:.85rem">Change</button>
        </div>

        <!-- Step 2: Mint -->
        <p style="font-weight:600">Step 2 — Sign and mint:</p>
        <p class="description" style="margin-bottom:.75rem">
            This will mint two tokens in one transaction:
            a <strong>reference NFT</strong> (label 100, held in your wallet with CIP-68 datum)
            and a <strong>user NFT</strong> (label 222, sent to <?php echo esc_html( $attendee->name ); ?>'s wallet).
        </p>

        <button id="bch-mint-btn" class="button button-primary button-hero" disabled>
            &#9997; Sign &amp; Mint NFT
        </button>

        <div id="bch-mint-error" class="notice notice-error" style="display:none;margin-top:1rem"></div>

        <div id="bch-mint-success" style="display:none;margin-top:1.5rem" class="bch-card bch-card--success">
            <h3 style="margin-top:0">&#10003; NFT Minted Successfully!</h3>
            <p>
                <strong>Transaction:</strong>
                <a id="bch-mint-tx-link" href="#" target="_blank"></a>
            </p>
            <p class="description">The NFT will appear in <?php echo esc_html( $attendee->name ); ?>'s wallet once confirmed (~20 seconds on Preprod).</p>
        </div>

        <?php else : ?>
            <p>&#10003; This NFT has already been minted.</p>
        <?php endif; ?>
    </div>
</div>
