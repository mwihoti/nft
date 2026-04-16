<?php
defined( 'ABSPATH' ) || exit;

class BCH_Claim_Token {

    /**
     * Generate a cryptographically random claim token (UUID v4 format).
     */
    public static function generate(): string {
        $bytes = random_bytes( 16 );
        $bytes[6] = chr( ( ord( $bytes[6] ) & 0x0f ) | 0x40 );
        $bytes[8] = chr( ( ord( $bytes[8] ) & 0x3f ) | 0x80 );
        return vsprintf( '%s%s-%s-%s-%s-%s%s%s', str_split( bin2hex( $bytes ), 4 ) );
    }

    /**
     * Calculate the expiry datetime string.
     */
    public static function expiry_datetime( int $hours = 0 ): string {
        if ( $hours <= 0 ) {
            $hours = (int) get_option( 'bch_claim_expiry_hours', 72 );
        }
        return gmdate( 'Y-m-d H:i:s', time() + $hours * 3600 );
    }

    /**
     * Check whether a claim token is still valid (exists + not expired + status = claim_ready).
     *
     * @return object|null  Attendee row if valid, null otherwise.
     */
    public static function validate( string $token ): object|null {
        $attendee = BCH_DB::get_attendee_by_claim_token( $token );
        if ( ! $attendee ) {
            return null;
        }
        if ( $attendee->nft_status !== 'claim_ready' ) {
            return null;
        }
        if ( strtotime( $attendee->claim_expires_at ) < time() ) {
            return null;
        }
        return $attendee;
    }

    /**
     * Build the full claim URL for a given token.
     */
    public static function claim_url( string $token ): string {
        return add_query_arg( 'token', $token, home_url( '/claim-nft/' ) );
    }
}
