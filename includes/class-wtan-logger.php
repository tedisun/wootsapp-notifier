<?php
defined( 'ABSPATH' ) || exit;

/**
 * Gère la table de logs des notifications WhatsApp envoyées.
 *
 * Table : {prefix}wtan_logs
 * Colonnes : id, order_id, phone, status ('sent'|'failed'), error_msg, sent_at
 */
class WTAN_Logger {

	const TABLE = 'wtan_logs';

	/**
	 * Crée la table de logs (appelé à l'activation du plugin).
	 */
	public static function create_table(): void {
		global $wpdb;

		$table           = $wpdb->prefix . self::TABLE;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			order_id bigint(20) NOT NULL,
			phone varchar(50) NOT NULL,
			status varchar(20) NOT NULL,
			error_msg text DEFAULT NULL,
			sent_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY order_id (order_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Insère un enregistrement de log.
	 *
	 * @param int    $order_id  ID de la commande WooCommerce.
	 * @param string $phone     Numéro normalisé (ou brut si normalisation échouée).
	 * @param bool   $success   true = envoyé, false = échec.
	 * @param string $error_msg Message d'erreur en cas d'échec.
	 */
	public static function insert( int $order_id, string $phone, bool $success, string $error_msg = '' ): void {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . self::TABLE,
			[
				'order_id'  => $order_id,
				'phone'     => $phone,
				'status'    => $success ? 'sent' : 'failed',
				'error_msg' => $error_msg ?: null,
				'sent_at'   => current_time( 'mysql' ),
			],
			[ '%d', '%s', '%s', '%s', '%s' ]
		);
	}

	/**
	 * Récupère les logs paginés.
	 *
	 * @param  int $per_page Nombre de lignes par page.
	 * @param  int $page     Numéro de page (1-indexé).
	 * @return array{rows: array, total: int}
	 */
	public static function get( int $per_page = 50, int $page = 1 ): array {
		global $wpdb;

		$table  = $wpdb->prefix . self::TABLE;
		$offset = ( $page - 1 ) * $per_page;

		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY sent_at DESC LIMIT %d OFFSET %d",
				$per_page,
				$offset
			)
		);

		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		return [
			'rows'  => $rows ?: [],
			'total' => $total,
		];
	}

	/**
	 * Supprime tous les logs.
	 */
	public static function clear(): void {
		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . $wpdb->prefix . self::TABLE );
	}
}
