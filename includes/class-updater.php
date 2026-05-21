<?php
/**
 * Self-Updater: GitHub Release Checker for Sego Lily Routine Quiz.
 *
 * Checks the GitHub releases API every 12 hours, caches the result, injects
 * update info into WP's update_plugins transient when a newer version is
 * available. No third-party plugin needed.
 *
 * @package SegoLilyRoutineQuiz
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class SLRQ_Updater {

	private static $github_repo  = 'louievillaverde/sego-lily-routine-quiz';
	private static $cache_key    = 'slrq_github_release';
	private static $cache_ttl    = 43200;
	private static $plugin_file  = 'sego-lily-routine-quiz/sego-lily-routine-quiz.php';

	public static function init() {
		add_filter( 'site_transient_update_plugins', array( __CLASS__, 'check_update' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_info' ), 20, 3 );
		add_action( 'load-update-core.php', array( __CLASS__, 'flush_cache_on_manual_check' ) );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'flush_after_update' ), 10, 2 );
	}

	public static function flush_cache_on_manual_check() {
		delete_transient( self::$cache_key );
	}

	public static function flush_after_update( $upgrader, $options ) {
		if ( ( $options['action'] ?? '' ) !== 'update' || ( $options['type'] ?? '' ) !== 'plugin' ) {
			return;
		}
		$plugins = $options['plugins'] ?? array();
		if ( in_array( self::$plugin_file, $plugins, true ) ) {
			delete_transient( self::$cache_key );
			delete_site_transient( 'update_plugins' );
		}
	}

	private static function get_release_info() {
		$cached = get_transient( self::$cache_key );
		if ( $cached !== false ) {
			return $cached;
		}

		$response = wp_remote_get(
			'https://api.github.com/repos/' . self::$github_repo . '/releases/latest',
			array(
				'timeout' => 10,
				'headers' => array(
					'Accept'     => 'application/vnd.github.v3+json',
					'User-Agent' => 'SegoLilyRoutineQuiz-Updater/' . SLRQ_VERSION,
				),
			)
		);

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			set_transient( self::$cache_key, null, 3600 );
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['tag_name'] ) ) {
			set_transient( self::$cache_key, null, 3600 );
			return null;
		}

		$version = ltrim( $body['tag_name'], 'v' );
		$zip_url = '';
		if ( ! empty( $body['assets'] ) ) {
			foreach ( $body['assets'] as $asset ) {
				if ( substr( $asset['name'] ?? '', -4 ) === '.zip' ) {
					$zip_url = $asset['browser_download_url'];
					break;
				}
			}
		}
		if ( empty( $zip_url ) ) {
			set_transient( self::$cache_key, null, 3600 );
			return null;
		}

		$icon_base = 'https://raw.githubusercontent.com/' . self::$github_repo . '/main/assets/';
		$info = array(
			'version'       => $version,
			'download_url'  => $zip_url,
			'tested'        => '6.4',
			'requires'      => '6.0',
			'requires_php'  => '7.4',
			'last_updated'  => $body['published_at'] ?? '',
			'changelog'     => $body['body'] ?? '',
			'plugin_name'   => 'Routine Quiz',
			'icons'         => array(
				'default' => $icon_base . 'icon-256x256.png',
				'1x'      => $icon_base . 'icon-128x128.png',
				'2x'      => $icon_base . 'icon-256x256.png',
			),
			'banners'       => array(
				'low'  => $icon_base . 'banner-772x250.png',
				'high' => $icon_base . 'banner-772x250.png',
			),
		);
		set_transient( self::$cache_key, $info, self::$cache_ttl );
		return $info;
	}

	public static function check_update( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}
		$info = self::get_release_info();
		if ( ! $info ) {
			return $transient;
		}
		if ( version_compare( SLRQ_VERSION, $info['version'], '<' ) ) {
			$transient->response[ self::$plugin_file ] = (object) array(
				'slug'         => 'sego-lily-routine-quiz',
				'plugin'       => self::$plugin_file,
				'new_version'  => $info['version'],
				'url'          => 'https://github.com/' . self::$github_repo,
				'package'      => $info['download_url'],
				'tested'       => $info['tested'],
				'requires'     => $info['requires'],
				'requires_php' => $info['requires_php'],
				'icons'        => $info['icons'],
				'banners'      => $info['banners'],
			);
		}
		return $transient;
	}

	public static function plugin_info( $result, $action, $args ) {
		if ( $action !== 'plugin_information' || empty( $args->slug ) || $args->slug !== 'sego-lily-routine-quiz' ) {
			return $result;
		}
		$info = self::get_release_info();
		if ( ! $info ) {
			return $result;
		}
		return (object) array(
			'name'          => $info['plugin_name'],
			'slug'          => 'sego-lily-routine-quiz',
			'version'       => $info['version'],
			'author'        => '<a href="https://leadpiranha.com">Lead Piranha</a>',
			'requires'      => $info['requires'],
			'tested'        => $info['tested'],
			'requires_php'  => $info['requires_php'],
			'last_updated'  => $info['last_updated'],
			'download_link' => $info['download_url'],
			'icons'         => $info['icons'],
			'banners'       => $info['banners'],
			'sections'      => array(
				'description' => 'Sego Lily-branded skincare routine quiz. Customer answers 5 questions, gets a 2-product recommendation from the Sego Lily product line, lead syncs to Mautic with tags.',
				'changelog'   => $info['changelog'],
			),
		);
	}
}
