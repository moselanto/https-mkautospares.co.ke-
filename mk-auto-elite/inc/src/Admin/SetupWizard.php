<?php
/**
 * Guided setup wizard admin page.
 *
 * @package MK_Auto_Elite
 */

namespace MKAutoElite\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A self-contained setup screen: plugins -> import -> fitment -> pages -> articles -> menus -> configure.
 */
class SetupWizard {

	/**
	 * Hook in.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
	}

	/**
	 * Register the top-level admin page.
	 */
	public function menu(): void {
		add_menu_page(
			__( 'MK Auto Elite', 'mk-auto-elite' ),
			__( 'MK Auto Elite', 'mk-auto-elite' ),
			'manage_options',
			'mk-auto-elite',
			array( $this, 'render' ),
			'dashicons-car',
			59
		);
	}

	/**
	 * Render the wizard.
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$woo    = class_exists( 'WooCommerce' );
		$action = esc_url( admin_url( 'admin-post.php' ) );
		$pretty = get_option( 'permalink_structure' );
		?>
		<div class="wrap mkae-wizard">
			<h1><?php esc_html_e( 'MK Auto Elite - Setup', 'mk-auto-elite' ); ?></h1>
			<p><?php esc_html_e( 'Complete these steps to launch your automotive store.', 'mk-auto-elite' ); ?></p>

			<?php if ( ! $pretty ) : ?>
				<div class="notice notice-warning inline"><p>
					<strong><?php esc_html_e( 'Recommended:', 'mk-auto-elite' ); ?></strong>
					<?php esc_html_e( 'Set Settings > Permalinks to "Post name" for clean, SEO-friendly URLs.', 'mk-auto-elite' ); ?>
					<a href="<?php echo esc_url( admin_url( 'options-permalink.php' ) ); ?>"><?php esc_html_e( 'Open Permalinks', 'mk-auto-elite' ); ?></a>
				</p></div>
			<?php endif; ?>

			<ol class="mkae-wizard__steps">
				<li>
					<h2><?php esc_html_e( '1. Install plugins', 'mk-auto-elite' ); ?></h2>
					<p><?php echo $woo ? esc_html__( 'WooCommerce is active. Great.', 'mk-auto-elite' ) : esc_html__( 'WooCommerce is required and not yet active.', 'mk-auto-elite' ); ?></p>
					<?php if ( ! $woo && current_user_can( 'install_plugins' ) ) : ?>
						<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=woocommerce' ), 'install-plugin_woocommerce' ) ); ?>"><?php esc_html_e( 'Install WooCommerce', 'mk-auto-elite' ); ?></a>
					<?php endif; ?>
				</li>

				<li>
					<h2><?php esc_html_e( '2. Import your products', 'mk-auto-elite' ); ?></h2>
					<p><?php esc_html_e( 'Import your cleaned catalogue CSV via WooCommerce > Products > Import.', 'mk-auto-elite' ); ?></p>
					<?php if ( $woo ) : ?>
						<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=product&page=product_importer' ) ); ?>"><?php esc_html_e( 'Open Product Importer', 'mk-auto-elite' ); ?></a>
					<?php endif; ?>
				</li>

				<li>
					<h2><?php esc_html_e( '3. Sync vehicle fitment', 'mk-auto-elite' ); ?></h2>
					<p><?php esc_html_e( 'Populates the vehicle finder (Make / Model / Year / Engine) from your product data. Re-run after each import.', 'mk-auto-elite' ); ?></p>
					<form method="post" action="<?php echo $action; ?>">
						<input type="hidden" name="action" value="mkae_sync_fitment">
						<?php wp_nonce_field( 'mkae_sync_fitment' ); ?>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Sync fitment now', 'mk-auto-elite' ); ?></button>
					</form>
				</li>

				<li>
					<h2><?php esc_html_e( '4. Create business & legal pages', 'mk-auto-elite' ); ?></h2>
					<p><?php esc_html_e( 'Creates About Us, Contact, Return & Refund, Shipping & Delivery, Terms, Privacy, and Payment Methods. Safe to re-run.', 'mk-auto-elite' ); ?></p>
					<form method="post" action="<?php echo $action; ?>">
						<input type="hidden" name="action" value="mkae_create_pages">
						<?php wp_nonce_field( 'mkae_create_pages' ); ?>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Create business pages', 'mk-auto-elite' ); ?></button>
					</form>
				</li>

				<li>
					<h2><?php esc_html_e( '5. Create Knowledge Centre articles', 'mk-auto-elite' ); ?></h2>
					<p><?php esc_html_e( 'Publishes 15 SEO-ready expert guides, each with a photo, organised by topic and interlinked. Safe to re-run; existing articles are updated and photos are imported once.', 'mk-auto-elite' ); ?></p>
					<form method="post" action="<?php echo $action; ?>">
						<input type="hidden" name="action" value="mkae_create_articles">
						<?php wp_nonce_field( 'mkae_create_articles' ); ?>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Create Knowledge Centre articles', 'mk-auto-elite' ); ?></button>
					</form>
					<p><?php esc_html_e( 'After running, open Settings > Permalinks and click Save once so the /knowledge/ URLs work.', 'mk-auto-elite' ); ?></p>
				</li>

				<li>
					<h2><?php esc_html_e( '6. Build the menus', 'mk-auto-elite' ); ?></h2>
					<p><?php esc_html_e( 'Builds and assigns a Primary menu (Home, Shop, Knowledge Centre, About, Contact). The Knowledge Centre link, with a dropdown of your guide topics, is added automatically once your articles are published. The "Shop by Category" mega menu is automatic and works on mobile.', 'mk-auto-elite' ); ?></p>
					<form method="post" action="<?php echo $action; ?>">
						<input type="hidden" name="action" value="mkae_build_menus">
						<?php wp_nonce_field( 'mkae_build_menus' ); ?>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Build menus now', 'mk-auto-elite' ); ?></button>
					</form>
					<p><a href="<?php echo esc_url( admin_url( 'nav-menus.php' ) ); ?>"><?php esc_html_e( 'Or edit menus manually', 'mk-auto-elite' ); ?></a></p>
				</li>

				<li>
					<h2><?php esc_html_e( '7. Configure contact & WhatsApp', 'mk-auto-elite' ); ?></h2>
					<p><?php esc_html_e( 'Set your phone, WhatsApp number, hours, delivery notice, and Google Map.', 'mk-auto-elite' ); ?></p>
					<a class="button" href="<?php echo esc_url( admin_url( 'customize.php?autofocus[section]=mkae_contact' ) ); ?>"><?php esc_html_e( 'Open Theme Options', 'mk-auto-elite' ); ?></a>
				</li>
			</ol>
		</div>
		<?php
	}
}
