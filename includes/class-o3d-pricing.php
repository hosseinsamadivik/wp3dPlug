<?php
defined( 'ABSPATH' ) || exit;

final class O3D_Pricing {
	public static function product_config( $product_id ) {
		$template_id = absint( get_post_meta( $product_id, '_o3d_template_id', true ) );
		$options     = array();

		if ( $template_id && 'publish' === get_post_status( $template_id ) && 'o3d_template' === get_post_type( $template_id ) ) {
			$saved   = get_post_meta( $template_id, '_o3d_config', true );
			$options = is_array( $saved ) && isset( $saved['options'] ) && is_array( $saved['options'] )
				? $saved['options']
				: array();
		}

		return array(
			'preview_url' => esc_url_raw( get_post_meta( $product_id, '_o3d_preview_url', true ) ),
			'base_dims'   => array(
				'x' => (float) get_post_meta( $product_id, '_o3d_base_x', true ),
				'y' => (float) get_post_meta( $product_id, '_o3d_base_y', true ),
				'z' => (float) get_post_meta( $product_id, '_o3d_base_z', true ),
			),
			'max_dims'    => array(
				'x' => (float) get_post_meta( $product_id, '_o3d_max_x', true ),
				'y' => (float) get_post_meta( $product_id, '_o3d_max_y', true ),
				'z' => (float) get_post_meta( $product_id, '_o3d_max_z', true ),
			),
			'min_scale'   => (float) get_post_meta( $product_id, '_o3d_min_scale', true ),
			'max_scale'   => (float) get_post_meta( $product_id, '_o3d_max_scale', true ),
			'mode'        => get_post_meta( $product_id, '_o3d_pricing_mode', true ) ?: 'fixed',
			'per_cm3'     => (float) get_post_meta( $product_id, '_o3d_price_per_cm3', true ),
			'options'     => $options,
		);
	}

	public static function quote( $product_id, array $selected, $scale ) {
		$product = wc_get_product( $product_id );
		$config  = self::product_config( $product_id );

		if ( ! $product || ! $product->is_type( 'simple' ) || 'yes' !== get_post_meta( $product_id, '_o3d_enabled', true ) ) {
			return new WP_Error( 'o3d_product', __( 'این محصول برای پیکربندی در دسترس نیست.', 'ordered-3d-print' ) );
		}

		if ( ! is_numeric( $scale ) || ! is_finite( (float) $scale ) ) {
			return new WP_Error( 'o3d_scale', __( 'اندازه انتخاب‌شده معتبر نیست.', 'ordered-3d-print' ) );
		}

		$scale = (float) $scale;
		if ( $scale <= 0 || $scale < $config['min_scale'] || $scale > $config['max_scale'] ) {
			return new WP_Error( 'o3d_scale', __( 'اندازه انتخاب‌شده خارج از محدوده مجاز است.', 'ordered-3d-print' ) );
		}

		$dimensions = array();
		foreach ( array( 'x', 'y', 'z' ) as $axis ) {
			$dimension = $config['base_dims'][ $axis ] * $scale;
			$maximum   = $config['max_dims'][ $axis ];
			if ( ! is_finite( $dimension ) || $dimension <= 0 || $maximum <= 0 || $dimension > $maximum ) {
				return new WP_Error( 'o3d_dimensions', __( 'ابعاد انتخاب‌شده از محدوده چاپگر بیشتر است.', 'ordered-3d-print' ) );
			}
			$dimensions[ $axis ] = round( $dimension, 2 );
		}

		$allowed_keys = array();
		$total        = (float) $product->get_price();
		if ( ! is_finite( $total ) || $total < 0 ) {
			return new WP_Error( 'o3d_price', __( 'قیمت پایه محصول معتبر نیست.', 'ordered-3d-print' ) );
		}

		if ( 'volume' === $config['mode'] ) {
			$volume = ( $dimensions['x'] * $dimensions['y'] * $dimensions['z'] ) / 1000;
			$total += $volume * max( 0, $config['per_cm3'] );
		}

		$normalized = array();
		$selections = array();
		foreach ( $config['options'] as $option ) {
			if ( ! is_array( $option ) || empty( $option['key'] ) || empty( $option['choices'] ) || ! is_array( $option['choices'] ) ) {
				continue;
			}
			$key            = sanitize_key( $option['key'] );
			$allowed_keys[] = $key;
			$raw_value = $selected[ $key ] ?? '';
			if ( ! is_scalar( $raw_value ) ) {
				return new WP_Error( 'o3d_choice', __( 'یکی از گزینه‌های انتخاب‌شده معتبر نیست.', 'ordered-3d-print' ) );
			}
			$value = sanitize_text_field( (string) $raw_value );
			if ( ! empty( $option['required'] ) && '' === $value ) {
				return new WP_Error(
					'o3d_required',
					sprintf( __( 'لطفاً گزینه «%s» را انتخاب کنید.', 'ordered-3d-print' ), sanitize_text_field( $option['label'] ?? $key ) )
				);
			}
			if ( '' === $value ) {
				continue;
			}

			$choice_found = false;
			foreach ( $option['choices'] as $choice ) {
				if ( ! is_array( $choice ) || ! isset( $choice['value'] ) || (string) $choice['value'] !== $value ) {
					continue;
				}
				$delta = isset( $choice['price_delta'] ) && is_numeric( $choice['price_delta'] ) ? (float) $choice['price_delta'] : 0;
				if ( ! is_finite( $delta ) || $delta < 0 ) {
					return new WP_Error( 'o3d_option_price', __( 'تنظیم قیمت یکی از گزینه‌ها معتبر نیست.', 'ordered-3d-print' ) );
				}
				$total        += $delta;
				$normalized[] = array(
					'key'   => $key,
					'label' => sanitize_text_field( $option['label'] ?? $key ),
					'value' => sanitize_text_field( $choice['label'] ?? $value ),
				);
				$selections[ $key ] = $value;
				$choice_found = true;
				break;
			}
			if ( ! $choice_found ) {
				return new WP_Error( 'o3d_choice', __( 'یکی از گزینه‌های انتخاب‌شده معتبر نیست.', 'ordered-3d-print' ) );
			}
		}

		foreach ( $selected as $key => $value ) {
			if ( ! is_scalar( $key ) || ! is_scalar( $value ) ) {
				return new WP_Error( 'o3d_choice', __( 'یکی از گزینه‌های انتخاب‌شده معتبر نیست.', 'ordered-3d-print' ) );
			}
			if ( ! in_array( sanitize_key( $key ), $allowed_keys, true ) && '' !== (string) $value ) {
				return new WP_Error( 'o3d_unknown_option', __( 'گزینه ارسال‌شده معتبر نیست.', 'ordered-3d-print' ) );
			}
		}

		if ( ! is_finite( $total ) ) {
			return new WP_Error( 'o3d_price', __( 'قیمت محاسبه‌شده معتبر نیست.', 'ordered-3d-print' ) );
		}

		return array(
			'price'      => round( $total, wc_get_price_decimals() ),
			'options'    => $normalized,
			'selections' => $selections,
			'scale'      => $scale,
			'dimensions' => $dimensions,
		);
	}
}
