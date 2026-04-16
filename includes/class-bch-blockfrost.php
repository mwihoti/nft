<?php
defined( 'ABSPATH' ) || exit;

/**
 * Thin PHP client for the Blockfrost REST API (Preprod).
 *
 * Only the endpoints needed for this plugin are implemented:
 *   - fetch UTXOs at an address
 *   - submit a signed CBOR transaction
 *   - query a specific UTXO (for ref-NFT lookup on update flow)
 */
class BCH_Blockfrost {

    private string $api_key;
    private string $base_url;

    public function __construct() {
        $this->api_key  = (string) get_option( 'bch_blockfrost_api_key', '' );
        $network        = (string) get_option( 'bch_network', 'preprod' );
        $this->base_url = "https://cardano-{$network}.blockfrost.io/api/v0";
    }

    // ---------------------------------------------------------------------------
    // Public API
    // ---------------------------------------------------------------------------

    /**
     * Get UTXOs at an address.
     *
     * @return array|WP_Error  Array of UTXO objects on success.
     */
    public function get_utxos( string $address ): array|WP_Error {
        return $this->get( "/addresses/{$address}/utxos" );
    }

    /**
     * Submit a CBOR-encoded signed transaction.
     *
     * @param string $tx_cbor  Hex-encoded CBOR transaction.
     * @return string|WP_Error Transaction hash on success.
     */
    public function submit_transaction( string $tx_cbor ): string|WP_Error {
        $response = $this->post( '/tx/submit', $tx_cbor, 'application/cbor' );
        if ( is_wp_error( $response ) ) {
            return $response;
        }
        // Blockfrost returns the tx hash as a JSON string
        return trim( (string) $response, '"' );
    }

    /**
     * Get a specific UTXO by tx hash and output index.
     *
     * @return array|WP_Error
     */
    public function get_utxo( string $tx_hash, int $index ): array|WP_Error {
        return $this->get( "/txs/{$tx_hash}/utxos" );
    }

    /**
     * Get latest protocol parameters.
     *
     * @return array|WP_Error
     */
    public function get_latest_epoch_parameters(): array|WP_Error {
        return $this->get( '/epochs/latest/parameters' );
    }

    /**
     * Get current slot (latest block).
     *
     * @return array|WP_Error
     */
    public function get_latest_block(): array|WP_Error {
        return $this->get( '/blocks/latest' );
    }

    // ---------------------------------------------------------------------------
    // HTTP helpers
    // ---------------------------------------------------------------------------

    private function get( string $path ): array|WP_Error {
        $response = wp_remote_get(
            $this->base_url . $path,
            [
                'headers' => [
                    'project_id' => $this->api_key,
                    'Content-Type' => 'application/json',
                ],
                'timeout' => 15,
            ]
        );
        return $this->parse_response( $response );
    }

    private function post( string $path, string $body, string $content_type = 'application/json' ): mixed {
        $response = wp_remote_post(
            $this->base_url . $path,
            [
                'headers' => [
                    'project_id'   => $this->api_key,
                    'Content-Type' => $content_type,
                ],
                'body'    => $content_type === 'application/cbor' ? hex2bin( $body ) : $body,
                'timeout' => 30,
            ]
        );
        return $this->parse_response( $response );
    }

    private function parse_response( array|WP_Error $response ): mixed {
        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( $code >= 400 ) {
            $message = $data['message'] ?? $body;
            return new WP_Error( 'blockfrost_error', "[{$code}] {$message}" );
        }

        return $data;
    }
}
