<?php
/**
 * Plugin Name: WootsApp Notifier
 * Plugin URI:  https://github.com/tedisun/wootsapp-notifier
 * Description: Envoie automatiquement une notification WhatsApp au client dès que sa commande WooCommerce passe au statut "Terminé". Supporte Evolution API et l'intégration LicenceFlow.
 * Version:     1.3.0
 * Author:      Tedisun SARL
 * Author URI:  https://tedisun.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wootsapp-notifier
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 9.0
 */

defined( 'ABSPATH' ) || exit;

define( 'WTAN_VERSION', '1.3.0' );
define( 'WTAN_PLUGIN_FILE', __FILE__ );
define( 'WTAN_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Déclare la compatibilité HPOS (High-Performance Order Storage).
 */
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			__FILE__,
			true
		);
	}
} );

/**
 * Vérifie que WooCommerce est actif avant de charger le plugin.
 */
function wtan_check_woocommerce(): bool {
	return class_exists( 'WooCommerce' );
}

/**
 * Affiche une notice admin si WooCommerce n'est pas actif.
 */
function wtan_admin_notice_woocommerce_missing(): void {
	echo '<div class="notice notice-error"><p>';
	echo '<strong>WootsApp Notifier</strong> nécessite WooCommerce. Veuillez installer et activer WooCommerce.';
	echo '</p></div>';
}

/**
 * Migration unique depuis l'ancienne version "Presellia WhatsApp Notify" (options pwan_*).
 * S'exécute une seule fois au premier chargement après mise à jour.
 */
function wtan_maybe_migrate_from_pwan(): void {
	if ( get_option( 'wtan_migrated_from_pwan' ) ) {
		return;
	}

	$map = [
		'pwan_enabled'          => 'wtan_enabled',
		'pwan_api_url'          => 'wtan_api_url',
		'pwan_api_instance'     => 'wtan_api_instance',
		'pwan_api_key'          => 'wtan_api_key',
		'pwan_test_phone'       => 'wtan_test_phone',
		'pwan_message_template' => 'wtan_message_template',
	];

	foreach ( $map as $old => $new ) {
		$value = get_option( $old );
		if ( false !== $value ) {
			update_option( $new, $value );
		}
	}

	update_option( 'wtan_migrated_from_pwan', '1' );
}
add_action( 'plugins_loaded', 'wtan_maybe_migrate_from_pwan', 1 );

/**
 * Charge toutes les classes du plugin.
 */
function wtan_load(): void {
	if ( ! wtan_check_woocommerce() ) {
		add_action( 'admin_notices', 'wtan_admin_notice_woocommerce_missing' );
		return;
	}

	require_once WTAN_PLUGIN_DIR . 'includes/class-wtan-phone.php';
	require_once WTAN_PLUGIN_DIR . 'includes/class-wtan-api.php';
	require_once WTAN_PLUGIN_DIR . 'includes/class-wtan-logger.php';
	require_once WTAN_PLUGIN_DIR . 'includes/class-wtan-notifier.php';

	if ( is_admin() ) {
		require_once WTAN_PLUGIN_DIR . 'admin/class-wtan-admin.php';
		new WTAN_Admin();
	}

	new WTAN_Notifier();
}
add_action( 'plugins_loaded', 'wtan_load' );

/**
 * Activation : crée la table de logs.
 */
function wtan_activate(): void {
	require_once WTAN_PLUGIN_DIR . 'includes/class-wtan-logger.php';
	WTAN_Logger::create_table();
}
register_activation_hook( __FILE__, 'wtan_activate' );

/**
 * Désactivation : nettoyage léger (données conservées).
 */
function wtan_deactivate(): void {
	// Les données (logs, options) sont conservées pour éviter toute perte accidentelle.
}
register_deactivation_hook( __FILE__, 'wtan_deactivate' );
