<?php

namespace Pronamic\WordPress\Pay\Extensions\Shopp;

use ModuleFile;
use Pronamic\WordPress\Pay\Core\Statuses;
use Pronamic\WordPress\Pay\Payments\Payment;
use Pronamic_WP_Pay_Extensions_Shopp_Gateway;
use Purchase;
use ReflectionClass;

/**
 * Title: WordPress pay extension Shopp
 * Description:
 * Copyright: 2005-2019 Pronamic
 * Company: Pronamic
 *
 * @author  Remco Tolsma
 * @version 1.0.6
 * @since   1.0.0
 */
class Extension {
	/**
	 * Slug
	 */
	const SLUG = 'shopp';

	/**
	 * Bootstrap
	 */
	public static function bootstrap() {
		// Actions.
		add_action( 'shopp_init', array( __CLASS__, 'shopp_init' ) );
	}

	/**
	 * Initialize the Shopp Add-On
	 */
	public static function shopp_init() {
		self::add_gateway();

		add_action( 'pronamic_payment_status_update_' . self::SLUG, array( __CLASS__, 'status_update' ), 10, 2 );
		add_filter( 'pronamic_payment_source_text_' . self::SLUG, array( __CLASS__, 'source_text' ), 10, 2 );
		add_filter( 'pronamic_payment_source_description_' . self::SLUG, array( __CLASS__, 'source_description' ), 10, 2 );
		add_filter( 'pronamic_payment_source_url_' . self::SLUG, array( __CLASS__, 'source_url' ), 10, 2 );
	}

	/**
	 * Checks if Shopp is supported
	 *
	 * @return true if Shopp is supported, false otherwise
	 */
	public static function is_shopp_supported() {
		return defined( 'SHOPP_VERSION' );
	}

	/**
	 * Add the Shopp gateway
	 */
	public static function add_gateway() {
		global $Shopp;

		// Class aliases.
		class_alias( 'Pronamic_WP_Pay_Extensions_Shopp_Gateway', 'Pronamic_Shopp_IDeal_GatewayModule' );
		class_alias( 'Pronamic_WP_Pay_Extensions_Shopp_Gateway', 'Pronamic_Shopp_Gateways_IDeal_IDeal' );

		// Add gateway.
		if ( Shopp::version_compare( '1.3', '<' ) ) {
			/*
			 * Shop 1.2.9 (or lower)
			 *
			 * @link https://github.com/ingenesis/shopp/blob/1.2.9/core/model/Modules.php#L123
			 */
			$path = dirname( __FILE__ );
			$file = '/GatewayModule.php';

			$module = new ModuleFile( $path, $file );
			if ( $module->addon ) {
				$Shopp->Gateways->modules[ $module->subpackage ] = $module;
			} else {
				$Shopp->Gateways->legacy[] = md5_file( $path . $file );
			}

			if ( isset( $Shopp->Settings ) ) {
				$active_gateways = $Shopp->Settings->get( 'active_gateways' );

				if ( false !== strpos( $active_gateways, 'Pronamic_Shopp_IDeal_GatewayModule' ) ) {
					$Shopp->Gateways->activated[] = 'Pronamic_Shopp_IDeal_GatewayModule';
				}
			}
		} else {
			/*
			 * Shop 1.3 (or higher)
			 *
			 * @link https://github.com/ingenesis/shopp/blob/1.3/core/library/Modules.php#L262
			 */
			$class = new ReflectionClass( 'GatewayModules' );

			$property = $class->getProperty( 'paths' );
			$property->setAccessible( true );

			$paths = $property->getValue( $Shopp->Gateways );
			// @link https://github.com/ingenesis/shopp/blob/1.3/Shopp.php#L193
			$paths[] = dirname( __FILE__ );

			$property->setValue( $Shopp->Gateways, $paths );
		}
	}

	/**
	 * Update lead status of the specified advanced payment
	 *
	 * @param Payment $payment      Payment.
	 * @param bool    $can_redirect Can redirect.
	 */
	public static function status_update( Payment $payment, $can_redirect = false ) {
		if ( $payment->get_source() !== self::SLUG || ! self::is_shopp_supported() ) {
			return;
		}

		$id = $payment->get_source_id();

		$purchase = new Purchase( $id );
		$gateway  = new Pronamic_WP_Pay_Extensions_Shopp_Gateway();
		$data     = new PaymentData( $purchase, $gateway );

		if ( Shopp::is_purchase_paid( $purchase ) ) {
			return;
		}

		$url = $data->get_normal_return_url();

		switch ( $payment->status ) {
			case Statuses::CANCELLED:
				Shopp::update_purchase_status( $purchase, Shopp::PAYMENT_STATUS_CANCELLED );

				$url = $data->get_cancel_url();

				break;
			case Statuses::EXPIRED:
				Shopp::update_purchase_status( $purchase, Shopp::PAYMENT_STATUS_EXPIRED );

				break;
			case Statuses::FAILURE:
				Shopp::update_purchase_status( $purchase, Shopp::PAYMENT_STATUS_FAILURE );

				$url = $data->get_error_url();

				break;
			case Statuses::SUCCESS:
				Shopp::update_purchase_status( $purchase, Shopp::PAYMENT_STATUS_CAPTURED );

				$url = $data->get_success_url();

				Shopp::resession();

				break;
			case Statuses::OPEN:
				Shopp::update_purchase_status( $purchase, Shopp::PAYMENT_STATUS_OPEN );

				break;
		}

		if ( $url && $can_redirect ) {
			wp_redirect( $url );

			exit;
		}
	}

	/**
	 * Source text.
	 *
	 * @param string  $text    Source text.
	 * @param Payment $payment Payment.
	 *
	 * @return string
	 */
	public static function source_text( $text, Payment $payment ) {
		$text = __( 'Shopp', 'pronamic_ideal' ) . '<br />';

		$text .= sprintf(
			'<a href="%s">%s</a>',
			add_query_arg(
				array(
					'page' => 'shopp-orders',
					'id'   => $payment->get_source_id(),
				),
				admin_url( 'admin.php' )
			),
			sprintf(
				/* translators: %s: payment source id */
				__( 'Order #%s', 'pronamic_ideal' ),
				$payment->get_source_id()
			)
		);

		return $text;
	}

	/**
	 * Source description.
	 *
	 * @param string  $description Source description.
	 * @param Payment $payment     Payment.
	 *
	 * @return string
	 */
	public static function source_description( $description, Payment $payment ) {
		return __( 'Shopp Order', 'pronamic_ideal' );
	}

	/**
	 * Source URL.
	 *
	 * @param string  $url     Source URL.
	 * @param Payment $payment Payment.
	 *
	 * @return string
	 */
	public static function source_url( $url, Payment $payment ) {
		$url = add_query_arg(
			array(
				'page' => 'shopp-orders',
				'id'   => $payment->get_source_id(),
			),
			admin_url( 'admin.php' )
		);

		return $url;
	}
}
