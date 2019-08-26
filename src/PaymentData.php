<?php

namespace Pronamic\WordPress\Pay\Extensions\Shopp;

use GatewayFramework;
use Pronamic\WordPress\Pay\Payments\PaymentData as Pay_PaymentData;
use Pronamic\WordPress\Pay\Payments\Item;
use Pronamic\WordPress\Pay\Payments\Items;
use Purchase;

/**
 * Title: Shopp payment data
 * Description:
 * Copyright: 2005-2019 Pronamic
 * Company: Pronamic
 *
 * @author  Remco Tolsma
 * @version 2.0.1
 * @since   1.0.0
 */
class PaymentData extends Pay_PaymentData {
	/**
	 * Purchase
	 *
	 * @see /shopp/core/model/Purchase.php
	 * @var Purchase
	 */
	private $purchase;

	/**
	 * Gateway
	 *
	 * @see /shopp/core/model/Gateway.php
	 * @var GatewayFramework
	 */
	private $gateway;

	/**
	 * Constructs and initialize an Shopp iDEAL data proxy
	 *
	 * @param Purchase         $purchase Shopp purchase.
	 * @param GatewayFramework $gateway  Gateway.
	 */
	public function __construct( $purchase, $gateway ) {
		parent::__construct();

		$this->purchase = $purchase;
		$this->gateway  = $gateway;
	}

	/**
	 * Get source indicator
	 *
	 * @return string
	 */
	public function get_source() {
		return 'shopp';
	}

	/**
	 * Get description
	 *
	 * @see Pronamic_Pay_PaymentDataInterface::get_description()
	 * @return string
	 */
	public function get_description() {
		return sprintf(
			/* translators: %s: order id */
			__( 'Order %s', 'pronamic_ideal' ),
			$this->purchase->id
		);
	}

	/**
	 * Get order ID
	 *
	 * @see Pronamic_Pay_PaymentDataInterface::get_order_id()
	 * @return string
	 */
	public function get_order_id() {
		return $this->purchase->id;
	}

	/**
	 * Get items
	 *
	 * @see Pronamic_Pay_PaymentDataInterface::get_items()
	 * @return Items
	 */
	public function get_items() {
		$items = new Items();

		// Item
		// We only add one total item, because iDEAL cant work with negative price items (discount).
		$item = new Item();
		$item->set_number( $this->purchase->id );
		$item->set_description(
			sprintf(
				/* translators: %s: order id */
				__( 'Order %s', 'pronamic_ideal' ),
				$this->purchase->id
			)
		);
		$item->set_price( $this->purchase->total );
		$item->set_quantity( 1 );

		$items->addItem( $item );

		return $items;
	}

	/**
	 * Get currency alphabetic code.
	 *
	 * @return string
	 */
	public function get_currency_alphabetic_code() {
		// @see /shopp/core/model/Lookup.php#L58
		// @see /shopp/core/model/Gateway.php
		return $this->gateway->baseop['currency']['code'];
	}

	/**
	 * Get email address.
	 *
	 * @return string
	 */
	public function get_email() {
		// @see /shopp/core/model/Purchase.php
		return $this->purchase->email;
	}

	/**
	 * Get first name.
	 *
	 * @return string
	 */
	public function get_first_name() {
		// @see /shopp/core/model/Purchase.php
		return $this->purchase->firstname;
	}

	/**
	 * Get last name.
	 *
	 * @return string
	 */
	public function get_last_name() {
		// @see /shopp/core/model/Purchase.php
		return $this->purchase->lastname;
	}

	/**
	 * Get customer name.
	 *
	 * @return string
	 */
	public function get_customer_name() {
		// @see /shopp/core/model/Purchase.php
		return $this->purchase->firstname . ' ' . $this->purchase->lastname;
	}

	/**
	 * Get address.
	 *
	 * @return string
	 */
	public function get_address() {
		// @see /shopp/core/model/Purchase.php
		return $this->purchase->address;
	}

	/**
	 * Get city.
	 *
	 * @return string
	 */
	public function get_city() {
		// @see /shopp/core/model/Purchase.php
		return $this->purchase->city;
	}

	/**
	 * Get ZIP.
	 *
	 * @return string
	 */
	public function get_zip() {
		// @see /shopp/core/model/Purchase.php
		return $this->purchase->postcode;
	}

	/**
	 * Get normal return URL.
	 *
	 * Shoppurl default pages:
	 * catalog, account, cart, checkout, confirm, thanks
	 */
	public function get_normal_return_url() {
		// @see /shopp/core/functions.php#L1873
		// @see /shopp/core/flow/Storefront.php#L1364
		return shoppurl( array( 'messagetype' => 'open' ), 'thanks' );
	}

	/**
	 * Get cancel URL.
	 *
	 * @return string
	 */
	public function get_cancel_url() {
		// @see /shopp/core/functions.php#L1873
		// @see /shopp/core/flow/Storefront.php#L1364
		return shoppurl( array( 'messagetype' => 'cancelled' ), 'thanks' );
	}

	/**
	 * Get success URL.
	 *
	 * @return string
	 */
	public function get_success_url() {
		// @see /shopp/core/functions.php#L1873
		// @see /shopp/core/flow/Storefront.php#L1364
		return shoppurl( false, 'thanks' );
	}

	/**
	 * Get error URL.
	 *
	 * @return string
	 */
	public function get_error_url() {
		// @see /shopp/core/functions.php#L1873
		// @see /shopp/core/flow/Storefront.php#L1364
		return shoppurl( array( 'messagetype' => 'error' ), 'thanks' );
	}

	/**
	 * Get issuer ID.
	 *
	 * @return string
	 */
	public function get_issuer_id() {
		global $Shopp;

		return $Shopp->Order->PronamicIDealIssuerId;
	}
}
