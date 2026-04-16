<?php defined( 'ABSPATH' ) || exit; ?>

<div class="bch-checkin-page">

    <?php if ( ! $event ) : ?>
        <div class="bch-alert bch-alert--error">
            <strong>Event not found.</strong> Please check the link and try again.
        </div>

    <?php else : ?>

        <!-- Event header -->
        <div class="bch-event-hero">
            <?php if ( ! empty( $event->banner_ipfs ) ) : ?>
                <?php
                $cid = preg_replace( '#^ipfs://#', '', $event->banner_ipfs );
                $img = "https://ipfs.io/ipfs/{$cid}";
                ?>
                <img src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( $event->title ); ?>" class="bch-event-banner">
            <?php endif; ?>

            <div class="bch-event-hero-content">
                <h1><?php echo esc_html( $event->title ); ?></h1>
                <div class="bch-event-meta">
                    <span>&#128197; <?php echo esc_html( date( 'F j, Y  g:i A', strtotime( $event->event_date ) ) ); ?></span>
                    <span>&#128205; <?php echo esc_html( $event->venue ); ?></span>
                </div>
                <?php if ( ! empty( $event->description ) ) : ?>
                    <p class="bch-event-desc"><?php echo esc_html( $event->description ); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- NFT badge info -->
        <div class="bch-nft-info">
            <div class="bch-nft-badge">&#127881;</div>
            <div>
                <h3>Claim Your Attendance NFT</h3>
                <p>Register below to receive a <strong>CIP-68 Cardano NFT</strong> proving your attendance. The NFT will be sent directly to your Cardano wallet.</p>
            </div>
        </div>

        <!-- Registration form -->
        <div class="bch-card bch-checkin-card" id="bch-checkin-form-wrap">
            <h2>Check In</h2>
            <form id="bch-checkin-form" novalidate>
                <input type="hidden" name="event_id" value="<?php echo (int) $event->id; ?>">

                <div class="bch-form-group">
                    <label for="bch-name">Full Name <span class="bch-required">*</span></label>
                    <input type="text" id="bch-name" name="name" class="bch-input" placeholder="e.g. Jane Wanjiku" required>
                </div>

                <div class="bch-form-group">
                    <label for="bch-email">Email Address <span class="bch-required">*</span></label>
                    <input type="email" id="bch-email" name="email" class="bch-input" placeholder="jane@example.com" required>
                </div>

                <!-- Wallet section -->
                <div class="bch-form-group">
                    <label>Cardano Wallet <span class="bch-required">*</span></label>

                    <div class="bch-wallet-connect-section" id="bch-wallet-connect-section">
                        <p class="bch-wallet-label">Connect your wallet to auto-fill your address:</p>
                        <div class="bch-wallet-grid bch-wallet-grid--checkin">
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
                        <p id="bch-wallet-connect-status" class="bch-status-msg" style="min-height:1.4em"></p>

                        <div id="bch-wallet-connected" style="display:none" class="bch-wallet-connected-box">
                            <span class="bch-connected-icon">&#10003;</span>
                            <div>
                                <strong id="bch-connected-wallet-name"></strong> connected
                                <div class="bch-connected-addr" id="bch-connected-addr-display"></div>
                            </div>
                            <button type="button" id="bch-disconnect-wallet" class="bch-btn-link">Change</button>
                        </div>
                    </div>

                    <div class="bch-manual-toggle">
                        <button type="button" id="bch-toggle-manual" class="bch-btn-link">
                            &#9997; Enter address manually instead
                        </button>
                    </div>

                    <div id="bch-manual-wallet" style="display:none">
                        <input type="text" id="bch-wallet-manual-input" class="bch-input bch-input--mono"
                               placeholder="addr1... or addr_test1...">
                        <p class="bch-field-hint">Open Eternl/Nami/Lace → Receive → copy your address.</p>
                    </div>

                    <input type="hidden" id="bch-wallet" name="wallet_address" value="">
                </div>

                <button type="submit" class="bch-btn-primary bch-btn-full" id="bch-checkin-submit" disabled>
                    &#128203; Check In &amp; Request NFT
                </button>
                <p class="bch-submit-hint" id="bch-submit-hint">Connect your wallet or enter your address to continue.</p>

                <div id="bch-checkin-error" class="bch-alert bch-alert--error" style="display:none;margin-top:1rem"></div>
            </form>

            <!-- Success state -->
            <div id="bch-checkin-success" style="display:none" class="bch-success-panel">
                <div class="bch-success-icon">&#10003;</div>
                <h2>You're Checked In!</h2>
                <p id="bch-success-message"></p>
                <div class="bch-success-steps">
                    <div class="bch-step-item">
                        <span class="bch-step-num">1</span>
                        <span>Your details have been received by the event organiser</span>
                    </div>
                    <div class="bch-step-item">
                        <span class="bch-step-num">2</span>
                        <span>The admin will issue your NFT shortly</span>
                    </div>
                    <div class="bch-step-item">
                        <span class="bch-step-num">3</span>
                        <span>Check your Cardano wallet — your attendance NFT will appear there</span>
                    </div>
                </div>
            </div>
        </div>

    <?php endif; ?>

</div>
