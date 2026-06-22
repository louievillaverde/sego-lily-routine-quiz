<?php
/**
 * Standalone Mautic integration for Sego Lily Routine Quiz.
 *
 * Reads credentials from this plugin's own settings (lprq_mautic_*). Falls
 * back to the Sego Lily Wholesale plugin's settings (slw_mautic_*) if this
 * plugin's settings are empty AND the wholesale plugin is installed. That
 * fallback exists so Holly's existing Mautic config keeps working without
 * her re-entering credentials. Future clients use the lprq_mautic_* settings
 * directly.
 *
 * @package SegoLilyRoutineQuiz
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SLRQ_Mautic {

	const TOKEN_TRANSIENT = 'lprq_mautic_access_token';
	const USER_AGENT      = 'SegoLilyRoutineQuiz/1.0';

	/**
	 * Send a quiz lead to Mautic. Creates contact if new, updates + tags if existing.
	 *
	 * @param array $data {
	 *     @type string $email          (required)
	 *     @type string $firstname      (optional)
	 *     @type string $skin_concern   (optional, drives skin-* tag)
	 *     @type string $product_count  (optional)
	 *     @type string $frustration    (optional, drives frustration-* tag)
	 *     @type array  $extra_tags     (optional, additional tags to apply)
	 * }
	 * @return array { success: bool, message: string, contact_id: int|null }
	 */
	public static function send_quiz_lead( $data ) {
		$creds = self::get_credentials();
		if ( ! $creds ) {
			return array( 'success' => false, 'message' => 'Mautic not configured. Go to Settings > Routine Quiz to add credentials.' );
		}

		$email = isset( $data['email'] ) ? sanitize_email( $data['email'] ) : '';
		if ( ! $email || ! is_email( $email ) ) {
			return array( 'success' => false, 'message' => 'Invalid email.' );
		}

		$token = self::get_token( $creds );
		if ( ! $token ) {
			return array( 'success' => false, 'message' => 'Mautic auth failed. Check credentials.' );
		}

		$headers = array(
			'Authorization' => 'Bearer ' . $token,
			'Content-Type'  => 'application/json',
			'Accept'        => 'application/json',
			'User-Agent'    => self::USER_AGENT,
		);

		// Look up existing contact by email
		$search_url = $creds['url'] . '/api/contacts?search=' . rawurlencode( $email ) . '&limit=1';
		$search     = wp_remote_get( $search_url, array( 'timeout' => 10, 'headers' => $headers ) );

		// Handle token expiry on search
		if ( ! is_wp_error( $search ) && wp_remote_retrieve_response_code( $search ) === 401 ) {
			delete_transient( self::TOKEN_TRANSIENT );
			$token = self::get_token( $creds );
			if ( ! $token ) {
				return array( 'success' => false, 'message' => 'Mautic token expired and refresh failed.' );
			}
			$headers['Authorization'] = 'Bearer ' . $token;
			$search = wp_remote_get( $search_url, array( 'timeout' => 10, 'headers' => $headers ) );
		}

		if ( is_wp_error( $search ) ) {
			return array( 'success' => false, 'message' => 'Contact search error: ' . $search->get_error_message() );
		}

		$contact_id   = null;
		$search_body  = json_decode( wp_remote_retrieve_body( $search ), true );
		$contacts     = $search_body['contacts'] ?? array();
		if ( ! empty( $contacts ) ) {
			$contact_id = array_key_first( $contacts );
		}

		// Build contact payload
		$tags = array( 'quiz-completed', 'retail-quiz-lead' );

		if ( ! empty( $data['skin_concern'] ) ) {
			$skin_tag = self::map_skin_tag( $data['skin_concern'] );
			if ( $skin_tag ) {
				$tags[] = $skin_tag;
			}
		}
		if ( ! empty( $data['frustration'] ) ) {
			$frustration_tag = self::map_frustration_tag( $data['frustration'] );
			if ( $frustration_tag ) {
				$tags[] = $frustration_tag;
			}
		}
		if ( ! empty( $data['extra_tags'] ) && is_array( $data['extra_tags'] ) ) {
			$tags = array_merge( $tags, $data['extra_tags'] );
		}

		$tags = apply_filters( 'lprq_mautic_tags', array_values( array_unique( $tags ) ), $data );

		$contact_data = array(
			'email' => $email,
			'tags'  => $tags,
		);
		if ( ! empty( $data['firstname'] ) ) {
			$contact_data['firstname'] = sanitize_text_field( $data['firstname'] );
		}
		if ( ! empty( $data['skin_concern'] ) ) {
			$contact_data['skin_concern'] = $data['skin_concern'];
		}
		if ( ! empty( $data['product_count'] ) ) {
			$contact_data['product_count'] = $data['product_count'];
		}
		if ( ! empty( $data['frustration'] ) ) {
			$contact_data['frustration'] = $data['frustration'];
		}

		$contact_data = apply_filters( 'lprq_mautic_contact_data', $contact_data, $data );

		if ( $contact_id ) {
			$endpoint = $creds['url'] . '/api/contacts/' . $contact_id . '/edit';
			$response = wp_remote_request( $endpoint, array(
				'method'  => 'PATCH',
				'timeout' => 10,
				'headers' => $headers,
				'body'    => wp_json_encode( $contact_data ),
			) );
			$action = 'updated';
		} else {
			$response = wp_remote_post( $creds['url'] . '/api/contacts/new', array(
				'timeout' => 10,
				'headers' => $headers,
				'body'    => wp_json_encode( $contact_data ),
			) );
			$action = 'created';
		}

		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'message' => 'Mautic ' . $action . ' error: ' . $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			$body = wp_remote_retrieve_body( $response );
			return array( 'success' => false, 'message' => 'Mautic HTTP ' . $code . ': ' . substr( $body, 0, 200 ) );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$final_id = $body['contact']['id'] ?? $contact_id;

		do_action( 'lprq_mautic_lead_sent', $email, $data, $final_id );

		return array( 'success' => true, 'message' => 'Contact ' . $action, 'contact_id' => $final_id );
	}

	/**
	 * Upsert a Mautic contact by email and apply tags. Tags prefixed with a
	 * leading minus are removed by Mautic (its native tag-removal syntax), so a
	 * caller can add and remove in one call. Optional $fields sets standard
	 * contact fields (firstname, etc.). Best-effort: returns a result array and
	 * never throws, so a hook can fail quietly without breaking the request it
	 * runs inside. Separate from send_quiz_lead so subscriber syncs do not pick
	 * up the quiz-specific tags.
	 */
	public static function upsert_tags( $email, $tags, $fields = array() ) {
		$creds = self::get_credentials();
		if ( ! $creds ) {
			return array( 'success' => false, 'message' => 'Mautic not configured.' );
		}
		$email = sanitize_email( $email );
		if ( ! $email || ! is_email( $email ) ) {
			return array( 'success' => false, 'message' => 'Invalid email.' );
		}
		$token = self::get_token( $creds );
		if ( ! $token ) {
			return array( 'success' => false, 'message' => 'Mautic auth failed.' );
		}

		$headers = array(
			'Authorization' => 'Bearer ' . $token,
			'Content-Type'  => 'application/json',
			'Accept'        => 'application/json',
			'User-Agent'    => self::USER_AGENT,
		);

		$search_url = $creds['url'] . '/api/contacts?search=' . rawurlencode( $email ) . '&limit=1';
		$search     = wp_remote_get( $search_url, array( 'timeout' => 10, 'headers' => $headers ) );

		if ( ! is_wp_error( $search ) && wp_remote_retrieve_response_code( $search ) === 401 ) {
			delete_transient( self::TOKEN_TRANSIENT );
			$token = self::get_token( $creds );
			if ( ! $token ) {
				return array( 'success' => false, 'message' => 'Mautic token refresh failed.' );
			}
			$headers['Authorization'] = 'Bearer ' . $token;
			$search = wp_remote_get( $search_url, array( 'timeout' => 10, 'headers' => $headers ) );
		}

		if ( is_wp_error( $search ) ) {
			return array( 'success' => false, 'message' => 'Contact search error: ' . $search->get_error_message() );
		}

		$contact_id  = null;
		$search_body = json_decode( wp_remote_retrieve_body( $search ), true );
		$contacts    = $search_body['contacts'] ?? array();
		if ( ! empty( $contacts ) ) {
			$contact_id = array_key_first( $contacts );
		}

		$contact_data = array(
			'email' => $email,
			'tags'  => array_values( array_unique( (array) $tags ) ),
		);
		if ( is_array( $fields ) ) {
			foreach ( $fields as $k => $v ) {
				if ( $v !== '' && $v !== null ) {
					$contact_data[ $k ] = sanitize_text_field( $v );
				}
			}
		}

		if ( $contact_id ) {
			$response = wp_remote_request( $creds['url'] . '/api/contacts/' . $contact_id . '/edit', array(
				'method'  => 'PATCH',
				'timeout' => 10,
				'headers' => $headers,
				'body'    => wp_json_encode( $contact_data ),
			) );
			$action = 'updated';
		} else {
			$response = wp_remote_post( $creds['url'] . '/api/contacts/new', array(
				'timeout' => 10,
				'headers' => $headers,
				'body'    => wp_json_encode( $contact_data ),
			) );
			$action = 'created';
		}

		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'message' => 'Mautic ' . $action . ' error: ' . $response->get_error_message() );
		}
		$code = wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return array( 'success' => false, 'message' => 'Mautic HTTP ' . $code );
		}

		$body     = json_decode( wp_remote_retrieve_body( $response ), true );
		$final_id = $body['contact']['id'] ?? $contact_id;
		return array( 'success' => true, 'message' => 'Contact ' . $action, 'contact_id' => $final_id );
	}

	/**
	 * Authenticated GET to the Mautic API. Returns decoded JSON or null on error.
	 * Used by the admin dashboard to render live campaign + email data.
	 */
	public static function api_get( $path, $timeout = 8 ) {
		$creds = self::get_credentials();
		if ( ! $creds ) {
			return null;
		}
		$token = self::get_token( $creds );
		if ( ! $token ) {
			return null;
		}
		$url = $creds['url'] . '/' . ltrim( $path, '/' );
		$resp = wp_remote_get( $url, array(
			'timeout' => $timeout,
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Accept'        => 'application/json',
				'User-Agent'    => self::USER_AGENT,
			),
		) );
		if ( is_wp_error( $resp ) ) {
			return null;
		}
		$code = wp_remote_retrieve_response_code( $resp );
		if ( $code === 401 ) {
			delete_transient( self::TOKEN_TRANSIENT );
			$token = self::get_token( $creds );
			if ( ! $token ) {
				return null;
			}
			$resp = wp_remote_get( $url, array(
				'timeout' => $timeout,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Accept'        => 'application/json',
					'User-Agent'    => self::USER_AGENT,
				),
			) );
			if ( is_wp_error( $resp ) ) {
				return null;
			}
		}
		$body = wp_remote_retrieve_body( $resp );
		return json_decode( $body, true );
	}

	public static function get_dashboard_base_url() {
		$creds = self::get_credentials();
		return $creds ? $creds['url'] : '';
	}

	/**
	 * Fetch + cache OAuth2 token. 50-min TTL (Mautic tokens are 1 hour).
	 */
	private static function get_token( $creds ) {
		$cached = get_transient( self::TOKEN_TRANSIENT );
		if ( $cached ) {
			return $cached;
		}

		$response = wp_remote_post( $creds['url'] . '/oauth/v2/token', array(
			'timeout' => 10,
			'headers' => array(
				'Content-Type' => 'application/x-www-form-urlencoded',
				'User-Agent'   => self::USER_AGENT,
			),
			'body' => array(
				'grant_type'    => 'client_credentials',
				'client_id'     => $creds['client_id'],
				'client_secret' => $creds['client_secret'],
			),
		) );

		if ( is_wp_error( $response ) ) {
			error_log( '[LPRQ] Mautic token fetch error: ' . $response->get_error_message() );
			return false;
		}
		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['access_token'] ) ) {
			error_log( '[LPRQ] Mautic token response missing access_token: ' . wp_remote_retrieve_body( $response ) );
			return false;
		}

		set_transient( self::TOKEN_TRANSIENT, $body['access_token'], 50 * MINUTE_IN_SECONDS );
		return $body['access_token'];
	}

	/**
	 * Resolve Mautic credentials. Prefers this plugin's settings; falls back
	 * to the Sego Lily Wholesale plugin's settings if this plugin's are empty.
	 * Returns false if neither source has all 3 credentials.
	 *
	 * @return array|false { url, client_id, client_secret }
	 */
	public static function get_credentials() {
		$url     = get_option( 'lprq_mautic_url', '' );
		$cid     = get_option( 'lprq_mautic_client_id', '' );
		$secret  = get_option( 'lprq_mautic_client_secret', '' );

		// Fallback to wholesale plugin's settings if our settings are empty
		// (and the wholesale plugin is installed)
		if ( empty( $url ) ) {
			$url = get_option( 'slw_mautic_url', '' );
		}
		if ( empty( $cid ) ) {
			$cid = get_option( 'slw_mautic_client_id', '' );
		}
		if ( empty( $secret ) ) {
			$secret = get_option( 'slw_mautic_client_secret', '' );
		}

		if ( empty( $url ) || empty( $cid ) || empty( $secret ) ) {
			return false;
		}

		return array(
			'url'           => rtrim( $url, '/' ),
			'client_id'     => $cid,
			'client_secret' => $secret,
		);
	}

	/**
	 * Skin concern → Mautic tag mapping. Filterable per client.
	 */
	private static function map_skin_tag( $concern ) {
		$map = apply_filters( 'lprq_skin_tag_map', array(
			'Wrinkles & dark spots'  => 'skin-aging',
			'Dryness & tightness'    => 'skin-dryness',
			'Redness & sensitivity'  => 'skin-sensitivity',
			'Breakouts'              => 'skin-breakouts',
		) );
		return $map[ $concern ] ?? null;
	}

	/**
	 * Frustration → Mautic tag mapping. Filterable per client.
	 */
	private static function map_frustration_tag( $frustration ) {
		$map = apply_filters( 'lprq_frustration_tag_map', array(
			'Nothing works long enough'  => 'frustration-durability',
			'Too many products'          => 'frustration-simplify',
			'Don\'t trust ingredients'   => 'frustration-ingredients',
			'Just want something simple' => 'frustration-simple',
		) );
		return $map[ $frustration ] ?? null;
	}
}
