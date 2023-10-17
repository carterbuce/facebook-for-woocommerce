<?php
declare( strict_types=1 );

namespace WooCommerce\Facebook\API\Campaign\Create;

defined( 'ABSPATH' ) || exit;

use WooCommerce\Facebook\API;

/**
 * Request object for the User API.
 */
class Request extends API\Request {
	/**
	 * API request constructor.
	 *
	 * @param string $account_id Facebook Ad Account Id.
	 * @param array  $data POST data for the Campaign Creation request.
	 */
	public function __construct( $account_id, $data ) {

		$path = "/act_{$account_id}/campaigns?fields=id,name";

		parent::__construct( $path, 'POST' );

		parent::set_data( $data );
	}
}
