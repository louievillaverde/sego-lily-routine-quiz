<?php
/**
 * WooCommerce Subscriptions to Mautic bridge.
 *
 * Tags a contact "subscriber" in Mautic the moment their subscription goes
 * active, and flips them to "subscriber-cancelled" (removing "subscriber") when
 * it cancels or expires. Two purposes:
 *
 *   1. Exit trigger: the subscription/replenishment email campaign suppresses
 *      anyone tagged "subscriber" so a fresh subscriber is dropped from the
 *      remaining sends instead of being pitched to subscribe again.
 *   2. A durable "subscriber" segment for every future campaign.
 *
 * Why this runs server-side on Holly's site: the Mautic write API is blocked
 * by SiteGround's WAF for external callers, but a request originating on the
 * site itself is internal and goes through, the same path the quiz already
 * uses successfully.
 *
 * Best-effort and non-fatal. A Mautic failure is logged and swallowed so it
 * can never interrupt a checkout or a subscription status change.
 *
 * @package SegoLilyRoutineQuiz
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SLRQ_Subscriptions {

	/**
	 * Register the WooCommerce Subscriptions status hooks. No-op when WC
	 * Subscriptions is not active so the plugin stays safe on any install.
	 */
	public static function init() {
		if ( ! class_exists( 'WC_Subscriptions' ) && ! function_exists( 'wcs_get_subscription' ) ) {
			return;
		}
		add_action( 'woocommerce_subscription_status_active', array( __CLASS__, 'on_active' ), 10, 1 );
		add_action( 'woocommerce_subscription_status_cancelled', array( __CLASS__, 'on_inactive' ), 10, 1 );
		add_action( 'woocommerce_subscription_status_expired', array( __CLASS__, 'on_inactive' ), 10, 1 );
		add_action( 'woocommerce_subscription_status_pending-cancel', array( __CLASS__, 'on_inactive' ), 10, 1 );
	}

	public static function on_active( $subscription ) {
		self::sync( $subscription, true );
	}

	public static function on_inactive( $subscription ) {
		self::sync( $subscription, false );
	}

	/**
	 * Resolve the subscription to its billing email and push the tag change.
	 *
	 * @param WC_Subscription|int $subscription Subscription object or id.
	 * @param bool                $active       True when newly active.
	 */
	private static function sync( $subscription, $active ) {
		if ( is_numeric( $subscription ) && function_exists( 'wcs_get_subscription' ) ) {
			$subscription = wcs_get_subscription( $subscription );
		}
		if ( ! is_object( $subscription ) || ! method_exists( $subscription, 'get_billing_email' ) ) {
			return;
		}

		$email = $subscription->get_billing_email();
		if ( ! $email || ! is_email( $email ) ) {
			return;
		}

		// Leading-minus tags are removed by Mautic, so each path both sets the
		// state it wants and clears the opposite state in one call.
		$tags = $active
			? array( 'subscriber', '-subscriber-cancelled' )
			: array( '-subscriber', 'subscriber-cancelled' );

		$fields = array();
		$firstname = $subscription->get_billing_first_name();
		if ( $firstname ) {
			$fields['firstname'] = $firstname;
		}

		if ( ! class_exists( 'SLRQ_Mautic' ) ) {
			return;
		}
		$result = SLRQ_Mautic::upsert_tags( $email, $tags, $fields );

		if ( empty( $result['success'] ) ) {
			error_log( 'SLRQ_Subscriptions Mautic sync failed for ' . $email . ': ' . ( $result['message'] ?? 'unknown' ) );
		}

		do_action( 'slrq_subscriber_synced', $email, $active, $subscription );
	}
}
