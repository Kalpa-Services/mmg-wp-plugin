<?php
if ( ! function_exists( 'add_submenu_page' ) ) {
    function add_submenu_page( ...$args ) {}
}
if ( ! function_exists( 'settings_fields' ) ) { function settings_fields( $group ) {} }
if ( ! function_exists( 'do_settings_sections' ) ) { function do_settings_sections( $page ) {} }
if ( ! function_exists( 'submit_button' ) ) { function submit_button( $text = '' ) {} }
if ( ! function_exists( 'current_user_can' ) ) { function current_user_can( $cap ) { return true; } }
if ( ! function_exists( 'check_admin_referer' ) ) { function check_admin_referer( $action ) { return true; } }
if ( ! function_exists( 'wp_nonce_field' ) ) { function wp_nonce_field( ...$args ) {} }
if ( ! function_exists( 'wp_kses_post' ) ) { function wp_kses_post( $data ) { return $data; } }

require_once dirname(__DIR__) . '/includes/class-mmg-logger.php';
require_once dirname(__DIR__) . '/includes/class-mmg-subscription-email.php';
require_once dirname(__DIR__) . '/includes/class-mmg-subscription-email-settings.php';

class MMGSubscriptionEmailSettingsTest extends \PHPUnit\Framework\TestCase {

    protected function setUp(): void {
        parent::setUp();
        $GLOBALS['mmg_test_options'] = [];
    }

    public function test_save_reminder_schedule_persists_as_json_array() {
        $_POST['mmg_reminder_schedule'] = '7,3,1';
        $_POST['_wpnonce']              = 'test-nonce';

        try { ( new MMG_Subscription_Email_Settings() )->save_settings(); } catch ( MMGTestRedirectException $e ) {}

        $saved = get_option( 'mmg_reminder_schedule' );
        $this->assertSame( [7, 3, 1], json_decode( $saved, true ) );
        unset( $_POST['mmg_reminder_schedule'], $_POST['_wpnonce'] );
    }

    public function test_save_reminder_template_persists_subject_and_body() {
        $_POST['mmg_tpl_reminder_subject'] = 'Your renewal is coming up';
        $_POST['mmg_tpl_reminder_body']    = '<p>Hi {customer_name}</p>';
        $_POST['_wpnonce']                 = 'test-nonce';

        try { ( new MMG_Subscription_Email_Settings() )->save_settings(); } catch ( MMGTestRedirectException $e ) {}

        $tpl = get_option( 'mmg_email_tpl_reminder' );
        $this->assertSame( 'Your renewal is coming up', $tpl['subject'] );
        $this->assertSame( '<p>Hi {customer_name}</p>', $tpl['body'] );
        unset( $_POST['mmg_tpl_reminder_subject'], $_POST['mmg_tpl_reminder_body'], $_POST['_wpnonce'] );
    }

    public function test_save_confirmed_and_failed_templates() {
        $_POST['mmg_tpl_confirmed_subject'] = 'Payment confirmed';
        $_POST['mmg_tpl_confirmed_body']    = '<p>Confirmed body</p>';
        $_POST['mmg_tpl_failed_subject']    = 'Payment failed';
        $_POST['mmg_tpl_failed_body']       = '<p>Failed body</p>';
        $_POST['_wpnonce']                  = 'test-nonce';

        try { ( new MMG_Subscription_Email_Settings() )->save_settings(); } catch ( MMGTestRedirectException $e ) {}

        $this->assertSame( 'Payment confirmed', get_option( 'mmg_email_tpl_confirmed' )['subject'] );
        $this->assertSame( 'Payment failed',    get_option( 'mmg_email_tpl_failed' )['subject'] );
        unset( $_POST['mmg_tpl_confirmed_subject'], $_POST['mmg_tpl_confirmed_body'],
               $_POST['mmg_tpl_failed_subject'], $_POST['mmg_tpl_failed_body'], $_POST['_wpnonce'] );
    }

    public function test_save_reminder_schedule_rejects_non_numeric_values() {
        $_POST['mmg_reminder_schedule'] = 'abc,3,foo';
        $_POST['_wpnonce']              = 'test-nonce';

        try { ( new MMG_Subscription_Email_Settings() )->save_settings(); } catch ( MMGTestRedirectException $e ) {}

        $saved = json_decode( get_option( 'mmg_reminder_schedule' ), true );
        // Only valid numeric entries should be kept (3).
        $this->assertSame( [3], $saved );
        unset( $_POST['mmg_reminder_schedule'], $_POST['_wpnonce'] );
    }
}
