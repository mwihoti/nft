<?php defined( 'ABSPATH' ) || exit;

if ( ! $event ) {
    echo '<div class="bch-alert bch-alert--error"><strong>Event not found.</strong> Check the shortcode <code>id</code> attribute.</div>';
    return;
}

$total       = count( $attendees );
$minted      = count( array_filter( $attendees, fn($a) => $a->nft_status === 'minted' ) );
$pending     = count( array_filter( $attendees, fn($a) => $a->nft_status === 'pending' ) );
$claim_ready = count( array_filter( $attendees, fn($a) => $a->nft_status === 'claim_ready' ) );
$network     = get_option( 'bch_network', 'preprod' );
$policy_id   = get_option( 'bch_policy_id', '' );
$explorer    = $network === 'mainnet' ? 'https://cardanoscan.io' : 'https://preprod.cardanoscan.io';
?>

<div class="bch-ea-wrap">

    <!-- Header -->
    <div class="bch-ea-header">
        <div class="bch-ea-header-left">
            <span class="bch-ea-badge">&#9883; Admin</span>
            <h1 class="bch-ea-title"><?php echo esc_html( $event->title ); ?></h1>
            <div class="bch-ea-subtitle">
                &#128197; <?php echo esc_html( date( 'F j, Y', strtotime( $event->event_date ) ) ); ?>
                &nbsp;&middot;&nbsp;
                &#128205; <?php echo esc_html( $event->venue ); ?>
            </div>
        </div>
        <div class="bch-ea-header-right">
            <a href="<?php echo esc_url( admin_url( "admin.php?page=bch-events&bch_action=detail&event_id={$event->id}" ) ); ?>"
               class="bch-ea-btn bch-ea-btn--ghost">WP Admin &#8599;</a>
        </div>
    </div>

    <!-- Stats -->
    <div class="bch-ea-stats">
        <div class="bch-ea-stat">
            <span class="bch-ea-stat-num"><?php echo $total; ?></span>
            <span class="bch-ea-stat-label">Total</span>
        </div>
        <div class="bch-ea-stat">
            <span class="bch-ea-stat-num bch-ea-stat--pending"><?php echo $pending; ?></span>
            <span class="bch-ea-stat-label">Pending</span>
        </div>
        <div class="bch-ea-stat">
            <span class="bch-ea-stat-num bch-ea-stat--claim"><?php echo $claim_ready; ?></span>
            <span class="bch-ea-stat-label">Claim Ready</span>
        </div>
        <div class="bch-ea-stat">
            <span class="bch-ea-stat-num bch-ea-stat--minted"><?php echo $minted; ?></span>
            <span class="bch-ea-stat-label">Minted</span>
        </div>
    </div>

    <!-- Toolbar -->
    <div class="bch-ea-toolbar">
        <input type="text" id="bch-ea-search" class="bch-ea-search" placeholder="&#128269; Search name or email…">
        <div class="bch-ea-toolbar-right">
            <select id="bch-ea-filter" class="bch-ea-select">
                <option value="">All statuses</option>
                <option value="pending">Pending</option>
                <option value="claim_ready">Claim Ready</option>
                <option value="minted">Minted</option>
            </select>
        </div>
    </div>

    <!-- Global action result -->
    <div id="bch-ea-result" style="display:none;margin:.75rem 0"></div>

    <!-- Attendee table -->
    <?php if ( empty( $attendees ) ) : ?>
        <div class="bch-ea-empty">No attendees have checked in yet.</div>
    <?php else : ?>

    <div class="bch-ea-table-wrap">
        <table class="bch-ea-table" id="bch-ea-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Wallet Address</th>
                    <th>Check-in</th>
                    <th>Status</th>
                    <th>NFT / Tx</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $attendees as $i => $att ) :
                $tx = $att->tx_hash ?? '';
                $has_real_tx = strlen( $tx ) === 64 && ctype_xdigit( $tx );
            ?>
                <tr class="bch-ea-row"
                    data-name="<?php echo esc_attr( strtolower( $att->name ) ); ?>"
                    data-email="<?php echo esc_attr( strtolower( $att->email ?? '' ) ); ?>"
                    data-status="<?php echo esc_attr( $att->nft_status ); ?>">

                    <td class="bch-ea-num"><?php echo $i + 1; ?></td>

                    <td class="bch-ea-name"><?php echo esc_html( $att->name ); ?></td>

                    <td><?php echo esc_html( $att->email ?: '—' ); ?></td>

                    <td class="bch-ea-wallet">
                        <?php if ( $att->wallet_address ) : ?>
                            <span class="bch-ea-addr" title="<?php echo esc_attr( $att->wallet_address ); ?>">
                                <?php echo esc_html( substr( $att->wallet_address, 0, 14 ) . '…' . substr( $att->wallet_address, -6 ) ); ?>
                            </span>
                            <button class="bch-ea-copy" data-copy="<?php echo esc_attr( $att->wallet_address ); ?>" title="Copy full address">&#128203;</button>
                        <?php else : ?>
                            <em class="bch-ea-none">no wallet</em>
                        <?php endif; ?>
                    </td>

                    <td class="bch-ea-time">
                        <?php echo $att->check_in_time
                            ? esc_html( date( 'M j, g:i A', strtotime( $att->check_in_time ) ) )
                            : '—'; ?>
                    </td>

                    <td>
                        <span class="bch-status bch-status--<?php echo esc_attr( $att->nft_status ); ?>">
                            <?php echo esc_html( str_replace( '_', ' ', $att->nft_status ) ); ?>
                        </span>
                    </td>

                    <td class="bch-ea-tx">
                        <?php if ( $has_real_tx ) : ?>
                            <a href="<?php echo esc_url( "{$explorer}/transaction/{$tx}" ); ?>" target="_blank" class="bch-ea-tx-link" title="<?php echo esc_attr( $tx ); ?>">
                                <?php echo esc_html( substr( $tx, 0, 8 ) . '…' ); ?> &#8599;
                            </a>
                        <?php elseif ( $att->nft_status === 'minted' ) : ?>
                            <?php if ( $policy_id ) : ?>
                                <a href="<?php echo esc_url( "{$explorer}/tokenPolicy/{$policy_id}" ); ?>" target="_blank" class="bch-ea-tx-link">
                                    Policy &#8599;
                                </a>
                                <span class="bch-ea-tx-polling"
                                      data-attendee-id="<?php echo (int) $att->id; ?>"
                                      style="font-size:.75rem;color:#9ca3af;display:block"></span>
                            <?php else : ?>
                                <span style="color:#059669;font-size:.82rem">&#10003; Minted</span>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=bch-settings' ) ); ?>"
                                   style="font-size:.75rem;color:#9ca3af;display:block">Set Policy ID &#8599;</a>
                            <?php endif; ?>
                        <?php else : ?>
                            —
                        <?php endif; ?>
                    </td>

                    <td class="bch-ea-actions">
                        <?php if ( $att->nft_status === 'pending' ) : ?>
                            <?php if ( $att->wallet_address ) : ?>
                                <button class="bch-ea-btn bch-ea-btn--mint"
                                        data-attendee-id="<?php echo (int) $att->id; ?>">
                                    &#127881; Issue NFT
                                </button>
                            <?php else : ?>
                                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
                                    <?php wp_nonce_field( 'bch_gen_qr' ); ?>
                                    <input type="hidden" name="action" value="bch_gen_qr">
                                    <input type="hidden" name="attendee_id" value="<?php echo (int) $att->id; ?>">
                                    <button type="submit" class="bch-ea-btn bch-ea-btn--ghost">&#128279; Send QR</button>
                                </form>
                            <?php endif; ?>

                        <?php elseif ( $att->nft_status === 'claim_ready' ) : ?>
                            <?php $claim_url = BCH_Claim_Token::claim_url( $att->claim_token ); ?>
                            <a href="<?php echo esc_url( $claim_url ); ?>" target="_blank" class="bch-ea-btn bch-ea-btn--ghost">&#128279; Claim Link</a>
                            <button class="bch-ea-copy bch-ea-copy--btn" data-copy="<?php echo esc_attr( $claim_url ); ?>">Copy</button>

                        <?php else : ?>
                            <span class="bch-ea-minted">&#10003; Done</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php endif; ?>

    <!-- Add attendee inline -->
    <div class="bch-ea-add-section">
        <button type="button" class="bch-ea-btn bch-ea-btn--primary" id="bch-ea-add-toggle">
            + Add Attendee
        </button>
        <div id="bch-ea-add-form" style="display:none" class="bch-ea-add-form">
            <h3>Add Attendee</h3>
            <div class="bch-ea-form-row">
                <input type="text" id="bch-ea-add-name" class="bch-input" placeholder="Full Name *" required>
                <input type="email" id="bch-ea-add-email" class="bch-input" placeholder="Email">
                <input type="text" id="bch-ea-add-wallet" class="bch-input bch-input--mono" placeholder="addr1… wallet address">
                <input type="hidden" id="bch-ea-add-event" value="<?php echo (int) $event->id; ?>">
                <button type="button" class="bch-ea-btn bch-ea-btn--primary" id="bch-ea-add-submit">Save</button>
                <button type="button" class="bch-ea-btn bch-ea-btn--ghost" id="bch-ea-add-cancel">Cancel</button>
            </div>
            <div id="bch-ea-add-error" class="bch-alert bch-alert--error" style="display:none;margin-top:.5rem"></div>
        </div>
    </div>

</div>
