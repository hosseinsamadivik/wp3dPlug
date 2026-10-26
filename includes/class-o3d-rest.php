<?php
defined( 'ABSPATH' ) || exit;

final class O3D_REST {
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes() {
		register_rest_route(
			'o3d/v1',
			'/quote',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'quote' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'product_id' => array( 'required' => true, 'sanitize_callback' => 'absint' ),
					'scale'      => array( 'required' => true ),
					'options'    => array( 'required' => false, 'type' => 'object' ),
				),
			)
		);
	}

	public static function quote( WP_REST_Request $request ) {
		$product_id = absint( $request->get_param( 'product_id' ) );
		$product    = wc_get_product( $product_id );
		if ( ! $product || 'publish' !== get_post_status( $product_id ) ) {
			return new WP_Error( 'o3d_product', __( 'محصول پیدا نشد.', 'ordered-3d-print' ), array( 'status' => 404 ) );
		}

		$options = $request->get_param( 'options' );
		if ( ! is_array( $options ) ) {
			$options = array();
		}
		$quote = O3D_Pricing::quote( $product_id, $options, $request->get_param( 'scale' ) );
		if ( is_wp_error( $quote ) ) {
			$quote->add_data( array( 'status' => 400 ) );
			return $quote;
		}

		return rest_ensure_response(
			array(
				'price'      => $quote['price'],
				'formatted'  => html_entity_decode( wp_strip_all_tags( wc_price( $quote['price'] ) ), ENT_QUOTES, get_bloginfo( 'charset' ) ),
				'dimensions' => $quote['dimensions'],
				'currency'   => get_woocommerce_currency(),
			)
		);
	}
}
