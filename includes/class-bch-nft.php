<?php
defined( 'ABSPATH' ) || exit;

/**
 * CIP-68 asset name utilities.
 *
 * CIP-0067 label encoding:
 *   label 100 (Reference NFT) → prefix 000643b0
 *   label 222 (User NFT)      → prefix 001bc280
 *
 * Asset name structure:
 *   {label_prefix}{hex(event_id_padded + attendee_id_padded)}
 *
 * The suffix is 28 bytes (56 hex chars) so the total asset name stays
 * within the 32-byte Cardano limit (4 bytes prefix + 28 bytes suffix).
 */
class BCH_NFT {

    private const LABEL_100 = '000643b0'; // Reference NFT
    private const LABEL_222 = '001bc280'; // User NFT

    /**
     * Build the hex suffix shared by both tokens for a given event+attendee pair.
     * 14 bytes event_id (zero-padded) + 14 bytes attendee_id (zero-padded) = 28 bytes.
     */
    public static function make_suffix( int $event_id, int $attendee_id ): string {
        return str_pad( dechex( $event_id ),    28, '0', STR_PAD_LEFT )
             . str_pad( dechex( $attendee_id ), 28, '0', STR_PAD_LEFT );
    }

    /** Full asset name hex for the Reference NFT (label 100). */
    public static function ref_asset_name( int $event_id, int $attendee_id ): string {
        return self::LABEL_100 . self::make_suffix( $event_id, $attendee_id );
    }

    /** Full asset name hex for the User NFT (label 222). */
    public static function user_asset_name( int $event_id, int $attendee_id ): string {
        return self::LABEL_222 . self::make_suffix( $event_id, $attendee_id );
    }

    /**
     * Build the CIP-68 metadata map for the datum.
     *
     * Returns an associative array ready to be JSON-encoded and passed to Mesh.js
     * as the datum's metadata field.
     */
    public static function build_metadata(
        string $event_title,
        string $event_date,
        string $venue,
        string $attendee_name,
        int    $attendee_id,
        string $banner_ipfs = ''
    ): array {
        return [
            'name'          => "Attendance \u{2013} {$event_title}",
            'image'         => $banner_ipfs ?: 'ipfs://QmPlaceholder',
            'event_name'    => $event_title,
            'event_date'    => $event_date,
            'venue'         => $venue,
            'attendee_name' => $attendee_name,
            'attendee_id'   => (string) $attendee_id,
            'issued_by'     => 'Blockchain Centre Hub',
            'mediaType'     => 'image/png',
        ];
    }

    /**
     * Build the full CIP-68 datum object passed to Mesh.js for on-chain storage.
     *
     * Structure: Constr 0 [metadata_map, version_int, admin_pkh_bytes]
     */
    public static function build_datum( array $metadata, string $admin_pkh ): array {
        return [
            'constructor' => 0,
            'fields'      => [
                [ 'map'   => self::map_to_pairs( $metadata ) ],
                [ 'int'   => 1 ],
                [ 'bytes' => $admin_pkh ],
            ],
        ];
    }

    // ---------------------------------------------------------------------------
    // Internal helpers
    // ---------------------------------------------------------------------------

    /** Convert an assoc array to a list of {k, v} pairs for Plutus map encoding. */
    private static function map_to_pairs( array $map ): array {
        $pairs = [];
        foreach ( $map as $key => $value ) {
            $pairs[] = [
                'k' => [ 'bytes' => bin2hex( $key ) ],
                'v' => [ 'bytes' => bin2hex( (string) $value ) ],
            ];
        }
        return $pairs;
    }
}
