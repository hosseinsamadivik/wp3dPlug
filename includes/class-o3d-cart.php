<?php
defined( 'ABSPATH' ) || exit;

final class O3D_Cart {
	public static function init() {
		add_action( 'woocommerce_before_add_to_cart_button', array( __CLASS__, 'render_configurator' ) );
		add_filter( 'woocommerce_add_to_cart_validation', array( __CLASS__, 'validate_add' ), 10, 3 );
		add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'add_item_data' ), 10, 4 );
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'apply_price' ) );
		add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'display_item_data' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'save_order_item' ), 10, 4 );
	}

	public static function render_configurator() {
		global $product;
		if ( ! $product instanceof WC_Product || ! $product->is_type( 'simple' ) || 'yes' !== get_post_meta( $product->get_id(), '_o3d_enabled', true ) ) {
			return;
		}

		$config = O3D_Pricing::product_config( $product->get_id() );
		$initial_scale = max( $config['min_scale'], min( 1, $config['max_scale'] ) );
		?>
		<section class="o3d-configurator" data-o3d-configurator data-product-id="<?php echo esc_attr( $product->get_id() ); ?>" data-preview-url="<?php echo esc_url( $config['preview_url'] ); ?>" data-base-dimensions="<?php echo esc_attr( wp_json_encode( $config['base_dims'] ) ); ?>">
			<h3><?php esc_html_e( 'سفارشی‌سازی چاپ سه‌بعدی', 'ordered-3d-print' ); ?></h3>
			<?php if ( $config['preview_url'] ) : ?>
				<div class="o3d-preview" data-o3d-preview role="img" aria-label="<?php esc_attr_e( 'پیش‌نمایش مدل سه‌بعدی', 'ordered-3d-print' ); ?>">
					<canvas data-o3d-canvas></canvas>
					<p data-o3d-preview-message><?php esc_html_e( 'برای چرخاندن مدل، بکشید؛ برای بزرگ‌نمایی از چرخ ماوس استفاده کنید.', 'ordered-3d-print' ); ?></p>
				</div>
			<?php endif; ?>
			<label class="o3d-field">
				<span><?php esc_html_e( 'ضریب اندازه', 'ordered-3d-print' ); ?></span>
				<input type="number" name="o3d_scale" min="<?php echo esc_attr( $config['min_scale'] ); ?>" max="<?php echo esc_attr( $config['max_scale'] ); ?>" step="0.01" value="<?php echo esc_attr( $initial_scale ); ?>" required />
			</label>
			<p class="o3d-dimensions" data-o3d-dimensions><?php esc_html_e( 'ابعاد: در حال محاسبه', 'ordered-3d-print' ); ?></p>
			<?php foreach ( $config['options'] as $option ) : ?>
				<?php if ( empty( $option['key'] ) || empty( $option['choices'] ) ) { continue; } ?>
				<label class="o3d-field">
					<span><?php echo esc_html( $option['label'] ?? $option['key'] ); ?></span>
					<select name="o3d_options[<?php echo esc_attr( $option['key'] ); ?>]" <?php echo ! empty( $option['required'] ) ? 'required' : ''; ?>>
						<?php if ( empty( $option['required'] ) ) : ?><option value=""><?php esc_html_e( 'انتخاب کنید', 'ordered-3d-print' ); ?></option><?php endif; ?>
						<?php foreach ( $option['choices'] as $choice ) : ?>
							<option value="<?php echo esc_attr( $choice['value'] ); ?>"><?php echo esc_html( $choice['label'] ); ?><?php if ( ! empty( $choice['price_delta'] ) ) : ?> (+<?php echo wp_kses_post( wc_price( $choice['price_delta'] ) ); ?>)<?php endif; ?></option>
						<?php endforeach; ?>
					</select>
				</label>
			<?php endforeach; ?>
			<p class="o3d-price-row"><strong><?php esc_html_e( 'قیمت پیکربندی‌شده:', 'ordered-3d-print' ); ?></strong> <span data-o3d-price aria-live="polite"><?php echo wp_kses_post( wc_price( $product->get_price() ) ); ?></span></p>
			<p class="o3d-error" data-o3d-error role="alert" hidden></p>
			<input type="hidden" name="o3d_nonce" value="<?php echo esc_attr( wp_create_nonce( 'o3d_add_to_cart_' . $product->get_id() ) ); ?>" />
		</section>
		<?php
	}

	private static function request_data( $product_id ) {
		$nonce = isset( $_POST['o3d_nonce'] ) && is_string( $_POST['o3d_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['o3d_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'o3d_add_to_cart_' . $product_id ) ) {
			return new WP_Error( 'o3d_nonce', __( 'درخواست معتبر نیست. صفحه را تازه‌سازی کنید.', 'ordered-3d-print' ) );
		}
		$scale   = isset( $_POST['o3d_scale'] ) ? wp_unslash( $_POST['o3d_scale'] ) : null;
		$options = isset( $_POST['o3d_options'] ) ? wp_unslash( $_POST['o3d_options'] ) : array();
		if ( ! is_array( $options ) ) {
			return new WP_Error( 'o3d_options', __( 'گزینه‌های انتخاب‌شده معتبر نیستند.', 'ordered-3d-print' ) );
		}
		return O3D_Pricing::quote( $product_id, $options, $scale );
	}

	public static function validate_add( $passed, $product_id, $quantity ) {
		if ( ! $passed || 'yes' !== get_post_meta( $product_id, '_o3d_enabled', true ) ) {
			return $passed;
		}
		$quote = self::request_data( $product_id );
		if ( is_wp_error( $quote ) ) {
			wc_add_notice( $quote->get_error_message(), 'error' );
			return false;
		}
		return $passed;
	}

	public static function add_item_data( $cart_item_data, $product_id, $variation_id, $quantity ) {
		if ( 'yes' !== get_post_meta( $product_id, '_o3d_enabled', true ) ) {
			return $cart_item_data;
		}
		$quote = self::request_data( $product_id );
		if ( is_wp_error( $quote ) ) {
			$cart_item_data['o3d_invalid'] = $quote->get_error_message();
			return $cart_item_data;
		}
		$cart_item_data['o3d']        = $quote;
		$cart_item_data['o3d_unique'] = wp_generate_uuid4();
		return $cart_item_data;
	}

	public static function apply_price( $cart ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}
		if ( ! $cart instanceof WC_Cart ) {
			return;
		}
		foreach ( $cart->get_cart() as $cart_item_key => $item ) {
			if ( ! empty( $item['o3d_invalid'] ) ) {
				wc_add_notice( $item['o3d_invalid'], 'error' );
				$cart->remove_cart_item( $cart_item_key );
				continue;
			}
			if ( empty( $item['o3d'] ) || empty( $item['data'] ) || ! $item['data'] instanceof WC_Product ) {
				continue;
			}
			$quote = O3D_Pricing::quote(
				$item['product_id'],
				$item['o3d']['selections'] ?? array(),
				$item['o3d']['scale'] ?? 1
			);
			if ( is_wp_error( $quote ) ) {
				wc_add_notice( $quote->get_error_message(), 'error' );
				$cart->remove_cart_item( $cart_item_key );
				continue;
			}
			$cart->cart_contents[ $cart_item_key ]['o3d'] = $quote;
			$item['data']->set_price( $quote['price'] );
		}
	}

	public static function display_item_data( $data, $item ) {
		if ( empty( $item['o3d'] ) ) {
			return $data;
		}
		$data[] = array(
			'key'   => __( 'اندازه', 'ordered-3d-print' ),
			'value' => esc_html( number_format_i18n( $item['o3d']['scale'], 2 ) . '×' ),
		);
		foreach ( $item['o3d']['dimensions'] as $axis => $dimension ) {
			$data[] = array(
				'key'   => esc_html( strtoupper( $axis ) . ' (mm)' ),
				'value' => esc_html( number_format_i18n( $dimension, 2 ) ),
			);
		}
		foreach ( $item['o3d']['options'] as $option ) {
			$data[] = array( 'key' => esc_html( $option['label'] ), 'value' => esc_html( $option['value'] ) );
		}
		return $data;
	}

	public static function save_order_item( $item, $cart_item_key, $values, $order ) {
		if ( empty( $values['o3d'] ) || ! is_array( $values['o3d'] ) ) {
			return;
		}
		$config = $values['o3d'];
		unset( $config['selections'] );
		$item->add_meta_data( '_o3d_configuration', wp_json_encode( $config, JSON_UNESCAPED_UNICODE ), true );
		$item->add_meta_data( __( 'ضریب اندازه', 'ordered-3d-print' ), $config['scale'] . '×', true );
		foreach ( $config['options'] as $option ) {
			$item->add_meta_data( $option['label'], $option['value'], true );
		}
	}
}
