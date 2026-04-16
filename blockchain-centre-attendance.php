<?php
/**
 * Plugin Name:       Blockchain Centre Hub — Attendance NFT
 * Plugin URI:        https://blockchaincertrehub.com
 * Description:       Issue CIP-68 Cardano NFTs to event attendees on Preprod Testnet.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.1
 * Author:            Blockchain Centre Hub
 * License:           GPL-2.0-or-later
 * Text Domain:       bch-attendance
 */

defined( 'ABSPATH' ) || exit;

define( 'BCH_VERSION',     '1.0.0' );
define( 'BCH_PLUGIN_FILE', __FILE__ );
define( 'BCH_PLUGIN_DIR',  plugin_dir_path( __FILE__ ) );
define( 'BCH_PLUGIN_URL',  plugin_dir_url( __FILE__ ) );

// ---------------------------------------------------------------------------
// Autoload includes
// ---------------------------------------------------------------------------
foreach ( [
    'includes/class-bch-activator.php',
    'includes/class-bch-db.php',
    'includes/class-bch-nmkr.php',
    'includes/class-bch-nft.php',
    'includes/class-bch-claim-token.php',
    'admin/class-bch-admin.php',
    'admin/class-bch-ajax.php',
    'public/class-bch-shortcode.php',
    'public/class-bch-checkin.php',
    'public/class-bch-event-admin.php',
] as $file ) {
    require_once BCH_PLUGIN_DIR . $file;
}

// Composer autoload (endroid/qr-code)
if ( file_exists( BCH_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
    require_once BCH_PLUGIN_DIR . 'vendor/autoload.php';
}

// ---------------------------------------------------------------------------
// Activation / Deactivation
// ---------------------------------------------------------------------------
register_activation_hook( __FILE__, [ 'BCH_Activator', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'BCH_Activator', 'deactivate' ] );

// ---------------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------------
add_action( 'plugins_loaded', function () {
    new BCH_Admin();
    new BCH_Ajax();
    new BCH_Shortcode();
    new BCH_Checkin();
    new BCH_Event_Admin();
} );
