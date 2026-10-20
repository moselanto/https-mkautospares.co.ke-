<?php
/**
 * Live price lists on make, model and category archives (2.33.0).
 *
 * Why: answer engines and Google quote pages that state real prices in plain
 * HTML ("Toyota spare parts prices in Kenya"). Competitors that win model +
 * part + price queries show a priced table; MK has 4,300 priced items but only
 * showed them as product cards. This adds a crawlable table of up to 20
 * in-stock, priced parts on page 1 of each hub / category, spread across part
 * categories (max 3 per category, parts with an OEM number first).
 *
 * Every cell comes from WooCommerce at build time (name, price, stock, OEM
 * number, fitment taxonomies / meta). Nothing is estimated. Rows are cached
 * for 12 hours in the same namespace as Seo\Answers, so saving a product or
 * editing a term rebuilds them.
 *
 * No schema is added: Seo\Schema already prints one ItemList of the archive's
 * products, and a second list would duplicate it.
 *
 * @package MK_Auto_Elite
 */

namespace MKAutoElite\Seo;

use MKAutoElite\Woo\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the "<X> spare parts prices in Kenya" table.
 */
class PriceList {

	/** Cache lifetime. */
	private const TTL = 12 * HOUR_IN_SECONDS;

	/** Max rows in the table. */
	private const MAX_ROWS = 20;

	/** Max rows from one part category. */
	private const PER_CATEGORY = 3;

	/** Candidates read from the catalogue before picking the spread. */
	private const CANDIDATES = 240;

	/**
	 * Printed once per request (a template could fire both hooks).
	 *
	 * @var bool
	 */
	private static bool $done = false;

	/**
	 * Hook in.
	 */
	public function __construct() {
		// Above the archive FAQ (Answers renders at 40 / default 10 on hubs).
		add_action( 'woocommerce_after_shop_loop', array( __CLASS__, 'render' ), 30 );
		add_action( 'mkae_hub_after_products', array( __CLASS__, 'render' ), 5 );
	}

	/**
	 * Namespaced transient key (shares the Answers namespace so
	 * Answers::flush() also rebuilds price lists).
	 *
	 * @param string $key Key.
	 */
	private static function key( string $key ): string {
		return 'mkae_pl_' . md5( (string) get_option( 'mkae_answers_ns', '1' ) . '|' . $key );
	}

	/** Unfiltered first page of a category, make or model archive? */
	private static function applies(): bool {
		if ( ! function_exists( 'wc_get_product' ) || ! is_tax( array( 'product_cat', 'mk_make', 'mk_model' ) ) ) {
			return false;
		}
		if ( (int) get_query_var( 'paged' ) > 1 || is_search() || Answers::is_faceted() ) {
			return false;
		}
		return get_queried_object() instanceof \WP_Term;
	}

	/**
	 * Vehicle label for a make / model term ("Toyota Prado").
	 *
	 * @param \WP_Term $term Term.
	 */
	private static function subject( \WP_Term $term ): string {
		if ( 'product_cat' === $term->taxonomy ) {
			return Answers::category_label( $term->name );
		}
		$name = $term->name;
		if ( $term->parent ) {
			$parent = get_term( $term->parent, $term->taxonomy );
			if ( $parent instanceof \WP_Term && 0 !== stripos( $name, $parent->name ) ) {
				$name = $parent->name . ' ' . $name;
			}
		}
		/* translators: %s: vehicle make or model. */
		return sprintf( __( '%s spare parts', 'mk-auto-elite' ), $name );
	}

	/**
	 * Fitment text for a product: model terms (with make), else meta, plus years.
	 *
	 * @param int $id Product ID.
	 */
	private static function fitment( int $id ): string {
		$make  = '';
		$makes = taxonomy_exists( 'mk_make' ) ? wp_get_post_terms( $id, 'mk_make', array( 'fields' => 'names' ) ) : array();
		if ( ! is_wp_error( $makes ) && $makes ) {
			$make = (string) $makes[0];
		}
		$names  = array();
		$models = taxonomy_exists( 'mk_model' ) ? wp_get_post_terms( $id, 'mk_model', array( 'fields' => 'names' ) ) : array();
		if ( ! is_wp_error( $models ) ) {
			foreach ( array_slice( (array) $models, 0, 3 ) as $model ) {
				$model   = (string) $model;
				$names[] = ( '' !== $make && 0 !== stripos( $model, $make ) ) ? $make . ' ' . $model : $model;
			}
		}
		$text = implode( ', ', $names );
		if ( '' === $text ) {
			$text = WooCommerce::fitment_line( wc_get_product( $id ) );
			return trim( $text );
		}
		$years = WooCommerce::year_range( $id );
		return trim( $text . ( '' !== $years ? ' ' . $years : '' ) );
	}

	/**
	 * Rows for a term (cached): name, url, fitment, oem, price.
	 *
	 * @param \WP_Term $term Term.
	 * @return array{built:int,rows:array<int,array<string,string>>}
	 */
	public static function rows( \WP_Term $term ): array {
		$key  = self::key( 'rows|' . $term->term_id );
		$data = get_transient( $key );
		if ( is_array( $data ) && isset( $data['rows'] ) ) {
			return $data;
		}

		$ids = get_posts(
			array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'posts_per_page' => self::CANDIDATES,
				'no_found_rows'  => true,
				'orderby'        => 'modified',
				'order'          => 'DESC',
				'tax_query'      => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					array(
						'taxonomy'         => $term->taxonomy,
						'terms'            => array( (int) $term->term_id ),
						'include_children' => true,
					),
				),
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
					'relation' => 'AND',
					array(
						'key'   => '_stock_status',
						'value' => 'instock',
					),
					array(
						'key'     => '_price',
						'value'   => 0,
						'compare' => '>',
						'type'    => 'DECIMAL(14,2)',
					),
				),
			)
		);

		// Candidates with an OEM number first, otherwise most recently updated.
		$with_oem = array();
		$without  = array();
		foreach ( array_map( 'intval', (array) $ids ) as $id ) {
			$oem = trim( (string) get_post_meta( $id, '_mk_oem', true ) );
			if ( '' !== $oem ) {
				$with_oem[] = array( $id, $oem );
			} else {
				$without[] = array( $id, '' );
			}
		}

		$rows    = array();
		$per_cat = array();
		foreach ( array_merge( $with_oem, $without ) as $candidate ) {
			if ( count( $rows ) >= self::MAX_ROWS ) {
				break;
			}
			list( $id, $oem ) = $candidate;
			$product          = wc_get_product( $id );
			if ( ! $product instanceof \WC_Product || ! $product->is_visible() || ! $product->is_in_stock() ) {
				continue;
			}
			$price = (float) wc_get_price_to_display( $product );
			if ( $price <= 0 ) {
				continue;
			}
			// Spread: group on the product's first category (on a category
			// archive that is usually the sub-category).
			$cats  = wp_get_post_terms( $id, 'product_cat', array( 'fields' => 'ids' ) );
			$group = ( ! is_wp_error( $cats ) && $cats ) ? (int) $cats[0] : 0;
			if ( ( $per_cat[ $group ] ?? 0 ) >= self::PER_CATEGORY ) {
				continue;
			}
			$per_cat[ $group ] = ( $per_cat[ $group ] ?? 0 ) + 1;

			$rows[] = array(
				'name'    => trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $product->get_name() ) ) ),
				'url'     => (string) get_permalink( $id ),
				'fitment' => self::fitment( $id ),
				'oem'     => $oem,
				'price'   => 'KSh ' . number_format_i18n( $price, 0 ),
			);
		}

		$data = array(
			'built' => time(),
			'rows'  => $rows,
		);
		set_transient( $key, $data, self::TTL );
		return $data;
	}

	/** Print the table. */
	public static function render(): void {
		if ( self::$done || ! self::applies() ) {
			return;
		}
		$term = get_queried_object();
		$data = self::rows( $term );
		if ( count( $data['rows'] ) < 3 ) {
			return; // Too few priced parts to be a useful list.
		}
		self::$done = true;

		$show_fit = false;
		$show_oem = false;
		foreach ( $data['rows'] as $row ) {
			$show_fit = $show_fit || '' !== $row['fitment'];
			$show_oem = $show_oem || '' !== $row['oem'];
		}
		$heading = sprintf(
			/* translators: 1: subject e.g. "Toyota spare parts", 2: month and year. */
			__( '%1$s prices in Kenya (updated %2$s)', 'mk-auto-elite' ),
			self::subject( $term ),
			date_i18n( 'F Y', (int) $data['built'] )
		);
		?>
		<section class="mkae-section mkae-pricelist" id="price-list">
			<h2 class="mkae-section__title mkae-pricelist__title"><?php echo esc_html( $heading ); ?></h2>
			<p class="mkae-pricelist__note"><?php esc_html_e( 'A selection of in-stock parts with their current website price in Kenya shillings. Prices and stock change; the product page always shows the live price. Confirm fitment with your chassis number on WhatsApp before you pay.', 'mk-auto-elite' ); ?></p>
			<div class="mkae-pricelist__wrap">
				<table class="mkae-pricelist__table">
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Part', 'mk-auto-elite' ); ?></th>
							<?php if ( $show_fit ) : ?>
								<th scope="col"><?php esc_html_e( 'Fits', 'mk-auto-elite' ); ?></th>
							<?php endif; ?>
							<?php if ( $show_oem ) : ?>
								<th scope="col"><?php esc_html_e( 'OEM number', 'mk-auto-elite' ); ?></th>
							<?php endif; ?>
							<th scope="col" class="mkae-pricelist__price"><?php esc_html_e( 'Price', 'mk-auto-elite' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $data['rows'] as $row ) : ?>
							<tr>
								<td data-label="<?php esc_attr_e( 'Part', 'mk-auto-elite' ); ?>"><a href="<?php echo esc_url( $row['url'] ); ?>"><?php echo esc_html( $row['name'] ); ?></a></td>
								<?php if ( $show_fit ) : ?>
									<td data-label="<?php esc_attr_e( 'Fits', 'mk-auto-elite' ); ?>"><?php echo esc_html( '' !== $row['fitment'] ? $row['fitment'] : '-' ); ?></td>
								<?php endif; ?>
								<?php if ( $show_oem ) : ?>
									<td data-label="<?php esc_attr_e( 'OEM number', 'mk-auto-elite' ); ?>"><?php echo esc_html( '' !== $row['oem'] ? $row['oem'] : '-' ); ?></td>
								<?php endif; ?>
								<td data-label="<?php esc_attr_e( 'Price', 'mk-auto-elite' ); ?>" class="mkae-pricelist__price"><?php echo esc_html( $row['price'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</section>
		<?php
	}
}
