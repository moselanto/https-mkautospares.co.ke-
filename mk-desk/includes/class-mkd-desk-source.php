<?php
/**
 * MKauto Desk - Neighbour sourcing (MKauto Desk 1.0.0).
 *
 * Spare-parts reality: a customer asks for a part we do not have, we run to a
 * neighbour shop on Kirinyaga Road, buy it and still make the sale. This module
 * records every such line from the till (supplier, our cost, selling price,
 * profit) and keeps a running "owed to suppliers" ledger so nothing is forgotten.
 *
 * Storage: {prefix}mkd_sourcing (one row per sourced order line).
 *
 * @package MK_Desk
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MKD_Desk_Source {

	const DBV     = '1.0.0';
	const DBV_OPT = 'mkd_sourcing_db';
	const PAGE    = 'mkd-sourcing';

	public static function hooks() {
		add_action( 'init', array( __CLASS__, 'maybe_install' ), 6 );
		add_action( 'admin_post_mkd_src_pay', array( __CLASS__, 'h_pay' ) );
		add_action( 'admin_post_mkd_src_pay_all', array( __CLASS__, 'h_pay_all' ) );
	}

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'mkd_sourcing';
	}

	public static function maybe_install() {
		if ( self::DBV === get_option( self::DBV_OPT ) ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$t = self::table();
		$c = $wpdb->get_charset_collate();
		dbDelta( "CREATE TABLE {$t} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created datetime NOT NULL,
			order_id bigint(20) unsigned NOT NULL DEFAULT 0,
			item_id bigint(20) unsigned NOT NULL DEFAULT 0,
			part varchar(160) NOT NULL DEFAULT '',
			oem varchar(60) NOT NULL DEFAULT '',
			qty int(11) NOT NULL DEFAULT 1,
			unit_cost decimal(14,2) NOT NULL DEFAULT 0,
			unit_price decimal(14,2) NOT NULL DEFAULT 0,
			supplier varchar(80) NOT NULL DEFAULT '',
			supplier_phone varchar(20) NOT NULL DEFAULT '',
			paid tinyint(1) NOT NULL DEFAULT 0,
			paid_at datetime NULL DEFAULT NULL,
			paid_ref varchar(40) NOT NULL DEFAULT '',
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY item_id (item_id),
			KEY supplier (supplier),
			KEY paid (paid),
			KEY created (created)
		) {$c};" );
		update_option( self::DBV_OPT, self::DBV, false );
	}

	/**
	 * Validate the "src" block a till line sends. Returns null when the line is
	 * not sourced or is incomplete (it is then sold as a plain custom item).
	 */
	public static function clean_line( $src, $qty ) {
		if ( ! is_array( $src ) ) {
			return null;
		}
		$supplier = mb_substr( sanitize_text_field( isset( $src['supplier'] ) ? (string) $src['supplier'] : '' ), 0, 80 );
		$cost     = isset( $src['cost'] ) ? max( 0, round( (float) $src['cost'], 2 ) ) : 0;
		if ( '' === $supplier || $cost <= 0 ) {
			return null;
		}
		return array(
			'supplier' => $supplier,
			'phone'    => MKD_Desk::phone( isset( $src['phone'] ) ? (string) $src['phone'] : '' ),
			'cost'     => $cost,
			'oem'      => mb_substr( sanitize_text_field( isset( $src['oem'] ) ? (string) $src['oem'] : '' ), 0, 60 ),
			'paid'     => ! empty( $src['paid'] ) ? 1 : 0,
			'qty'      => max( 1, (int) $qty ),
		);
	}

	/** After a till sale is saved: copy each sourced line into the ledger. */
	public static function record_order( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		global $wpdb;
		self::maybe_install();
		foreach ( $order->get_items( array( 'line_item', 'fee' ) ) as $item_id => $item ) {
			$src = $item->get_meta( '_mkd_sourced', true );
			if ( ! is_array( $src ) || empty( $src['supplier'] ) ) {
				continue;
			}
			$qty   = max( 1, (int) $src['qty'] );
			$name  = preg_replace( '/ x \d+$/', '', (string) $item->get_name() );
			$now   = current_time( 'mysql' );
			$wpdb->replace( self::table(), array( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				'created'        => $now,
				'order_id'       => $order->get_id(),
				'item_id'        => (int) $item_id,
				'part'           => mb_substr( $name, 0, 160 ),
				'oem'            => (string) $src['oem'],
				'qty'            => $qty,
				'unit_cost'      => (float) $src['cost'],
				'unit_price'     => round( (float) $item->get_total() / $qty, 2 ),
				'supplier'       => (string) $src['supplier'],
				'supplier_phone' => (string) $src['phone'],
				'paid'           => (int) $src['paid'],
				'paid_at'        => $src['paid'] ? $now : null,
				'user_id'        => get_current_user_id(),
			) );
			if ( class_exists( 'MKD_Staff' ) ) {
				MKD_Staff::log( 'sale', 'Sourced ' . $name . ' from ' . $src['supplier'] . ' at ' . MKD_Desk::money( $src['cost'] ) . ' (' . ( $src['paid'] ? 'paid' : 'owed' ) . ')', array( 'object' => 'order', 'object_id' => $order->get_id(), 'amount' => (float) $src['cost'] * $qty ) );
			}
		}
	}

	/** Known suppliers, most used first (for the till's suggestion list). */
	public static function supplier_names( $limit = 40 ) {
		global $wpdb;
		if ( self::DBV !== get_option( self::DBV_OPT ) ) {
			return array();
		}
		$t = self::table();
		return (array) $wpdb->get_col( $wpdb->prepare( "SELECT supplier FROM {$t} GROUP BY supplier ORDER BY COUNT(*) DESC LIMIT %d", $limit ) ); // phpcs:ignore WordPress.DB
	}

	/** Totals for the Overview and this page. */
	public static function kpis() {
		global $wpdb;
		$out = array( 'owed' => 0, 'owed_n' => 0, 'suppliers' => 0, 'm_sales' => 0, 'm_cost' => 0, 'm_profit' => 0, 'm_n' => 0 );
		if ( self::DBV !== get_option( self::DBV_OPT ) ) {
			return $out;
		}
		$t    = self::table();
		$o    = $wpdb->get_row( "SELECT COALESCE(SUM(qty*unit_cost),0) s, COUNT(*) n, COUNT(DISTINCT supplier) d FROM {$t} WHERE paid = 0" ); // phpcs:ignore WordPress.DB
		$from = current_time( 'Y-m-01 00:00:00' );
		$m    = $wpdb->get_row( $wpdb->prepare( "SELECT COALESCE(SUM(qty*unit_price),0) p, COALESCE(SUM(qty*unit_cost),0) c, COUNT(*) n FROM {$t} WHERE created >= %s", $from ) ); // phpcs:ignore WordPress.DB
		$out['owed']      = $o ? (float) $o->s : 0;
		$out['owed_n']    = $o ? (int) $o->n : 0;
		$out['suppliers'] = $o ? (int) $o->d : 0;
		$out['m_sales']   = $m ? (float) $m->p : 0;
		$out['m_cost']    = $m ? (float) $m->c : 0;
		$out['m_profit']  = $out['m_sales'] - $out['m_cost'];
		$out['m_n']       = $m ? (int) $m->n : 0;
		return $out;
	}

	public static function h_pay() {
		MKD_Desk::guard( 'mkd_src_pay' );
		if ( class_exists( 'MKD_Staff' ) && ! MKD_Staff::can( 'mark_paid' ) ) {
			wp_safe_redirect( MKD_Desk::url( self::PAGE, array( 'nd_msg' => 'notfound' ) ) );
			exit;
		}
		global $wpdb;
		$id  = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		$ref = isset( $_POST['ref'] ) ? mb_substr( strtoupper( preg_replace( '/[^A-Za-z0-9\- ]/', '', wp_unslash( $_POST['ref'] ) ) ), 0, 40 ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$row = $id ? $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ) ) : null; // phpcs:ignore WordPress.DB
		if ( ! $row ) {
			wp_safe_redirect( MKD_Desk::url( self::PAGE, array( 'nd_msg' => 'notfound' ) ) );
			exit;
		}
		$wpdb->update( self::table(), array( 'paid' => 1, 'paid_at' => current_time( 'mysql' ), 'paid_ref' => $ref ), array( 'id' => $id ) ); // phpcs:ignore WordPress.DB
		if ( class_exists( 'MKD_Staff' ) ) {
			MKD_Staff::log( 'payment', 'Paid supplier ' . $row->supplier . ' ' . MKD_Desk::money( $row->qty * $row->unit_cost ) . ' for ' . $row->part . ( $ref ? ' (' . $ref . ')' : '' ), array( 'amount' => (float) $row->qty * (float) $row->unit_cost ) );
		}
		wp_safe_redirect( wp_get_referer() ? add_query_arg( 'nd_msg', 'paid', wp_get_referer() ) : MKD_Desk::url( self::PAGE, array( 'nd_msg' => 'paid' ) ) );
		exit;
	}

	public static function h_pay_all() {
		MKD_Desk::guard( 'mkd_src_pay_all' );
		if ( class_exists( 'MKD_Staff' ) && ! MKD_Staff::can( 'mark_paid' ) ) {
			wp_safe_redirect( MKD_Desk::url( self::PAGE, array( 'nd_msg' => 'notfound' ) ) );
			exit;
		}
		global $wpdb;
		$sup = isset( $_POST['supplier'] ) ? sanitize_text_field( wp_unslash( $_POST['supplier'] ) ) : '';
		$ref = isset( $_POST['ref'] ) ? mb_substr( strtoupper( preg_replace( '/[^A-Za-z0-9\- ]/', '', wp_unslash( $_POST['ref'] ) ) ), 0, 40 ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( '' !== $sup ) {
			$sum = (float) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE(SUM(qty*unit_cost),0) FROM ' . self::table() . ' WHERE paid = 0 AND supplier = %s', $sup ) ); // phpcs:ignore WordPress.DB
			$wpdb->query( $wpdb->prepare( 'UPDATE ' . self::table() . ' SET paid = 1, paid_at = %s, paid_ref = %s WHERE paid = 0 AND supplier = %s', current_time( 'mysql' ), $ref, $sup ) ); // phpcs:ignore WordPress.DB
			if ( class_exists( 'MKD_Staff' ) ) {
				MKD_Staff::log( 'payment', 'Settled supplier ' . $sup . ' - ' . MKD_Desk::money( $sum ) . ( $ref ? ' (' . $ref . ')' : '' ), array( 'amount' => $sum ) );
			}
		}
		wp_safe_redirect( MKD_Desk::url( self::PAGE, array( 'nd_msg' => 'paid' ) ) );
		exit;
	}

	/** Rows for the Excel / CSV export. */
	public static function export_rows() {
		global $wpdb;
		$rows   = array( array( 'Date', 'Sale #', 'Part', 'Part no.', 'Qty', 'Our cost', 'Sold at', 'Profit', 'Supplier', 'Supplier phone', 'Supplier paid', 'Paid on', 'Ref' ) );
		$t      = self::table();
		$result = (array) $wpdb->get_results( "SELECT * FROM {$t} ORDER BY created DESC LIMIT 5000" ); // phpcs:ignore WordPress.DB
		foreach ( $result as $r ) {
			$o      = wc_get_order( $r->order_id );
			$rows[] = array( $r->created, $o ? $o->get_order_number() : $r->order_id, $r->part, $r->oem, (int) $r->qty, (float) $r->unit_cost * $r->qty, (float) $r->unit_price * $r->qty, ( (float) $r->unit_price - (float) $r->unit_cost ) * $r->qty, $r->supplier, $r->supplier_phone, $r->paid ? 'Yes' : 'No', (string) $r->paid_at, $r->paid_ref );
		}
		return $rows;
	}

	public static function page() {
		if ( ! current_user_can( MKD_Desk::CAP ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do that.', 'mk-desk' ) );
		}
		self::maybe_install();
		global $wpdb;
		$t   = self::table();
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'owed'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$sup = isset( $_GET['supplier'] ) ? sanitize_text_field( wp_unslash( $_GET['supplier'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$k   = self::kpis();
		$act = MKD_Desk::btn( MKD_Desk::url( MKD_Desk::P_POS ), '+ Sourced sale at the till', 'nd-btn--primary' );
		MKD_Desk::header( self::PAGE, 'Sourcing & suppliers', $act );

		echo '<p class="nd-mut nd-lead">When a part is not in stock, get it from a neighbour shop and still make the sale. At the till, tap <strong>Source from a neighbour</strong> (or the custom item button), enter the supplier and our cost. Every sourced line lands here with its profit and whether the supplier has been paid.</p>';
		echo '<div class="nd-kpis">';
		MKD_Desk::kpi( 'Owed to suppliers', MKD_Desk::money( $k['owed'] ), $k['owed_n'] . ' parts from ' . $k['suppliers'] . ' shops', $k['owed'] > 0 ? 'red' : 'green', MKD_Desk::url( self::PAGE, array( 'tab' => 'owed' ) ) );
		MKD_Desk::kpi( 'Sourced sales this month', MKD_Desk::money( $k['m_sales'] ), $k['m_n'] . ' parts sold that were not in stock', 'ink' );
		MKD_Desk::kpi( 'Profit on sourced parts', MKD_Desk::money( $k['m_profit'] ), 'this month, after paying suppliers', $k['m_profit'] >= 0 ? 'gold' : 'red' );
		MKD_Desk::kpi( 'Average margin', $k['m_sales'] > 0 ? round( $k['m_profit'] / $k['m_sales'] * 100 ) . '%' : '-', 'on sourced parts this month' );
		echo '</div>';

		// Supplier balances: who do we owe, settle in one click.
		$bal = (array) $wpdb->get_results( "SELECT supplier, MAX(supplier_phone) phone, SUM(qty*unit_cost) owed, COUNT(*) n, MIN(created) oldest FROM {$t} WHERE paid = 0 GROUP BY supplier ORDER BY owed DESC" ); // phpcs:ignore WordPress.DB
		echo '<div class="nd-grid nd-grid--2-1"><div>';
		$tabs = array( 'owed' => 'Owed to supplier', 'paid' => 'Paid', 'all' => 'All sourced parts' );
		echo '<nav class="nd-pills">';
		foreach ( $tabs as $key => $label ) {
			echo '<a class="nd-pill' . ( $key === $tab ? ' is-on' : '' ) . '" href="' . esc_url( MKD_Desk::url( self::PAGE, array( 'tab' => $key ) ) ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';
		$where = '1=1';
		$args  = array();
		if ( 'owed' === $tab ) {
			$where .= ' AND paid = 0';
		} elseif ( 'paid' === $tab ) {
			$where .= ' AND paid = 1';
		}
		if ( '' !== $sup ) {
			$where .= ' AND supplier = %s';
			$args[] = $sup;
		}
		$sql  = "SELECT * FROM {$t} WHERE {$where} ORDER BY created DESC LIMIT 200";
		$rows = (array) ( $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ) ) : $wpdb->get_results( $sql ) ); // phpcs:ignore WordPress.DB
		if ( '' !== $sup ) {
			echo '<p class="nd-mut">Showing <strong>' . esc_html( $sup ) . '</strong> only. <a href="' . esc_url( MKD_Desk::url( self::PAGE, array( 'tab' => $tab ) ) ) . '">Show every supplier</a></p>';
		}
		if ( ! $rows ) {
			echo '<div class="nd-empty"><strong>' . ( 'owed' === $tab ? 'Nothing owed to suppliers.' : 'No sourced parts yet.' ) . '</strong><p>Parts bought from neighbour shops at the till show up here.</p></div>';
		} else {
			echo '<div class="nd-table-wrap"><table class="nd-table"><thead><tr><th>Date</th><th>Part</th><th>Supplier</th><th class="nd-num">Our cost</th><th class="nd-num">Sold at</th><th class="nd-num">Profit</th><th>Sale</th><th>Supplier</th></tr></thead><tbody>';
			foreach ( $rows as $r ) {
				$cost   = (float) $r->unit_cost * (int) $r->qty;
				$price  = (float) $r->unit_price * (int) $r->qty;
				$profit = $price - $cost;
				$o      = wc_get_order( $r->order_id );
				echo '<tr><td>' . esc_html( MKD_Desk::date_label( substr( $r->created, 0, 10 ), 'j M Y' ) ) . '<small>' . esc_html( substr( $r->created, 11, 5 ) ) . '</small></td>';
				echo '<td><strong>' . esc_html( $r->part ) . '</strong><small>' . esc_html( ( (int) $r->qty > 1 ? (int) $r->qty . ' units' : '1 unit' ) . ( $r->oem ? ' - ' . $r->oem : '' ) ) . '</small></td>';
				echo '<td><a href="' . esc_url( MKD_Desk::url( self::PAGE, array( 'tab' => $tab, 'supplier' => $r->supplier ) ) ) . '">' . esc_html( $r->supplier ) . '</a><small>' . esc_html( MKD_Desk::phone_label( $r->supplier_phone ) ) . '</small></td>';
				echo '<td class="nd-num">' . esc_html( MKD_Desk::money( $cost ) ) . '</td><td class="nd-num">' . esc_html( MKD_Desk::money( $price ) ) . '</td>';
				echo '<td class="nd-num ' . ( $profit < 0 ? 'nd-neg' : 'nd-pos' ) . '">' . esc_html( MKD_Desk::money( $profit ) ) . '</td>';
				echo '<td>' . ( $o ? '<a href="' . esc_url( $o->get_edit_order_url() ) . '">#' . esc_html( $o->get_order_number() ) . '</a>' : '-' ) . '</td><td>';
				if ( $r->paid ) {
					echo MKD_Desk::badge( 'completed', 'Paid' ) . ( $r->paid_ref ? '<small>' . esc_html( $r->paid_ref ) . '</small>' : '' ); // phpcs:ignore WordPress.Security.EscapeOutput
				} else {
					echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="nd-inrow">';
					wp_nonce_field( 'mkd_src_pay' );
					echo '<input type="hidden" name="action" value="mkd_src_pay"><input type="hidden" name="id" value="' . (int) $r->id . '"><input type="text" name="ref" placeholder="M-Pesa ref" maxlength="40" class="nd-in-sm"><button class="nd-btn nd-btn--sm">Mark paid</button></form>';
				}
				echo '</td></tr>';
			}
			echo '</tbody></table></div>';
		}
		echo '</div><aside>';
		echo '<section class="nd-card"><header class="nd-card__h"><h2>Supplier balances</h2>' . MKD_Desk::export_btns( 'sourcing' ) . '</header>'; // phpcs:ignore WordPress.Security.EscapeOutput
		if ( ! $bal ) {
			echo '<p class="nd-mut">All neighbour shops are paid up.</p>';
		} else {
			echo '<ul class="nd-list">';
			foreach ( $bal as $b ) {
				$wa = $b->phone ? MKD_Desk::wa( $b->phone, 'Hi ' . $b->supplier . ', MK Auto Spares here. Sending ' . MKD_Desk::money( $b->owed ) . ' for the ' . (int) $b->n . ' part(s) we picked from you. Thank you.' ) : '';
				echo '<li><div><strong>' . esc_html( $b->supplier ) . '</strong><small>' . (int) $b->n . ' parts, oldest ' . esc_html( MKD_Desk::date_label( substr( $b->oldest, 0, 10 ), 'j M' ) ) . '</small></div><div class="nd-list__r"><strong>' . esc_html( MKD_Desk::money( $b->owed ) ) . '</strong>';
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="nd-inrow">';
				wp_nonce_field( 'mkd_src_pay_all' );
				echo '<input type="hidden" name="action" value="mkd_src_pay_all"><input type="hidden" name="supplier" value="' . esc_attr( $b->supplier ) . '"><input type="text" name="ref" placeholder="Ref" maxlength="40" class="nd-in-sm"><button class="nd-btn nd-btn--sm nd-btn--primary">Settle all</button></form>';
				if ( $wa ) {
					echo '<a class="nd-btn nd-btn--sm nd-btn--wa" target="_blank" rel="noopener" href="' . esc_url( $wa ) . '">WhatsApp</a>';
				}
				echo '</div></li>';
			}
			echo '</ul>';
		}
		echo '</section>';
		$top = (array) $wpdb->get_results( "SELECT supplier, COUNT(*) n, SUM(qty*(unit_price-unit_cost)) p FROM {$t} GROUP BY supplier ORDER BY n DESC LIMIT 6" ); // phpcs:ignore WordPress.DB
		if ( $top ) {
			echo '<section class="nd-card"><header class="nd-card__h"><h2>Most-used neighbours</h2></header><ul class="nd-list">';
			foreach ( $top as $x ) {
				echo '<li><div><strong>' . esc_html( $x->supplier ) . '</strong><small>' . (int) $x->n . ' parts sourced</small></div><div class="nd-list__r">' . esc_html( MKD_Desk::money( $x->p ) ) . '<small>profit</small></div></li>';
			}
			echo '</ul><p class="nd-mut">Parts you source often are worth stocking yourself.</p></section>';
		}
		echo '</aside></div>';
		MKD_Desk::footer();
	}
}
