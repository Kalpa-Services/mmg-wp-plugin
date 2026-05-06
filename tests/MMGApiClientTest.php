<?php
require_once dirname(__DIR__) . '/includes/class-mmg-api-client.php';

class MMGApiClientTest extends \PHPUnit\Framework\TestCase {

    protected function setUp(): void {
        parent::setUp();
        $GLOBALS['mmg_test_options']    = [];
        $GLOBALS['mmg_test_transients'] = [];
    }

    public function test_demo_base_url() {
        $GLOBALS['mmg_test_options']['mmg_mode'] = 'demo';
        $client = new MMG_API_Client();
        $this->assertSame( 'https://mwallet.mmgtest.net/olive/publisher/v1', $client->get_base_url() );
    }

    public function test_live_base_url_uses_option() {
        $GLOBALS['mmg_test_options']['mmg_mode']             = 'live';
        $GLOBALS['mmg_test_options']['mmg_live_mwallet_url'] = 'https://mwallet.mymmg.gy/olive/publisher/v1';
        $client = new MMG_API_Client();
        $this->assertSame( 'https://mwallet.mymmg.gy/olive/publisher/v1', $client->get_base_url() );
    }

    public function test_live_base_url_defaults_when_option_missing() {
        $GLOBALS['mmg_test_options']['mmg_mode'] = 'live';
        $client = new MMG_API_Client();
        $this->assertSame( 'https://mwallet.mymmg.gy/olive/publisher/v1', $client->get_base_url() );
    }

    public function test_ensure_token_skips_login_when_cached() {
        $GLOBALS['mmg_test_options']['mmg_mode']                 = 'demo';
        $GLOBALS['mmg_test_transients']['mmg_access_token_demo'] = 'cached';

        $client = $this->getMockBuilder( MMG_API_Client::class )
            ->onlyMethods( ['do_login'] )
            ->getMock();
        $client->expects( $this->never() )->method( 'do_login' );

        $client->ensure_token_public();
    }

    public function test_ensure_token_calls_login_when_no_token() {
        $GLOBALS['mmg_test_options']['mmg_mode'] = 'demo';

        $client = $this->getMockBuilder( MMG_API_Client::class )
            ->onlyMethods( ['do_login'] )
            ->getMock();
        $client->expects( $this->once() )->method( 'do_login' );

        $client->ensure_token_public();
    }

    public function test_clear_tokens_removes_access_token() {
        $GLOBALS['mmg_test_options']['mmg_mode']                 = 'demo';
        $GLOBALS['mmg_test_transients']['mmg_access_token_demo'] = 'a';

        ( new MMG_API_Client() )->clear_tokens();

        $this->assertFalse( get_transient( 'mmg_access_token_demo' ) );
    }

    public function test_login_stores_access_token_on_success() {
        $GLOBALS['mmg_test_options']['mmg_mode']              = 'demo';
        $GLOBALS['mmg_test_options']['mmg_demo_merchant_id']  = 'MID';
        $GLOBALS['mmg_test_options']['mmg_demo_api_key']      = 'APIKEY';
        $GLOBALS['mmg_test_options']['mmg_demo_mmg_password'] = '';

        $response = [
            'response' => ['code' => 200],
            'body'     => json_encode(['access_token' => 'acc', 'expires_in' => 120]),
        ];

        $client = $this->getMockBuilder( MMG_API_Client::class )
            ->onlyMethods( ['http_post'] )
            ->getMock();
        $client->method( 'http_post' )->willReturn( $response );

        $client->do_login();

        $this->assertSame( 'acc', get_transient( 'mmg_access_token_demo' ) );
    }

    public function test_login_throws_on_non_200() {
        $GLOBALS['mmg_test_options']['mmg_mode']              = 'demo';
        $GLOBALS['mmg_test_options']['mmg_demo_merchant_id']  = 'MID';
        $GLOBALS['mmg_test_options']['mmg_demo_api_key']      = 'APIKEY';
        $GLOBALS['mmg_test_options']['mmg_demo_mmg_password'] = '';

        $client = $this->getMockBuilder( MMG_API_Client::class )
            ->onlyMethods( ['http_post'] )
            ->getMock();
        $client->method( 'http_post' )->willReturn( ['response' => ['code' => 422], 'body' => '{}'] );

        $this->expectException( Exception::class );
        $this->expectExceptionMessage( 'Login failed: HTTP 422' );
        $client->do_login();
    }

    private function make_testable_client( $mode = 'demo' ) {
        $GLOBALS['mmg_test_options']['mmg_mode']               = $mode;
        $GLOBALS['mmg_test_options']["mmg_{$mode}_merchant_id"] = 'MID999';
        $GLOBALS['mmg_test_options']["mmg_{$mode}_api_key"]    = 'APIKEY999';
        $GLOBALS['mmg_test_options']["mmg_{$mode}_secret_key"] = 'SK999';
        $GLOBALS['mmg_test_transients']["mmg_access_token_{$mode}"] = 'tok';

        $ok = ['response' => ['code' => 200], 'body' => '{}'];
        $client = $this->getMockBuilder( MMG_API_Client::class )
            ->onlyMethods( ['http_get', 'http_post'] )
            ->getMock();
        $client->method( 'http_get' )->willReturn( $ok );
        $client->method( 'http_post' )->willReturn( $ok );
        return $client;
    }

    public function test_get_balance_url_contains_balance_path_and_merchant_msisdn() {
        $client = $this->make_testable_client();
        $client->expects( $this->once() )->method( 'http_get' )
            ->with(
                $this->logicalAnd(
                    $this->stringContains( '/e-merchant-initiated-transactions/balance' ),
                    $this->stringContains( 'merchant_msisdn=MID999' )
                ),
                $this->anything()
            );
        $client->get_balance();
    }

    public function test_get_transaction_history_url_contains_txn_history_path_and_msisdn() {
        $client = $this->make_testable_client();
        $client->expects( $this->once() )->method( 'http_get' )
            ->with(
                $this->logicalAnd(
                    $this->stringContains( '/txn-history' ),
                    $this->stringContains( 'msisdn=MID999' ),
                    $this->stringContains( 'start_date=2025-01-01' )
                ),
                $this->anything()
            );
        $client->get_transaction_history( ['start_date' => '2025-01-01'] );
    }

    public function test_lookup_transaction_url_contains_lookup_path_and_txn_id() {
        $client = $this->make_testable_client();
        $client->expects( $this->once() )->method( 'http_get' )
            ->with(
                $this->logicalAnd(
                    $this->stringContains( '/lookup' ),
                    $this->stringContains( 'transactionId=TXN-42' )
                ),
                $this->anything()
            );
        $client->lookup_transaction( 'TXN-42' );
    }

    public function test_reversal_posts_to_reversal_path_with_mid_and_txn_id() {
        $client = $this->make_testable_client();
        $client->expects( $this->once() )->method( 'http_post' )
            ->with(
                $this->logicalAnd(
                    $this->stringContains( '/reversal' ),
                    $this->stringContains( 'merchant_msisdn=MID999' ),
                    $this->stringContains( 'transactionId=TXN-77' )
                ),
                $this->anything(),
                $this->anything()
            );
        $client->reversal( 'MID999', 'TXN-77' );
    }

    public function test_all_authenticated_calls_inject_required_headers() {
        $client = $this->make_testable_client();
        $client->expects( $this->once() )->method( 'http_get' )
            ->with(
                $this->anything(),
                $this->logicalAnd(
                    $this->arrayHasKey( 'x-wss-token' ),
                    $this->arrayHasKey( 'x-wss-mid' ),
                    $this->arrayHasKey( 'x-wss-mkey' ),
                    $this->arrayHasKey( 'x-wss-msecret' ),
                    $this->arrayHasKey( 'x-api-key' ),
                    $this->arrayHasKey( 'x-wss-correlationid' )
                )
            );
        $client->get_balance();
    }
}
