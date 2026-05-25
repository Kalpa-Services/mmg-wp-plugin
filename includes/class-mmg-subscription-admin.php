<?php
/**
 * MMG Subscription Admin Class
 *
 * @package MMG_Checkout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MMG_Subscription_Admin class.
 */
class MMG_Subscription_Admin {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'product_type_selector', array( $this, 'add_product_type' ) );
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'subscription_tabs' ) );
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'subscription_fields' ) );
		add_action( 'woocommerce_process_product_meta_mmg_subscription', array( $this, 'save_subscription_meta' ) );
		add_action( 'admin_footer', array( $this, 'subscription_js' ) );
	}

	/**
	 * Add MMG Subscription to product type selector.
	 *
	 * @param array $types Product types.
	 * @return array
	 */
	public function add_product_type( $types ) {
		$types['mmg_subscription'] = 'MMG Subscription';
		return $types;
	}

	/**
	 * Show relevant tabs for MMG Subscription.
	 *
	 * @param array $tabs Tabs.
	 * @return array
	 */
	public function subscription_tabs( $tabs ) {
		$tabs['general']['class'][] = 'show_if_mmg_subscription';
		return $tabs;
	}

	/**
	 * Add subscription fields to General tab.
	 */
	public function subscription_fields() {
		echo '<div class="options_group show_if_mmg_subscription">';

		woocommerce_wp_select(
			array(
				'id'          => '_mmg_sub_period',
				'label'       => 'Billing Period',
				'options'     => array(
					'day'   => 'Day',
					'week'  => 'Week',
					'month' => 'Month',
					'year'  => 'Year',
				),
				'desc_tip'    => true,
				'description' => 'How often the customer should be charged.',
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'                => '_mmg_sub_interval',
				'label'             => 'Billing Interval',
				'placeholder'       => '1',
				'type'              => 'number',
				'custom_attributes' => array(
					'step' => '1',
					'min'  => '1',
				),
				'desc_tip'          => true,
				'description'       => 'Charge every X periods (e.g. every 2 months).',
			)
		);

		echo '</div>';
	}

	/**
	 * Save subscription meta.
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_subscription_meta( $post_id ) {
		if ( ! isset( $_POST['woocommerce_meta_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ), 'woocommerce_save_data' ) ) {
			return;
		}

		$period   = isset( $_POST['_mmg_sub_period'] ) ? sanitize_text_field( wp_unslash( $_POST['_mmg_sub_period'] ) ) : 'month';
		$interval = isset( $_POST['_mmg_sub_interval'] ) ? intval( wp_unslash( $_POST['_mmg_sub_interval'] ) ) : 1;

		update_post_meta( $post_id, '_mmg_sub_period', $period );
		update_post_meta( $post_id, '_mmg_sub_interval', $interval );
	}

	/**
	 * Toggle panels via JS.
	 */
	public function subscription_js() {
		if ( 'product' !== get_post_type() ) {
			return;
		}
		?>
		<script type="text/javascript">
			jQuery(function($){
				$('.options_group.pricing').addClass('show_if_mmg_subscription');
				
				function toggle_mmg_sub_fields() {
					var product_type = $('#product-type').val();
					if (product_type === 'mmg_subscription') {
						$('.show_if_mmg_subscription').show();
						$('.options_group.pricing').show();
						$('.general_options').show();
						$('.general_tab').show();
					}
				}

				$('body').on('woocommerce-product-type-change', function(event, select_val){
					toggle_mmg_sub_fields();
				});

				// Initial check on load with a small delay to override default Woo JS.
				setTimeout(toggle_mmg_sub_fields, 100);
			});
		</script>
		<?php
	}
}
