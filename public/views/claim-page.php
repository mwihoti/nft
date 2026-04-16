<?php defined( 'ABSPATH' ) || exit; ?>
<div class="bch-claim-page">
    <div class="bch-claim-header">
        <h1>&#127381; Claim Your Attendance NFT</h1>
        <p class="bch-claim-subtitle">Blockchain Centre Hub — Proof of Attendance</p>
    </div>

    <?php if ( ! $token ) : ?>
        <div class="bch-alert bch-alert--error">
            <strong>Invalid Link</strong> — No claim token found. Please scan the QR code provided at the event.
        </div>

    <?php elseif ( ! $attendee ) : ?>
        <div class="bch-alert bch-alert--error">
            <strong>Link Expired or Invalid</strong> — This claim link is no longer valid. Please contact the event organiser.
        </div>

    <?php elseif ( $attendee->nft_status === 'minted' ) : ?>
        <div class="bch-alert bch-alert--success">
            <strong>&#10003; Already Minted!</strong> — Your attendance NFT has already been issued.
            <?php if ( $attendee->tx_hash ) : ?>
                <br><a href="https://preprod.cardanoscan.io/transaction/<?php echo esc_attr( $attendee->tx_hash ); ?>" target="_blank">View on CardanoScan &rarr;</a>
            <?php endif; ?>
        </div>

    <?php else : ?>
        <div class="bch-card bch-claim-card">
            <div class="bch-attendee-info">
                <h2>Hello, <?php echo esc_html( $attendee->name ); ?>!</h2>
                <p>Connect your Cardano wallet to receive your attendance NFT for this event.</p>
                <?php
                $event = BCH_DB::get_event( (int) $attendee->event_id );
                if ( $event ) :
                ?>
                <div class="bch-event-meta">
                    <span>&#128197; <?php echo esc_html( $event->event_date ); ?></span>
                    <span>&#128205; <?php echo esc_html( $event->venue ); ?></span>
                    <span>&#127881; <?php echo esc_html( $event->title ); ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Step 1: Connect Wallet -->
            <div id="bch-step-connect" class="bch-step">
                <h3>Step 1 — Connect Your Wallet</h3>
                <p class="bch-description">This will detect your wallet address. No transaction is signed in this step.</p>
                <div class="bch-wallet-grid">
                    <button class="bch-wallet-btn" data-wallet="eternl">
                        <img src="<?php echo esc_url( BCH_PLUGIN_URL . 'assets/img/eternl.svg' ); ?>" alt="">
                        Eternl
                    </button>
                    <button class="bch-wallet-btn" data-wallet="nami">
                        <img src="<?php echo esc_url( BCH_PLUGIN_URL . 'assets/img/nami.svg' ); ?>" alt="">
                        Nami
                    </button>
                    <button class="bch-wallet-btn" data-wallet="lace">
                        <img src="<?php echo esc_url( BCH_PLUGIN_URL . 'assets/img/lace.svg' ); ?>" alt="">
                        Lace
                    </button>
                    <button class="bch-wallet-btn" data-wallet="flint">
                        <img src="<?php echo esc_url( BCH_PLUGIN_URL . 'assets/img/flint.svg' ); ?>" alt="">
                        Flint
                    </button>
                </div>
                <p id="bch-connect-status" class="bch-status-msg"></p>
            </div>

            <!-- Step 2: Claim -->
            <div id="bch-step-claim" class="bch-step" style="display:none">
                <h3>Step 2 — Claim Your NFT</h3>
                <p>Wallet connected: <code id="bch-connected-addr"></code></p>
                <p>Click below to mint and send the NFT to your wallet. The admin's signing key authorises the mint on the server side.</p>
                <button id="bch-claim-btn" class="bch-btn-primary">
                    &#127381; Claim NFT
                </button>
                <p id="bch-claim-status" class="bch-status-msg"></p>
            </div>

            <!-- Success -->
            <div id="bch-step-success" class="bch-step bch-step--success" style="display:none">
                <h3>&#10003; NFT Claimed!</h3>
                <p>Your attendance NFT is on its way to your wallet.</p>
                <p><strong>Transaction:</strong> <a id="bch-claim-tx-link" href="#" target="_blank" id="bch-claim-tx-hash"></a></p>
                <p class="bch-note">It may take ~20 seconds to appear on-chain. Check your wallet or <a id="bch-explorer-link" href="#" target="_blank">CardanoScan</a>.</p>
            </div>

            <!-- Error -->
            <div id="bch-claim-error" class="bch-alert bch-alert--error" style="display:none"></div>
        </div>
    <?php endif; ?>
</div>
