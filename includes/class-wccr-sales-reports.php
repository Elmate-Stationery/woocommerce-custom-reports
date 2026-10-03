<?php
defined( 'ABSPATH' ) || exit;

/** WooCommerce Analytics lookup-table reports. No checkout, product, or tracking data is changed. */
final class WCCR_Sales_Reports {
	public static function menu() {
		add_submenu_page( 'woocommerce', __( 'Custom Report', 'woocommerce-custom-reports' ), __( 'Custom Report', 'woocommerce-custom-reports' ), 'view_woocommerce_reports', 'wccr-customer-report', array( __CLASS__, 'render' ) );
	}

	public static function render() {
		if ( ! current_user_can( 'view_woocommerce_reports' ) ) { wp_die( esc_html__( 'You do not have permission to view reports.', 'woocommerce-custom-reports' ) ); }
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'overview';
		if ( ! in_array( $tab, array( 'overview', 'products', 'categories', 'dates' ), true ) ) { $tab = 'overview'; }
		$range = self::range();
		echo '<div class="wrap"><h1>' . esc_html__( 'Custom Report', 'woocommerce-custom-reports' ) . '</h1>';
		self::tabs( $tab ); WPCOC_Customer_Order_Count::report_settings_html(); self::modal_assets(); self::filters( $tab, $range );
		if ( 'overview' === $tab ) { self::overview( $range ); } elseif ( 'products' === $tab ) { self::products( $range ); } elseif ( 'categories' === $tab ) { self::categories( $range ); } else { self::dates( $range ); }
		echo '</div>';
	}

	private static function tabs( $active ) {
		$tabs = array( 'overview' => __( 'Overview', 'woocommerce-custom-reports' ), 'products' => __( 'Product Sales', 'woocommerce-custom-reports' ), 'categories' => __( 'Category Sales', 'woocommerce-custom-reports' ), 'dates' => __( 'Sales by Date', 'woocommerce-custom-reports' ) );
		echo '<div class="wccr-tabs"><nav class="nav-tab-wrapper">'; foreach ( $tabs as $key => $label ) { echo '<a class="nav-tab ' . ( $key === $active ? 'nav-tab-active' : '' ) . '" href="' . esc_url( add_query_arg( array( 'page' => 'wccr-customer-report', 'tab' => $key ) ) ) . '">' . esc_html( $label ) . '</a>'; } echo '</nav><button type="button" id="wccr-settings-open" class="button-link wccr-settings-open">&#9656; ' . esc_html__( 'Report Settings', 'woocommerce-custom-reports' ) . '</button></div>';
	}

	private static function modal_assets() {
		echo '<style>.wccr-tabs{display:flex;align-items:center;border-bottom:1px solid #c3c4c7}.wccr-tabs .nav-tab-wrapper{border:0;flex:1}.wccr-settings-open{color:#3c434a;text-decoration:none;padding:8px 14px;white-space:nowrap}.wccr-modal[hidden]{display:none}.wccr-modal{position:fixed;z-index:100000;inset:0;background:rgba(0,0,0,.45);display:flex;align-items:flex-start;justify-content:center;padding:8vh 20px}.wccr-modal__panel{position:relative;background:#fff;width:min(620px,100%);max-height:84vh;overflow:auto;padding:28px;box-shadow:0 4px 18px rgba(0,0,0,.3)}.wccr-modal__panel h2{margin-top:0}.wccr-modal__close{position:absolute;top:8px;right:10px;border:0;background:transparent;font-size:30px;line-height:1;cursor:pointer;color:#50575e}</style><script>document.addEventListener("DOMContentLoaded",function(){var modal=document.getElementById("wccr-settings-modal"),open=document.getElementById("wccr-settings-open"),close=document.getElementById("wccr-settings-close");if(!modal||!open||!close)return;open.addEventListener("click",function(){modal.hidden=false;close.focus();});close.addEventListener("click",function(){modal.hidden=true;open.focus();});});</script>';
	}

	private static function range() {
		$key = isset( $_GET['range'] ) ? sanitize_key( wp_unslash( $_GET['range'] ) ) : 'last-30-days'; $today = current_time( 'Y-m-d' );
		$map = array( 'today' => array( $today, $today ), 'yesterday' => array( gmdate( 'Y-m-d', strtotime( $today . ' -1 day' ) ), gmdate( 'Y-m-d', strtotime( $today . ' -1 day' ) ) ), 'last-7-days' => array( gmdate( 'Y-m-d', strtotime( $today . ' -6 days' ) ), $today ), 'last-30-days' => array( gmdate( 'Y-m-d', strtotime( $today . ' -29 days' ) ), $today ), 'this-month' => array( gmdate( 'Y-m-01', strtotime( $today ) ), $today ), 'last-month' => array( gmdate( 'Y-m-01', strtotime( $today . ' -1 month' ) ), gmdate( 'Y-m-t', strtotime( $today . ' -1 month' ) ) ) );
		if ( 'custom' === $key && ! empty( $_GET['start'] ) && ! empty( $_GET['end'] ) ) {
			$start = sanitize_text_field( wp_unslash( $_GET['start'] ) ); $end = sanitize_text_field( wp_unslash( $_GET['end'] ) );
			$valid = static function ( $d ) { $o = DateTime::createFromFormat( 'Y-m-d', $d ); return $o && $o->format( 'Y-m-d' ) === $d; };
			if ( $valid( $start ) && $valid( $end ) ) {
				if ( $start > $end ) { list( $start, $end ) = array( $end, $start ); }
				return array( 'key' => $key, 'start' => $start, 'end' => $end );
			}
		}
		$dates = isset( $map[ $key ] ) ? $map[ $key ] : $map['last-30-days']; return array( 'key' => $key, 'start' => $dates[0], 'end' => $dates[1] );
	}

	private static function filters( $tab, $range ) {
		echo '<form method="get" style="margin:18px 0"><input type="hidden" name="page" value="wccr-customer-report"><input type="hidden" name="tab" value="' . esc_attr( $tab ) . '"><select name="range">';
		foreach ( array( 'today'=>'Today','yesterday'=>'Yesterday','last-7-days'=>'Last 7 Days','last-30-days'=>'Last 30 Days','this-month'=>'This Month','last-month'=>'Last Month','custom'=>'Custom Range' ) as $key=>$label ) echo '<option value="' . esc_attr($key) . '" ' . selected($range['key'],$key,false) . '>' . esc_html($label) . '</option>';
		echo '</select> <input type="date" name="start" value="' . esc_attr($range['start']) . '"> <input type="date" name="end" value="' . esc_attr($range['end']) . '">';
		if ( 'products' === $tab ) { echo ' <input type="search" name="q" placeholder="' . esc_attr__( 'Product search', 'woocommerce-custom-reports' ) . '" value="' . esc_attr( isset($_GET['q']) ? wp_unslash($_GET['q']) : '' ) . '">'; }
		if ( 'categories' === $tab ) { echo ' <input type="search" name="q" placeholder="' . esc_attr__( 'Category search', 'woocommerce-custom-reports' ) . '" value="' . esc_attr( isset($_GET['q']) ? wp_unslash($_GET['q']) : '' ) . '">'; }
		if ( 'dates' === $tab ) { $view=isset($_GET['view'])?sanitize_key(wp_unslash($_GET['view'])):'day'; echo ' <select name="view"><option value="day" ' . selected($view,'day',false) . '>Daily</option><option value="week" ' . selected($view,'week',false) . '>Weekly</option><option value="month" ' . selected($view,'month',false) . '>Monthly</option></select>'; }
		submit_button( __( 'Filter', 'woocommerce-custom-reports' ), 'secondary', '', false ); echo '</form>';
	}

	private static function tables() { global $wpdb; return array( $wpdb->prefix . 'wc_order_stats', $wpdb->prefix . 'wc_order_product_lookup' ); }
	private static function where( $range ) { global $wpdb; $statuses=WPCOC_Customer_Order_Count::enabled_statuses(); if(empty($statuses)) return '1=0'; $holders=implode(',',array_fill(0,count($statuses),'%s')); return $wpdb->prepare( "os.date_created >= %s AND os.date_created < DATE_ADD(%s, INTERVAL 1 DAY) AND os.status IN ($holders)", array_merge(array($range['start'],$range['end']),$statuses) ); }
	private static function overview( $range ) { global $wpdb; list($stats,$lookup)=self::tables(); $where=self::where($range); $sql="SELECT COUNT(DISTINCT os.order_id) orders, COALESCE(SUM(opl.product_qty),0) qty, COALESCE(SUM(opl.product_gross_revenue),0) gross, COALESCE(SUM(opl.product_net_revenue),0) net, COUNT(DISTINCT os.customer_id) customers FROM $stats os LEFT JOIN $lookup opl ON os.order_id=opl.order_id WHERE $where"; $r=$wpdb->get_row($sql,ARRAY_A); $items=array('Total Orders'=>$r['orders'],'Total Products Sold'=>$r['qty'],'Gross Sales'=>wc_price($r['gross']),'Net Sales'=>wc_price($r['net']),'Average Order Value'=>wc_price($r['orders']?$r['net']/$r['orders']:0),'Total Customers'=>$r['customers']); echo '<div class="card" style="max-width:900px;padding:20px"><table class="widefat"><tbody>'; foreach($items as $l=>$v) echo '<tr><th>'.esc_html($l).'</th><td>'.wp_kses_post($v).'</td></tr>'; echo '</tbody></table></div>'; }
	private static function products( $range ) { self::table_report($range,'products'); }
	private static function categories( $range ) { self::table_report($range,'categories'); }
	private static function dates( $range ) { self::table_report($range,'dates'); }
	private static function table_report( $range, $type ) { global $wpdb; list($stats,$lookup)=self::tables(); $where=self::where($range); $q=isset($_GET['q'])?'%'.$wpdb->esc_like(sanitize_text_field(wp_unslash($_GET['q']))).'%':''; $sort=isset($_GET['orderby'])?sanitize_key(wp_unslash($_GET['orderby'])):('dates'===$type?'period':'qty'); $allowed=('dates'===$type)?array('orders','qty','gross','net','period'):array('orders','qty','gross','net'); if(!in_array($sort,$allowed,true))$sort=('dates'===$type)?'period':'qty'; $dir=(isset($_GET['order'])&&'asc'===strtolower($_GET['order']))?'ASC':'DESC'; $page=max(1,absint(isset($_GET['paged'])?$_GET['paged']:1)); $limit=20;$offset=($page-1)*$limit;
		if('products'===$type){$extra=$q?$wpdb->prepare(' AND (p.post_title LIKE %s OR pm.meta_value LIKE %s)',$q,$q):'';$sql="SELECT opl.product_id id, MAX(p.post_title) label, MAX(pm.meta_value) sku, COUNT(DISTINCT os.order_id) orders, SUM(opl.product_qty) qty, SUM(opl.product_gross_revenue) gross, SUM(opl.product_net_revenue) net FROM $lookup opl JOIN $stats os ON os.order_id=opl.order_id LEFT JOIN {$wpdb->posts} p ON p.ID=opl.product_id LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id=opl.product_id AND pm.meta_key='_sku' WHERE $where $extra GROUP BY opl.product_id";$headers=array('Product','SKU','Orders','Quantity Sold','Gross Sales','Net Sales');}
		elseif('categories'===$type){$extra=$q?$wpdb->prepare(' AND t.name LIKE %s',$q):'';$sql="SELECT t.term_id id, t.name label, COUNT(DISTINCT os.order_id) orders, SUM(opl.product_qty) qty, SUM(opl.product_gross_revenue) gross, SUM(opl.product_net_revenue) net FROM $lookup opl JOIN $stats os ON os.order_id=opl.order_id LEFT JOIN {$wpdb->posts} product_post ON product_post.ID=opl.product_id JOIN {$wpdb->term_relationships} tr ON tr.object_id=COALESCE(NULLIF(product_post.post_parent,0), opl.product_id) JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id=tr.term_taxonomy_id AND tt.taxonomy='product_cat' JOIN {$wpdb->terms} t ON t.term_id=tt.term_id WHERE $where $extra GROUP BY t.term_id";$headers=array('Category','Orders','Quantity Sold','Gross Sales','Net Sales');}
		else{$view=isset($_GET['view'])?sanitize_key(wp_unslash($_GET['view'])):'day';$fmt='day'===$view?'%Y-%m-%d':('week'===$view?'%x-W%v':'%Y-%m');$sql="SELECT DATE_FORMAT(os.date_created, '$fmt') period, COUNT(DISTINCT os.order_id) orders, SUM(opl.product_qty) qty, SUM(opl.product_gross_revenue) gross, SUM(opl.product_net_revenue) net FROM $lookup opl JOIN $stats os ON os.order_id=opl.order_id WHERE $where GROUP BY period";$headers=array('Date','Orders','Quantity Sold','Gross Sales','Net Sales');}
		$total=(int)$wpdb->get_var("SELECT COUNT(*) FROM ($sql) report"); $rows=$wpdb->get_results("SELECT * FROM ($sql) report ORDER BY $sort $dir LIMIT $limit OFFSET $offset",ARRAY_A); $keys=array('Product'=>'label','Category'=>'label','SKU'=>'sku','Date'=>'period','Orders'=>'orders','Quantity Sold'=>'qty','Gross Sales'=>'gross','Net Sales'=>'net'); echo '<table class="widefat striped"><thead><tr>';foreach($headers as $h){$k=$keys[$h];if(in_array($k,array('orders','qty','gross','net','period'),true)){$next=($sort===$k&&'DESC'===$dir)?'asc':'desc';$u=add_query_arg(array('orderby'=>$k,'order'=>$next,'paged'=>1));echo '<th><a href="'.esc_url($u).'">'.esc_html($h).($sort===$k?' '.('DESC'===$dir?'↓':'↑'):'').'</a></th>';}else echo '<th>'.esc_html($h).'</th>';}echo '</tr></thead><tbody>';if(!$rows)echo '<tr><td colspan="'.count($headers).'">'.esc_html__('No sales data found for the selected date range.','woocommerce-custom-reports').'</td></tr>';foreach($rows as $r){echo '<tr>';foreach($headers as $h){$k=$keys[$h];$v=in_array($k,array('gross','net'),true)?wc_price($r[$k]):$r[$k];echo '<td>'.wp_kses_post($v).'</td>';}echo '</tr>';}echo '</tbody></table>'; if($total>$limit){echo '<div class="tablenav"><div class="tablenav-pages">'.wp_kses_post(paginate_links(array('base'=>add_query_arg('paged','%#%'),'format'=>'','current'=>$page,'total'=>(int)ceil($total/$limit)))).'</div></div>';}
	}
}
