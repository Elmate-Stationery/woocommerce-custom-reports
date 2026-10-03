<?php
/**
 * Plugin Name: WooCommerce Custom Reports
 * Description: Custom WooCommerce admin reports, starting with a clickable customer order count by billing phone.
 * Version: 1.1.8
 * Author: Elmates
 * Author URI: https://elmates.com
 * Requires Plugins: woocommerce
 * Requires PHP: 7.4
 * Text Domain: woocommerce-custom-reports
 */

defined( 'ABSPATH' ) || exit;

final class WPCOC_Customer_Order_Count {
	const OPTION_STATUSES = 'wccr_report_statuses';
	const CACHE_KEY       = 'wpcoc_phone_order_counts_v1';

	/** @var array<string,int>|null */
	private static $counts = null;

	public static function init() {
		add_action( 'before_woocommerce_init', array( __CLASS__, 'declare_hpos_compatibility' ) );
		add_action( 'admin_notices', array( __CLASS__, 'woocommerce_notice' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );

		add_filter( 'manage_edit-shop_order_columns', array( __CLASS__, 'add_legacy_column' ), 20 );
		add_action( 'manage_shop_order_posts_custom_column', array( __CLASS__, 'render_legacy_column' ), 20, 2 );

		add_filter( 'woocommerce_shop_order_list_table_columns', array( __CLASS__, 'add_hpos_column' ), 20 );
		add_action( 'woocommerce_shop_order_list_table_custom_column', array( __CLASS__, 'render_hpos_column' ), 20, 2 );

		add_action( 'woocommerce_process_shop_order_meta', array( __CLASS__, 'invalidate_counts' ) );
		add_action( 'woocommerce_new_order', array( __CLASS__, 'invalidate_counts' ) );
		add_action( 'woocommerce_update_order', array( __CLASS__, 'invalidate_counts' ) );
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'invalidate_counts' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'invalidate_on_delete' ) );
		add_action( 'woocommerce_before_trash_order', array( __CLASS__, 'invalidate_counts' ) );
		add_action( 'woocommerce_before_delete_order', array( __CLASS__, 'invalidate_counts' ) );

		add_action( 'admin_menu', array( 'WCCR_Sales_Reports', 'menu' ) );
	}

	public static function declare_hpos_compatibility() {
		if ( class_exists( '\Automattic\\WooCommerce\\Utilities\\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		}
	}

	public static function woocommerce_notice() {
		if ( current_user_can( 'activate_plugins' ) && ! class_exists( 'WooCommerce' ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'WooCommerce Custom Reports requires WooCommerce to be active.', 'woocommerce-custom-reports' ) . '</p></div>';
		}
	}

	public static function add_legacy_column( $columns ) {
		return self::insert_column( $columns );
	}

	public static function add_hpos_column( $columns ) {
		return self::insert_column( $columns );
	}

	private static function insert_column( $columns ) {
		$result = array();
		foreach ( $columns as $key => $label ) {
			$result[ $key ] = $label;
			if ( 'order_number' === $key || 'order_title' === $key ) {
				$result['wpcoc_customer_orders'] = esc_html__( 'Customer Orders', 'woocommerce-custom-reports' );
			}
		}
		if ( ! isset( $result['wpcoc_customer_orders'] ) ) {
			$result['wpcoc_customer_orders'] = esc_html__( 'Customer Orders', 'woocommerce-custom-reports' );
		}
		return $result;
	}

	public static function render_legacy_column( $column, $post_id ) {
		if ( 'wpcoc_customer_orders' === $column && function_exists( 'wc_get_order' ) ) {
			self::render_count( wc_get_order( $post_id ) );
		}
	}

	public static function render_hpos_column( $column, $order ) {
		if ( 'wpcoc_customer_orders' === $column ) {
			self::render_count( $order instanceof WC_Order ? $order : wc_get_order( $order ) );
		}
	}

	private static function render_count( $order ) {
		if ( ! $order ) {
			echo '&mdash;';
			return;
		}
		// Counts use the canonical key, while the link deliberately uses the stored
		// value so it behaves exactly like an administrator's native order search.
		$billing_phone = trim( (string) $order->get_billing_phone() );
		$phone         = self::normalize_phone( $billing_phone );
		if ( '' === $phone ) {
			echo '&mdash;';
			return;
		}
		$counts = self::get_counts();
		$count  = isset( $counts[ $phone ] ) ? (int) $counts[ $phone ] : 0;
		if ( ! $count ) {
			echo '&mdash;';
			return;
		}
		$url = add_query_arg( array( 'page' => 'wc-orders', 's' => $billing_phone, 'search-filter' => 'all', 'paged' => 1 ), admin_url( 'admin.php' ) );
		if ( ! self::is_hpos_screen() ) {
			$url = add_query_arg( array( 'post_type' => 'shop_order', 's' => $billing_phone, 'paged' => 1 ), admin_url( 'edit.php' ) );
		}
		echo '<a href="' . esc_url( $url ) . '" aria-label="' . esc_attr( sprintf( __( 'Show %d orders for this phone number', 'woocommerce-custom-reports' ), $count ) ) . '">' . esc_html( (string) $count ) . '</a>';
	}

	/** Bangladesh mobile numbers only: strip common separators; +880/880 becomes 0; require 01XXXXXXXXX. */
	public static function normalize_phone( $phone ) {
		$digits = preg_replace( '/[^0-9]/', '', (string) $phone );
		if ( 0 === strpos( $digits, '8801' ) && 13 === strlen( $digits ) ) {
			$digits = '0' . substr( $digits, 3 );
		}
		return ( 11 === strlen( $digits ) && 0 === strpos( $digits, '01' ) ) ? $digits : '';
	}

	/** The shared report policy: all registered WooCommerce statuses by default. */
	public static function enabled_statuses() {
		$all = function_exists( 'wc_get_order_statuses' ) ? array_keys( wc_get_order_statuses() ) : array();
		$saved = get_option( self::OPTION_STATUSES, null );
		return is_array( $saved ) ? array_values( array_intersect( $all, $saved ) ) : $all;
	}

	public static function register_settings() {
		register_setting( 'wccr_report_settings', self::OPTION_STATUSES, array( 'sanitize_callback' => array( __CLASS__, 'sanitize_statuses' ) ) );
	}

	public static function sanitize_statuses( $statuses ) {
		self::invalidate_counts();
		$all = function_exists( 'wc_get_order_statuses' ) ? array_keys( wc_get_order_statuses() ) : array();
		return array_values( array_intersect( $all, array_map( 'sanitize_key', (array) $statuses ) ) );
	}

	public static function report_settings_html() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) { return; }
		$all = function_exists( 'wc_get_order_statuses' ) ? wc_get_order_statuses() : array();
		$enabled = self::enabled_statuses();
		echo '<div id="wccr-settings-modal" class="wccr-modal" role="dialog" aria-modal="true" aria-labelledby="wccr-settings-title" hidden><div class="wccr-modal__panel"><button type="button" class="wccr-modal__close" id="wccr-settings-close" aria-label="' . esc_attr__( 'Close report settings', 'woocommerce-custom-reports' ) . '">&times;</button><h2 id="wccr-settings-title">' . esc_html__( 'Report Settings', 'woocommerce-custom-reports' ) . '</h2><form method="post" action="options.php">';
		settings_fields( 'wccr_report_settings' );
		echo '<p>' . esc_html__( 'Only enabled statuses are included across all Custom Report screens and Customer Orders counts.', 'woocommerce-custom-reports' ) . '</p>';
		foreach ( $all as $status => $label ) { echo '<label style="display:inline-block;min-width:180px;margin:4px 12px 4px 0"><input type="checkbox" name="' . esc_attr( self::OPTION_STATUSES ) . '[]" value="' . esc_attr( $status ) . '" ' . checked( in_array( $status, $enabled, true ), true, false ) . '> ' . esc_html( $label ) . '</label>'; }
		submit_button( __( 'Save Report Settings', 'woocommerce-custom-reports' ), 'secondary', 'submit', false );
		echo '</form></div></div>';
	}

	private static function get_counts() {
		if ( null !== self::$counts ) {
			return self::$counts;
		}
		$statuses = self::enabled_statuses();
		if ( empty( $statuses ) ) { return self::$counts = array(); }
		$key = self::CACHE_KEY . '_' . ( self::is_hpos_enabled() ? 'hpos' : 'legacy' );
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return self::$counts = $cached;
		}
		global $wpdb;
		$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		// The expression intentionally mirrors normalize_phone(): no stored customer data is changed.
		if ( self::is_hpos_enabled() ) {
			$table = $wpdb->prefix . 'wc_order_addresses';
			$orders = $wpdb->prefix . 'wc_orders';
			$raw = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(a.phone, '+', ''), ' ', ''), '-', ''), '(', ''), ')', ''), '.', '')";
			$sql = "SELECT CASE WHEN {$raw} LIKE '8801%' AND CHAR_LENGTH({$raw}) = 13 THEN CONCAT('0', SUBSTRING({$raw}, 4)) WHEN {$raw} LIKE '01%' AND CHAR_LENGTH({$raw}) = 11 THEN {$raw} ELSE '' END AS phone, COUNT(*) AS total FROM {$orders} o INNER JOIN {$table} a ON o.id = a.order_id AND a.address_type = 'billing' WHERE o.type = 'shop_order' AND o.status IN ({$placeholders}) GROUP BY phone HAVING phone <> ''";
		} else {
			$raw = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(pm.meta_value, '+', ''), ' ', ''), '-', ''), '(', ''), ')', ''), '.', '')";
			$sql = "SELECT CASE WHEN {$raw} LIKE '8801%' AND CHAR_LENGTH({$raw}) = 13 THEN CONCAT('0', SUBSTRING({$raw}, 4)) WHEN {$raw} LIKE '01%' AND CHAR_LENGTH({$raw}) = 11 THEN {$raw} ELSE '' END AS phone, COUNT(*) AS total FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_billing_phone' WHERE p.post_type = 'shop_order' AND p.post_status IN ({$placeholders}) GROUP BY phone HAVING phone <> ''";
		}
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $statuses ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$counts = array();
		foreach ( (array) $rows as $row ) {
			$counts[ $row['phone'] ] = (int) $row['total'];
		}
		set_transient( $key, $counts, MINUTE_IN_SECONDS * 10 );
		return self::$counts = $counts;
	}

	public static function invalidate_counts() {
		foreach ( array( true, false ) as $hpos ) {
			delete_transient( self::CACHE_KEY . '_' . ( $hpos ? 'hpos' : 'legacy' ) );
		}
		self::$counts = null;
	}

	public static function invalidate_on_delete( $post_id ) {
		if ( 'shop_order' === get_post_type( $post_id ) ) {
			self::invalidate_counts();
		}
	}

	private static function is_hpos_enabled() {
		return class_exists( '\Automattic\\WooCommerce\\Utilities\\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
	}

	private static function is_hpos_screen() {
		return isset( $_GET['page'] ) && 'wc-orders' === $_GET['page'];
	}

}

add_action( 'plugins_loaded', array( 'WPCOC_Customer_Order_Count', 'init' ) );

require_once __DIR__ . '/includes/class-wccr-sales-reports.php';
WCCR_Sales_Reports::init();
