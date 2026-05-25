<?php
/**
 * MMG Wallet Payment Token
 *
 * @package MMG_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Represents a saved MMG mobile wallet payment token.
 *
 * Extends the generic WC_Payment_Token base class rather than WC_Payment_Token_CC
 * because MMG tokens carry no credit-card metadata (no last4, no expiry date).
 */
class WC_Payment_Token_MMG extends WC_Payment_Token {

	/**
	 * Token type identifier — stored in woocommerce_payment_tokens.type.
	 *
	 * @var string
	 */
	protected $type = 'mmg';

	/**
	 * Validates that the token contains a non-empty raw mWallet identifier.
	 *
	 * @return bool
	 */
	public function validate(): bool {
		if ( false === parent::validate() ) {
			return false;
		}
		return (bool) $this->get_token();
	}

	/**
	 * Returns the human-readable label shown on the checkout saved-payment screen.
	 *
	 * @param string $deprecated Unused; present for interface compatibility.
	 * @return string
	 */
	public function get_display_name( $deprecated = '' ): string {
		return __( 'MMG Wallet', 'mmg-checkout' );
	}
}
