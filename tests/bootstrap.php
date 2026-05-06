<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';

if ( ! defined( 'MINUTE_IN_SECONDS' ) ) define( 'MINUTE_IN_SECONDS', 60 );
if ( ! defined( 'DAY_IN_SECONDS' ) )    define( 'DAY_IN_SECONDS', 86400 );
if ( ! defined( 'ABSPATH' ) )           define( 'ABSPATH', '/' );

$GLOBALS['mmg_test_options']    = [];
$GLOBALS['mmg_test_transients'] = [];
$GLOBALS['mmg_test_orders']     = [];

function get_option( $key, $default = false ) {
    return $GLOBALS['mmg_test_options'][ $key ] ?? $default;
}
function update_option( $key, $value ) {
    $GLOBALS['mmg_test_options'][ $key ] = $value;
}
function get_transient( $key ) {
    return $GLOBALS['mmg_test_transients'][ $key ] ?? false;
}
function set_transient( $key, $value, $ttl = 0 ) {
    $GLOBALS['mmg_test_transients'][ $key ] = $value;
}
function delete_transient( $key ) {
    unset( $GLOBALS['mmg_test_transients'][ $key ] );
}
function wp_generate_uuid4() { return 'test-uuid'; }
function wp_json_encode( $data, $flags = 0 ) { return json_encode( $data, $flags ); }
function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
function wp_remote_retrieve_response_code( $r ) { return $r['response']['code'] ?? 200; }
function wp_remote_retrieve_body( $r ) { return $r['body'] ?? ''; }
function sanitize_text_field( $str ) { return $str; }
function wp_unslash( $str ) { return $str; }
function esc_html( $str ) { return $str; }
function esc_url_raw( $url ) { return $url; }
function wp_parse_url( $url, $c = -1 ) { return parse_url( $url, $c ); }
function add_action() {}
function add_filter() {}
function current_user_can() { return true; }
function check_ajax_referer() { return true; }
function register_rest_route() {}
function admin_url( $path = '' ) { return 'http://example.com/wp-admin/' . $path; }
function get_bloginfo( $show = '' ) { return 'Test Site'; }
function home_url( $path = '' ) { return 'http://example.com' . $path; }
function wp_generate_password( $length = 12, $special_chars = true ) { return str_repeat( 'x', $length ); }
function add_query_arg( $args, $url = '' ) { return $url . '?' . http_build_query( $args ); }
function is_checkout_pay_page() { return false; }
function wp_verify_nonce( $nonce, $action ) { return true; }
function wp_create_nonce( $action ) { return 'test-nonce'; }
function plugin_dir_url( $file ) { return 'http://example.com/wp-content/plugins/mmg/'; }
function wp_enqueue_script() {}
function wp_enqueue_style() {}
function wp_localize_script() {}
function selected( $selected, $current = true ) { return $selected === $current ? ' selected' : ''; }
function wc_get_logger() {
    return new class {
        public function error( $msg, $ctx = [] ) {}
        public function info( $msg, $ctx = [] ) {}
    };
}
function wc_get_order( $id ) { return $GLOBALS['mmg_test_orders'][ $id ] ?? null; }
function wc_add_notice( $msg, $type = 'success' ) {}
function wp_safe_redirect( $url ) { throw new MMGTestRedirectException( $url ); }
function wp_send_json_success( $data = null, $code = 200 ) {
    throw new MMGTestJsonException( 'success', $data, $code );
}
function wp_send_json_error( $data = null, $code = 400 ) {
    throw new MMGTestJsonException( 'error', $data, $code );
}
function wp_die( $msg = '', $title = '', $args = [] ) {
    $status = is_array( $args ) ? ( $args['response'] ?? 500 ) : 500;
    throw new MMGTestWpDieException( $msg, $status );
}

class WP_Error {
    public $code, $message;
    public function __construct( $code = '', $message = '' ) {
        $this->code = $code; $this->message = $message;
    }
    public function get_error_message() { return $this->message; }
}
class WP_REST_Response {
    public $data, $status;
    public function __construct( $data = null, $status = 200 ) {
        $this->data = $data; $this->status = $status;
    }
}
class MMGTestJsonException extends RuntimeException {
    public $type, $data, $status;
    public function __construct( $type, $data, $status ) {
        $this->type = $type; $this->data = $data; $this->status = $status;
        parent::__construct( "$type: " . json_encode( $data ) );
    }
}
class MMGTestWpDieException extends RuntimeException {
    public $status;
    public function __construct( $msg, $status ) {
        $this->status = $status; parent::__construct( $msg );
    }
}
class MMGTestRedirectException extends RuntimeException {
    public $url;
    public function __construct( $url ) { $this->url = $url; parent::__construct( "redirect: $url" ); }
}
