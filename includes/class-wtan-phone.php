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
 *   3. Si ≥ 10 chiffres et ne commence pas par "0" → indicatif pays déjà inclus → passer tel quel
 *   4. Supprimer le "0" initial (format local avec zéro)
 *   5. Chercher l'indicatif dans la map pays de facturation
 *   6. Fallback : 8 chiffres → Burkina Faso (226)
 *   7. Sinon → invalide
 *
 * Exemples :
 *   +226 70 12 34 56     → 22670123456@s.whatsapp.net  (Burkina Faso)
 *   +225 07 12 34 56 78  → 22507123456@s.whatsapp.net  (Côte d'Ivoire)
 *   0768319147 [CI]      → 225768319147@s.whatsapp.net (local CI avec 0 initial)
 *   +221 77 123 45 67    → 22177123456@s.whatsapp.net  (Sénégal)
 *   +33 6 12 34 56 78    → 33612345678@s.whatsapp.net  (France)
 *   70 12 34 56          → 22670123456@s.whatsapp.net  (local BF 8 chiffres)
 */
class WTAN_Phone {

	/**
	 * Map pays ISO → [indicatif international, longueur locale SANS le 0 initial].
	 *
	 * @var array<string, array{dial: string, local: int}>
	 */
	private static $country_map = [
		'BF' => [ 'dial' => '226', 'local' => 8  ],
		'CI' => [ 'dial' => '225', 'local' => 9  ],
		'SN' => [ 'dial' => '221', 'local' => 9  ],
		'ML' => [ 'dial' => '223', 'local' => 8  ],
		'GN' => [ 'dial' => '224', 'local' => 9  ],
		'TG' => [ 'dial' => '228', 'local' => 8  ],
		'BJ' => [ 'dial' => '229', 'local' => 8  ],
		'NE' => [ 'dial' => '227', 'local' => 8  ],
		'CM' => [ 'dial' => '237', 'local' => 9  ],
		'FR' => [ 'dial' => '33',  'local' => 9  ],
		'MA' => [ 'dial' => '212', 'local' => 9  ],
		'GH' => [ 'dial' => '233', 'local' => 9  ],
		'NG' => [ 'dial' => '234', 'local' => 10 ],
		'CD' => [ 'dial' => '243', 'local' => 9  ],
		'CG' => [ 'dial' => '242', 'local' => 9  ],
		'MR' => [ 'dial' => '222', 'local' => 8  ],
		'SL' => [ 'dial' => '232', 'local' => 8  ],
		'LR' => [ 'dial' => '231', 'local' => 8  ],
		'RW' => [ 'dial' => '250', 'local' => 9  ],
		'KE' => [ 'dial' => '254', 'local' => 9  ],
		'TZ' => [ 'dial' => '255', 'local' => 9  ],
		'BE' => [ 'dial' => '32',  'local' => 9  ],
		'CH' => [ 'dial' => '41',  'local' => 9  ],
		'TD' => [ 'dial' => '235', 'local' => 8  ],
		'CF' => [ 'dial' => '236', 'local' => 8  ],
	];

	/**
	 * Normalise un numéro brut vers le format WhatsApp.
	 *
	 * @param  string $raw     Numéro brut saisi par le client ou en option de test.
	 * @param  string $country Code ISO 2 lettres du pays de facturation (ex: 'BF', 'CI', 'FR').
	 * @return string|false    Numéro au format "{indicatif}{numéro}@s.whatsapp.net", ou false si invalide.
	 */
	public static function normalize( string $raw, string $country = 'BF' ) {
		$digits = preg_replace( '/\D+/', '', $raw );

		if ( empty( $digits ) ) {
			return false;
		}

		// Supprimer le préfixe "00" d'un indicatif international (ex: 00226… → 226…).
		if ( strpos( $digits, '00' ) === 0 ) {
			$digits = substr( $digits, 2 );
		}

		if ( empty( $digits ) ) {
			return false;
		}

		// ≥ 10 chiffres sans "0" initial → l'indicatif pays est déjà inclus → passer tel quel.
		if ( strlen( $digits ) >= 10 && strpos( $digits, '0' ) !== 0 ) {
			return $digits . '@s.whatsapp.net';
		}

		// Supprimer le "0" initial s'il est présent (format local avec zéro, ex: 0768319147).
		$stripped = ( strpos( $digits, '0' ) === 0 ) ? substr( $digits, 1 ) : $digits;

		// Utiliser la map du pays de facturation pour déterminer l'indicatif.
		$country = strtoupper( $country );
		if ( isset( self::$country_map[ $country ] ) ) {
			$info = self::$country_map[ $country ];
			if ( strlen( $stripped ) === $info['local'] ) {
				return $info['dial'] . $stripped . '@s.whatsapp.net';
			}
		}

		// Fallback : 8 chiffres → Burkina Faso (compatibilité ascendante).
		if ( strlen( $stripped ) === 8 ) {
			return '226' . $stripped . '@s.whatsapp.net';
		}

		return false;
	}
}
