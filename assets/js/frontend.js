/**
 * رفتارهای کوچک پنل نماینده.
 *
 * فقط دو کار انجام می‌دهد و هیچ وابستگی یا درخواست شبکه‌ای ندارد:
 *   ۱) اگر فرم با خطا برگشته باشد، خلاصهٔ خطا را فوکوس می‌کند تا
 *      صفحه‌خوان و کاربر کیبورد بلافاصله آن را ببینند.
 *   ۲) دکمهٔ رونوشت لینک معرف را به Clipboard API وصل می‌کند و
 *      در صورت نبود دسترسی، به انتخاب دستی متن برمی‌گردد.
 */
(function () {
	'use strict';

	function focusErrorSummary() {
		var summary = document.querySelector('[data-wap-error-summary]');

		if (!summary) {
			return;
		}

		if (!summary.hasAttribute('tabindex')) {
			summary.setAttribute('tabindex', '-1');
		}

		try {
			summary.focus();
		} catch (error) {
			// فوکوس کردن نباید هرگز صفحه را بشکند.
		}
	}

	function announce(node, message) {
		if (node) {
			node.textContent = message;
		}
	}

	function copyBySelection(field) {
		field.focus();
		field.select();

		try {
			return document.execCommand('copy');
		} catch (error) {
			return false;
		}
	}

	// فیلدهای readonly (مثل لینک معرف) با فوکوس یا کلیک کامل انتخاب می‌شوند
	// تا کاربر مجبور نباشد برای رونوشت دستی، خودش متن را درگ کند.
	function selectField(event) {
		var field = event.target;

		if (!field || !field.matches || !field.matches('[data-wap-select]')) {
			return;
		}

		try {
			field.select();
		} catch (error) {
			// انتخاب متن نباید هرگز صفحه را بشکند.
		}
	}

	document.addEventListener('focusin', selectField);
	document.addEventListener('click', selectField);

	document.addEventListener('click', function (event) {
		var trigger = event.target && event.target.closest ? event.target.closest('[data-wap-copy]') : null;

		if (!trigger) {
			return;
		}

		var field = document.querySelector(trigger.getAttribute('data-wap-copy') || '');

		if (!field) {
			return;
		}

		var status = document.querySelector(trigger.getAttribute('data-wap-copy-status') || '');
		var value = field.value || field.textContent || '';

		function report(copied) {
			announce(status, copied ? 'رونوشت شد.' : 'رونوشت نشد؛ لینک را دستی انتخاب کنید.');
			trigger.setAttribute('data-copied', copied ? 'true' : 'false');

			window.setTimeout(function () {
				trigger.removeAttribute('data-copied');
			}, 2400);
		}

		event.preventDefault();

		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(value).then(
				function () {
					report(true);
				},
				function () {
					report(copyBySelection(field));
				}
			);

			return;
		}

		report(copyBySelection(field));
	});

	if ('loading' === document.readyState) {
		document.addEventListener('DOMContentLoaded', focusErrorSummary);
	} else {
		focusErrorSummary();
	}
}());
