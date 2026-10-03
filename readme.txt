=== WooCommerce Custom Reports ===
Contributors: elmates
Tags: woocommerce, reports, orders, customer, phone
Requires at least: 6.2
Requires PHP: 7.4
Stable tag: 1.1.8

Custom WooCommerce admin reports: a Customer Orders column in WooCommerce admin orders, plus sales reports under WooCommerce > Custom Report. In the column, the number is the current total for the billing phone. Clicking it opens WooCommerce's native order search using the `s` parameter and resets pagination to page one; it does not use a plugin-specific filter parameter.

== Changelog ==

= 1.1.8 =
* Fixed report Orders links showing all orders on HPOS stores: the ID filter now uses the `post__in` argument that WooCommerce's Orders table supports.

= 1.1.7 =
* Prevented legacy order filtering from running report lookups on unrelated HPOS admin queries.

= 1.1.6 =
* Made report Orders counts link to the matching native WooCommerce Orders table results.

= 1.1.5 =
* Moved Report Settings to a cross-button-only modal opened from the Custom Report tab bar.

= 1.1.4 =
* Fixed SQL error when sorting Products/Categories by an invalid column.
* Custom date ranges are validated (invalid dates fall back to the last 30 days; reversed ranges are swapped).
* Updated readme.

= 1.1.3 =
* Added shared Report Settings for dynamically available WooCommerce statuses, applied consistently to every report and customer order count.

= 1.1.2 =
* Customer order counts now include every placed order regardless of its current status; removed the count settings UI.

= 1.1.1 =
* Consolidated all reports and customer order count settings under WooCommerce > Custom Report.

= 1.1.0 =
* Added Customer Report sales overview, product sales, category sales, and sales-by-date reports.

= 1.0.0 =
* Initial release: customer order count by billing phone.

== Phone normalization ==

This release supports Bangladeshi mobile numbers. It removes spaces, punctuation and a leading plus. A 13-digit number beginning 8801 is normalized to 01XXXXXXXXX; an 11-digit number beginning 01 is used as-is. Empty or invalid values show an em dash and are not grouped.

The count link intentionally sends the order's original stored billing-phone value to WooCommerce native search. It does not send the normalized grouping key.

== Statuses ==

Which order statuses are counted is controlled in one place: the Report Settings panel on WooCommerce > Custom Report. By default all registered WooCommerce statuses are enabled. The selection applies to every report tab and to the Customer Orders column. Trashed and draft/auto-draft orders are never counted.
