<?php
if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
    function as_unschedule_all_actions( $hook, $args = [], $group = '' ) {
        $GLOBALS['mmg_as_cancelled'][] = compact( 'hook', 'args', 'group' );
    }
}
if ( ! function_exists( 'as_enqueue_scheduled_action' ) ) {
    function as_enqueue_scheduled_action( $timestamp, $hook, $args = [], $group = '' ) {
        $GLOBALS['mmg_as_scheduled'][] = compact( 'timestamp', 'hook', 'args', 'group' );
        return 1;
    }
}
if ( ! function_exists( 'current_time' ) ) {
    function current_time( $type, $gmt = 0 ) { return '2026-05-10 10:00:00'; }
}
if ( ! function_exists( 'get_current_user_id' ) ) {
    function get_current_user_id() { return $GLOBALS['mmg_test_user_id'] ?? 1; }
}
if ( ! function_exists( 'wc_get_page_permalink' ) ) {
    function wc_get_page_permalink( $page ) { return 'http://example.com/my-account/'; }
}
if ( ! function_exists( 'wc_get_endpoint_url' ) ) {
    function wc_get_endpoint_url( $endpoint, $value = '', $permalink = '' ) {
        return 'http://example.com/my-account/' . $endpoint . '/';
    }
}
if ( ! function_exists( 'wc_get_product' ) ) {
    function wc_get_product( $id ) { return null; }
}
if ( ! function_exists( 'wp_nonce_url' ) ) {
    function wp_nonce_url( $url, $action ) { return $url . '&_nonce=' . $action; }
}
if ( ! function_exists( 'wc_create_order' ) ) {
    function wc_create_order( $args = [] ) { return new WC_Order(); }
}
if ( ! function_exists( 'sanitize_key' ) ) {
    function sanitize_key( $key ) { return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', $key ) ); }
}
if ( ! function_exists( 'esc_attr' ) ) {
    function esc_attr( $text ) { return htmlspecialchars( $text, ENT_QUOTES ); }
}
if ( ! function_exists( 'esc_url' ) ) {
    function esc_url( $url ) { return $url; }
}

// Minimal wpdb stub.
class FakeWpdb {
    public string $prefix = 'wp_';
    public array  $updates = [];
    public ?object $row = null;

    public function prepare( $sql, ...$args ) {
        return vsprintf( str_replace( '%d', '%s', $sql ), $args );
    }
    public function get_row( $sql ) { return $this->row; }
    public function update( $table, $data, $where ) {
        $this->updates[] = compact( 'table', 'data', 'where' );
    }
}

require_once dirname(__DIR__) . '/includes/class-mmg-logger.php';
require_once dirname(__DIR__) . '/includes/class-mmg-subscription-manager.php';
require_once dirname(__DIR__) . '/includes/class-mmg-subscription-reminder-scheduler.php';
require_once dirname(__DIR__) . '/includes/class-mmg-subscription-email.php';
require_once dirname(__DIR__) . '/includes/class-mmg-subscription-account.php';

class MMGSubscriptionAccountTest extends \PHPUnit\Framework\TestCase {

    private FakeWpdb $wpdb;

    protected function setUp(): void {
        parent::setUp();
        $this->wpdb = new FakeWpdb();
        $GLOBALS['wpdb']              = $this->wpdb;
        $GLOBALS['mmg_test_options']  = ['mmg_reminder_schedule' => json_encode([3])];
        $GLOBALS['mmg_as_scheduled']  = [];
        $GLOBALS['mmg_as_cancelled']  = [];
        $GLOBALS['mmg_test_user_id']  = 42;
    }

    protected function tearDown(): void {
        parent::tearDown();
        unset( $GLOBALS['wpdb'] );
    }

    private function make_sub( array $overrides = [] ): object {
        return (object) array_merge( [
            'id'               => 7,
            'customer_id'      => 42,
            'order_id'         => 100,
            'product_id'       => 5,
            'status'           => 'active',
            'billing_period'   => 'month',
            'billing_interval' => 1,
            'next_payment_date'=> '2026-06-10 00:00:00',
            'payment_cycle_id' => '7-2026-06-10',
            'payment_token'    => 'tok',
        ], $overrides );
    }

    public function test_halt_sets_status_to_on_hold() {
        $this->wpdb->row = $this->make_sub();
        $account = new MMG_Subscription_Account();

        try {
            $_GET['halt_mmg_sub'] = 7;
            $_GET['_wpnonce']     = 'test-nonce';
            $account->handle_actions();
        } catch ( MMGTestRedirectException $e ) {}

        $updated = array_filter( $this->wpdb->updates, fn($u) => isset($u['data']['status']) && $u['data']['status'] === 'on-hold' );
        $this->assertNotEmpty( $updated );
        unset( $_GET['halt_mmg_sub'], $_GET['_wpnonce'] );
    }

    public function test_halt_cancels_as_jobs() {
        $this->wpdb->row = $this->make_sub();
        $account = new MMG_Subscription_Account();

        try {
            $_GET['halt_mmg_sub'] = 7;
            $_GET['_wpnonce']     = 'test-nonce';
            $account->handle_actions();
        } catch ( MMGTestRedirectException $e ) {}

        $hooks = array_column( $GLOBALS['mmg_as_cancelled'], 'hook' );
        $this->assertContains( 'mmg_subscription_renewal', $hooks );
        $this->assertContains( 'mmg_subscription_reminder', $hooks );
        unset( $_GET['halt_mmg_sub'], $_GET['_wpnonce'] );
    }

    public function test_renew_sets_status_to_active() {
        $this->wpdb->row = $this->make_sub( ['status' => 'on-hold'] );
        $account = new MMG_Subscription_Account();

        try {
            $_GET['renew_mmg_sub'] = 7;
            $_GET['_wpnonce']      = 'test-nonce';
            $account->handle_actions();
        } catch ( MMGTestRedirectException $e ) {}

        $activated = array_filter( $this->wpdb->updates, fn($u) => isset($u['data']['status']) && $u['data']['status'] === 'active' );
        $this->assertNotEmpty( $activated );
        unset( $_GET['renew_mmg_sub'], $_GET['_wpnonce'] );
    }

    public function test_renew_schedules_new_as_jobs() {
        $this->wpdb->row = $this->make_sub( ['status' => 'on-hold'] );
        $account = new MMG_Subscription_Account();

        try {
            $_GET['renew_mmg_sub'] = 7;
            $_GET['_wpnonce']      = 'test-nonce';
            $account->handle_actions();
        } catch ( MMGTestRedirectException $e ) {}

        $this->assertNotEmpty( $GLOBALS['mmg_as_scheduled'] );
        unset( $_GET['renew_mmg_sub'], $_GET['_wpnonce'] );
    }

    public function test_upgrade_frequency_updates_billing_period_and_interval() {
        $this->wpdb->row = $this->make_sub();
        $account = new MMG_Subscription_Account();

        try {
            $_GET['upgrade_mmg_sub_freq'] = 7;
            $_GET['period']               = 'year';
            $_GET['interval']             = '1';
            $_GET['_wpnonce']             = 'test-nonce';
            $account->handle_actions();
        } catch ( MMGTestRedirectException $e ) {}

        $freq_updates = array_filter(
            $this->wpdb->updates,
            fn($u) => isset($u['data']['billing_period']) && $u['data']['billing_period'] === 'year'
        );
        $this->assertNotEmpty( $freq_updates );
        unset( $_GET['upgrade_mmg_sub_freq'], $_GET['period'], $_GET['interval'], $_GET['_wpnonce'] );
    }

    public function test_generate_pay_token_url_returns_url_with_token_query_arg() {
        $url = MMG_Subscription_Account::generate_pay_token_url( 7 );
        $this->assertStringContainsString( 'mmg_pay_token=', $url );
    }

    public function test_expired_pay_token_redirects_with_error() {
        // Transient not set → token not found.
        $account = new MMG_Subscription_Account();
        try {
            $_GET['mmg_pay_token'] = 'nonexistent-token';
            $account->handle_actions();
            $this->fail( 'Expected redirect exception was not thrown.' );
        } catch ( MMGTestRedirectException $e ) {
            $this->assertStringContainsString( 'mmg-subscriptions', $e->url );
        }
        unset( $_GET['mmg_pay_token'] );
    }
}
