<?php
defined( 'ABSPATH' ) || exit;

/**
 * Public event check-in shortcode.
 *
 * Usage: [bch_event_checkin id="1"]
 * Place on any WordPress page to let attendees register for an event.
 */
class BCH_Checkin {

    public function __construct() {
        add_shortcode( 'bch_event_checkin', [ $this, 'render' ] );
        add_action( 'wp_enqueue_scripts',    [ $this, 'enqueue_assets' ] );

        // AJAX — logged-out attendees
        add_action( 'wp_ajax_nopriv_bch_public_checkin', [ $this, 'handle_checkin' ] );
        add_action( 'wp_ajax_bch_public_checkin',         [ $this, 'handle_checkin' ] );
    }

    // -------------------------------------------------------------------------
    // Assets
    // -------------------------------------------------------------------------

    public function enqueue_assets(): void {
        if ( ! $this->page_has_shortcode() ) {
            return;
        }
        wp_enqueue_style( 'bch-checkin', BCH_PLUGIN_URL . 'assets/css/bch.css', [], BCH_VERSION );
        wp_enqueue_script( 'bch-checkin-form', BCH_PLUGIN_URL . 'assets/js/checkin-form.js', [], BCH_VERSION, true );
        wp_localize_script( 'bch-checkin-form', 'bchCheckin', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'bch_checkin_nonce' ),
        ] );
    }

    private function page_has_shortcode(): bool {
        global $post;
        return is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'bch_event_checkin' );
    }

    // -------------------------------------------------------------------------
    // Shortcode render
    // -------------------------------------------------------------------------

    public function render( array $atts ): string {
        $atts     = shortcode_atts( [ 'id' => 0 ], $atts );
        $event_id = (int) $atts['id'];
        $event    = $event_id ? BCH_DB::get_event( $event_id ) : null;

        ob_start();
        require BCH_PLUGIN_DIR . 'public/views/checkin-page.php';
        return ob_get_clean();
    }

    // -------------------------------------------------------------------------
    // AJAX handler
    // -------------------------------------------------------------------------

    public function handle_checkin(): void {
        if ( ! check_ajax_referer( 'bch_checkin_nonce', 'nonce', false ) ) {
            wp_send_json_error( 'Invalid request.' );
        }

        $event_id = (int) ( $_POST['event_id'] ?? 0 );
        $event    = $event_id ? BCH_DB::get_event( $event_id ) : null;

        if ( ! $event ) {
            wp_send_json_error( 'Event not found.' );
        }

        $name    = sanitize_text_field( $_POST['name'] ?? '' );
        $email   = sanitize_email( $_POST['email'] ?? '' );
        $wallet  = sanitize_text_field( $_POST['wallet_address'] ?? '' );

        if ( empty( $name ) ) {
            wp_send_json_error( 'Please enter your full name.' );
        }
        if ( empty( $email ) || ! is_email( $email ) ) {
            wp_send_json_error( 'Please enter a valid email address.' );
        }
        if ( empty( $wallet ) || ! str_starts_with( $wallet, 'addr' ) ) {
            wp_send_json_error( 'Please enter a valid Cardano wallet address (starts with addr...).' );
        }

        // Prevent duplicate registration for same event + email
        global $wpdb;
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}bch_attendees WHERE event_id = %d AND email = %s",
            $event_id, $email
        ) );
        if ( $exists ) {
            wp_send_json_error( 'You have already registered for this event.' );
        }

        $attendee_id = BCH_DB::insert_attendee( [
            'event_id'       => $event_id,
            'name'           => $name,
            'email'          => $email,
            'wallet_address' => $wallet,
            'check_in_time'  => current_time( 'mysql', true ),
            'nft_status'     => 'pending',
        ] );

        if ( ! $attendee_id ) {
            wp_send_json_error( 'Registration failed. Please try again.' );
        }

        // Notify admin by email
        $admin_email = get_option( 'admin_email' );
        $subject     = "[BCH] New check-in: {$name} for {$event->title}";
        $message     = "A new attendee has checked in:\n\n"
                     . "Name:    {$name}\n"
                     . "Email:   {$email}\n"
                     . "Wallet:  {$wallet}\n"
                     . "Event:   {$event->title}\n"
                     . "Date:    {$event->event_date}\n\n"
                     . "Issue their NFT here:\n"
                     . admin_url( "admin.php?page=bch-events&bch_action=issue_nft&attendee_id={$attendee_id}" );

        wp_mail( $admin_email, $subject, $message );

        wp_send_json_success( [
            'message' => "You're checked in! The admin will issue your NFT shortly. Check your wallet at {$wallet} after confirmation.",
        ] );
    }
}
