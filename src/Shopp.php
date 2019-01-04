<?php

namespace Pronamic\WordPress\Pay\Extensions\Shopp;

use Purchase;
use Shopping;

/**
 * Title: Shopp
 * Description:
 * Copyright: 2005-2019 Pronamic
 * Company: Pronamic
 *
 * @author  Remco Tolsma
 * @version 1.0.0
 * @since   1.0.0
 */
class Shopp {
	/**
	 * Payment status pending
	 *
	 * @var string
	 */
	const PAYMENT_STATUS_PENDING = 'PENDING';

	/**
	 * Payment status expired
	 *
	 * @var string
	 */
	const PAYMENT_STATUS_EXPIRED = 'EXPIRED';

	/**
	 * Payment status cancelled
	 *
	 * @var string
	 */
	const PAYMENT_STATUS_CANCELLED = 'CANCELLED';

	/**
	 * Payment status failure
	 *
	 * @var string
	 */
	const PAYMENT_STATUS_FAILURE = 'FAILURE';

	/**
	 * Payment status charged
	 *
	 * @var string
	 */
	const PAYMENT_STATUS_CHARGED = 'CHARGED';

	/**
	 * Payment status charged
	 *
	 * @var string
	 */
	const PAYMENT_STATUS_OPEN = 'OPEN';

	/**
	 * Payment status authed
	 *
	 * @since Shopp v1.2
	 * @var string
	 */
	const PAYMENT_STATUS_AUTHED = 'authed';

	/**
	 * Payment status captured
	 *
	 * @since Shopp v1.2
	 * @var string
	 */
	const PAYMENT_STATUS_CAPTURED = 'captured';

	/**
	 * Check if Shopp is active (Automattic/developer style)
	 *
	 * @link https://bitbucket.org/Pronamic/shopp/src/12ebdb1d82a029bed956a58135833e3507baf432/Shopp.php?at=1.2.9#cl-29
	 * @link https://github.com/Automattic/developer/blob/1.1.2/developer.php#L73
	 *
	 * @return boolean
	 */
	public static function is_active() {
		return defined( 'SHOPP_VERSION' );
	}

	/**
	 * Version compare
	 *
	 * @param string $version
	 * @param string $operator
	 *
	 * @return bool|mixed
	 */
	public static function version_compare( $version, $operator ) {
		$result = true;

		// @link https://github.com/ingenesis/shopp/blob/1.3/Shopp.php#L142
		if ( defined( 'SHOPP_VERSION' ) ) {
			$result = version_compare( SHOPP_VERSION, $version, $operator );
		}

		return $result;
	}

	/**
	 * Check if the purchase is paid
	 *
	 * @param Purchase $purchase
	 *
	 * @return bool
	 */
	public static function is_purchase_paid( $purchase ) {
		if ( self::version_compare( '1.2', '<' ) ) {
			// In Shopp < 1.2 an paid purchase has not the status 'PENDING'
			$is_paid = ! in_array(
				$purchase->txnstatus,
				array(
					self::PAYMENT_STATUS_PENDING,
				),
				true
			);
		} else {
			// In Shopp >= 1.2 an paid purchase has the 'captured' status
			$is_paid = in_array(
				$purchase->txnstatus,
				array(
					self::PAYMENT_STATUS_CAPTURED,
				),
				true
			);
		}

		return $is_paid;
	}

	/**
	 * Update purchase status
	 *
	 * @param Purchase $purchase
	 * @param string   $status
	 */
	public static function update_purchase_status( $purchase, $status ) {
		global $wpdb;

		$wpdb->update(
			$wpdb->prefix . SHOPP_DBPREFIX . 'purchase',
			array( 'txnstatus' => $status ),
			array( 'id' => $purchase->id )
		);
	}

	/**
	 * Resession
	 */
	public static function resession() {
		global $Shopp;

		if ( method_exists( 'Shopping', 'resession' ) ) {
			// Shopp >= 1.2
			// @link https://github.com/ingenesis/shopp/blob/1.2/Shopp.php#L362-L368
			// @link https://github.com/ingenesis/shopp/blob/1.2/core/model/Shopping.php#L94-L135
			Shopping::resession();
		} elseif ( method_exists( $Shopp, 'resession' ) ) {
			// Shopp <= 1.1.9.1
			// @link https://github.com/ingenesis/shopp/blob/1.1.9.1/Shopp.php#L385-L423
			// @link https://github.com/ingenesis/shopp/blob/1.1/Shopp.php#L382-L413
			$Shopp->resession();
		}
	}
}
