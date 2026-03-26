<?php
defined( 'ABSPATH' ) || exit;

/**
 * Vérification des mises à jour depuis GitHub Releases.
 *
 * Interroge l'API GitHub sur pre_set_site_transient_update_plugins pour détecter
 * si une nouvelle version est disponible sur le dépôt tedisun/wootsapp-notifier.
 * WordPress propose alors la mise à jour directement depuis Plugins > Mises à jour.
 *
 * Prérequis côté release :
 *   - Le ZIP doit contenir les fichiers dans un sous-dossier "wootsapp-notifier/"
 *     (voir .github/workflows/release.yml)
 *   - Le tag GitHub doit être "vX.X.X"
 */
class WTAN_Updater {

	const GITHUB_REPO = 'tedisun/wootsapp-notifier';
	const PLUGIN_SLUG = 'wootsapp-notifier';
	const PLUGIN_FILE = 'wootsapp-notifier/wootsapp-notifier.php';

	/** Durée de cache de la réponse GitHub (12 heures). */
	const CACHE_TTL = 43200;

	public function __construct() {
		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_update' ] );
		add_filter( 'plugins_api', [ $this, 'plugin_info' ], 10, 3 );
		add_filter( 'upgrader_source_selection', [ $this, 'fix_source_dir' ], 10, 4 );
	}

	/**
	 * Injecte la mise à jour dans le transient WordPress si une version plus récente
	 * est disponible sur GitHub.
	 *
	 * @param  object $transient Transient update_plugins.
	 * @return object
	 */
	public function check_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = $this->get_latest_release();

		if ( ! $release ) {
			return $transient;
		}

		$latest_version = ltrim( $release['tag_name'], 'v' );

		if ( version_compare( $latest_version, WTAN_VERSION, '>' ) ) {
			$transient->response[ self::PLUGIN_FILE ] = (object) [
				'slug'        => self::PLUGIN_SLUG,
				'plugin'      => self::PLUGIN_FILE,
				'new_version' => $latest_version,
				'url'         => 'https://github.com/' . self::GITHUB_REPO,
				'package'     => $release['zip_url'],
			];
		} else {
			// Aucune mise à jour — signaler que le plugin est à jour.
			$transient->no_update[ self::PLUGIN_FILE ] = (object) [
				'slug'        => self::PLUGIN_SLUG,
				'plugin'      => self::PLUGIN_FILE,
				'new_version' => $latest_version,
				'url'         => 'https://github.com/' . self::GITHUB_REPO,
				'package'     => '',
			];
		}

		return $transient;
	}

	/**
	 * Fournit les informations du plugin dans la modale "Voir les détails" de WordPress.
	 *
	 * @param  false|object $result Résultat existant.
	 * @param  string       $action Action demandée.
	 * @param  object       $args   Arguments (slug, etc.).
	 * @return false|object
	 */
	public function plugin_info( $result, string $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}

		if ( ! isset( $args->slug ) || self::PLUGIN_SLUG !== $args->slug ) {
			return $result;
		}

		$release = $this->get_latest_release();

		if ( ! $release ) {
			return $result;
		}

		$latest_version = ltrim( $release['tag_name'], 'v' );

		return (object) [
			'name'          => 'WootsApp Notifier',
			'slug'          => self::PLUGIN_SLUG,
			'version'       => $latest_version,
			'author'        => '<a href="https://tedisun.com">Tedisun SARL</a>',
			'homepage'      => 'https://github.com/' . self::GITHUB_REPO,
			'download_link' => $release['zip_url'],
			'sections'      => [
				'description' => 'Envoie automatiquement une notification WhatsApp au client dès que sa commande WooCommerce passe au statut "Terminé". Supporte Evolution API et l\'intégration LicenceFlow.',
				'changelog'   => ! empty( $release['body'] ) ? nl2br( esc_html( $release['body'] ) ) : '',
			],
			'requires'      => '6.0',
			'requires_php'  => '7.4',
			'tested'        => '6.7',
		];
	}

	/**
	 * Corrige le nom du dossier extrait si WordPress l'a renommé avec le suffixe du tag.
	 * GitHub nomme parfois le dossier extrait "wootsapp-notifier-1.4.0" au lieu de
	 * "wootsapp-notifier". Ce filtre le renomme correctement.
	 *
	 * @param  string      $source        Chemin du dossier extrait.
	 * @param  string      $remote_source Dossier ZIP distant.
	 * @param  WP_Upgrader $upgrader      Instance upgrader.
	 * @param  array       $hook_extra    Infos supplémentaires.
	 * @return string|WP_Error
	 */
	public function fix_source_dir( string $source, string $remote_source, $upgrader, array $hook_extra ) {
		if ( ! isset( $hook_extra['plugin'] ) || self::PLUGIN_FILE !== $hook_extra['plugin'] ) {
			return $source;
		}

		$corrected = trailingslashit( $remote_source ) . self::PLUGIN_SLUG . '/';

		if ( $source !== $corrected && is_dir( $source ) ) {
			global $wp_filesystem;
			if ( $wp_filesystem->move( $source, $corrected ) ) {
				return $corrected;
			}
		}

		return $source;
	}

	/**
	 * Interroge l'API GitHub pour récupérer la dernière release.
	 * Résultat mis en cache 12h dans un transient WordPress.
	 *
	 * @return array{tag_name:string,zip_url:string,body:string}|null
	 */
	private function get_latest_release(): ?array {
		$cache_key = 'wtan_github_latest_release';
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached ?: null; // '' stocké = échec précédent → ne pas retry
		}

		$url      = 'https://api.github.com/repos/' . self::GITHUB_REPO . '/releases/latest';
		$response = wp_remote_get( $url, [
			'timeout' => 10,
			'headers' => [
				'Accept'     => 'application/vnd.github+json',
				'User-Agent' => 'WootsApp-Notifier/' . WTAN_VERSION . '; WordPress/' . get_bloginfo( 'version' ),
			],
		] );

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			set_transient( $cache_key, '', self::CACHE_TTL );
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $data['tag_name'] ) || empty( $data['assets'] ) ) {
			set_transient( $cache_key, '', self::CACHE_TTL );
			return null;
		}

		// Chercher l'asset ZIP principal.
		$zip_url = '';
		foreach ( $data['assets'] as $asset ) {
			if ( strpos( $asset['name'], self::PLUGIN_SLUG ) !== false && strpos( $asset['name'], '.zip' ) !== false ) {
				$zip_url = $asset['browser_download_url'];
				break;
			}
		}

		if ( empty( $zip_url ) ) {
			set_transient( $cache_key, '', self::CACHE_TTL );
			return null;
		}

		$result = [
			'tag_name' => $data['tag_name'],
			'zip_url'  => $zip_url,
			'body'     => $data['body'] ?? '',
		];

		set_transient( $cache_key, $result, self::CACHE_TTL );

		return $result;
	}
}
