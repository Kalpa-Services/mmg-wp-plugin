<?php
$GLOBALS['mmg_as_scheduled']   = [];
$GLOBALS['mmg_as_cancelled']   = [];

if ( ! function_exists( 'as_enqueue_scheduled_action' ) ) {
    function as_enqueue_scheduled_action( $timestamp, $hook, $args = [], $group = '' ) {
        $GLOBALS['mmg_as_scheduled'][] = compact( 'timestamp', 'hook', 'args', 'group' );
        return count( $GLOBALS['mmg_as_scheduled'] );
    }
}
if ( ! function_exists( 'as_has_scheduled_action' ) ) {
    function as_has_scheduled_action( $hook, $args = [] ) { return false; }
}
if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
    function as_unschedule_all_actions( $hook, $args = [], $group = '' ) {
        $GLOBALS['mmg_as_cancelled'][] = compact( 'hook', 'args', 'group' );
    }
}
if ( ! function_exists( 'current_time' ) ) {
    function current_time( $type, $gmt = 0 ) { return '2026-05-10 10:00:00'; }
}

require_once dirname(__DIR__) . '/includes/class-mmg-logger.php';
require_once dirname(__DIR__) . '/includes/class-mmg-subscription-reminder-scheduler.php';

class MMGSubscriptionReminderSchedulerTest extends \PHPUnit\Framework\TestCase {

    protected function setUp(): void {
        parent::setUp();
        $GLOBALS['mmg_test_options']  = [];
        $GLOBALS['mmg_as_scheduled']  = [];
        $GLOBALS['mmg_as_cancelled']  = [];
    }

    public function test_default_schedule_enqueues_one_reminder_job() {
        // Default schedule is [3] — 3 days before renewal.
        $GLOBALS['mmg_test_options']['mmg_reminder_schedule'] = json_encode( [3] );

        $scheduler = new MMG_Subscription_Reminder_Scheduler();
        $scheduler->schedule_for_subscription( 1, '2026-06-10 00:00:00' );

        $reminder_jobs = array_filter(
            $GLOBALS['mmg_as_scheduled'],
            fn( $j ) => $j['hook'] === 'mmg_subscription_reminder'
        );
        $this->assertCount( 1, $reminder_jobs );
    }

    public function test_multi_offset_schedule_enqueues_correct_number_of_jobs() {
        $GLOBALS['mmg_test_options']['mmg_reminder_schedule'] = json_encode( [7, 3, 1] );

        $scheduler = new MMG_Subscription_Reminder_Scheduler();
        $scheduler->schedule_for_subscription( 1, '2026-06-10 00:00:00' );

        $reminder_jobs = array_filter(
            $GLOBALS['mmg_as_scheduled'],
            fn( $j ) => $j['hook'] === 'mmg_subscription_reminder'
        );
        $this->assertCount( 3, $reminder_jobs );
    }

    public function test_reminder_job_timestamp_is_correct_days_before_renewal() {
        $GLOBALS['mmg_test_options']['mmg_reminder_schedule'] = json_encode( [3] );

        $scheduler = new MMG_Subscription_Reminder_Scheduler();
        $scheduler->schedule_for_subscription( 5, '2026-06-10 00:00:00' );

        $job = $GLOBALS['mmg_as_scheduled'][0];
        $expected_ts = strtotime( '2026-06-07 00:00:00' ); // 3 days before
        $this->assertSame( $expected_ts, $job['timestamp'] );
    }

    public function test_reminder_job_args_contain_subscription_id_and_days_before() {
        $GLOBALS['mmg_test_options']['mmg_reminder_schedule'] = json_encode( [3] );

        $scheduler = new MMG_Subscription_Reminder_Scheduler();
        $scheduler->schedule_for_subscription( 7, '2026-06-10 00:00:00' );

        $job = $GLOBALS['mmg_as_scheduled'][0];
        $this->assertSame( 7, $job['args']['subscription_id'] );
        $this->assertSame( 3, $job['args']['days_before'] );
        $this->assertSame( 'mmg-subscriptions', $job['group'] );
    }

    public function test_cancel_unschedules_reminder_and_renewal_hooks() {
        $scheduler = new MMG_Subscription_Reminder_Scheduler();
        $scheduler->cancel_for_subscription( 42 );

        $this->assertCount( 2, $GLOBALS['mmg_as_cancelled'] );

        $reminder = $GLOBALS['mmg_as_cancelled'][0];
        $this->assertSame( 'mmg_subscription_reminder', $reminder['hook'] );
        $this->assertSame( 42, $reminder['args']['subscription_id'] );
        $this->assertSame( 'mmg-subscriptions', $reminder['group'] );

        $renewal = $GLOBALS['mmg_as_cancelled'][1];
        $this->assertSame( 'mmg_subscription_renewal', $renewal['hook'] );
        $this->assertSame( 42, $renewal['args']['subscription_id'] );
        $this->assertSame( 'mmg-subscriptions', $renewal['group'] );
    }

    public function test_schedule_uses_default_3_days_when_option_missing() {
        // No option set.
        $scheduler = new MMG_Subscription_Reminder_Scheduler();
        $scheduler->schedule_for_subscription( 1, '2026-06-10 00:00:00' );

        $reminder_jobs = array_filter(
            $GLOBALS['mmg_as_scheduled'],
            fn( $j ) => $j['hook'] === 'mmg_subscription_reminder'
        );
        $this->assertCount( 1, $reminder_jobs );
    }
}
