<?php
/**
 * Plugin Name: Ordered 3D Print Configurator
 * Description: پیکربندی و قیمت‌گذاری چاپ سه‌بعدی برای فروشگاه ووکامرس.
 * Version: 1.0.0
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Text Domain: ordered-3d-print
 */

defined( 'ABSPATH' ) || exit;

define( 'O3D_VERSION', '1.0.0' );
define( 'O3D_PATH', plugin_dir_path( __FILE__ ) );
define( 'O3D_URL', plugin_dir_url( __FILE__ ) );

require_once O3D_PATH . 'includes/class-o3d-pricing.php';
require_once O3D_PATH . 'includes/class-o3d-admin.php';
require_once O3D_PATH . 'includes/class-o3d-rest.php';
require_once O3D_PATH . 'includes/class-o3d-cart.php';

add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function () {
					echo '<div class="notice notice-error"><p>' .
						esc_html__( 'افزونه پیکربندی چاپ سه‌بعدی به ووکامرس نیاز دارد.', 'ordered-3d-print' ) .
						'</p></div>';
				}
			);
			return;
		}

		O3D_Admin::init();
		O3D_REST::init();
		O3D_Cart::init();
	}
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return;
		}

		wp_enqueue_style(
			'o3d-configurator',
			O3D_URL . 'assets/css/configurator.css',
			array(),
			O3D_VERSION
		);
		wp_enqueue_script(
			'o3d-configurator',
			O3D_URL . 'assets/js/configurator.js',
			array(),
			O3D_VERSION,
			true
		);
		wp_localize_script(
			'o3d-configurator',
			'o3dSettings',
			array(
				'quoteUrl' => esc_url_raw( rest_url( 'o3d/v1/quote' ) ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'currency' => get_woocommerce_currency_symbol(),
				'strings'  => array(
					'loading' => __( 'در حال محاسبه…', 'ordered-3d-print' ),
					'error'   => __( 'محاسبه قیمت انجام نشد. انتخاب‌ها را بررسی کنید.', 'ordered-3d-print' ),
					'preview' => __( 'پیش‌نمایش سه‌بعدی در دسترس نیست.', 'ordered-3d-print' ),
				),
			)
		);
	}
);
