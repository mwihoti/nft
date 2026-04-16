<?php defined( 'ABSPATH' ) || exit; ?>
<div class="wrap bch-wrap">
    <h1><?php echo esc_html( $event->title ?? 'Event' ); ?></h1>
    <p>
        <strong>Date:</strong> <?php echo esc_html( $event->event_date ); ?> &nbsp;|&nbsp;
        <strong>Venue:</strong> <?php echo esc_html( $event->venue ); ?>
        &nbsp;&mdash;&nbsp;
        <a href="<?php echo esc_url( admin_url( "admin.php?page=bch-events&bch_action=edit&event_id={$event->id}" ) ); ?>">Edit Event</a>
        &nbsp;|&nbsp;
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=bch-events' ) ); ?>">&larr; All Events</a>
    </p>

    <?php foreach ( [ 'checked_in' => 'Attendee checked in.', 'qr_generated' => 'QR claim link generated.', 'updated' => 'Event updated.' ] as $key => $msg ) : ?>
        <?php if ( isset( $_GET[ $key ] ) ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $msg ); ?></p></div>
        <?php endif; ?>
    <?php endforeach; ?>

    <!-- Check-in form -->
    <h2>Check In Attendee</h2>
    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <?php wp_nonce_field( 'bch_checkin' ); ?>
        <input type="hidden" name="action" value="bch_checkin">
        <input type="hidden" name="event_id" value="<?php echo (int) $event->id; ?>">
        <table class="form-table">
            <tr>
                <th><label for="ci_name">Full Name *</label></th>
                <td><input type="text" id="ci_name" name="name" class="regular-text" required></td>
            </tr>
            <tr>
                <th><label for="ci_email">Email</label></th>
                <td><input type="email" id="ci_email" name="email" class="regular-text"></td>
            </tr>
            <tr>
                <th><label for="ci_wallet">Cardano Wallet Address</label></th>
                <td>
                    <input type="text" id="ci_wallet" name="wallet_address" class="large-text" placeholder="addr1...">
                    <p class="description">Optional at check-in — can be filled later via QR claim.</p>
                </td>
            </tr>
        </table>
        <?php submit_button( 'Check In', 'primary', 'submit', false ); ?>
    </form>

    <hr>

    <!-- Attendee table -->
    <h2>Attendees (<?php echo count( $attendees ); ?>)</h2>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Wallet Address</th>
                <th>Check-in</th>
                <th>NFT Status</th>
                <th>Tx Hash</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if ( empty( $attendees ) ) : ?>
            <tr><td colspan="7">No attendees checked in yet.</td></tr>
        <?php else : ?>
            <?php foreach ( $attendees as $att ) : ?>
            <tr>
                <td><?php echo esc_html( $att->name ); ?></td>
                <td><?php echo esc_html( $att->email ); ?></td>
                <td class="bch-addr"><?php echo esc_html( $att->wallet_address ?: '—' ); ?></td>
                <td><?php echo esc_html( $att->check_in_time ); ?></td>
                <td>
                    <span class="bch-status bch-status--<?php echo esc_attr( $att->nft_status ); ?>">
                        <?php echo esc_html( $att->nft_status ); ?>
                    </span>
                </td>
                <td>
                    <?php
                    $tx        = $att->tx_hash ?? '';
                    $policy_id = get_option( 'bch_policy_id', '' );
                    $nft_uid   = $att->asset_name_hex ?? '';

                    if ( strlen( $tx ) === 64 && ctype_xdigit( $tx ) ) :
                        // Real Cardano tx hash — link to transaction
                    ?>
                        <a href="https://preprod.cardanoscan.io/transaction/<?php echo esc_attr( $tx ); ?>" target="_blank">
                            <?php echo esc_html( substr( $tx, 0, 8 ) . '...' ); ?>
                        </a>
                    <?php elseif ( $att->nft_status === 'minted' && ! empty( $policy_id ) ) :
                        // Link to the token policy on CardanoScan — visible as soon as minted
                        $token_name    = 'BCH' . (int) $att->event_id . 'ATT' . (int) $att->id;
                        $user_asset_id = $policy_id . '001bc280' . bin2hex( $token_name );
                    ?>
                        <a href="https://preprod.cardanoscan.io/tokenPolicy/<?php echo esc_attr( $policy_id ); ?>" target="_blank" title="View all tokens for this policy">
                            Policy ↗
                        </a>
                        &nbsp;
                        <span class="bch-tx-pending"
                              data-attendee-id="<?php echo (int) $att->id; ?>"
                              data-nonce="<?php echo esc_attr( wp_create_nonce( 'bch_ajax' ) ); ?>"
                              style="font-size:.8em;color:#888">
                            (checking tx…)
                        </span>
                    <?php else : ?>
                        —
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ( $att->nft_status === 'pending' ) : ?>
                        <?php if ( $att->wallet_address ) : ?>
                            <a class="button button-primary" href="<?php echo esc_url( admin_url( "admin.php?page=bch-events&bch_action=issue_nft&attendee_id={$att->id}" ) ); ?>">Issue NFT</a>
                        <?php endif; ?>
                        &nbsp;
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
                            <?php wp_nonce_field( 'bch_gen_qr' ); ?>
                            <input type="hidden" name="action" value="bch_gen_qr">
                            <input type="hidden" name="attendee_id" value="<?php echo (int) $att->id; ?>">
                            <button type="submit" class="button">Generate QR Link</button>
                        </form>
                    <?php elseif ( $att->nft_status === 'claim_ready' ) : ?>
                        <?php $claim_url = BCH_Claim_Token::claim_url( $att->claim_token ); ?>
                        <a href="<?php echo esc_url( $claim_url ); ?>" target="_blank" class="button">View Claim Link</a>
                        <button class="button bch-copy-btn" data-copy="<?php echo esc_attr( $claim_url ); ?>">Copy</button>
                    <?php else : ?>
                        <span class="bch-minted">&#10003; Minted</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
document.querySelectorAll('.bch-copy-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        navigator.clipboard.writeText(btn.dataset.copy);
        btn.textContent = 'Copied!';
        setTimeout(() => btn.textContent = 'Copy', 2000);
    });
});

// Poll NMKR for real Cardano tx hash — stops after 5 tries (NMKR Preprod rarely provides it)
document.querySelectorAll('.bch-tx-pending').forEach(el => {
    const attendeeId = el.dataset.attendeeId;
    const nonce      = el.dataset.nonce;
    let   attempts   = 0;
    const MAX        = 5;

    const poll = async () => {
        const body = new FormData();
        body.append('action',      'bch_refresh_tx_hash');
        body.append('nonce',       nonce);
        body.append('attendee_id', attendeeId);

        try {
            const res  = await fetch('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', { method: 'POST', body });
            const json = await res.json();

            if (json.success && json.data.confirmed) {
                const tx   = json.data.tx_hash;
                const link = document.createElement('a');
                link.href        = `https://preprod.cardanoscan.io/transaction/${tx}`;
                link.target      = '_blank';
                link.textContent = tx.slice(0, 8) + '…';
                el.replaceWith(link);
                return;
            }
        } catch { /* ignore */ }

        if (++attempts < MAX) {
            setTimeout(poll, 5000);
        } else {
            el.remove(); // Policy ↗ link above is sufficient
        }
    };

    setTimeout(poll, 5000);
});
</script>
