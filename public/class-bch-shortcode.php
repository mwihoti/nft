<?php
defined( 'ABSPATH' ) || exit;

/**
 * Registers the [bch_claim_nft] shortcode.
 *
 * Place this shortcode on a WordPress page with slug /claim-nft/.
 * The page receives ?token=UUID from the QR code link.
 */
class BCH_Shortcode {

    public function __construct() {
        add_shortcode( 'bch_claim_nft', [ $this, 'render' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
    }

    public function enqueue_assets(): void {
        if ( ! is_page() || ! isset( $_GET['token'] ) ) {
            return;
        }

        wp_enqueue_style( 'bch-claim', BCH_PLUGIN_URL . 'assets/css/bch.css', [], BCH_VERSION );
        wp_enqueue_script( 'bch-claim', BCH_PLUGIN_URL . 'assets/js/claim.js', [], BCH_VERSION, true );

        $network      = get_option( 'bch_network', 'preprod' );
        $explorer_base = $network === 'mainnet'
            ? 'https://cardanoscan.io/transaction/'
            : 'https://preprod.cardanoscan.io/transaction/';

        wp_localize_script( 'bch-claim', 'bchClaim', [
            'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
            'nonce'        => wp_create_nonce( 'bch_ajax' ),
            'claimToken'   => sanitize_text_field( $_GET['token'] ?? '' ),
            'policyId'     => get_option( 'bch_policy_id', '' ),
            'network'      => $network,
            'explorerBase' => $explorer_base,
        ] );
    }

    public function render( array $atts ): string {
        $token    = sanitize_text_field( $_GET['token'] ?? '' );
        $attendee = $token ? BCH_Claim_Token::validate( $token ) : null;

        ob_start();
        require BCH_PLUGIN_DIR . 'public/views/claim-page.php';
        return ob_get_clean();
    }
}
