<?php
/**
 * تست مارک‌آپ قالب‌ها.
 *
 * قالب‌ها به وردپرس وابسته‌اند، پس چند تابع خروجی وردپرس در همین فایل
 * با حداقل رفتار لازم شبیه‌سازی شده‌اند (بدون دیتابیس و بدون نصب وردپرس).
 * هدف تست، «قرارداد» مارک‌آپ است نه ظاهر: نام فیلدها، nonce، برچسب
 * ستون‌ها با data-label و اتصال خلاصهٔ خطا به فیلدهای نامعتبر.
 *
 * @package WooAffiliatePro
 */

declare( strict_types=1 );

namespace {

	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', __DIR__ . '/' );
	}

	if ( ! function_exists( 'esc_html' ) ) {
		/**
		 * @param mixed $text
		 */
		function esc_html( $text ): string {
			return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
		}
	}

	if ( ! function_exists( 'esc_attr' ) ) {
		/**
		 * @param mixed $text
		 */
		function esc_attr( $text ): string {
			return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
		}
	}

	if ( ! function_exists( 'esc_url' ) ) {
		/**
		 * @param mixed $url
		 */
		function esc_url( $url ): string {
			return htmlspecialchars( (string) $url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
		}
	}

	if ( ! function_exists( 'esc_textarea' ) ) {
		/**
		 * @param mixed $text
		 */
		function esc_textarea( $text ): string {
			return esc_html( $text );
		}
	}

	if ( ! function_exists( 'wp_kses_post' ) ) {
		/**
		 * @param mixed $html
		 */
		function wp_kses_post( $html ): string {
			return (string) $html;
		}
	}

	if ( ! function_exists( 'wp_nonce_field' ) ) {
		/**
		 * @param string $action
		 * @param string $name
		 */
		function wp_nonce_field( $action = '', $name = '_wpnonce' ): void {
			echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="test-nonce" />';
		}
	}

	if ( ! function_exists( 'wp_get_current_user' ) ) {
		/**
		 * @return object
		 */
		function wp_get_current_user() {
			return (object) array( 'display_name' => 'نمونهٔ کاربر' );
		}
	}

	if ( ! function_exists( 'mysql2date' ) ) {
		/**
		 * @param string $format
		 * @param string $date
		 */
		function mysql2date( $format, $date ): string {
			return substr( (string) $date, 0, 10 );
		}
	}
}

namespace WooAffiliatePro\Tests\Unit {

	use PHPUnit\Framework\TestCase;
	use WooAffiliatePro\Domain\Commission\Status;
	use WooAffiliatePro\Domain\Money;
	use WooAffiliatePro\Domain\Payout\BalanceCalculator;
	use WooAffiliatePro\Domain\Payout\PayoutStatus;

	final class TemplateMarkupTest extends TestCase {

		private const TEMPLATES = __DIR__ . '/../../templates/';

		/**
		 * @param array<string, mixed> $vars
		 */
		private function render( string $template, array $vars ): string {
			$file = self::TEMPLATES . $template;

			ob_start();
			// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- همان کاری که Shortcodes::render می‌کند.
			extract( $vars, EXTR_SKIP );
			include $file;

			return (string) ob_get_clean();
		}

		private function dom( string $html ): \DOMDocument {
			$document = new \DOMDocument();
			$previous = libxml_use_internal_errors( true );

			$document->loadHTML(
				'<?xml encoding="UTF-8"><div id="wap-test-root">' . $html . '</div>',
				LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
			);

			libxml_clear_errors();
			libxml_use_internal_errors( $previous );

			return $document;
		}

		private function normalize( string $text ): string {
			return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
		}

		/**
		 * @return array<string, mixed>
		 */
		private function dashboardVars(): array {
			$balance = ( new BalanceCalculator() )->calculate(
				array(
					array( 'amount' => 250_000, 'status' => Status::APPROVED ),
					array( 'amount' => 100_000, 'status' => Status::PAID ),
					array( 'amount' => 40_000, 'status' => Status::PENDING ),
				),
				array(
					array( 'amount' => 30_000, 'status' => PayoutStatus::PAID ),
				)
			);

			return array(
				'affiliate'   => array( 'id' => 7, 'code' => 'AB12CD34', 'status' => 'active' ),
				'balance'     => $balance,
				'refUrl'      => 'https://shop.ir/?ref=AB12CD34',
				'clicks'      => 128,
				'clicks30'    => 47,
				'commissions' => array(
					array(
						'order_id'   => 912,
						'amount'     => 150_000,
						'status'     => Status::APPROVED,
						'created_at' => '2026-02-11 10:00:00',
					),
				),
				'payouts'     => array(
					array(
						'amount'       => 30_000,
						'status'       => PayoutStatus::PAID,
						'requested_at' => '2026-02-12 09:30:00',
					),
				),
				'minPayout'   => Money::of( 100_000 ),
				'currency'    => 'IRR',
			);
		}

		public function test_dashboard_money_is_formatted_with_persian_digits(): void {
			$markup = $this->render( 'dashboard.php', $this->dashboardVars() );
			$dom    = $this->dom( $markup );
			$xpath  = new \DOMXPath( $dom );

			$values = $xpath->query( '//*[contains(concat(" ", normalize-space(@class), " "), " wap-stat__value ")]' );

			self::assertNotFalse( $values );
			self::assertSame( 3, $values->length );

			// موجودی قابل برداشت = ۳۵۰٬۰۰۰ منهای ۳۰٬۰۰۰ پرداخت‌شده.
			self::assertSame( '۳۲۰٬۰۰۰ IRR', $this->normalize( $values->item( 0 )->textContent ) );
			self::assertSame( '۳۵۰٬۰۰۰ IRR', $this->normalize( $values->item( 1 )->textContent ) );
			self::assertSame( '۴۷', $this->normalize( $values->item( 2 )->textContent ) );
		}

		public function test_every_dashboard_table_cell_carries_a_matching_data_label(): void {
			$markup = $this->render( 'dashboard.php', $this->dashboardVars() );
			$dom    = $this->dom( $markup );

			$tables    = $dom->getElementsByTagName( 'table' );
			$cellCount = 0;

			self::assertSame( 2, $tables->length, 'داشبورد باید جدول پورسانت‌ها و تسویه‌ها را داشته باشد.' );

			foreach ( $tables as $table ) {
				$headers = array();
				$body    = null;

				foreach ( $table->childNodes as $child ) {
					if ( ! $child instanceof \DOMElement ) {
						continue;
					}

					if ( 'thead' === $child->nodeName ) {
						foreach ( $child->getElementsByTagName( 'th' ) as $th ) {
							$headers[] = $this->normalize( $th->textContent );
						}
					}

					if ( 'tbody' === $child->nodeName ) {
						$body = $child;
					}
				}

				self::assertNotEmpty( $headers );
				self::assertNotNull( $body );

				foreach ( $body->getElementsByTagName( 'tr' ) as $row ) {
					$column = 0;

					foreach ( $row->childNodes as $cell ) {
						if ( ! $cell instanceof \DOMElement || 'td' !== $cell->nodeName ) {
							continue;
						}

						if ( $cell->hasAttribute( 'colspan' ) ) {
							continue;
						}

						self::assertTrue(
							$cell->hasAttribute( 'data-label' ),
							'هر سلول جدول باید data-label داشته باشد تا در موبایل به کارت تبدیل شود.'
						);

						self::assertSame(
							$headers[ $column ] ?? null,
							$this->normalize( $cell->getAttribute( 'data-label' ) ),
							'data-label باید با عنوان همان ستون یکی باشد.'
						);

						++$column;
						++$cellCount;
					}
				}
			}

			self::assertGreaterThanOrEqual( 7, $cellCount, 'تست نباید با جدول خالی از معنا بیفتد.' );
		}

		public function test_dashboard_renders_status_badges_with_persian_labels(): void {
			$markup = $this->render( 'dashboard.php', $this->dashboardVars() );
			$dom    = $this->dom( $markup );
			$xpath  = new \DOMXPath( $dom );

			$badges = $xpath->query( '//span[contains(@class, "wap-badge")]' );

			self::assertNotFalse( $badges );
			self::assertSame( 2, $badges->length );

			self::assertStringContainsString( 'wap-badge--approved', $badges->item( 0 )->getAttribute( 'class' ) );
			self::assertSame( 'تأیید شده', $this->normalize( $badges->item( 0 )->textContent ) );

			// وضعیت تسویه باید برچسب فارسی داشته باشد، نه مقدار خام دیتابیس.
			self::assertStringContainsString( 'wap-badge--paid', $badges->item( 1 )->getAttribute( 'class' ) );
			self::assertSame( 'پرداخت شده', $this->normalize( $badges->item( 1 )->textContent ) );
		}

		public function test_dashboard_empty_state_keeps_the_original_sentence(): void {
			$vars              = $this->dashboardVars();
			$vars['commissions'] = array();

			$markup = $this->render( 'dashboard.php', $vars );

			self::assertStringContainsString( 'هنوز پورسانتی ثبت نشده است.', $markup );
			self::assertStringContainsString( 'colspan="4"', $markup );
		}

		public function test_register_form_preserves_names_nonces_and_option_values(): void {
			$markup = $this->render( 'register.php', array( 'notice' => '', 'errors' => array() ) );
			$dom    = $this->dom( $markup );
			$xpath  = new \DOMXPath( $dom );

			$form = $xpath->query( '//form' )->item( 0 );
			self::assertInstanceOf( \DOMElement::class, $form );
			self::assertSame( 'post', strtolower( $form->getAttribute( 'method' ) ) );

			$names = array();
			foreach ( $xpath->query( '//form//*[self::input or self::select or self::textarea][@name]' ) as $input ) {
				$names[] = $input->getAttribute( 'name' );
			}

			self::assertSame(
				array( 'wap_register_nonce', 'wap_action', 'wap_name', 'wap_mobile', 'wap_payout_method', 'wap_payout_details' ),
				$names
			);

			$nonce = $xpath->query( '//input[@name="wap_register_nonce"]' )->item( 0 );
			self::assertInstanceOf( \DOMElement::class, $nonce );
			self::assertSame( 'wap_register_nonce', $nonce->getAttribute( 'name' ) );

			$action = $xpath->query( '//input[@name="wap_action"]' )->item( 0 );
			self::assertInstanceOf( \DOMElement::class, $action );
			self::assertSame( 'register', $action->getAttribute( 'value' ) );

			$options = array();
			foreach ( $xpath->query( '//select[@name="wap_payout_method"]/option' ) as $option ) {
				$options[] = $option->getAttribute( 'value' );
			}

			self::assertSame( array( 'card', 'bank', 'wallet' ), $options );
		}

		public function test_every_register_control_has_a_label_hint_and_an_existing_describedby_target(): void {
			$markup = $this->render( 'register.php', array( 'notice' => '', 'errors' => array() ) );
			$dom    = $this->dom( $markup );
			$xpath  = new \DOMXPath( $dom );

			$controls = $xpath->query( '//form//*[self::select or self::textarea or (self::input and @type != "hidden")]' );

			self::assertNotFalse( $controls );
			self::assertSame( 4, $controls->length );

			foreach ( $controls as $control ) {
				$id = $control->getAttribute( 'id' );

				self::assertNotSame( '', $id, 'هر کنترل باید شناسه داشته باشد.' );
				self::assertSame(
					1,
					$xpath->query( '//label[@for="' . $id . '"]' )->length,
					'هر کنترل باید برچسب داشته باشد: ' . $id
				);

				$describedBy = trim( $control->getAttribute( 'aria-describedby' ) );
				self::assertNotSame( '', $describedBy, 'هر کنترل باید راهنمای متصل داشته باشد: ' . $id );

				foreach ( preg_split( '/\s+/', $describedBy ) ?: array() as $target ) {
					self::assertSame(
						1,
						$xpath->query( '//*[@id="' . $target . '"]' )->length,
						'شناسهٔ مقصد aria-describedby وجود ندارد: ' . $target
					);
				}
			}
		}

		public function test_failed_registration_exposes_a_focusable_error_summary_linked_to_invalid_fields(): void {
			$errors = array(
				'wap_name'   => 'نام و نام خانوادگی را وارد کنید.',
				'wap_mobile' => 'شمارهٔ موبایل باید 11 رقم باشد و با 09 شروع شود.',
			);

			$markup = $this->render(
				'register.php',
				array(
					'notice' => 'نام و شمارهٔ موبایل (مثل 09121234567) را درست وارد کنید.',
					'errors' => $errors,
				)
			);
			$dom   = $this->dom( $markup );
			$xpath = new \DOMXPath( $dom );

			$summary = $xpath->query( '//*[@data-wap-error-summary]' )->item( 0 );

			self::assertInstanceOf( \DOMElement::class, $summary );
			self::assertSame( 'alert', $summary->getAttribute( 'role' ) );
			self::assertSame( '-1', $summary->getAttribute( 'tabindex' ) );

			$title = $xpath->query( '//*[@id="wap-errorsummary-title"]' )->item( 0 );

			self::assertInstanceOf( \DOMElement::class, $title );
			self::assertSame(
				'نام و شمارهٔ موبایل (مثل 09121234567) را درست وارد کنید.',
				$this->normalize( $title->textContent )
			);

			$links = $xpath->query( '//*[@data-wap-error-summary]//a[starts-with(@href, "#")]' );

			self::assertNotFalse( $links );
			self::assertSame( 2, $links->length );

			foreach ( $links as $link ) {
				$targetId = substr( $link->getAttribute( 'href' ), 1 );
				$target   = $xpath->query( '//*[@id="' . $targetId . '"]' )->item( 0 );

				self::assertInstanceOf( \DOMElement::class, $target, 'لینک خلاصه به فیلد موجود اشاره نمی‌کند: ' . $targetId );
				self::assertSame( 'true', $target->getAttribute( 'aria-invalid' ) );
				self::assertSame(
					1,
					$xpath->query( '//*[@id="' . $targetId . '-error"]' )->length,
					'خطای درون‌خطی برای فیلد وجود ندارد: ' . $targetId
				);
			}
		}

		public function test_decoded_icons_are_decorative_for_assistive_technology(): void {
			$markup = $this->render( 'dashboard.php', $this->dashboardVars() )
				. $this->render( 'register.php', array( 'notice' => '', 'errors' => array() ) )
				. $this->render( 'notice.php', array( 'message' => 'پیام نمونه', 'type' => 'success' ) );

			$dom   = $this->dom( $markup );
			$xpath = new \DOMXPath( $dom );

			$icons = $xpath->query( '//svg' );

			self::assertNotFalse( $icons );
			self::assertGreaterThan( 5, $icons->length );

			foreach ( $icons as $icon ) {
				$decorative = 'true' === $icon->getAttribute( 'aria-hidden' )
					|| '' !== $icon->getAttribute( 'aria-label' )
					|| 'img' === $icon->getAttribute( 'role' );

				self::assertTrue( $decorative, 'هر SVG باید aria-hidden یا برچسب داشته باشد.' );
				self::assertSame( 'currentColor', $icon->getAttribute( 'stroke' ) );
			}
		}

		public function test_notice_uses_alert_only_for_errors(): void {
			$error = $this->dom( $this->render( 'notice.php', array( 'message' => 'خطا', 'type' => 'error' ) ) );
			$ok    = $this->dom( $this->render( 'notice.php', array( 'message' => 'انجام شد', 'type' => 'success' ) ) );
			$info  = $this->dom( $this->render( 'notice.php', array( 'message' => 'اطلاع', 'type' => 'info' ) ) );

			self::assertSame( 'alert', $this->noticeRole( $error ) );
			self::assertSame( 'status', $this->noticeRole( $ok ) );
			self::assertSame( 'status', $this->noticeRole( $info ) );
		}

		private function noticeRole( \DOMDocument $document ): string {
			$xpath  = new \DOMXPath( $document );
			$notice = $xpath->query( '//*[contains(concat(" ", normalize-space(@class), " "), " wap-notice ")]' )->item( 0 );

			self::assertInstanceOf( \DOMElement::class, $notice );

			return $notice->getAttribute( 'role' );
		}
	}
}
