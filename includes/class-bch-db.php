<?php
defined( 'ABSPATH' ) || exit;

class BCH_DB {

    // ---------------------------------------------------------------------------
    // Table creation
    // ---------------------------------------------------------------------------
    public static function create_tables(): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $sql_events = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bch_events (
            id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            title        VARCHAR(255)    NOT NULL,
            description  TEXT,
            event_date   DATETIME        NOT NULL,
            venue        VARCHAR(255)    NOT NULL DEFAULT 'Blockchain Centre Hub',
            banner_ipfs  VARCHAR(255),
            policy_id    VARCHAR(56),
            validator_address VARCHAR(255),
            created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset;";

        $sql_attendees = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}bch_attendees (
            id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_id         BIGINT UNSIGNED NOT NULL,
            name             VARCHAR(255)    NOT NULL,
            email            VARCHAR(255),
            wallet_address   VARCHAR(255),
            check_in_time    DATETIME,
            nft_status       ENUM('pending','claim_ready','minted') NOT NULL DEFAULT 'pending',
            claim_token      VARCHAR(64),
            claim_expires_at DATETIME,
            asset_name_hex   VARCHAR(128),
            tx_hash          VARCHAR(64),
            created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_id (event_id),
            UNIQUE KEY claim_token (claim_token)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql_events );
        dbDelta( $sql_attendees );
    }

    // ---------------------------------------------------------------------------
    // Events
    // ---------------------------------------------------------------------------
    public static function insert_event( array $data ): int|false {
        global $wpdb;
        $result = $wpdb->insert( $wpdb->prefix . 'bch_events', $data );
        return $result ? (int) $wpdb->insert_id : false;
    }

    public static function get_event( int $id ): object|null {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bch_events WHERE id = %d", $id
        ) );
    }

    public static function get_all_events(): array {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}bch_events ORDER BY event_date DESC"
        ) ?: [];
    }

    public static function update_event( int $id, array $data ): bool {
        global $wpdb;
        return (bool) $wpdb->update( $wpdb->prefix . 'bch_events', $data, [ 'id' => $id ] );
    }

    public static function delete_event( int $id ): bool {
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'bch_attendees', [ 'event_id' => $id ] );
        return (bool) $wpdb->delete( $wpdb->prefix . 'bch_events', [ 'id' => $id ] );
    }

    // ---------------------------------------------------------------------------
    // Attendees
    // ---------------------------------------------------------------------------
    public static function insert_attendee( array $data ): int|false {
        global $wpdb;
        $result = $wpdb->insert( $wpdb->prefix . 'bch_attendees', $data );
        return $result ? (int) $wpdb->insert_id : false;
    }

    public static function get_attendee( int $id ): object|null {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bch_attendees WHERE id = %d", $id
        ) );
    }

    public static function get_attendees_by_event( int $event_id ): array {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bch_attendees WHERE event_id = %d ORDER BY created_at DESC",
            $event_id
        ) ) ?: [];
    }

    public static function get_attendee_by_claim_token( string $token ): object|null {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}bch_attendees WHERE claim_token = %s", $token
        ) );
    }

    public static function update_attendee( int $id, array $data ): bool {
        global $wpdb;
        return (bool) $wpdb->update( $wpdb->prefix . 'bch_attendees', $data, [ 'id' => $id ] );
    }

    public static function delete_attendee( int $id ): bool {
        global $wpdb;
        return (bool) $wpdb->delete( $wpdb->prefix . 'bch_attendees', [ 'id' => $id ] );
    }
}
