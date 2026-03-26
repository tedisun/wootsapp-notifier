<?php
defined( 'ABSPATH' ) || exit;

/**
 * Client HTTP pour l'API Evolution API (WhatsApp).
 *
 * Endpoint : POST {api_url}/message/sendText/{instance}
 * Headers  : apikey: {api_key}, Content-Type: application/json
 * Body     : { "number": "226XXXXXXXX@s.whatsapp.net", "text": "...", "delay": 0 }
 */
class WTAN_Api {

	/**
	 * Envoie un message texte via Evolution API.
	 *
	 * @param  string $number  Numéro au format "{indicatif}{numéro}@s.whatsapp.net".
	 * @param  string $message Contenu du message.
	 * @return array{success: bool, code: int, body: string}
	 */
	public static function send( string $number, string $message ): array {
		$api_url  = rtrim( (string) get_option( 'wtan_api_url', '' ), '/' );
		$instance = sanitize_text_field( (string) get_option( 'wtan_api_instance', '' ) );
		$api_key  = (string) get_option( 'wtan_api_key', '' );

		if ( empty( $api_url ) || empty( $instance ) || empty( $api_key ) ) {
			return [
				'success' => false,
				'code'    => 0,
				'body'    => 'Configuration incomplète (URL, instance ou clé API manquante).',
			];
		}

		$endpoint = $api_url . '/message/sendText/' . rawurlencode( $instance );

		$response = wp_remote_post( $endpoint, [
			'timeout' => 15,
			'headers' => [
				'apikey'       => $api_key,
				'Content-Type' => 'application/json',
			],
			'body'    => wp_json_encode( [
				'number' => $number,
				'text'   => $message,
				'delay'  => 0,
			] ),
		] );

		if ( is_wp_error( $response ) ) {
			return [
				'success' => false,
				'code'    => 0,
				'body'    => $response->get_error_message(),
			];
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		return [
			'success' => in_array( $code, [ 200, 201 ], true ),
			'code'    => $code,
			'body'    => $body,
		];
	}
}
