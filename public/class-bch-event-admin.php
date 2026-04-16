<?php
defined( 'ABSPATH' ) || exit;

/**
 * Frontend admin dashboard shortcode.
 *
 * Usage: [bch_event_admin id="6"]
 * Place on a password-protected or members-only WordPress page.
 * Only accessible to users with manage_options capability.
 */
class BCH_Event_Admin {

    public function __construct() {
        add_shortcode( 'bch_event_admin', [ $this, 'render' ] );
        add_action( 'wp_enqueue_scripts',  [ $this, 'enqueue_assets' ] );

        // Virtual URL: /event-admin/{id}/
        add_action( 'init',              [ $this, 'register_rewrite' ] );
        add_filter( 'query_vars',        [ $this, 'add_query_var' ] );
        add_action( 'template_redirect', [ $this, 'maybe_render_page' ] );
    }

    public function register_rewrite(): void {
        add_rewrite_rule(
            '^event-admin/([0-9]+)/?$',
            'index.php?bch_event_admin_id=$matches[1]',
            'top'
        );
        add_rewrite_rule(
            '^event-admin/?$',
            'index.php?bch_event_admin_id=0',
            'top'
        );

        // Flush once whenever our rules aren't in the DB yet
        $rules = get_option( 'rewrite_rules', [] );
        if ( empty( $rules['event-admin/?$'] ) ) {
            flush_rewrite_rules( false );
        }
    }

    public function add_query_var( array $vars ): array {
        $vars[] = 'bch_event_admin_id';
        return $vars;
    }

    public function maybe_render_page(): void {
        $event_id = get_query_var( 'bch_event_admin_id', null );
        if ( $event_id === null ) return;

        // Access check
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_redirect( wp_login_url( home_url( add_query_arg( [] ) ) ) );
            exit;
        }

        $event_id  = (int) $event_id;
        // If no ID in URL, use first event
        if ( ! $event_id ) {
            $events   = BCH_DB::get_all_events();
            $event_id = $events ? (int) $events[0]->id : 0;
        }

        $event     = $event_id ? BCH_DB::get_event( $event_id ) : null;
        $attendees = $event    ? BCH_DB::get_attendees_by_event( $event_id ) : [];

        // Enqueue assets manually since we bypass the normal WP loop
        wp_enqueue_style( 'bch-css', BCH_PLUGIN_URL . 'assets/css/bch.css', [], BCH_VERSION );
        wp_enqueue_script( 'bch-event-admin', BCH_PLUGIN_URL . 'assets/js/event-admin.js', [], BCH_VERSION, true );
        wp_localize_script( 'bch-event-admin', 'bchEventAdmin', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'bch_ajax' ),
            'network' => get_option( 'bch_network', 'preprod' ),
        ] );

        // Render a minimal HTML shell with the admin view
        $site_name = get_bloginfo( 'name' );
        $title     = $event ? esc_html( $event->title ) . ' — Admin' : 'Event Admin';

        ob_start();
        require BCH_PLUGIN_DIR . 'public/views/event-admin-page.php';
        $content = ob_get_clean();

        // Output full page
        status_header( 200 );
        nocache_headers();
        echo '<!DOCTYPE html><html ' . get_language_attributes() . '><head>';
        echo '<meta charset="' . esc_attr( get_bloginfo( 'charset' ) ) . '">';
        echo '<meta name="viewport" content="width=device-width,initial-scale=1">';
        echo '<title>' . esc_html( $title ) . ' — ' . esc_html( $site_name ) . '</title>';
        wp_head();
        echo '</head><body class="bch-ea-body">';
        echo '<div class="bch-ea-page-wrap">' . $content . '</div>';
        wp_footer();
        echo '</body></html>';
        exit;
    }

    public function enqueue_assets(): void {
        global $post;
        $has_shortcode = is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'bch_event_admin' );
        if ( ! $has_shortcode ) return;

        wp_enqueue_style( 'bch-css', BCH_PLUGIN_URL . 'assets/css/bch.css', [], BCH_VERSION );
        wp_enqueue_script( 'bch-event-admin', BCH_PLUGIN_URL . 'assets/js/event-admin.js', [], BCH_VERSION, true );
        wp_localize_script( 'bch-event-admin', 'bchEventAdmin', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'bch_ajax' ),
            'network' => get_option( 'bch_network', 'preprod' ),
        ] );
    }

    public function render( array $atts ): string {
        // Only admins may see this
        if ( ! current_user_can( 'manage_options' ) ) {
            return '<div class="bch-alert bch-alert--error"><strong>Access denied.</strong> You must be logged in as an administrator.</div>';
        }

        $atts     = shortcode_atts( [ 'id' => 0 ], $atts );
        $event_id = (int) $atts['id'];
        $event    = $event_id ? BCH_DB::get_event( $event_id ) : null;
        $attendees = $event ? BCH_DB::get_attendees_by_event( $event_id ) : [];

        ob_start();
        require BCH_PLUGIN_DIR . 'public/views/event-admin-page.php';
        return ob_get_clean();
    }
}
