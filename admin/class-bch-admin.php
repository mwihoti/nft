<?php
defined( 'ABSPATH' ) || exit;

class BCH_Admin {

    public function __construct() {
        add_action( 'admin_menu',            [ $this, 'register_menus' ] );
        add_action( 'admin_init',            [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
        add_action( 'admin_post_bch_save_event',    [ $this, 'handle_save_event' ] );
        add_action( 'admin_post_bch_delete_event',  [ $this, 'handle_delete_event' ] );
        add_action( 'admin_post_bch_checkin',       [ $this, 'handle_checkin' ] );
        add_action( 'admin_post_bch_gen_qr',        [ $this, 'handle_generate_qr' ] );
        add_action( 'admin_post_bch_update_meta',   [ $this, 'handle_update_metadata' ] );
    }

    // ---------------------------------------------------------------------------
    // Menus
    // ---------------------------------------------------------------------------

    public function register_menus(): void {
        add_menu_page(
            'BCH Attendance NFT',
            'BCH Attendance',
            'manage_options',
            'bch-events',
            [ $this, 'page_events' ],
            'dashicons-tickets-alt',
            30
        );
        add_submenu_page( 'bch-events', 'Events',       'Events',       'manage_options', 'bch-events',   [ $this, 'page_events' ] );
        add_submenu_page( 'bch-events', 'Admin Wallet', 'Admin Wallet', 'manage_options', 'bch-wallet',   [ $this, 'page_wallet' ] );
        add_submenu_page( 'bch-events', 'Settings',     'Settings',     'manage_options', 'bch-settings', [ $this, 'page_settings' ] );
    }

    // ---------------------------------------------------------------------------
    // Settings
    // ---------------------------------------------------------------------------

    public function register_settings(): void {
        foreach ( [ 'bch_nmkr_api_key', 'bch_nmkr_project_uid', 'bch_network', 'bch_policy_id', 'bch_blockfrost_api_key' ] as $field ) {
            register_setting( 'bch_settings_group', $field, [ 'sanitize_callback' => 'sanitize_text_field' ] );
        }
        register_setting( 'bch_settings_group', 'bch_claim_expiry_hours', [ 'sanitize_callback' => 'absint' ] );
    }

    // ---------------------------------------------------------------------------
    // Asset enqueue
    // ---------------------------------------------------------------------------

    public function enqueue_assets( string $hook ): void {
        if ( ! str_contains( $hook, 'bch' ) ) return;

        wp_enqueue_style( 'bch-admin', BCH_PLUGIN_URL . 'assets/css/bch.css', [], BCH_VERSION );

        // Load Mesh.js + mint script only on the Issue NFT page
        $action = $_GET['bch_action'] ?? '';
        if ( $action === 'issue_nft' ) {
            $attendee_id = (int) ( $_GET['attendee_id'] ?? 0 );
            $attendee    = $attendee_id ? BCH_DB::get_attendee( $attendee_id ) : null;
            $event       = $attendee ? BCH_DB::get_event( (int) $attendee->event_id ) : null;

            wp_enqueue_script( 'bch-mesh', 'https://unpkg.com/@meshsdk/core/dist/browser.js', [], null, true );
            wp_enqueue_script( 'bch-admin-mint', BCH_PLUGIN_URL . 'assets/js/admin-mint.js', [ 'bch-mesh' ], BCH_VERSION, true );

            $network = get_option( 'bch_network', 'preprod' );
            wp_localize_script( 'bch-admin-mint', 'bchMint', [
                'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
                'nonce'          => wp_create_nonce( 'bch_ajax' ),
                'network'        => $network,
                'blockfrostKey'  => get_option( 'bch_blockfrost_api_key', '' ),
                'adminPkh'       => get_option( 'bch_admin_pkh', '' ),
                'attendeeId'     => $attendee ? (int) $attendee->id : 0,
                'attendeeWallet' => $attendee->wallet_address ?? '',
                'attendeeName'   => $attendee->name ?? '',
                'eventId'        => $event ? (int) $event->id : 0,
                'eventTitle'     => $event->title ?? '',
                'eventDate'      => $event ? date( 'Y-m-d', strtotime( $event->event_date ) ) : '',
                'venue'          => $event->venue ?? 'Blockchain Centre Hub',
                'bannerIpfs'     => $event->banner_ipfs ?? '',
                'explorerBase'   => $network === 'mainnet'
                    ? 'https://cardanoscan.io/transaction/'
                    : 'https://preprod.cardanoscan.io/transaction/',
            ] );
        }
    }

    // ---------------------------------------------------------------------------
    // Page renderers
    // ---------------------------------------------------------------------------

    public function page_events(): void {
        $action   = $_GET['bch_action'] ?? 'list';
        $event_id = isset( $_GET['event_id'] ) ? (int) $_GET['event_id'] : 0;

        switch ( $action ) {
            case 'new':
            case 'edit':
                $event = $event_id ? BCH_DB::get_event( $event_id ) : null;
                require BCH_PLUGIN_DIR . 'admin/views/page-event-form.php';
                break;
            case 'detail':
                $event     = BCH_DB::get_event( $event_id );
                $attendees = BCH_DB::get_attendees_by_event( $event_id );
                require BCH_PLUGIN_DIR . 'admin/views/page-event-detail.php';
                break;
            case 'issue_nft':
                $attendee_id = isset( $_GET['attendee_id'] ) ? (int) $_GET['attendee_id'] : 0;
                $attendee    = BCH_DB::get_attendee( $attendee_id );
                $event       = $attendee ? BCH_DB::get_event( (int) $attendee->event_id ) : null;
                require BCH_PLUGIN_DIR . 'admin/views/page-issue-nft.php';
                break;
            default:
                $events = BCH_DB::get_all_events();
                require BCH_PLUGIN_DIR . 'admin/views/page-events.php';
        }
    }

    public function page_wallet(): void {
        require BCH_PLUGIN_DIR . 'admin/views/page-wallet.php';
    }

    public function page_settings(): void {
        require BCH_PLUGIN_DIR . 'admin/views/page-settings.php';
    }

    // ---------------------------------------------------------------------------
    // Form handlers
    // ---------------------------------------------------------------------------

    public function handle_save_event(): void {
        check_admin_referer( 'bch_save_event' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $event_id = isset( $_POST['event_id'] ) ? (int) $_POST['event_id'] : 0;
        $data = [
            'title'       => sanitize_text_field( $_POST['title'] ?? '' ),
            'description' => sanitize_textarea_field( $_POST['description'] ?? '' ),
            'event_date'  => sanitize_text_field( $_POST['event_date'] ?? '' ),
            'venue'       => sanitize_text_field( $_POST['venue'] ?? 'Blockchain Centre Hub' ),
            'banner_ipfs' => sanitize_text_field( $_POST['banner_ipfs'] ?? '' ),
        ];

        if ( $event_id ) {
            BCH_DB::update_event( $event_id, $data );
        } else {
            $event_id = BCH_DB::insert_event( $data );
        }

        wp_redirect( admin_url( "admin.php?page=bch-events&bch_action=detail&event_id={$event_id}&updated=1" ) );
        exit;
    }

    public function handle_delete_event(): void {
        check_admin_referer( 'bch_delete_event' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }
        $event_id = (int) ( $_POST['event_id'] ?? 0 );
        BCH_DB::delete_event( $event_id );
        wp_redirect( admin_url( 'admin.php?page=bch-events&deleted=1' ) );
        exit;
    }

    public function handle_checkin(): void {
        check_admin_referer( 'bch_checkin' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $event_id = (int) ( $_POST['event_id'] ?? 0 );
        $data = [
            'event_id'       => $event_id,
            'name'           => sanitize_text_field( $_POST['name'] ?? '' ),
            'email'          => sanitize_email( $_POST['email'] ?? '' ),
            'wallet_address' => sanitize_text_field( $_POST['wallet_address'] ?? '' ),
            'check_in_time'  => current_time( 'mysql', true ),
            'nft_status'     => 'pending',
        ];

        BCH_DB::insert_attendee( $data );
        wp_redirect( admin_url( "admin.php?page=bch-events&bch_action=detail&event_id={$event_id}&checked_in=1" ) );
        exit;
    }

    public function handle_generate_qr(): void {
        check_admin_referer( 'bch_gen_qr' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }

        $attendee_id = (int) ( $_POST['attendee_id'] ?? 0 );
        $token       = BCH_Claim_Token::generate();
        $expiry      = BCH_Claim_Token::expiry_datetime();

        BCH_DB::update_attendee( $attendee_id, [
            'nft_status'     => 'claim_ready',
            'claim_token'    => $token,
            'claim_expires_at' => $expiry,
        ] );

        $attendee = BCH_DB::get_attendee( $attendee_id );
        wp_redirect( admin_url( "admin.php?page=bch-events&bch_action=detail&event_id={$attendee->event_id}&qr_generated=1" ) );
        exit;
    }

    public function handle_update_metadata(): void {
        check_admin_referer( 'bch_update_meta' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }
        // Metadata update is handled client-side via Mesh.js; this endpoint
        // only saves the pending metadata changes to the DB for the JS to pick up.
        $attendee_id = (int) ( $_POST['attendee_id'] ?? 0 );
        $custom_meta = sanitize_textarea_field( $_POST['custom_meta'] ?? '' );

        BCH_DB::update_attendee( $attendee_id, [ 'nft_status' => 'minted' ] );

        $attendee = BCH_DB::get_attendee( $attendee_id );
        wp_redirect( admin_url( "admin.php?page=bch-events&bch_action=detail&event_id={$attendee->event_id}&meta_updated=1" ) );
        exit;
    }
}
