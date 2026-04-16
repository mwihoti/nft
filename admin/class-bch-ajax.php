<?php
defined( 'ABSPATH' ) || exit;

/**
 * AJAX endpoints — minting now done entirely server-side via NMKR API.
 * Mesh.js is only used on the claim page to detect the attendee's wallet address.
 */
class BCH_Ajax {

    public function __construct() {
        // Admin: issue NFT directly (Flow A)
        add_action( 'wp_ajax_bch_issue_nft',      [ $this, 'issue_nft' ] );

        // Admin: save connected wallet address + PKH to options
        add_action( 'wp_ajax_bch_save_admin_wallet', [ $this, 'save_admin_wallet' ] );

        // Admin: add attendee from frontend event admin page
        add_action( 'wp_ajax_bch_admin_add_attendee', [ $this, 'admin_add_attendee' ] );

        // Save real Cardano tx hash after Mesh.js mint
        add_action( 'wp_ajax_bch_save_tx_hash', [ $this, 'save_tx_hash' ] );

        // Admin: poll NMKR for real Cardano tx hash after mint
        add_action( 'wp_ajax_bch_refresh_tx_hash', [ $this, 'refresh_tx_hash' ] );

        // Public: save attendee wallet address from CIP-30 connect (Flow B)
        add_action( 'wp_ajax_nopriv_bch_save_wallet', [ $this, 'save_wallet' ] );
        add_action( 'wp_ajax_bch_save_wallet',         [ $this, 'save_wallet' ] );

        // Public: trigger server-side mint after wallet is saved (Flow B)
        add_action( 'wp_ajax_nopriv_bch_claim_mint', [ $this, 'claim_mint' ] );
        add_action( 'wp_ajax_bch_claim_mint',         [ $this, 'claim_mint' ] );

        // Public: poll claim status
        add_action( 'wp_ajax_nopriv_bch_claim_status', [ $this, 'claim_status' ] );
        add_action( 'wp_ajax_bch_claim_status',         [ $this, 'claim_status' ] );
    }

    // -------------------------------------------------------------------------
    // Flow A — Admin direct issue (PHP-only, no browser signing)
    // -------------------------------------------------------------------------

    public function issue_nft(): void {
        $this->verify_nonce( true );

        $attendee_id = (int) ( $_POST['attendee_id'] ?? 0 );
        $attendee    = BCH_DB::get_attendee( $attendee_id );

        if ( ! $attendee ) {
            wp_send_json_error( 'Attendee not found.' );
        }
        if ( empty( $attendee->wallet_address ) ) {
            wp_send_json_error( 'No wallet address on record for this attendee.' );
        }
        if ( ! str_starts_with( $attendee->wallet_address, 'addr' ) ) {
            wp_send_json_error( "Invalid wallet address \"{$attendee->wallet_address}\" — must start with addr. Please update this attendee's wallet address first." );
        }

        $result = $this->do_mint( (int) $attendee->id );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        wp_send_json_success( $result );
    }

    // -------------------------------------------------------------------------
    // Flow B — QR claim: save wallet address
    // -------------------------------------------------------------------------

    public function save_wallet(): void {
        $this->verify_nonce( false );

        $token          = sanitize_text_field( $_POST['claim_token'] ?? '' );
        $wallet_address = sanitize_text_field( $_POST['wallet_address'] ?? '' );

        $attendee = BCH_Claim_Token::validate( $token );
        if ( ! $attendee ) {
            wp_send_json_error( 'Invalid or expired claim link.' );
        }
        if ( empty( $wallet_address ) ) {
            wp_send_json_error( 'Wallet address is required.' );
        }

        BCH_DB::update_attendee( (int) $attendee->id, [ 'wallet_address' => $wallet_address ] );
        wp_send_json_success( [ 'attendee_id' => $attendee->id ] );
    }

    // -------------------------------------------------------------------------
    // Flow B — QR claim: trigger NMKR mint after wallet address saved
    // -------------------------------------------------------------------------

    public function claim_mint(): void {
        $this->verify_nonce( false );

        $token    = sanitize_text_field( $_POST['claim_token'] ?? '' );
        $attendee = BCH_Claim_Token::validate( $token );

        if ( ! $attendee ) {
            wp_send_json_error( 'Invalid or expired claim link.' );
        }
        if ( empty( $attendee->wallet_address ) ) {
            wp_send_json_error( 'Wallet address not yet saved.' );
        }

        $result = $this->do_mint( (int) $attendee->id );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        wp_send_json_success( $result );
    }

    // -------------------------------------------------------------------------
    // Shared: poll status
    // -------------------------------------------------------------------------

    public function claim_status(): void {
        $this->verify_nonce( false );

        $token    = sanitize_text_field( $_POST['claim_token'] ?? '' );
        $attendee = BCH_DB::get_attendee_by_claim_token( $token );

        if ( ! $attendee ) {
            wp_send_json_error( 'Token not found.' );
        }

        wp_send_json_success( [
            'status'  => $attendee->nft_status,
            'tx_hash' => $attendee->tx_hash,
        ] );
    }

    // -------------------------------------------------------------------------
    // Core mint logic — shared by Flow A and Flow B
    // -------------------------------------------------------------------------

    private function do_mint( int $attendee_id ): array|WP_Error {
        $attendee = BCH_DB::get_attendee( $attendee_id );
        $event    = $attendee ? BCH_DB::get_event( (int) $attendee->event_id ) : null;

        if ( ! $attendee || ! $event ) {
            return new WP_Error( 'not_found', 'Attendee or event not found.' );
        }

        $nmkr = new BCH_NMKR();

        // 1. Build CIP-68 metadata
        $metadata = BCH_NFT::build_metadata(
            $event->title,
            $event->event_date,
            $event->venue,
            $attendee->name,
            $attendee_id,
            $event->banner_ipfs
        );

        // 2. Generate a unique on-chain token name (max 32 ASCII chars)
        $token_name = self::make_token_name( (int) $event->id, $attendee_id );

        // 3. Upload NFT definition to NMKR → get nft_uid
        $nft_uid = $nmkr->upload_nft( $metadata, $token_name );
        if ( is_wp_error( $nft_uid ) ) {
            return $nft_uid;
        }

        // 4. Mint and send to attendee wallet via NMKR
        $mint_result = $nmkr->mint_and_send( $nft_uid, $attendee->wallet_address );
        if ( is_wp_error( $mint_result ) ) {
            return $mint_result;
        }

        // 5. Extract mint-and-send ID (NMKR's internal reference)
        $mint_id = $mint_result['mintAndSendId'] ?? ( $mint_result['raw'] ?? '' );

        // Only mark as minted if NMKR returned a valid mint ID (non-empty)
        if ( empty( $mint_id ) ) {
            return new WP_Error( 'nmkr_mint', 'NMKR did not return a mint ID. Check NMKR Studio for errors.' );
        }

        // 6. Persist result.
        // asset_name_hex stores the NMKR NFT UID so we can poll for the real Cardano tx hash later.
        // tx_hash is initially the NMKR mint ID (numeric); updated to real 64-char hex after confirmation.
        BCH_DB::update_attendee( $attendee_id, [
            'nft_status'    => 'minted',
            'asset_name_hex'=> $nft_uid,
            'tx_hash'       => 'pending:' . $mint_id,
        ] );

        return [
            'mint_id'    => $mint_id,
            'nft_uid'    => $nft_uid,
            'token_name' => $token_name,
            'recipient'  => $attendee->wallet_address,
        ];
    }

    // -------------------------------------------------------------------------
    // Tx hash polling
    // -------------------------------------------------------------------------

    /**
     * Poll NMKR for the real Cardano tx hash using the stored NFT UID.
     * Called by the admin event-detail page after a mint.
     */
    public function refresh_tx_hash(): void {
        $this->verify_nonce( true );

        $attendee_id = (int) ( $_POST['attendee_id'] ?? 0 );
        $attendee    = BCH_DB::get_attendee( $attendee_id );

        if ( ! $attendee || $attendee->nft_status !== 'minted' ) {
            wp_send_json_error( 'Not applicable.' );
        }

        // asset_name_hex now stores the NMKR NFT UID
        $nft_uid = $attendee->asset_name_hex;
        if ( ! $nft_uid || ! str_contains( $nft_uid, '-' ) ) {
            wp_send_json_error( 'No NFT UID stored.' );
        }

        $nmkr     = new BCH_NMKR();
        $details  = $nmkr->get_nft_details( $nft_uid );

        if ( is_wp_error( $details ) ) {
            wp_send_json_error( $details->get_error_message() );
        }

        $tx_hash = $details['initialMintTxHash'] ?? null;

        if ( $tx_hash && strlen( $tx_hash ) === 64 ) {
            // Real Cardano tx hash confirmed — persist it
            BCH_DB::update_attendee( $attendee_id, [ 'tx_hash' => $tx_hash ] );
            wp_send_json_success( [ 'tx_hash' => $tx_hash, 'confirmed' => true ] );
        } else {
            // Still processing
            wp_send_json_success( [ 'tx_hash' => null, 'confirmed' => false, 'state' => $details['state'] ?? 'pending' ] );
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Short, unique ASCII token name: "BCH-{event_id}-{attendee_id}"
     * Padded/truncated to 32 chars max.
     */
    private static function make_token_name( int $event_id, int $attendee_id ): string {
        // NMKR prepends the project prefix (BCH) automatically.
        // We send only the suffix so the final token name is e.g. BCH1ATT2.
        return substr( "{$event_id}ATT{$attendee_id}", 0, 32 );
    }

    // -------------------------------------------------------------------------
    // Save tx hash after Mesh.js browser mint
    // -------------------------------------------------------------------------

    public function save_tx_hash(): void {
        $this->verify_nonce( true );

        $attendee_id = (int) ( $_POST['attendee_id'] ?? 0 );
        $tx_hash     = sanitize_text_field( $_POST['tx_hash'] ?? '' );
        $asset_name  = sanitize_text_field( $_POST['asset_name'] ?? '' );
        $policy_id   = sanitize_text_field( $_POST['policy_id'] ?? '' );

        if ( ! $attendee_id ) {
            wp_send_json_error( 'Invalid attendee.' );
        }
        if ( strlen( $tx_hash ) !== 64 || ! ctype_xdigit( $tx_hash ) ) {
            wp_send_json_error( 'Invalid tx hash.' );
        }

        $update = [ 'tx_hash' => $tx_hash, 'nft_status' => 'minted' ];
        if ( $asset_name ) $update['asset_name_hex'] = $asset_name;

        BCH_DB::update_attendee( $attendee_id, $update );

        // Save policy ID to options if provided and not already set
        if ( $policy_id && strlen( $policy_id ) === 56 && ! get_option( 'bch_policy_id' ) ) {
            update_option( 'bch_policy_id', $policy_id );
        }

        wp_send_json_success( [ 'tx_hash' => $tx_hash ] );
    }

    // -------------------------------------------------------------------------
    // Admin: add attendee from frontend dashboard
    // -------------------------------------------------------------------------

    public function admin_add_attendee(): void {
        $this->verify_nonce( true );

        $event_id = (int) ( $_POST['event_id'] ?? 0 );
        $name     = sanitize_text_field( $_POST['name'] ?? '' );
        $email    = sanitize_email( $_POST['email'] ?? '' );
        $wallet   = sanitize_text_field( $_POST['wallet_address'] ?? '' );

        if ( ! $event_id || ! BCH_DB::get_event( $event_id ) ) {
            wp_send_json_error( 'Event not found.' );
        }
        if ( empty( $name ) ) {
            wp_send_json_error( 'Name is required.' );
        }

        $id = BCH_DB::insert_attendee( [
            'event_id'       => $event_id,
            'name'           => $name,
            'email'          => $email,
            'wallet_address' => $wallet,
            'check_in_time'  => current_time( 'mysql', true ),
            'nft_status'     => 'pending',
        ] );

        if ( ! $id ) {
            wp_send_json_error( 'Failed to save attendee.' );
        }

        wp_send_json_success( [ 'attendee_id' => $id ] );
    }

    // -------------------------------------------------------------------------
    // Admin wallet save
    // -------------------------------------------------------------------------

    public function save_admin_wallet(): void {
        $this->verify_nonce( true );

        $address = sanitize_text_field( $_POST['wallet_address'] ?? '' );
        $pkh     = sanitize_text_field( $_POST['admin_pkh'] ?? '' );

        if ( empty( $address ) || ! str_starts_with( $address, 'addr' ) ) {
            wp_send_json_error( 'Invalid wallet address.' );
        }
        if ( empty( $pkh ) || strlen( $pkh ) !== 56 || ! ctype_xdigit( $pkh ) ) {
            wp_send_json_error( 'Invalid payment key hash — expected 56 hex characters.' );
        }

        update_option( 'bch_admin_wallet_address', $address );
        update_option( 'bch_admin_pkh', $pkh );

        wp_send_json_success( [ 'address' => $address, 'pkh' => $pkh ] );
    }

    private function verify_nonce( bool $admin_only = true ): void {
        if ( ! check_ajax_referer( 'bch_ajax', 'nonce', false ) ) {
            wp_send_json_error( 'Invalid nonce.' );
        }
        if ( $admin_only && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Unauthorized.' );
        }
    }
}
