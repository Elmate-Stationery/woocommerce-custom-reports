=== WooCommerce Custom Reports ===
Contributors: elmates
Tags: woocommerce, reports, orders, customer, phone
Requires at least: 6.2
Requires PHP: 7.4
Stable tag: 1.0.0

Custom WooCommerce admin reports. Version 1.0.0 provides a Customer Orders column in WooCommerce admin orders. The number is the current total for the billing phone. Clicking it opens WooCommerce's native order search using the `s` parameter and resets pagination to page one; it does not use a plugin-specific filter parameter.

== Changelog ==

= 1.0.0 =
* Initial release: customer order count by billing phone.

== Phone normalization ==

This release supports Bangladeshi mobile numbers. It removes spaces, punctuation and a leading plus. A 13-digit number beginning 8801 is normalized to 01XXXXXXXXX; an 11-digit number beginning 01 is used as-is. Empty or invalid values show an em dash and are not grouped.

The count link intentionally sends the order's original stored billing-phone value to WooCommerce native search. It does not send the normalized grouping key.

== Statuses ==

By default all normal WooCommerce order statuses, including pending, failed, cancelled and refunded, are counted. Change them at WooCommerce > Customer Order Count. The same selection applies to the linked filtered list.
