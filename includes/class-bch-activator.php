<?php
defined( 'ABSPATH' ) || exit;

class BCH_Activator {

    public static function activate(): void {
        BCH_DB::create_tables();
        self::set_defaults();
        flush_rewrite_rules();
    }

    public static function deactivate(): void {
        flush_rewrite_rules();
    }

    private static function set_defaults(): void {
        add_option( 'bch_nmkr_api_key',       '' );
        add_option( 'bch_nmkr_project_uid',   '' );
        add_option( 'bch_claim_expiry_hours',  72 );
        add_option( 'bch_network',             'preprod' );
    }
}
