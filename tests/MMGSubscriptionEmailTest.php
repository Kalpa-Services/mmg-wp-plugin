<?php
if ( ! function_exists( 'wp_mail' ) ) {
    function wp_mail( $to, $subject, $message, $headers = '', $attachments = [] ) {
        $GLOBALS['mmg_test_mail'][] = compact( 'to', 'subject', 'message', 'headers' );
        return true;
    }
}
if ( ! function_exists( 'number_format_i18n' ) ) {
    function number_format_i18n( $number, $decimals = 0 ) { return number_format( $number, $decimals ); }
}
if ( ! function_exists( 'wc_get_endpoint_url' ) ) {
    function wc_get_endpoint_url( $endpoint, $value = '', $permalink = '' ) {
        return 'http://example.com/my-account/' . $endpoint . '/';
    }
}
if ( ! function_exists( 'wc_price' ) ) {
    function wc_price( $price, $args = [] ) { return '$' . number_format( (float) $price, 2 ); }
}
if ( ! function_exists( 'get_user_by' ) ) {
    function get_user_by( $field, $value ) {
        $u = new stdClass();
        $u->display_name = 'Test Customer';
        $u->user_email   = 'customer@example.com';
        return $u;
    }
}
if ( ! function_exists( 'wc_get_product' ) ) {
    function wc_get_product( $id ) {
        return new class extends WC_Product {
            public function get_price() { return ''; }
        };
    }
}
if ( ! function_exists( 'wc_get_page_permalink' ) ) {
    function wc_get_page_permalink( $page ) {
        return 'http://example.com/my-account/';
    }
}

require_once dirname(__DIR__) . '/includes/class-mmg-logger.php';
require_once dirname(__DIR__) . '/includes/class-mmg-subscription-email.php';

class MMGSubscriptionEmailTest extends \PHPUnit\Framework\TestCase {

    private function make_sub( array $overrides = [] ): object {
        return (object) array_merge( [
            'id'                => 1,
            'customer_id'       => 10,
            'product_id'        => 5,
            'status'            => 'active',
            'billing_period'    => 'month',
            'billing_interval'  => 1,
            'next_payment_date' => '2026-06-10 00:00:00',
            'payment_cycle_id'  => '1-2026-06-10',
            'payment_token'     => 'tok123',
        ], $overrides );
    }

    protected function setUp(): void {
        parent::setUp();
        $GLOBALS['mmg_test_options'] = [];
        $GLOBALS['mmg_test_mail']    = [];
    }

    public function test_send_reminder_calls_wp_mail_with_customer_email() {
        $email = new MMG_Subscription_Email();
        $email->send_reminder( $this->make_sub(), 'http://pay.example.com/?token=abc' );

        $this->assertCount( 1, $GLOBALS['mmg_test_mail'] );
        $this->assertSame( 'customer@example.com', $GLOBALS['mmg_test_mail'][0]['to'] );
    }

    public function test_send_reminder_resolves_payment_url_variable() {
        $email = new MMG_Subscription_Email();
        $email->send_reminder( $this->make_sub(), 'http://pay.example.com/?token=abc' );

        $this->assertStringContainsString(
            'http://pay.example.com/?token=abc',
            $GLOBALS['mmg_test_mail'][0]['message']
        );
    }

    public function test_send_reminder_resolves_customer_name_variable() {
        $email = new MMG_Subscription_Email();
        $email->send_reminder( $this->make_sub(), 'http://pay.test/' );

        $this->assertStringContainsString( 'Test Customer', $GLOBALS['mmg_test_mail'][0]['message'] );
    }

    public function test_send_payment_confirmed_calls_wp_mail() {
        $email = new MMG_Subscription_Email();
        $email->send_payment_confirmed( $this->make_sub() );

        $this->assertCount( 1, $GLOBALS['mmg_test_mail'] );
        $this->assertSame( 'customer@example.com', $GLOBALS['mmg_test_mail'][0]['to'] );
    }

    public function test_send_payment_failed_calls_wp_mail() {
        $email = new MMG_Subscription_Email();
        $email->send_payment_failed( $this->make_sub(), 'Insufficient funds' );

        $this->assertCount( 1, $GLOBALS['mmg_test_mail'] );
    }

    public function test_custom_template_is_used_when_set() {
        $GLOBALS['mmg_test_options']['mmg_email_tpl_reminder'] = [
            'subject' => 'Custom subject',
            'body'    => 'Hello {customer_name} custom body',
        ];

        $email = new MMG_Subscription_Email();
        $email->send_reminder( $this->make_sub(), 'http://pay.test/' );

        $this->assertSame( 'Custom subject', $GLOBALS['mmg_test_mail'][0]['subject'] );
        $this->assertStringContainsString( 'Hello Test Customer custom body', $GLOBALS['mmg_test_mail'][0]['message'] );
    }

    public function test_missing_template_falls_back_to_default() {
        // No option set — should not throw and should still send.
        $email = new MMG_Subscription_Email();
        $email->send_reminder( $this->make_sub(), 'http://pay.test/' );

        $this->assertCount( 1, $GLOBALS['mmg_test_mail'] );
        $this->assertNotEmpty( $GLOBALS['mmg_test_mail'][0]['subject'] );
    }

    public function test_site_name_variable_resolved() {
        $email = new MMG_Subscription_Email();
        $email->send_reminder( $this->make_sub(), 'http://pay.test/' );

        $this->assertStringContainsString( 'Test Site', $GLOBALS['mmg_test_mail'][0]['message'] );
    }
}
