<?php
defined( 'ABSPATH' ) || exit;

/**
 * Normalise les numéros de téléphone vers le format WhatsApp (multi-pays).
 *
 * Format cible : {indicatif}{numéro}@s.whatsapp.net
 *
 * Logique :
 *   1. Supprimer tout caractère non numérique
 *   2. Supprimer le préfixe "00" s'il précède un indicatif
 *   3. Si ≥ 10 chiffres → indicatif pays déjà inclus → passer tel quel
 *   4. Si 9 chiffres commençant par "0" → format local avec zéro → supprimer le 0 → 8 chiffres BF
 *   5. Si exactement 8 chiffres → numéro local Burkina Faso → ajouter l'indicatif 226
 *   6. Sinon → invalide
 *
 * Exemples :
 *   +226 70 12 34 56     → 22670123456@s.whatsapp.net  (Burkina Faso)
 *   +225 07 12 34 56 78  → 22507123456@s.whatsapp.net  (Côte d'Ivoire)
 *   +221 77 123 45 67    → 22177123456@s.whatsapp.net  (Sénégal)
 *   +33 6 12 34 56 78    → 33612345678@s.whatsapp.net  (France)
 *   70 12 34 56          → 22670123456@s.whatsapp.net  (local BF 8 chiffres)
 */
class WTAN_Phone {

	/**
	 * Normalise un numéro brut vers le format WhatsApp.
	 *
	 * @param  string $raw  Numéro brut saisi par le client ou en option de test.
	 * @return string|false Numéro au format "{indicatif}{numéro}@s.whatsapp.net", ou false si invalide.
	 */
	public static function normalize( string $raw ) {
		$digits = preg_replace( '/\D+/', '', $raw );

		if ( empty( $digits ) ) {
			return false;
		}

		// Supprimer le préfixe "00" d'un indicatif international (ex: 00226… → 226…).
		if ( strpos( $digits, '00' ) === 0 ) {
			$digits = substr( $digits, 2 );
		}

		// ≥ 10 chiffres → l'indicatif pays est déjà inclus → passer tel quel.
		if ( strlen( $digits ) >= 10 ) {
			return $digits . '@s.whatsapp.net';
		}

		// 9 chiffres commençant par "0" → format local BF avec zéro initial (ex: 070123456).
		if ( strpos( $digits, '0' ) === 0 && strlen( $digits ) === 9 ) {
			$digits = substr( $digits, 1 );
		}

		// 8 chiffres → numéro local Burkina Faso → préfixer avec 226.
		if ( strlen( $digits ) === 8 ) {
			return '226' . $digits . '@s.whatsapp.net';
		}

		return false;
	}
}
