<?php
require_once dirname(__DIR__) . '/includes/class-mmg-checkout-payment-activator.php';

class MMGSubscriptionActivatorTest extends \PHPUnit\Framework\TestCase {

    public function test_subscription_table_sql_includes_payment_cycle_id_column() {
        $sql = MMG_Checkout_Payment_Activator::get_subscription_table_sql( 'wp_' );
        $this->assertStringContainsString( "payment_cycle_id varchar(64) DEFAULT '' NOT NULL", $sql );
    }

    public function test_subscription_table_sql_includes_last_reminder_sent_column() {
        $sql = MMG_Checkout_Payment_Activator::get_subscription_table_sql( 'wp_' );
        $this->assertStringContainsString( 'last_reminder_sent datetime DEFAULT NULL', $sql );
    }
}
