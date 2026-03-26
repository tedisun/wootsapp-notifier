<?php
defined( 'ABSPATH' ) || exit;

/**
 * Gère les pages d'administration de WootsApp Notifier (menu autonome) :
 *   - WA Notify > Réglages
 *   - WA Notify > Template de message
 *   - WA Notify > Logs
 */
class WTAN_Admin {

	const MENU_SLUG = 'wtan-settings';

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_menus' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_init', [ $this, 'handle_clear_logs' ] );
		add_action( 'wp_ajax_wtan_test_send', [ $this, 'ajax_test_send' ] );
		add_action( 'admin_footer', [ $this, 'inline_script' ] );
	}

	// -------------------------------------------------------------------------
	// Menus
	// -------------------------------------------------------------------------

	public function register_menus(): void {
		add_menu_page(
			__( 'WootsApp Notifier', 'wootsapp-notifier' ),
			__( 'WA Notify', 'wootsapp-notifier' ),
			'manage_options',
			self::MENU_SLUG,
			[ $this, 'page_settings' ],
			'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="#25D366" d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>' ),
			56
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Réglages — WootsApp Notifier', 'wootsapp-notifier' ),
			__( 'Réglages', 'wootsapp-notifier' ),
			'manage_options',
			self::MENU_SLUG,
			[ $this, 'page_settings' ]
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Template de message — WootsApp Notifier', 'wootsapp-notifier' ),
			__( 'Template de message', 'wootsapp-notifier' ),
			'manage_options',
			'wtan-template',
			[ $this, 'page_template' ]
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Logs — WootsApp Notifier', 'wootsapp-notifier' ),
			__( 'Logs', 'wootsapp-notifier' ),
			'manage_options',
			'wtan-logs',
			[ $this, 'page_logs' ]
		);
	}

	// -------------------------------------------------------------------------
	// Settings API
	// -------------------------------------------------------------------------

	public function register_settings(): void {
		register_setting( 'wtan_settings_group', 'wtan_enabled', [
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '1',
		] );
		register_setting( 'wtan_settings_group', 'wtan_api_url', [
			'type'              => 'string',
			'sanitize_callback' => 'esc_url_raw',
			'default'           => '',
		] );
		register_setting( 'wtan_settings_group', 'wtan_api_instance', [
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		] );
		register_setting( 'wtan_settings_group', 'wtan_api_key', [
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		] );
		register_setting( 'wtan_settings_group', 'wtan_test_phone', [
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		] );
		register_setting( 'wtan_settings_group', 'wtan_include_licences', [
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		] );
		register_setting( 'wtan_template_group', 'wtan_message_template', [
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_textarea_field',
			'default'           => '',
		] );
	}

	// -------------------------------------------------------------------------
	// Page Réglages
	// -------------------------------------------------------------------------

	public function page_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$licenceflow_active = class_exists( 'LicenceFlow_License_DB' );
		?>
		<div class="wrap">
			<h1>
				<span style="color:#25D366;font-size:1.2em;">&#9679;</span>
				<?php esc_html_e( 'WootsApp Notifier — Réglages', 'wootsapp-notifier' ); ?>
			</h1>

			<?php settings_errors( 'wtan_settings_group' ); ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'wtan_settings_group' ); ?>
				<table class="form-table" role="presentation">

					<tr>
						<th scope="row"><?php esc_html_e( 'Activer les notifications', 'wootsapp-notifier' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="wtan_enabled" value="1"
									<?php checked( get_option( 'wtan_enabled', '1' ), '1' ); ?> />
								<?php esc_html_e( 'Envoyer des notifications WhatsApp lors des commandes terminées', 'wootsapp-notifier' ); ?>
							</label>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="wtan_api_url"><?php esc_html_e( 'URL de l\'API WhatsApp', 'wootsapp-notifier' ); ?></label>
						</th>
						<td>
							<input type="url" id="wtan_api_url" name="wtan_api_url" class="regular-text"
								value="<?php echo esc_attr( get_option( 'wtan_api_url', '' ) ); ?>"
								placeholder="https://api.monserveur.com" />
							<p class="description"><?php esc_html_e( 'URL de base de votre serveur Evolution API (sans slash final).', 'wootsapp-notifier' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="wtan_api_instance"><?php esc_html_e( 'Nom de l\'instance', 'wootsapp-notifier' ); ?></label>
						</th>
						<td>
							<input type="text" id="wtan_api_instance" name="wtan_api_instance" class="regular-text"
								value="<?php echo esc_attr( get_option( 'wtan_api_instance', '' ) ); ?>"
								placeholder="mon-instance" />
							<p class="description"><?php esc_html_e( 'Nom de l\'instance Evolution API configurée sur votre serveur.', 'wootsapp-notifier' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="wtan_api_key"><?php esc_html_e( 'Clé API', 'wootsapp-notifier' ); ?></label>
						</th>
						<td>
							<input type="password" id="wtan_api_key" name="wtan_api_key" class="regular-text"
								value="<?php echo esc_attr( get_option( 'wtan_api_key', '' ) ); ?>"
								autocomplete="new-password" />
							<p class="description"><?php esc_html_e( 'Clé API Evolution API. Jamais affichée en clair après sauvegarde.', 'wootsapp-notifier' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="wtan_test_phone"><?php esc_html_e( 'Numéro de test', 'wootsapp-notifier' ); ?></label>
						</th>
						<td>
							<input type="text" id="wtan_test_phone" name="wtan_test_phone" class="regular-text"
								value="<?php echo esc_attr( get_option( 'wtan_test_phone', '' ) ); ?>"
								placeholder="22670123456" />
							<p class="description">
								<?php esc_html_e( 'Numéro complet avec indicatif pays, sans + ni 00.', 'wootsapp-notifier' ); ?>
								<br><strong><?php esc_html_e( 'Exemples :', 'wootsapp-notifier' ); ?></strong>
								<code>22670123456</code> (Burkina Faso) &nbsp;|&nbsp;
								<code>22507123456</code> (Côte d'Ivoire) &nbsp;|&nbsp;
								<code>33612345678</code> (France)
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Intégration LicenceFlow', 'wootsapp-notifier' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="wtan_include_licences" value="1"
									<?php checked( get_option( 'wtan_include_licences', '' ), '1' ); ?>
									<?php disabled( ! $licenceflow_active ); ?> />
								<?php esc_html_e( 'Inclure les licences dans le message WhatsApp', 'wootsapp-notifier' ); ?>
							</label>
							<p class="description">
								<?php if ( $licenceflow_active ) : ?>
									<span style="color:#2e7d32;">✅ <?php esc_html_e( 'LicenceFlow détecté.', 'wootsapp-notifier' ); ?></span>
									<?php esc_html_e( 'Ajoutez la variable {licences} dans votre template de message.', 'wootsapp-notifier' ); ?>
								<?php else : ?>
									<span style="color:#888;">⚠️ <?php esc_html_e( 'LicenceFlow non détecté. Installez et activez LicenceFlow pour utiliser cette option.', 'wootsapp-notifier' ); ?></span>
								<?php endif; ?>
							</p>
						</td>
					</tr>

				</table>

				<?php submit_button( __( 'Enregistrer les réglages', 'wootsapp-notifier' ) ); ?>
			</form>

			<hr />
			<h2><?php esc_html_e( 'Test de connexion', 'wootsapp-notifier' ); ?></h2>
			<p><?php esc_html_e( 'Envoie un message de test au numéro configuré ci-dessus.', 'wootsapp-notifier' ); ?></p>
			<button id="wtan-test-btn" class="button button-secondary">
				<?php esc_html_e( 'Envoyer un message de test', 'wootsapp-notifier' ); ?>
			</button>
			<span id="wtan-test-result" style="margin-left:12px;vertical-align:middle;"></span>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Page Template de message
	// -------------------------------------------------------------------------

	public function page_template(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$current_template = get_option( 'wtan_message_template', '' );
		$default_template = WTAN_Notifier::DEFAULT_TEMPLATE;
		$variables        = WTAN_Notifier::available_variables();
		$licenceflow_active = class_exists( 'LicenceFlow_License_DB' );
		?>
		<div class="wrap">
			<h1>
				<span style="color:#25D366;font-size:1.2em;">&#9679;</span>
				<?php esc_html_e( 'WootsApp Notifier — Template de message', 'wootsapp-notifier' ); ?>
			</h1>

			<?php settings_errors( 'wtan_template_group' ); ?>

			<div style="display:flex;gap:32px;align-items:flex-start;flex-wrap:wrap;">

				<!-- Formulaire template -->
				<div style="flex:1;min-width:400px;">
					<form method="post" action="options.php">
						<?php settings_fields( 'wtan_template_group' ); ?>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row">
									<label for="wtan_message_template"><?php esc_html_e( 'Template du message', 'wootsapp-notifier' ); ?></label>
								</th>
								<td>
									<textarea
										id="wtan_message_template"
										name="wtan_message_template"
										rows="16"
										style="width:100%;font-family:monospace;font-size:13px;line-height:1.6;"
									><?php echo esc_textarea( $current_template ?: $default_template ); ?></textarea>
									<p class="description">
										<?php esc_html_e( 'Utilisez les variables ci-contre. Laissez vide pour revenir au template par défaut.', 'wootsapp-notifier' ); ?>
									</p>
								</td>
							</tr>
						</table>

						<p>
							<?php submit_button( __( 'Enregistrer le template', 'wootsapp-notifier' ), 'primary', 'submit', false ); ?>
							&nbsp;
							<button type="button" id="wtan-reset-template" class="button button-secondary">
								<?php esc_html_e( 'Réinitialiser au template par défaut', 'wootsapp-notifier' ); ?>
							</button>
						</p>
					</form>
				</div>

				<!-- Panneau variables -->
				<div style="min-width:290px;background:#fff;border:1px solid #ddd;border-radius:4px;padding:16px 20px;">
					<h3 style="margin-top:0;"><?php esc_html_e( 'Variables disponibles', 'wootsapp-notifier' ); ?></h3>
					<p style="color:#666;font-size:12px;"><?php esc_html_e( 'Cliquez pour insérer à la position du curseur.', 'wootsapp-notifier' ); ?></p>
					<table style="width:100%;border-collapse:collapse;">
						<?php foreach ( $variables as $var => $desc ) : ?>
							<tr>
								<td style="padding:5px 8px 5px 0;vertical-align:top;">
									<?php if ( '{licences}' === $var && ! $licenceflow_active ) : ?>
										<code style="background:#f0f0f0;padding:2px 6px;border-radius:3px;font-size:12px;opacity:.5;"><?php echo esc_html( $var ); ?></code>
									<?php else : ?>
										<code
											class="wtan-var-copy"
											data-var="<?php echo esc_attr( $var ); ?>"
											style="cursor:pointer;background:#f0f0f0;padding:2px 6px;border-radius:3px;font-size:12px;"
											title="<?php esc_attr_e( 'Cliquer pour insérer', 'wootsapp-notifier' ); ?>"
										><?php echo esc_html( $var ); ?></code>
									<?php endif; ?>
								</td>
								<td style="padding:5px 0;color:#555;font-size:12px;">
									<?php echo esc_html( $desc ); ?>
									<?php if ( '{licences}' === $var && ! $licenceflow_active ) : ?>
										<span style="color:#c62828;">(LicenceFlow requis)</span>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</table>

					<hr style="margin:16px 0;" />
					<h4 style="margin-bottom:8px;"><?php esc_html_e( 'Template par défaut', 'wootsapp-notifier' ); ?></h4>
					<pre style="background:#f9f9f9;border:1px solid #eee;padding:10px;font-size:11px;line-height:1.5;white-space:pre-wrap;border-radius:3px;"><?php echo esc_html( $default_template ); ?></pre>
				</div>
			</div>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Page Logs
	// -------------------------------------------------------------------------

	public function page_logs(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$per_page = 50;
		$page     = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
		$data     = WTAN_Logger::get( $per_page, $page );
		$rows     = $data['rows'];
		$total    = $data['total'];
		$pages    = (int) ceil( $total / $per_page );
		?>
		<div class="wrap">
			<h1>
				<span style="color:#25D366;font-size:1.2em;">&#9679;</span>
				<?php esc_html_e( 'WootsApp Notifier — Logs', 'wootsapp-notifier' ); ?>
			</h1>

			<?php if ( isset( $_GET['wtan_cleared'] ) ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Logs supprimés avec succès.', 'wootsapp-notifier' ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" style="margin-bottom:16px;">
				<?php wp_nonce_field( 'wtan_clear_logs', 'wtan_clear_logs_nonce' ); ?>
				<input type="hidden" name="wtan_action" value="clear_logs" />
				<?php submit_button(
					__( 'Vider les logs', 'wootsapp-notifier' ),
					'delete',
					'submit',
					false,
					[ 'onclick' => 'return confirm("Supprimer tous les logs ?")' ]
				); ?>
			</form>

			<p>
				<?php printf(
					esc_html__( '%d enregistrement(s) au total.', 'wootsapp-notifier' ),
					$total
				); ?>
			</p>

			<?php if ( empty( $rows ) ) : ?>
				<p><?php esc_html_e( 'Aucun log pour l\'instant.', 'wootsapp-notifier' ); ?></p>
			<?php else : ?>
				<table class="widefat fixed striped">
					<thead>
						<tr>
							<th style="width:160px"><?php esc_html_e( 'Date', 'wootsapp-notifier' ); ?></th>
							<th style="width:100px"><?php esc_html_e( 'Commande', 'wootsapp-notifier' ); ?></th>
							<th style="width:200px"><?php esc_html_e( 'Téléphone', 'wootsapp-notifier' ); ?></th>
							<th style="width:80px"><?php esc_html_e( 'Statut', 'wootsapp-notifier' ); ?></th>
							<th><?php esc_html_e( 'Détail / Erreur', 'wootsapp-notifier' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $row ) : ?>
							<tr>
								<td><?php echo esc_html( $row->sent_at ); ?></td>
								<td>
									<?php
									$edit_url = get_edit_post_link( (int) $row->order_id );
									if ( $edit_url ) {
										printf( '<a href="%s">#%d</a>', esc_url( $edit_url ), (int) $row->order_id );
									} else {
										echo '#' . (int) $row->order_id;
									}
									?>
								</td>
								<td><?php echo esc_html( $row->phone ); ?></td>
								<td>
									<?php if ( 'sent' === $row->status ) : ?>
										<span style="color:#2e7d32;font-weight:bold;">✅ Envoyé</span>
									<?php else : ?>
										<span style="color:#c62828;font-weight:bold;">❌ Échec</span>
									<?php endif; ?>
								</td>
								<td>
									<?php if ( ! empty( $row->error_msg ) ) : ?>
										<code><?php echo esc_html( mb_substr( $row->error_msg, 0, 200 ) ); ?></code>
									<?php else : ?>
										—
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<?php if ( $pages > 1 ) : ?>
					<div class="tablenav bottom" style="margin-top:12px;">
						<?php echo paginate_links( [
							'base'    => add_query_arg( 'paged', '%#%' ),
							'format'  => '',
							'current' => $page,
							'total'   => $pages,
						] ); ?>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	// -------------------------------------------------------------------------
	// Clear logs (POST)
	// -------------------------------------------------------------------------

	public function handle_clear_logs(): void {
		if (
			! isset( $_POST['wtan_action'] ) ||
			'clear_logs' !== $_POST['wtan_action']
		) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		check_admin_referer( 'wtan_clear_logs', 'wtan_clear_logs_nonce' );

		WTAN_Logger::clear();

		wp_safe_redirect( add_query_arg( [
			'page'         => 'wtan-logs',
			'wtan_cleared' => '1',
		], admin_url( 'admin.php' ) ) );
		exit;
	}

	// -------------------------------------------------------------------------
	// AJAX — Test d'envoi
	// -------------------------------------------------------------------------

	public function ajax_test_send(): void {
		check_ajax_referer( 'wtan_test_send', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Permission refusée.' );
		}

		$raw_phone = sanitize_text_field( get_option( 'wtan_test_phone', '' ) );

		if ( empty( $raw_phone ) ) {
			wp_send_json_error( 'Aucun numéro de test configuré. Enregistrez d\'abord les réglages.' );
		}

		$phone = WTAN_Phone::normalize( $raw_phone );

		if ( false === $phone ) {
			wp_send_json_error( 'Numéro de test invalide : "' . $raw_phone . '"' );
		}

		$message = implode( "\n", [
			'🔔 Test — WootsApp Notifier',
			'',
			'Ce message confirme que votre configuration fonctionne correctement.',
			'',
			'— WootsApp Notifier by Tedisun',
		] );

		$result = WTAN_Api::send( $phone, $message );

		if ( $result['success'] ) {
			wp_send_json_success( 'Message envoyé avec succès sur ' . $phone );
		} else {
			wp_send_json_error( sprintf( 'Échec (HTTP %d) : %s', $result['code'], $result['body'] ) );
		}
	}

	// -------------------------------------------------------------------------
	// Scripts inline
	// -------------------------------------------------------------------------

	public function inline_script(): void {
		$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';

		if ( ! in_array( $page, [ 'wtan-settings', 'wtan-template', 'wtan-logs' ], true ) ) {
			return;
		}
		?>
		<script>
		(function () {
			// Bouton test d'envoi.
			var btn = document.getElementById('wtan-test-btn');
			if (btn) {
				btn.addEventListener('click', function () {
					var result = document.getElementById('wtan-test-result');
					result.textContent = 'Envoi en cours…';
					result.style.color = '#555';
					btn.disabled = true;

					var data = new FormData();
					data.append('action', 'wtan_test_send');
					data.append('nonce', '<?php echo esc_js( wp_create_nonce( 'wtan_test_send' ) ); ?>');

					fetch('<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>', {
						method: 'POST',
						body: data,
					})
					.then(function (r) { return r.json(); })
					.then(function (json) {
						if (json.success) {
							result.textContent = '✅ ' + json.data;
							result.style.color = '#2e7d32';
						} else {
							result.textContent = '❌ ' + json.data;
							result.style.color = '#c62828';
						}
					})
					.catch(function () {
						result.textContent = '❌ Erreur réseau.';
						result.style.color = '#c62828';
					})
					.finally(function () { btn.disabled = false; });
				});
			}

			// Clic sur variable → insérer dans le textarea.
			document.querySelectorAll('.wtan-var-copy').forEach(function (el) {
				el.addEventListener('click', function () {
					var variable = el.getAttribute('data-var');
					var textarea = document.getElementById('wtan_message_template');

					if (textarea) {
						var start = textarea.selectionStart;
						var end   = textarea.selectionEnd;
						textarea.value = textarea.value.substring(0, start) + variable + textarea.value.substring(end);
						textarea.selectionStart = textarea.selectionEnd = start + variable.length;
						textarea.focus();
					} else if (navigator.clipboard) {
						navigator.clipboard.writeText(variable);
					}

					var prev = el.textContent;
					el.textContent = '✓';
					el.style.background = '#d4edda';
					setTimeout(function () {
						el.textContent = prev;
						el.style.background = '';
					}, 1000);
				});
			});

			// Bouton réinitialiser le template.
			var resetBtn = document.getElementById('wtan-reset-template');
			if (resetBtn) {
				resetBtn.addEventListener('click', function () {
					if (!confirm('Réinitialiser le template au contenu par défaut ?')) return;
					var textarea = document.getElementById('wtan_message_template');
					if (textarea) {
						textarea.value = <?php echo wp_json_encode( WTAN_Notifier::DEFAULT_TEMPLATE ); ?>;
					}
				});
			}
		}());
		</script>
		<?php
	}
}
