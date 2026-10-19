<?php
/**
 * Plugin Name:       Affiliate Pro for WooCommerce
 * Plugin URI:        https://github.com/sirdadfar/woo-affiliate-pro
 * Description:       برنامهٔ کامل همکاری در فروش برای ووکامرس: لینک معرف، ردیابی کلیک، پورسانت چندسطحی بر اساس دسته‌بندی، پنل نماینده و مدیریت تسویه.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            sirdadfar
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       woo-affiliate-pro
 * Domain Path:       /languages
 * WC requires at least: 7.0
 * WC tested up to:   9.4
 *
 * @package WooAffiliatePro
 */

defined( 'ABSPATH' ) || exit;

define( 'WAP_VERSION', '1.0.0' );
define( 'WAP_FILE', __FILE__ );
define( 'WAP_PATH', plugin_dir_path( __FILE__ ) );
define( 'WAP_URL', plugin_dir_url( __FILE__ ) );
define( 'WAP_DB_VERSION', '1' );

/**
 * همان‌طور که ووکامرس توصیه می‌کند، سازگاری با HPOS پیش از load اعلام می‌شود.
 * بدون این اعلام، روی فروشگاه‌هایی که جدول سفارش‌ها را به HPOS منتقل کرده‌اند
 * متادیتای سفارش در جای اشتباه ذخیره می‌شود.
 */
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

/**
 * مسیر autoloader کامپوزر. اگر کاربر افزونه را به‌صورت زیپ نصب کرده باشد،
 * پوشهٔ vendor وجود ندارد؛ در آن حالت یک autoloader سبک جایگزین می‌شود.
 */
if ( file_exists( WAP_PATH . 'vendor/autoload.php' ) ) {
	require_once WAP_PATH . 'vendor/autoload.php';
} else {
	spl_autoload_register(
		static function ( $class ) {
			$prefix = 'WooAffiliatePro\\';
			$len    = strlen( $prefix );
			if ( 0 !== strncmp( $prefix, $class, $len ) ) {
				return;
			}
			$relative = substr( $class, $len );
			$file     = WAP_PATH . 'src/' . str_replace( '\\', '/', $relative ) . '.php';
			if ( is_readable( $file ) ) {
				require_once $file;
			}
		}
	);
}

register_activation_hook( __FILE__, array( \WooAffiliatePro\Install\Installer::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \WooAffiliatePro\Install\Installer::class, 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function () {
					if ( ! current_user_can( 'activate_plugins' ) ) {
						return;
					}
					echo '<div class="notice notice-error"><p>';
					echo esc_html__( 'افزونهٔ «همکاری در فروش حرفه‌ای» به ووکامرس نیاز دارد. لطفاً اول ووکامرس را نصب و فعال کنید.', 'woo-affiliate-pro' );
					echo '</p></div>';
				}
			);
			return;
		}

		\WooAffiliatePro\Plugin::instance()->boot();
	},
	20
);
