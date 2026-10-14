<?php
/**
 * Main theme container.
 *
 * @package MK_Auto_Elite
 */

namespace MKAutoElite;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Singleton bootstrap that wires up all theme modules.
 */
final class Theme {

	/**
	 * Single instance.
	 *
	 * @var Theme|null
	 */
	private static ?Theme $instance = null;

	/**
	 * Loaded modules.
	 *
	 * @var array<string,object>
	 */
	private array $modules = array();

	/**
	 * Get the shared instance.
	 */
	public static function instance(): Theme {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Wire modules. Each module only registers hooks in its constructor,
	 * so instantiation here is safe and side-effect free until WordPress fires.
	 */
	private function __construct() {
		$this->modules['setup']      = new Setup();
		$this->modules['assets']     = new Assets();
		$this->modules['customizer'] = new Customizer();
		$this->modules['finder']     = new Vehicle\Finder();
		$this->modules['ajax']       = new Ajax\Handlers();
		$this->modules['woo']        = new Woo\WooCommerce();
		$this->modules['schema']     = new Seo\Schema();
		$this->modules['security']   = new Security();
		$this->modules['content']    = new Content();
		$this->modules['landing']    = new Landing();
		$this->modules['analytics']  = new Analytics();
		$this->modules['fitment']    = new Fitment\Tools();
		$this->modules['booking']    = new Booking\Module();
		$this->modules['chat']       = new Chat\Module();

		if ( is_admin() ) {
			$this->modules['plugins'] = new Admin\PluginActivation();
			$this->modules['tools']   = new Admin\Tools();
			$this->modules['wizard']  = new Admin\SetupWizard();
		}
	}

	/**
	 * Fetch a loaded module by key.
	 */
	public function module( string $key ): ?object {
		return $this->modules[ $key ] ?? null;
	}
}
