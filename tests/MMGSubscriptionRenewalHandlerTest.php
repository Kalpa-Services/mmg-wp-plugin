<?php
if ( ! function_exists( 'as_enqueue_scheduled_action' ) ) {
    function as_enqueue_scheduled_action( $ts, $hook, $args = [], $group = '' ) {
        $GLOBALS['mmg_as_scheduled'][] = compact( 'ts', 'hook', 'args' );
        return 1;
    }
}
if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
    function as_unschedule_all_actions( $hook, $args = [], $group = '' ) {}
}
if ( ! function_exists( 'current_time' ) ) {
    function current_time( $type, $gmt = 0 ) { return '2026-05-10 10:00:00'; }
}
if ( ! function_exists( 'wc_get_product' ) ) {
    function wc_get_product( $id ) {
        $p = new WC_Product();
        return $p;
    }
}
if ( ! function_exists( 'wc_get_orders' ) ) {
    function wc_get_orders( $args = [] ) { return $GLOBALS['mmg_cycle_orders'] ?? []; }
}

require_once dirname(__DIR__) . '/includes/class-mmg-logger.php';
require_once dirname(__DIR__) . '/includes/class-mmg-subscription-manager.php';
require_once dirname(__DIR__) . '/includes/class-mmg-subscription-reminder-scheduler.php';
require_once dirname(__DIR__) . '/includes/class-mmg-subscription-email.php';
require_once dirname(__DIR__) . '/includes/class-mmg-api-client.php';
require_once dirname(__DIR__) . '/includes/class-mmg-subscription-renewal-handler.php';

class MMGSubscriptionRenewalHandlerTest extends \PHPUnit\Framework\TestCase {

    protected function setUp(): void {
        parent::setUp();
        $GLOBALS['mmg_test_options'] = ['mmg_mode' => 'demo', 'mmg_demo_merchant_id' => 'MID'];
        $GLOBALS['mmg_as_scheduled'] = [];
        $GLOBALS['mmg_cycle_orders'] = [];
        $GLOBALS['mmg_test_mail']    = [];
    }

    private function make_sub( array $overrides = [] ): object {
        return (object) array_merge( [
            'id'               => 1,
            'customer_id'      => 10,
            'product_id'       => 5,
            'order_id'         => 100,
            'status'           => 'active',
            'billing_period'   => 'month',
            'billing_interval' => 1,
            'next_payment_date'=> '2026-06-10 00:00:00',
            'payment_cycle_id' => '1-2026-06-10',
            'payment_token'    => 'tok123',
        ], $overrides );
    }

    public function test_skips_if_subscription_not_found() {
        $handler = $this->getMockBuilder( MMG_Subscription_Renewal_Handler::class )
            ->onlyMethods( ['get_subscription', 'call_mit_api'] )
            ->getMock();
        $handler->method( 'get_subscription' )->willReturn( null );
        $handler->expects( $this->never() )->method( 'call_mit_api' );

        $handler->process_renewal( 99 );
    }

    public function test_skips_if_subscription_not_active() {
        $handler = $this->getMockBuilder( MMG_Subscription_Renewal_Handler::class )
            ->onlyMethods( ['get_subscription', 'call_mit_api'] )
            ->getMock();
        $handler->method( 'get_subscription' )->willReturn( $this->make_sub( ['status' => 'on-hold'] ) );
        $handler->expects( $this->never() )->method( 'call_mit_api' );

        $handler->process_renewal( 1 );
    }

    public function test_skips_mit_api_when_cycle_already_paid() {
        $api   = $this->createMock( MMG_API_Client::class );
        $email = $this->createMock( MMG_Subscription_Email::class );
        $sched = $this->createMock( MMG_Subscription_Reminder_Scheduler::class );

        $handler = $this->getMockBuilder( MMG_Subscription_Renewal_Handler::class )
            ->setConstructorArgs( [ $api, $email, $sched ] )
            ->onlyMethods( ['get_subscription', 'is_cycle_paid', 'advance_cycle'] )
            ->getMock();
        $handler->method( 'get_subscription' )->willReturn( $this->make_sub() );
        $handler->method( 'is_cycle_paid' )->willReturn( true );
        $handler->expects( $this->once() )->method( 'advance_cycle' );

        $api->expects( $this->never() )->method( 'initiate_payment' );

        $handler->process_renewal( 1 );
    }

    public function test_calls_mit_api_when_cycle_not_paid() {
        $api   = $this->createMock( MMG_API_Client::class );
        $email = $this->createMock( MMG_Subscription_Email::class );
        $sched = $this->createMock( MMG_Subscription_Reminder_Scheduler::class );

        $handler = $this->getMockBuilder( MMG_Subscription_Renewal_Handler::class )
            ->setConstructorArgs( [ $api, $email, $sched ] )
            ->onlyMethods( ['get_subscription', 'is_cycle_paid'] )
            ->getMock();
        $handler->method( 'get_subscription' )->willReturn( $this->make_sub() );
        $handler->method( 'is_cycle_paid' )->willReturn( false );
        $api->expects( $this->once() )->method( 'initiate_payment' )->willReturn( [] );

        $handler->process_renewal( 1 );
    }

    public function test_halts_and_sends_failure_email_when_mit_api_throws() {
        $api   = $this->createMock( MMG_API_Client::class );
        $email = $this->createMock( MMG_Subscription_Email::class );
        $sched = $this->createMock( MMG_Subscription_Reminder_Scheduler::class );

        $handler = $this->getMockBuilder( MMG_Subscription_Renewal_Handler::class )
            ->setConstructorArgs( [ $api, $email, $sched ] )
            ->onlyMethods( ['get_subscription', 'is_cycle_paid', 'halt_subscription'] )
            ->getMock();
        $sub = $this->make_sub();
        $handler->method( 'get_subscription' )->willReturn( $sub );
        $handler->method( 'is_cycle_paid' )->willReturn( false );
        $handler->expects( $this->once() )->method( 'halt_subscription' )->with( 1 );
        $api->method( 'initiate_payment' )->willThrowException( new Exception( 'API down' ) );
        $email->expects( $this->once() )->method( 'send_payment_failed' )->with( $sub, 'API down' );

        $handler->process_renewal( 1 );
    }

    public function test_on_mit_payment_confirmed_advances_cycle_and_sends_confirmed_email() {
        $api   = $this->createMock( MMG_API_Client::class );
        $email = $this->createMock( MMG_Subscription_Email::class );
        $sched = $this->createMock( MMG_Subscription_Reminder_Scheduler::class );

        $sub = $this->make_sub();
        $handler = $this->getMockBuilder( MMG_Subscription_Renewal_Handler::class )
            ->setConstructorArgs( [ $api, $email, $sched ] )
            ->onlyMethods( ['get_subscription', 'advance_cycle'] )
            ->getMock();
        $handler->method( 'get_subscription' )->willReturn( $sub );
        $handler->expects( $this->once() )->method( 'advance_cycle' )->with( $sub );
        $email->expects( $this->once() )->method( 'send_payment_confirmed' )->with( $sub );

        $handler->on_mit_payment_confirmed( 1 );
    }
}
