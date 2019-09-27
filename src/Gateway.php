<?php

use Pronamic\WordPress\Pay\Extensions\Shopp\PaymentData;
use Pronamic\WordPress\Pay\Extensions\Shopp\Shopp;
use Pronamic\WordPress\Pay\Plugin;

/**
 * Pronamic Pay
 *
 * @author     Pronamic
 * @version    2.0.0
 * @copyright  Pronamic
 * @package    shopp
 * @since      1.1.9
 * @subpackage Pronamic_Shopp_IDeal_GatewayModule
 **/
class Pronamic_WP_Pay_Extensions_Shopp_Gateway extends GatewayFramework implements GatewayModule {
	/**
	 * Shopp 1.1 or lower will retrieve this from the documentation block above
	 *
	 * @var string
	 */
	const NAME = 'Pronamic Pay';

	/**
	 * Flag to let Shopp force auth-only order processing
	 *
	 * @var boolean
	 */
	public $authonly = true;

	/**
	 * Flag to let Shopp know that this gateway module capture separate of authorization
	 *
	 * @var boolean
	 */
	public $captures = true;

	/**
	 * Flag to let Shopp know that this gateway module doesn't require an secure connection
	 *
	 * @var boolean
	 */
	public $secure = false;

	/**
	 * The unique pay gateway config ID
	 *
	 * @var string
	 */
	private $config_id;

	/**
	 * The payment method
	 *
	 * @var string
	 */
	private $payment_method;

	/**
	 * Constructs and initialize an iDEAL gateway module
	 */
	public function __construct() {
		parent::__construct();

		// Setup.
		$this->setup( 'config_id' );

		// Config ID.
		$this->config_id = $this->settings['config_id'];

		// Payment method.
		$this->payment_method = $this->settings['payment_method'];

		// Order processing.
		// add_filter('shopp_purchase_order_processing', array($this, 'orderProcessing'), 20, 2);

		// Checkout gateway inputs.
		add_filter( 'shopp_checkout_gateway_inputs', array( $this, 'inputs' ), 50 );

		/*
		 * Actions
		 * @see /shopp/core/model/Gateway.php#L122
		 */
		$name = sanitize_key( __CLASS__ );

		add_action( 'shopp_' . $name . '_sale', array( $this, 'sale' ) );
		add_action( 'shopp_' . $name . '_auth', array( $this, 'auth' ) );
		add_action( 'shopp_' . $name . '_capture', array( $this, 'capture' ) );
		add_action( 'shopp_' . $name . '_refund', array( $this, 'refund' ) );
		add_action( 'shopp_' . $name . '_void', array( $this, 'void' ) );
	}

	/**
	 * Add actions
	 */
	public function actions() {
		/*
		 * In case of iDEAL advanced we have to store the chosen issuer ID on the
		 * checkout page. We will store the chosen issuer ID in the 'shopp_checkout_processed'
		 * action routine. This routine is triggered after processing all the
		 * checkout information.
		 *
		 * We don't have to confuse the 'shopp_process_checkout' action routine with
		 * the 'shopp_checkout_processed' routine. The 'shopp_checkout_processed' is called
		 * after / within the 'shopp_process_checkout' routine.
		 */
		add_action( 'shopp_checkout_processed', array( $this, 'checkout_processed' ) );

		/*
		 * In the Shopp settings checkout page you can require an confirmation for the
		 * order with order with tax or always. The 'shopp_process_order' action routine
		 * is only executed after the confirmation or directly when confirmation is not
		 * required (@see Order.php #357 and #396).
		 *
		 * We set the priority a little higher then the default priority becease our
		 * function is probably redirecting the user. We want to make sure all actions
		 * added by other plugins are executed.
		 */
		add_action( 'shopp_process_order', array( $this, 'process_order' ), 50 );

		add_action( 'shopp_order_success', array( $this, 'order_success' ) );
	}

	/**
	 * Sale
	 *
	 * @param OrderEventMessage $event Event.
	 */
	public function sale( $event ) {
		$this->auth( $event );
	}

	/**
	 * Auth
	 *
	 * @param OrderEventMessage $event Event.
	 */
	public function auth( $event ) {
		$order        = $this->Order;
		$order_totals = $order->Cart->Totals;
		$billing      = $order->Billing;
		$paymethod    = $order->paymethod();

		shopp_add_order_event(
			$event->order,
			'authed',
			array(
				'txnid'     => time(),
				'amount'    => $order_totals->total,
				'fees'      => 0,
				'gateway'   => $paymethod->processor,
				'paymethod' => $paymethod->label,
				'paytype'   => $billing->cardtype,
				'payid'     => $billing->card,
			)
		);
	}

	/**
	 * Capture
	 *
	 * @param OrderEventMessage $event Event.
	 */
	public function capture( OrderEventMessage $event ) {
	}

	/**
	 * Refund
	 *
	 * @param OrderEventMessage $event Event.
	 */
	public function refund( OrderEventMessage $event ) {
	}

	/**
	 * Void
	 *
	 * @param OrderEventMessage $event Event.
	 */
	public function void( OrderEventMessage $event ) {
	}

	/**
	 * Checkout processed
	 */
	public function checkout_processed() {
		global $Shopp;

		$issuer_id = filter_input( INPUT_POST, 'pronamic_ideal_issuer_id', FILTER_SANITIZE_STRING );

		if ( ! empty( $issuer_id ) ) {
			$Shopp->Order->PronamicIDealIssuerId = $issuer_id;
		}
	}

	/**
	 * Process order
	 *
	 * This function is called in the 'shopp_process_order' action routine.
	 * The 'shopp_process_order' action routine is only executed after the
	 * confirmation or directly when confirmation is not required.
	 */
	public function process_order() {
		// Sets transaction information to create the purchase record
		// This call still exists for backward-compatibility (< 1.2).
		if ( Shopp::version_compare( '1.2', '<' ) ) {
			$this->Order->transaction( $this->txnid(), Shopp::PAYMENT_STATUS_PENDING );
		}

		return true;
	}

	/**
	 * Order success
	 *
	 * In Shopp version 1.1.9 the 'shopp_order_success' the purchase is given as first parameter,
	 * in Shopp version 1.2+ the 'shopp_order_success' the purchase is not passed as parameter anymore
	 *
	 * @param Purchase $purchase Purchase.
	 */
	public function order_success( $purchase = null ) {
		// Check if the purchases is passed as first parameter, if not we
		// will load the purchase from the global Shopp variable.
		if ( empty( $purchase ) ) {
			global $Shopp;

			$purchase = $Shopp->Purchase;
		}

		// Check gateway.
		$gateway = Plugin::get_gateway( $this->config_id );

		if ( ! $gateway ) {
			return;
		}

		$gateway->set_payment_method( $this->payment_method );

		$data = new PaymentData( $purchase, $this );

		// Start.
		try {
			$payment = Plugin::start( $this->config_id, $gateway, $data, $this->payment_method );

			// Redirect.
			$gateway->redirect( $payment );
		} catch ( \Pronamic\WordPress\Pay\PayException $e ) {
			$e->render();

			exit;
		}
	}

	/**
	 * Is used
	 *
	 * @param Purchase $purchase Purchase.
	 *
	 * @return bool
	 */
	private static function is_used( $purchase ) {
		if ( Shopp::version_compare( '1.2', '<' ) ) {
			$is_used = self::NAME === $purchase->gateway;
		} else {
			$is_used = __CLASS__ === $purchase->gateway;
		}

		return $is_used;
	}

	/**
	 * Inputs
	 *
	 * @param string $inputs Inputs.
	 *
	 * @return string
	 */
	public function inputs( $inputs ) {
		$result = '';

		$gateway = Plugin::get_gateway( $this->config_id );

		if ( $gateway ) {
			$gateway->set_payment_method( $this->payment_method );

			$result .= '<div id="pronamic_ideal_inputs">';
			$result .= $gateway->get_input_html();
			$result .= '</div>';

			// Only show extra fields on this paymethod/gateway.
			$script = '
				(function($) {
					$(document).bind("shopp_paymethod", function(event, paymethod) {
						if(paymethod) {
							var fields = $("#pronamic_ideal_inputs");

							if(paymethod.indexOf("' . sanitize_title_with_dashes( $this->settings['label'] ) . '") !== -1) {
								fields.show();
							} else {
								fields.hide();
							}
						}
					});
				})(jQuery);
			';

			add_storefrontjs( $script );
		}

		return $inputs . $result;
	}

	/**
	 * Payment method select options
	 */
	public function get_payment_method_select_options() {
		$gateway = Plugin::get_gateway( $this->config_id );

		if ( $gateway ) {
			return $gateway->get_payment_method_field_options();
		}

		return array(
			'' => _x( 'All', 'Payment method field', 'pronamic_ideal' ),
		);
	}

	/**
	 * Settings
	 */
	public function settings() {
		$options = Plugin::get_config_select_options();

		$this->ui->menu(
			0,
			array(
				'name'     => 'config_id',
				'keyed'    => true,
				'label'    => __( 'Select configuration', 'pronamic_ideal' ),
				'selected' => $this->config_id,
			),
			$options
		);

		$options = $this->get_payment_method_select_options();

		$this->ui->menu(
			1,
			array(
				'name'     => 'payment_method',
				'keyed'    => true,
				'label'    => __( 'Select payment method', 'pronamic_ideal' ),
				'selected' => $this->payment_method,
			),
			$options
		);
	}
}
