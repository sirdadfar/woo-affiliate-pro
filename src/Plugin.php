<?php
declare( strict_types=1 );

namespace WooAffiliatePro;

defined( 'ABSPATH' ) || exit;

use WooAffiliatePro\Install\Installer;
use WooAffiliatePro\Integration\Admin\AdminMenu;
use WooAffiliatePro\Integration\Cron;
use WooAffiliatePro\Integration\OrderListener;
use WooAffiliatePro\Integration\ReferralTracker;
use WooAffiliatePro\Integration\RestController;
use WooAffiliatePro\Integration\Shortcodes;

/**
 * نقطهٔ ورود افزونه. تمام هوک‌ها از اینجا ثبت می‌شوند تا بشود
 * با یک نگاه فهمید افزونه چه چیزی را به وردپرس وصل می‌کند.
 */
final class Plugin {

	/** @var self|null */
	private static $instance;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function boot(): void {
		Installer::maybeUpgrade();

		load_plugin_textdomain( 'woo-affiliate-pro', false, dirname( plugin_basename( WAP_FILE ) ) . '/languages' );

		( new ReferralTracker() )->register();
		( new OrderListener() )->register();
		( new Shortcodes() )->register();
		( new RestController() )->register();

		add_action( Installer::CRON_HOOK, array( Cron::class, 'run' ) );

		if ( is_admin() ) {
			( new AdminMenu() )->register();
		}
	}
}
