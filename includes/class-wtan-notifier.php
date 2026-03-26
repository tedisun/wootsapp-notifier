<?php
defined( 'ABSPATH' ) || exit;

/**
 * Orchestre l'envoi des notifications WhatsApp lors du passage
 * d'une commande WooCommerce au statut "completed".
 *
 * Anti-doublon : la méta "_wtan_notification_sent" est vérifiée avant
 * l'envoi et écrite après un envoi réussi. Compatible HPOS.
 *
 * Template de message : configurable depuis l'admin via des variables
 * entre accolades (ex : {prenom}, {total}, {produits}, {licences}).
 *
 * Extensibilité :
 *   - Filtre "wtan_message_variables" : injecter des variables supplémentaires.
 *   - Intégration native LicenceFlow via la variable {licences} (si option activée).
 *
 * Note sur le timing LicenceFlow :
 *   LicenceFlow livre les licences sur woocommerce_order_status_completed (priorité 1).
 *   Ce notifier s'exécute sur woocommerce_order_status_changed (priorité 10, après).
 *   Les licences sont donc toujours disponibles quand on compose le message.
 */
class WTAN_Notifier {

	/**
	 * Template par défaut (utilisé si l'option wtan_message_template est vide).
	 */
	const DEFAULT_TEMPLATE = "✅ Commande confirmée !\n\nBonjour {prenom},\n\nMerci pour votre commande !\n\n{produits}\n\n💰 Total : {total}\n\nUne question ? Répondez à ce message.";

	public function __construct() {
		add_action( 'woocommerce_order_status_changed', [ $this, 'on_status_changed' ], 10, 3 );
	}

	/**
	 * Déclencheur : statut de commande modifié.
	 *
	 * @param int    $order_id ID de la commande.
	 * @param string $from     Ancien statut.
	 * @param string $to       Nouveau statut.
	 */
	public function on_status_changed( int $order_id, string $from, string $to ): void {
		if ( 'completed' !== $to ) {
			return;
		}

		if ( ! get_option( 'wtan_enabled', '1' ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order instanceof WC_Abstract_Order ) {
			return;
		}

		// Anti-doublon : ne pas renvoyer si déjà envoyé avec succès.
		if ( $order->get_meta( '_wtan_notification_sent' ) ) {
			return;
		}

		$raw_phone = $order->get_billing_phone();

		if ( empty( $raw_phone ) ) {
			error_log( sprintf( '[WootsApp] Commande #%d : numéro de téléphone absent, notification ignorée.', $order_id ) );
			return;
		}

		$phone = WTAN_Phone::normalize( $raw_phone );

		if ( false === $phone ) {
			$error = sprintf( 'Numéro invalide ou non reconnu : "%s"', $raw_phone );
			error_log( sprintf( '[WootsApp] Commande #%d : %s', $order_id, $error ) );
			$order->add_order_note( '❌ Notification WhatsApp non envoyée : ' . $error );
			WTAN_Logger::insert( $order_id, $raw_phone, false, $error );
			return;
		}

		$message = $this->render_template( $order );
		$result  = WTAN_Api::send( $phone, $message );

		WTAN_Logger::insert( $order_id, $phone, $result['success'], $result['body'] );

		if ( $result['success'] ) {
			$order->add_order_note( '✅ Notification WhatsApp envoyée avec succès.' );
			$order->update_meta_data( '_wtan_notification_sent', '1' );
			$order->save();
		} else {
			$error = sprintf( 'HTTP %d — %s', $result['code'], $result['body'] );
			error_log( sprintf( '[WootsApp] Commande #%d : échec envoi WhatsApp. %s', $order_id, $error ) );
			$order->add_order_note( '❌ Échec envoi WhatsApp : ' . $error );
		}
	}

	/**
	 * Applique le template de message en remplaçant les variables.
	 *
	 * @param  WC_Abstract_Order $order Commande WooCommerce.
	 * @return string Message final.
	 */
	public function render_template( WC_Abstract_Order $order ): string {
		$template = (string) get_option( 'wtan_message_template', '' );

		if ( empty( trim( $template ) ) ) {
			$template = self::DEFAULT_TEMPLATE;
		}

		$variables = $this->build_variables( $order );

		/**
		 * Filtre wtan_message_variables
		 *
		 * Permet à d'autres plugins d'ajouter des variables dans le template.
		 * Exemple :
		 *   add_filter( 'wtan_message_variables', function( $vars, $order ) {
		 *       $vars['{ma_variable}'] = 'valeur';
		 *       return $vars;
		 *   }, 10, 2 );
		 *
		 * @param array<string,string> $variables Variables disponibles.
		 * @param WC_Abstract_Order    $order     Commande en cours.
		 */
		$variables = apply_filters( 'wtan_message_variables', $variables, $order );

		$message = str_replace(
			array_keys( $variables ),
			array_values( $variables ),
			$template
		);

		// Nettoyer les lignes vides consécutives laissées par des variables vides.
		$message = preg_replace( '/\n{3,}/', "\n\n", $message );

		return trim( $message );
	}

	/**
	 * Construit le tableau des variables disponibles pour la commande.
	 *
	 * @param  WC_Abstract_Order $order Commande WooCommerce.
	 * @return array<string,string>
	 */
	private function build_variables( WC_Abstract_Order $order ): array {
		$lines = [];
		foreach ( $order->get_items() as $item ) {
			/** @var WC_Order_Item_Product $item */
			$lines[] = sprintf( '🛍️ %s x%d', $item->get_name(), (int) $item->get_quantity() );
		}

		$total = number_format( (float) $order->get_total(), 0, ',', ' ' );

		return [
			'{prenom}'          => $order->get_billing_first_name(),
			'{nom}'             => $order->get_billing_last_name(),
			'{prenom_nom}'      => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
			'{numero_commande}' => $order->get_order_number(),
			'{date_commande}'   => wc_format_datetime( $order->get_date_created() ),
			'{total}'           => $total,
			'{monnaie}'         => get_woocommerce_currency_symbol(),
			'{produits}'        => implode( "\n", $lines ),
			'{nb_articles}'     => (string) $order->get_item_count(),
			'{licences}'        => $this->build_licences_text( (int) $order->get_id() ),
		];
	}

	/**
	 * Compose le bloc de licences LicenceFlow pour la commande.
	 *
	 * Retourne une chaîne vide si :
	 *   - LicenceFlow n'est pas installé/actif
	 *   - L'option "Inclure les licences" est désactivée
	 *   - Aucune licence n'est associée à la commande
	 *
	 * Note : LicenceFlow livre les licences sur woocommerce_order_status_completed
	 * (priorité 1), avant ce notifier (woocommerce_order_status_changed, priorité 10).
	 * Les licences sont donc toujours disponibles ici.
	 *
	 * @param  int $order_id ID de la commande.
	 * @return string
	 */
	private function build_licences_text( int $order_id ): string {
		if ( ! class_exists( 'LicenceFlow_License_DB' ) ) {
			return '';
		}

		if ( ! get_option( 'wtan_include_licences', '' ) ) {
			return '';
		}

		$licenses = LicenceFlow_License_DB::get_by_order( $order_id );

		if ( empty( $licenses ) ) {
			return '';
		}

		$lines = [];

		foreach ( $licenses as $lic ) {
			$product      = wc_get_product( (int) $lic['product_id'] );
			$product_name = $product ? $product->get_name() : '#' . $lic['product_id'];

			$key_display = $this->format_license_key(
				(string) $lic['license_key'],
				(string) $lic['license_type']
			);

			$entry = '🔑 ' . $product_name . "\n" . $key_display;

			if ( ! empty( $lic['license_note'] ) ) {
				$entry .= "\n📝 " . $lic['license_note'];
			}

			$lines[] = $entry;
		}

		return implode( "\n\n", $lines );
	}

	/**
	 * Formate la clé d'une licence selon son type pour l'affichage dans le message.
	 *
	 * @param  string $key_raw Valeur brute décryptée (plain string ou JSON).
	 * @param  string $type    Type de licence : key | account | link | code.
	 * @return string
	 */
	private function format_license_key( string $key_raw, string $type ): string {
		switch ( $type ) {
			case 'account':
				$data = json_decode( $key_raw, true );
				if ( is_array( $data ) ) {
					return sprintf(
						"Utilisateur : %s\nMot de passe : %s",
						$data['username'] ?? '',
						$data['password'] ?? ''
					);
				}
				break;

			case 'link':
				$data = json_decode( $key_raw, true );
				if ( is_array( $data ) ) {
					$label = ! empty( $data['label'] ) ? $data['label'] : 'Lien';
					return $label . ' : ' . ( $data['url'] ?? '' );
				}
				break;

			case 'code':
				$data = json_decode( $key_raw, true );
				if ( is_array( $data ) ) {
					$out = 'Code : ' . ( $data['code'] ?? $key_raw );
					if ( ! empty( $data['note'] ) ) {
						$out .= "\n" . $data['note'];
					}
					return $out;
				}
				break;
		}

		// 'key' ou fallback : afficher la valeur brute directement.
		return $key_raw;
	}

	/**
	 * Retourne la liste des variables disponibles pour l'affichage dans l'admin.
	 *
	 * @return array<string,string> Clé = variable, Valeur = description.
	 */
	public static function available_variables(): array {
		$vars = [
			'{prenom}'          => 'Prénom du client (billing)',
			'{nom}'             => 'Nom du client (billing)',
			'{prenom_nom}'      => 'Prénom + Nom complet',
			'{numero_commande}' => 'Numéro de la commande',
			'{date_commande}'   => 'Date de la commande',
			'{total}'           => 'Montant total (chiffres seulement)',
			'{monnaie}'         => 'Symbole de la monnaie WooCommerce',
			'{produits}'        => 'Liste des produits (une ligne par article)',
			'{nb_articles}'     => 'Nombre total d\'articles',
			'{licences}'        => 'Licences LicenceFlow (vide si non configuré)',
		];

		return $vars;
	}
}
