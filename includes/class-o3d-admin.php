<?php
defined( 'ABSPATH' ) || exit;

final class O3D_Admin {
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_template_type' ) );
		add_action( 'add_meta_boxes_product', array( __CLASS__, 'add_product_box' ) );
		add_action( 'save_post_product', array( __CLASS__, 'save_product' ) );
		add_action( 'add_meta_boxes_o3d_template', array( __CLASS__, 'add_template_box' ) );
		add_action( 'save_post_o3d_template', array( __CLASS__, 'save_template' ) );
		add_action( 'admin_notices', array( __CLASS__, 'template_error_notice' ) );
	}

	public static function register_template_type() {
		register_post_type(
			'o3d_template',
			array(
				'labels'       => array(
					'name'          => __( 'قالب‌های چاپ سه‌بعدی', 'ordered-3d-print' ),
					'singular_name' => __( 'قالب گزینه‌ها', 'ordered-3d-print' ),
					'add_new_item'  => __( 'افزودن قالب گزینه‌ها', 'ordered-3d-print' ),
					'edit_item'     => __( 'ویرایش قالب گزینه‌ها', 'ordered-3d-print' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'woocommerce',
				'supports'     => array( 'title' ),
				'menu_icon'    => 'dashicons-screenoptions',
			)
		);
	}

	public static function add_product_box() {
		add_meta_box(
			'o3d_product_config',
			__( 'پیکربندی چاپ سه‌بعدی', 'ordered-3d-print' ),
			array( __CLASS__, 'render_product_box' ),
			'product',
			'normal',
			'default'
		);
	}

	public static function render_product_box( $post ) {
		wp_nonce_field( 'o3d_save_product', 'o3d_product_nonce' );
		$values = array(
			'enabled'      => get_post_meta( $post->ID, '_o3d_enabled', true ),
			'preview_url'  => get_post_meta( $post->ID, '_o3d_preview_url', true ),
			'template_id'  => absint( get_post_meta( $post->ID, '_o3d_template_id', true ) ),
			'base_x'       => get_post_meta( $post->ID, '_o3d_base_x', true ),
			'base_y'       => get_post_meta( $post->ID, '_o3d_base_y', true ),
			'base_z'       => get_post_meta( $post->ID, '_o3d_base_z', true ),
			'max_x'        => get_post_meta( $post->ID, '_o3d_max_x', true ),
			'max_y'        => get_post_meta( $post->ID, '_o3d_max_y', true ),
			'max_z'        => get_post_meta( $post->ID, '_o3d_max_z', true ),
			'min_scale'    => get_post_meta( $post->ID, '_o3d_min_scale', true ) ?: '0.5',
			'max_scale'    => get_post_meta( $post->ID, '_o3d_max_scale', true ) ?: '2',
			'mode'         => get_post_meta( $post->ID, '_o3d_pricing_mode', true ) ?: 'fixed',
			'price_per_cm3'=> get_post_meta( $post->ID, '_o3d_price_per_cm3', true ),
		);
		$templates = get_posts(
			array(
				'post_type'   => 'o3d_template',
				'post_status' => 'publish',
				'numberposts' => -1,
				'orderby'     => 'title',
				'order'       => 'ASC',
			)
		);
		?>
		<p><label><input type="checkbox" name="o3d_enabled" value="yes" <?php checked( 'yes', $values['enabled'] ); ?> /> <?php esc_html_e( 'فعال‌سازی پیکربندی برای این محصول', 'ordered-3d-print' ); ?></label></p>
		<p><label for="o3d_preview_url"><?php esc_html_e( 'نشانی فایل پیش‌نمایش STL (اختیاری)', 'ordered-3d-print' ); ?></label><br />
			<input class="widefat" type="url" id="o3d_preview_url" name="o3d_preview_url" value="<?php echo esc_attr( $values['preview_url'] ); ?>" dir="ltr" /></p>
		<p><label for="o3d_template_id"><?php esc_html_e( 'قالب گزینه‌ها', 'ordered-3d-print' ); ?></label><br />
			<select name="o3d_template_id" id="o3d_template_id"><option value="0"><?php esc_html_e( 'بدون گزینه', 'ordered-3d-print' ); ?></option>
				<?php foreach ( $templates as $template ) : ?>
					<option value="<?php echo esc_attr( $template->ID ); ?>" <?php selected( $template->ID, $values['template_id'] ); ?>><?php echo esc_html( $template->post_title ); ?></option>
				<?php endforeach; ?>
			</select></p>
		<p><?php esc_html_e( 'ابعاد مدل اصلی و بیشینه ابعاد چاپگر بر حسب میلی‌متر:', 'ordered-3d-print' ); ?></p>
		<table class="form-table"><tbody>
			<tr><th><?php esc_html_e( 'محور', 'ordered-3d-print' ); ?></th><th><?php esc_html_e( 'ابعاد مدل اصلی (mm)', 'ordered-3d-print' ); ?></th><th><?php esc_html_e( 'حداکثر چاپگر (mm)', 'ordered-3d-print' ); ?></th></tr>
			<?php foreach ( array( 'x', 'y', 'z' ) as $axis ) : ?>
				<tr><th><?php echo esc_html( strtoupper( $axis ) ); ?></th>
					<td><input type="number" min="0.01" step="0.01" name="o3d_base_<?php echo esc_attr( $axis ); ?>" value="<?php echo esc_attr( $values[ 'base_' . $axis ] ); ?>" /></td>
					<td><input type="number" min="0.01" step="0.01" name="o3d_max_<?php echo esc_attr( $axis ); ?>" value="<?php echo esc_attr( $values[ 'max_' . $axis ] ); ?>" /></td></tr>
			<?php endforeach; ?>
		</tbody></table>
		<p><label><?php esc_html_e( 'کمترین ضریب مقیاس', 'ordered-3d-print' ); ?> <input type="number" min="0.01" step="0.01" name="o3d_min_scale" value="<?php echo esc_attr( $values['min_scale'] ); ?>" /></label>
			&nbsp; <label><?php esc_html_e( 'بیشترین ضریب مقیاس', 'ordered-3d-print' ); ?> <input type="number" min="0.01" step="0.01" name="o3d_max_scale" value="<?php echo esc_attr( $values['max_scale'] ); ?>" /></label></p>
		<p><label for="o3d_pricing_mode"><?php esc_html_e( 'روش قیمت‌گذاری', 'ordered-3d-print' ); ?></label>
			<select name="o3d_pricing_mode" id="o3d_pricing_mode">
				<option value="fixed" <?php selected( 'fixed', $values['mode'] ); ?>><?php esc_html_e( 'قیمت پایه ثابت', 'ordered-3d-print' ); ?></option>
				<option value="volume" <?php selected( 'volume', $values['mode'] ); ?>><?php esc_html_e( 'قیمت پایه + حجم جعبه (cm³)', 'ordered-3d-print' ); ?></option>
			</select>
			<label><?php esc_html_e( 'مبلغ هر cm³', 'ordered-3d-print' ); ?> <input type="number" min="0" step="0.01" name="o3d_price_per_cm3" value="<?php echo esc_attr( $values['price_per_cm3'] ); ?>" /></label></p>
		<p class="description"><?php esc_html_e( 'قیمت پایه از قیمت محصول ووکامرس گرفته می‌شود. حجم بر اساس جعبه محدودکننده است و نه حجم دقیق مش.', 'ordered-3d-print' ); ?></p>
		<?php
	}

	public static function save_product( $post_id ) {
		if ( ! isset( $_POST['o3d_product_nonce'] ) || ! is_string( $_POST['o3d_product_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['o3d_product_nonce'] ) ), 'o3d_save_product' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		update_post_meta( $post_id, '_o3d_enabled', isset( $_POST['o3d_enabled'] ) ? 'yes' : 'no' );
		$url = isset( $_POST['o3d_preview_url'] ) && is_string( $_POST['o3d_preview_url'] ) ? esc_url_raw( wp_unslash( $_POST['o3d_preview_url'] ) ) : '';
		update_post_meta( $post_id, '_o3d_preview_url', $url );

		$template_id = isset( $_POST['o3d_template_id'] ) && is_scalar( $_POST['o3d_template_id'] ) ? absint( $_POST['o3d_template_id'] ) : 0;
		if ( $template_id && 'o3d_template' !== get_post_type( $template_id ) ) {
			$template_id = 0;
		}
		update_post_meta( $post_id, '_o3d_template_id', $template_id );

		foreach ( array( 'x', 'y', 'z' ) as $axis ) {
			foreach ( array( 'base', 'max' ) as $prefix ) {
				$key   = 'o3d_' . $prefix . '_' . $axis;
				$raw   = isset( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : null;
				$value = is_scalar( $raw ) && is_numeric( $raw ) ? (float) $raw : 0;
				update_post_meta( $post_id, '_o3d_' . $prefix . '_' . $axis, is_finite( $value ) && $value > 0 ? $value : 0 );
			}
		}

		$raw_min   = isset( $_POST['o3d_min_scale'] ) ? wp_unslash( $_POST['o3d_min_scale'] ) : null;
		$raw_max   = isset( $_POST['o3d_max_scale'] ) ? wp_unslash( $_POST['o3d_max_scale'] ) : null;
		$min_scale = is_scalar( $raw_min ) && is_numeric( $raw_min ) ? (float) $raw_min : 0.5;
		$max_scale = is_scalar( $raw_max ) && is_numeric( $raw_max ) ? (float) $raw_max : 2;
		if ( ! is_finite( $min_scale ) || $min_scale <= 0 ) {
			$min_scale = 0.5;
		}
		if ( ! is_finite( $max_scale ) || $max_scale < $min_scale ) {
			$max_scale = $min_scale;
		}
		update_post_meta( $post_id, '_o3d_min_scale', $min_scale );
		update_post_meta( $post_id, '_o3d_max_scale', $max_scale );

		$mode = isset( $_POST['o3d_pricing_mode'] ) && is_string( $_POST['o3d_pricing_mode'] ) ? sanitize_key( wp_unslash( $_POST['o3d_pricing_mode'] ) ) : 'fixed';
		update_post_meta( $post_id, '_o3d_pricing_mode', in_array( $mode, array( 'fixed', 'volume' ), true ) ? $mode : 'fixed' );
		$raw_rate = isset( $_POST['o3d_price_per_cm3'] ) ? wp_unslash( $_POST['o3d_price_per_cm3'] ) : null;
		$rate     = is_scalar( $raw_rate ) && is_numeric( $raw_rate ) ? (float) $raw_rate : 0;
		update_post_meta( $post_id, '_o3d_price_per_cm3', is_finite( $rate ) && $rate > 0 ? $rate : 0 );
	}

	public static function add_template_box() {
		add_meta_box(
			'o3d_template_options',
			__( 'گزینه‌های قابل انتخاب', 'ordered-3d-print' ),
			array( __CLASS__, 'render_template_box' ),
			'o3d_template',
			'normal',
			'default'
		);
	}

	public static function render_template_box( $post ) {
		wp_nonce_field( 'o3d_save_template', 'o3d_template_nonce' );
		$config = get_post_meta( $post->ID, '_o3d_config', true );
		if ( ! is_array( $config ) ) {
			$config = array(
				'options' => array(
					array(
						'key'      => 'material',
						'label'    => 'جنس',
						'type'     => 'select',
						'required' => true,
						'choices'  => array(
							array( 'value' => 'pla', 'label' => 'PLA', 'price_delta' => 0 ),
							array( 'value' => 'petg', 'label' => 'PETG', 'price_delta' => 3 ),
						),
					),
				),
			);
		}
		?>
		<p><?php esc_html_e( 'تعریف گزینه‌ها با ساختار JSON. برای هر گزینه، key پایدار و حداقل یک انتخاب لازم است. فقط نوع select پشتیبانی می‌شود.', 'ordered-3d-print' ); ?></p>
		<textarea class="widefat" rows="18" dir="ltr" name="o3d_template_json"><?php echo esc_textarea( wp_json_encode( $config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); ?></textarea>
		<p class="description"><?php esc_html_e( 'ساختار نمونه: {"options":[{"key":"material","label":"جنس","type":"select","required":true,"choices":[{"value":"pla","label":"PLA","price_delta":0}]}]}', 'ordered-3d-print' ); ?></p>
		<?php
	}

	public static function save_template( $post_id ) {
		if ( ! isset( $_POST['o3d_template_nonce'] ) || ! is_string( $_POST['o3d_template_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['o3d_template_nonce'] ) ), 'o3d_save_template' ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) || ! isset( $_POST['o3d_template_json'] ) || ! is_string( $_POST['o3d_template_json'] ) ) {
			return;
		}

		$raw    = wp_unslash( $_POST['o3d_template_json'] );
		$config = json_decode( $raw, true );
		$valid  = self::validate_template_config( $config );
		if ( is_wp_error( $valid ) ) {
			set_transient( 'o3d_template_error_' . get_current_user_id(), $valid->get_error_message(), 30 );
			return;
		}
		update_post_meta( $post_id, '_o3d_config', $valid );
	}

	private static function validate_template_config( $config ) {
		if ( ! is_array( $config ) || empty( $config['options'] ) || ! is_array( $config['options'] ) ) {
			return new WP_Error( 'o3d_template_json', __( 'ساختار JSON قالب معتبر نیست یا گزینه‌ای تعریف نشده است.', 'ordered-3d-print' ) );
		}

		$options = array();
		$keys    = array();
		foreach ( $config['options'] as $option ) {
			if ( ! is_array( $option ) || ! isset( $option['key'], $option['label'] ) || ! is_scalar( $option['key'] ) || ! is_scalar( $option['label'] ) || empty( $option['key'] ) || empty( $option['label'] ) || empty( $option['choices'] ) || ! is_array( $option['choices'] ) ) {
				return new WP_Error( 'o3d_template_option', __( 'هر گزینه باید key، label و حداقل یک انتخاب داشته باشد.', 'ordered-3d-print' ) );
			}
			$key = sanitize_key( $option['key'] );
			if ( '' === $key || in_array( $key, $keys, true ) ) {
				return new WP_Error( 'o3d_template_key', __( 'شناسه گزینه خالی یا تکراری است.', 'ordered-3d-print' ) );
			}
			$keys[]  = $key;
			$choices = array();
			$values  = array();
			foreach ( $option['choices'] as $choice ) {
				if ( ! is_array( $choice ) ) {
					return new WP_Error( 'o3d_template_choice', __( 'یکی از انتخاب‌ها معتبر نیست.', 'ordered-3d-print' ) );
				}
				$value = isset( $choice['value'] ) && is_scalar( $choice['value'] ) ? sanitize_key( $choice['value'] ) : '';
				$label = isset( $choice['label'] ) && is_scalar( $choice['label'] ) ? sanitize_text_field( $choice['label'] ) : '';
				$raw_delta = $choice['price_delta'] ?? 0;
				$delta     = is_scalar( $raw_delta ) && is_numeric( $raw_delta ) ? (float) $raw_delta : NAN;
				if ( '' === $value || '' === $label || in_array( $value, $values, true ) || ! is_finite( $delta ) || $delta < 0 ) {
					return new WP_Error( 'o3d_template_choice', __( 'یکی از انتخاب‌ها دارای شناسه، عنوان یا مبلغ نامعتبر است.', 'ordered-3d-print' ) );
				}
				$values[]  = $value;
				$choices[] = array( 'value' => $value, 'label' => $label, 'price_delta' => $delta );
			}
			$options[] = array(
				'key'      => $key,
				'label'    => sanitize_text_field( $option['label'] ),
				'type'     => 'select',
				'required' => ! empty( $option['required'] ),
				'choices'  => $choices,
			);
		}
		return array( 'options' => $options );
	}

	public static function template_error_notice() {
		$key     = 'o3d_template_error_' . get_current_user_id();
		$message = get_transient( $key );
		if ( ! $message ) {
			return;
		}
		delete_transient( $key );
		printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html( $message ) );
	}
}
