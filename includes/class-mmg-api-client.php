<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MMG_API_Client {

    private $mode;
    private $base_url;

    public function __construct() {
        $this->mode     = get_option( 'mmg_mode', 'demo' );
        $this->base_url = $this->resolve_base_url();
    }

    protected function resolve_base_url() {
        if ( 'live' === $this->mode ) {
            return rtrim( get_option( 'mmg_live_mwallet_url', 'https://mwallet.mmgtest.net' ), '/' );
        }
        return 'https://mwallet.mmgtest.net';
    }

    public function get_mode() { return $this->mode; }
    public function get_base_url() { return $this->base_url; }

    public function ensure_token_public() { $this->ensure_token(); }

    protected function ensure_token() {
        if ( get_transient( 'mmg_access_token_' . $this->mode ) ) return;
        if ( ! $this->refresh_token() ) $this->do_login();
    }

    public function do_login() {
        $body     = wp_json_encode( [
            'merchant_msisdn' => get_option( "mmg_{$this->mode}_merchant_id" ),
            'password'        => get_option( "mmg_{$this->mode}_secret_key" ),
        ] );
        $response = $this->http_post( $this->base_url . '/mwallet/v1/e-commerce-login/mer', [], $body );

        $code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $code ) {
            throw new Exception( "Login failed with HTTP {$code}" );
        }
        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( empty( $data['access_token'] ) ) {
            throw new Exception( 'Login response missing access_token' );
        }
        set_transient( 'mmg_access_token_' . $this->mode,  $data['access_token'],             55 * MINUTE_IN_SECONDS );
        set_transient( 'mmg_refresh_token_' . $this->mode, $data['refresh_token'] ?? '', DAY_IN_SECONDS );
    }

    protected function refresh_token() {
        // Refresh endpoint not yet documented by MMG; always fall back to full login.
        return false;
    }

    public function clear_tokens() {
        delete_transient( 'mmg_access_token_' . $this->mode );
        delete_transient( 'mmg_refresh_token_' . $this->mode );
    }

    public function reauthenticate() {
        $this->clear_tokens();
        $this->do_login();
    }

    protected function get_auth_headers() {
        return [
            'X-REQUEST-ID'    => wp_generate_uuid4(),
            'X-CHANNEL'       => 'ECommerce',
            'X-MVNO-ID'       => '1',
            'X-ACCESS-TOKEN'  => get_transient( 'mmg_access_token_' . $this->mode ) ?: '',
            'X-REFRESH-TOKEN' => get_transient( 'mmg_refresh_token_' . $this->mode ) ?: '',
        ];
    }

    protected function http_get( $url, $headers = [] ) {
        return wp_remote_get( $url, [ 'headers' => $headers ] );
    }

    protected function http_post( $url, $headers = [], $body = '' ) {
        return wp_remote_post( $url, [
            'headers' => array_merge( [ 'Content-Type' => 'application/json' ], $headers ),
            'body'    => $body,
        ] );
    }

    protected function parse_response( $response ) {
        if ( is_wp_error( $response ) ) {
            throw new Exception( $response->get_error_message() );
        }
        $code = wp_remote_retrieve_response_code( $response );
        if ( $code < 200 || $code >= 300 ) {
            throw new Exception( "API error: HTTP {$code}", (int) $code );
        }
        return json_decode( wp_remote_retrieve_body( $response ), true ) ?? [];
    }

    protected function authenticated_get( $path ) {
        $this->ensure_token();
        return $this->parse_response( $this->http_get( $this->base_url . $path, $this->get_auth_headers() ) );
    }

    protected function authenticated_post( $path ) {
        $this->ensure_token();
        return $this->parse_response( $this->http_post( $this->base_url . $path, $this->get_auth_headers() ) );
    }

    public function get_balance() {
        $mid = get_option( "mmg_{$this->mode}_merchant_id" );
        return $this->authenticated_get(
            '/mwallet/v1/e-merchant-initiated-transactions/balance?merchant_msisdn=' . rawurlencode( $mid )
        );
    }

    public function get_transaction_history( array $params = [] ) {
        $mid   = get_option( "mmg_{$this->mode}_merchant_id" );
        $query = http_build_query( array_merge( ['msisdn' => $mid], $params ) );
        return $this->authenticated_get(
            '/mwallet/v1/e-merchant-initiated-transactions/txn-history?' . $query
        );
    }

    public function lookup_transaction( $txn_id ) {
        return $this->authenticated_get(
            '/mwallet/v1/e-merchant-initiated-transactions/lookup?transactionId=' . rawurlencode( $txn_id )
        );
    }

    public function reversal( $merchant_mid, $txn_id ) {
        $query = 'merchant_msisdn=' . rawurlencode( $merchant_mid ) . '&transactionId=' . rawurlencode( $txn_id );
        return $this->authenticated_post(
            '/mwallet/v1/e-merchant-initiated-transactions/reversal?' . $query
        );
    }
}
