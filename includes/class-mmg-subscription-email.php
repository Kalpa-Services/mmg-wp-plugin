<?php
/**
 * MMG Subscription Email
 *
 * @package MMG_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles all subscription email notifications via wp_mail().
 */
class MMG_Subscription_Email {

    private const DEFAULT_TEMPLATES = [
        'mmg_email_tpl_reminder' => [
            'subject' => 'Subscription renewal reminder — {site_name}',
            'body'    => '<p>Hi {customer_name},</p>
<p>Your subscription to <strong>{subscription_name}</strong> will renew for <strong>{amount}</strong> on <strong>{next_payment_date}</strong>.</p>
<p><a href="{payment_url}" style="background:#0071a1;color:#fff;padding:10px 20px;text-decoration:none;border-radius:4px;display:inline-block;">Pay Now</a></p>
<p>You can also manage your subscriptions from <a href="{account_url}">your account</a>.</p>
<p>— {site_name}</p>',
        ],
        'mmg_email_tpl_confirmed' => [
            'subject' => 'Subscription payment confirmed — {site_name}',
            'body'    => '<p>Hi {customer_name},</p>
<p>Your renewal payment for <strong>{subscription_name}</strong> has been confirmed. Your next renewal is on <strong>{next_payment_date}</strong>.</p>
<p><a href="{account_url}">View your subscriptions</a></p>
<p>— {site_name}</p>',
        ],
        'mmg_email_tpl_failed' => [
            'subject' => 'Subscription payment failed — {site_name}',
            'body'    => '<p>Hi {customer_name},</p>
<p>We were unable to process your renewal for <strong>{subscription_name}</strong>. Your subscription has been paused.</p>
<p>Please visit <a href="{account_url}">your account</a> to renew.</p>
<p>— {site_name}</p>',
        ],
    ];

    /**
     * Send pre-renewal reminder email.
     *
     * @param object $sub        Subscription row.
     * @param string $payment_url Signed pay URL.
     */
    public function send_reminder( object $sub, string $payment_url ): void {
        $this->send( 'mmg_email_tpl_reminder', $sub, [ '{payment_url}' => $payment_url ] );
    }

    /**
     * Send payment confirmed email.
     *
     * @param object $sub Subscription row.
     */
    public function send_payment_confirmed( object $sub ): void {
        $this->send( 'mmg_email_tpl_confirmed', $sub );
    }

    /**
     * Send payment failed email.
     *
     * @param object $sub    Subscription row.
     * @param string $reason Failure reason for logging (not shown to customer).
     */
    public function send_payment_failed( object $sub, string $reason = '' ): void {
        $this->send( 'mmg_email_tpl_failed', $sub );
        if ( $reason ) {
            MMG_Logger::error( "Subscription #{$sub->id} payment failed: {$reason}", 'errors' );
        }
    }

    /**
     * Resolve template, replace variables, and dispatch via wp_mail().
     *
     * @param string $option_key   WP option key for the template.
     * @param object $sub          Subscription row.
     * @param array  $extra_vars   Additional placeholder replacements.
     */
    private function send( string $option_key, object $sub, array $extra_vars = [] ): void {
        $tpl = get_option( $option_key, [] );
        if ( empty( $tpl['subject'] ) || empty( $tpl['body'] ) ) {
            $tpl = self::DEFAULT_TEMPLATES[ $option_key ] ?? [ 'subject' => '', 'body' => '' ];
        }

        $user       = get_user_by( 'id', $sub->customer_id );
        $email_to   = $user ? $user->user_email : '';
        if ( ! $email_to ) {
            MMG_Logger::error( "Cannot send email for subscription #{$sub->id}: no user email.", 'errors' );
            return;
        }

        $product      = wc_get_product( $sub->product_id );
        $product_name = $product ? $product->get_name( 'edit' ) : "Subscription #{$sub->id}";
        $account_url  = wc_get_endpoint_url( 'mmg-subscriptions', '', wc_get_page_permalink( 'myaccount' ) );

        $vars = array_merge(
            [
                '{customer_name}'     => $user->display_name,
                '{subscription_name}' => $product_name,
                '{amount}'            => $product ? wc_price( $product->get_price() ) : '',
                '{next_payment_date}' => $sub->next_payment_date,
                '{account_url}'       => $account_url,
                '{site_name}'         => get_bloginfo( 'name' ),
                '{payment_url}'       => '',
            ],
            $extra_vars
        );

        $subject = str_replace( array_keys( $vars ), array_values( $vars ), $tpl['subject'] );
        $body    = str_replace( array_keys( $vars ), array_values( $vars ), $tpl['body'] );

        wp_mail( $email_to, $subject, $body, [ 'Content-Type: text/html; charset=UTF-8' ] );
    }

    /**
     * Return default templates (for seeding on activation).
     *
     * @return array
     */
    public static function get_default_templates(): array {
        return self::DEFAULT_TEMPLATES;
    }
}
