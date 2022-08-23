<?php
// phpcs:ignoreFile
/**
 * Copyright (c) Facebook, Inc. and its affiliates. All Rights Reserved
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 *
 * @package FacebookCommerce
 */

require_once __DIR__ . '/facebook-config-warmer.php';

use SkyVerge\WooCommerce\Facebook\Events\Event;

if ( ! class_exists( 'WC_Facebookcommerce_Pixel' ) ) :


	class WC_Facebookcommerce_Pixel {


		const SETTINGS_KEY     = 'facebook_config';
		const PIXEL_ID_KEY     = 'pixel_id';
		const USE_PII_KEY      = 'use_pii';
		const USE_S2S_KEY      = 'use_s2s';
		const ACCESS_TOKEN_KEY = 'access_token';

		/**
		 * Cache key for pixel script block output.
		 *
		 * @var string cache key.
		 * */
		const PIXEL_RENDER = 'pixel_render';

		/**
		 * Cache key for pixel noscript block output.
		 *
		 * @var string cache key.
		 * */
		const NO_SCRIPT_RENDER = 'no_script_render';

		/**
		 * Script render memoization helper.
		 *
		 * @var array Cache array.
		 */
		public static $render_cache = array();

		/**
		 * User information.
		 *
		 * @var array Information array.
		 */
		private $user_info;

		/**
		 * The name of the last event.
		 *
		 * @var string Event name.
		 */
		private $last_event;

		/**
		 * Class constructor.
		 *
		 * @param array $user_info User information array.
		 */
		public function __construct( $user_info = array() ) {

			$this->user_info  = $user_info;
			$this->last_event = '';
		}

		/**
		 * Initialize pixelID.
		 */
		public static function initialize() {
			if ( ! is_admin() ) {
				return;
			}

			// Initialize PixelID in storage - this will only need to happen when the user is an admin.
			$pixel_id = self::get_pixel_id();
			if ( ! WC_Facebookcommerce_Utils::is_valid_id( $pixel_id ) && class_exists( 'WC_Facebookcommerce_WarmConfig' ) ) {
				$fb_warm_pixel_id = WC_Facebookcommerce_WarmConfig::$fb_warm_pixel_id;

				if ( WC_Facebookcommerce_Utils::is_valid_id( $fb_warm_pixel_id ) && (int) $fb_warm_pixel_id == $fb_warm_pixel_id ) {
					$fb_warm_pixel_id = (string) $fb_warm_pixel_id;
					self::set_pixel_id( $fb_warm_pixel_id );
				}
			}

			$is_advanced_matching_enabled = self::get_use_pii_key();
			if ( null == $is_advanced_matching_enabled && class_exists( 'WC_Facebookcommerce_WarmConfig' ) ) {
				$fb_warm_is_advanced_matching_enabled = WC_Facebookcommerce_WarmConfig::$fb_warm_is_advanced_matching_enabled;
				if ( is_bool( $fb_warm_is_advanced_matching_enabled ) ) {
					self::set_use_pii_key( $fb_warm_is_advanced_matching_enabled ? 1 : 0 );
				}
			}
		}


		/**
		 * Gets Facebook Pixel init code.
		 *
		 * Init code might contain additional information to help matching website users with facebook users.
		 * Information is hashed in JS side using SHA256 before sending to Facebook.
		 *
		 * @return string
		 */
		private function get_pixel_init_code() {

			$agent_string = Event::get_platform_identifier();

			/**
			 * Filters Facebook Pixel init code.
			 *
			 * @param string $js_code
			 */
			return apply_filters(
				'facebook_woocommerce_pixel_init',
				sprintf(
					"fbq('init', '%s', %s, %s);\n",
					esc_js( self::get_pixel_id() ),
					json_encode( $this->user_info, JSON_PRETTY_PRINT | JSON_FORCE_OBJECT ),
					json_encode( array( 'agent' => $agent_string ), JSON_PRETTY_PRINT | JSON_FORCE_OBJECT )
				)
			);
		}


		/**
		 * Gets the Facebook Pixel code scripts.
		 *
		 * @return string HTML scripts
		 */
		public function pixel_base_code() {

			$pixel_id = self::get_pixel_id();

			// Bail if no ID or already rendered.
			if ( empty( $pixel_id ) || ! empty( self::$render_cache[ self::PIXEL_RENDER ] ) ) {
				return '';
			}

			self::$render_cache[ self::PIXEL_RENDER ] = true;

			ob_start();

			?>
			<script <?php echo self::get_script_attributes(); ?>>
				!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
					n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
					n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
					t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
					document,'script','https://connect.facebook.net/en_US/fbevents.js');
			</script>
			<!-- WooCommerce Facebook Integration Begin -->
			<script <?php echo self::get_script_attributes(); ?>>

				<?php echo $this->get_pixel_init_code(); ?>

				fbq( 'track', 'PageView', <?php echo json_encode( self::build_params( array(), 'PageView' ), JSON_PRETTY_PRINT | JSON_FORCE_OBJECT ); ?> );

				document.addEventListener( 'DOMContentLoaded', function() {
					jQuery && jQuery( function( $ ) {
						// Insert placeholder for events injected when a product is added to the cart through AJAX.
						$( document.body ).append( '<div class=\"wc-facebook-pixel-event-placeholder\"></div>' );
					} );
				}, false );

			</script>
			<!-- WooCommerce Facebook Integration End -->
			<?php

			return ob_get_clean();
		}


		/**
		 * Gets Facebook Pixel code noscript part to avoid W3 validation errors.
		 *
		 * @return string
		 */
		public function pixel_base_code_noscript() {

			$pixel_id = self::get_pixel_id();

			if ( empty( $pixel_id ) || ! empty( self::$render_cache[ self::NO_SCRIPT_RENDER ] ) ) {
				return '';
			}

			self::$render_cache[ self::NO_SCRIPT_RENDER ] = true;

			ob_start();

			?>
			<!-- Facebook Pixel Code -->
			<noscript>
				<img
					height="1"
					width="1"
					style="display:none"
					alt="fbpx"
					src="https://www.facebook.com/tr?id=<?php echo esc_attr( $pixel_id ); ?>&ev=PageView&noscript=1"
				/>
			</noscript>
			<!-- End Facebook Pixel Code -->
			<?php

			return ob_get_clean();
		}


		/**
		 * Determines if the last event in the current thread matches a given event.
		 *
		 * @since 1.11.0
		 *
		 * @param string $event_name
		 * @return bool
		 */
		public function is_last_event( $event_name ) {

			return $event_name === $this->last_event;
		}


		/**
		 * Determines if the last event in the current thread matches a given event.
		 *
		 * TODO remove this deprecated method by March 2020 or version 2.0.0 {FN 2020-03-25}.
		 *
		 * @deprecated since 1.11.0
		 *
		 * @param string $event_name Name of the event.
		 * @return bool
		 */
		public function check_last_event( $event_name ) {

			wc_deprecated_function( __METHOD__, '1.11.0', __CLASS__ . '::has_last_event()' );

			return $this->is_last_event( $event_name );
		}


		/**
		 * Gets the JavaScript code to track an event.
		 *
		 * Updates the last event property and returns the code.
		 *
		 * Use {@see \WC_Facebookcommerce_Pixel::inject_event()} to print or enqueue the code.
		 *
		 * @since 1.10.2
		 *
		 * @param string $event_name The name of the event to track.
		 * @param array  $params     Custom event parameters.
		 * @param string $method     Name of the pixel's fbq() function to call.
		 * @return string
		 */
		public function get_event_code( $event_name, $params, $method = 'track' ) {

			$this->last_event = $event_name;

			return self::build_event( $event_name, $params, $method );
		}


		/**
		 * Gets the JavaScript code to track an event wrapped in <script> tag.
		 *
		 * @see \WC_Facebookcommerce_Pixel::get_event_code()
		 *
		 * @since 1.10.2
		 *
		 * @param string $event_name The name of the event to track.
		 * @param array  $params     Custom event parameters.
		 * @param string $method     Name of the pixel's fbq() function to call.
		 * @return string
		 */
		public function get_event_script( $event_name, $params, $method = 'track' ) {

			ob_start();

			?>
			<!-- Facebook Pixel Event Code -->
			<script <?php echo self::get_script_attributes(); ?>>
				<?php echo $this->get_event_code( $event_name, $params, $method ); ?>
			</script>
			<!-- End Facebook Pixel Event Code -->
			<?php

			return ob_get_clean();
		}


		/**
		 * Prints or enqueues the JavaScript code to track an event.
		 * Preferred method to inject events in a page.
		 *
		 * @see \WC_Facebookcommerce_Pixel::build_event()
		 *
		 * @param string $event_name The name of the event to track.
		 * @param array  $params     Custom event parameters.
		 * @param string $method     Name of the pixel's fbq() function to call.
		 */
		public function inject_event( $event_name, $params, $method = 'track' ) {

			if ( \WC_Facebookcommerce_Utils::isWoocommerceIntegration() ) {
				\WC_Facebookcommerce_Utils::wc_enqueue_js( $this->get_event_code( $event_name, self::build_params( $params, $event_name ), $method ) );
			} else {
				printf( $this->get_event_script( $event_name, self::build_params( $params, $event_name ), $method ) ); // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
			}
		}


		/**
		 * Gets the JavaScript code to track a conditional event wrapped in <script> tag.
		 *
		 * @see \WC_Facebookcommerce_Pixel::get_event_code()
		 *
		 * @since 1.10.2
		 *
		 * @param string $event_name    The name of the event to track.
		 * @param array  $params        Custom event parameters.
		 * @param string $listener      Name of the JavaScript event to listen for.
		 * @param string $jsonified_pii JavaScript code representing an object of data for Advanced Matching.
		 * @return string
		 */
		public function get_conditional_event_script( $event_name, $params, $listener, $jsonified_pii ) {

			$code             = self::build_event( $event_name, $params, 'track' );
			$this->last_event = $event_name;

			/**
			 * TODO: use the settings stored by {@see \WC_Facebookcommerce_Integration}.
			 * The use_pii setting here is currently always disabled regardless of
			 * the value configured in the plugin settings page {WV-2020-01-02}.
			 */

			// Prepends fbq(...) with pii information to the injected code.
			if ( $jsonified_pii && get_option( self::SETTINGS_KEY )[ self::USE_PII_KEY ] ) {
				$this->user_info = '%s';
				$code            = sprintf( $this->get_pixel_init_code(), '" || ' . $jsonified_pii . ' || "' ) . $code;
			}

			ob_start();

			?>
			<!-- Facebook Pixel Event Code -->
			<script <?php echo self::get_script_attributes(); ?>>
				document.addEventListener( '<?php echo esc_js( $listener ); ?>', function (event) {
					<?php echo $code; ?>
				}, false );
			</script>
			<!-- End Facebook Pixel Event Code -->
			<?php

			return ob_get_clean();
		}


		/**
		 * Prints the JavaScript code to track a conditional event.
		 *
		 * The tracking code will be executed when the given JavaScript event is triggered.
		 *
		 * @param string $event_name    Name of the event.
		 * @param array  $params        Custom event parameters.
		 * @param string $listener      Name of the JavaScript event to listen for.
		 * @param string $jsonified_pii JavaScript code representing an object of data for Advanced Matching.
		 * @return string
		 */
		public function inject_conditional_event( $event_name, $params, $listener, $jsonified_pii = '' ) {

			// phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
			return $this->get_conditional_event_script( $event_name, self::build_params( $params, $event_name ), $listener, $jsonified_pii );
		}


		/**
		 * Gets the JavaScript code to track a conditional event that is only triggered one time wrapped in <script> tag.
		 *
		 * @internal
		 *
		 * @since 1.10.2
		 *
		 * @param string $event_name     The name of the event to track.
		 * @param array  $params         Custom event parameters.
		 * @param string $listened_event Name of the JavaScript event to listen for.
		 * @return string
		 */
		public function get_conditional_one_time_event_script( $event_name, $params, $listened_event ) {

			$code = $this->get_event_code( $event_name, $params );

			ob_start();

			?>
			<!-- Facebook Pixel Event Code -->
			<script <?php echo self::get_script_attributes(); ?>>
				function handle<?php echo $event_name; ?>Event() {
					<?php echo $code; ?>
					// Some weird themes (hi, Basel) are running this script twice, so two listeners are added and we need to remove them after running one.
					jQuery( document.body ).off( '<?php echo esc_js( $listened_event ); ?>', handle<?php echo $event_name; ?>Event );
				}

				jQuery( document.body ).one( '<?php echo esc_js( $listened_event ); ?>', handle<?php echo $event_name; ?>Event );
			</script>
			<!-- End Facebook Pixel Event Code -->
			<?php

			return ob_get_clean();
		}


		/**
		 * Builds an event.
		 *
		 * @see \WC_Facebookcommerce_Pixel::inject_event() for the preferred method to inject an event.
		 *
		 * @param string $event_name Event name.
		 * @param array  $params     Event params.
		 * @param string $method     Optional, defaults to 'track'.
		 * @return string
		 */
		public static function build_event( $event_name, $params, $method = 'track' ) {

			// Do not send the event name in the params.
			if ( isset( $params['event_name'] ) ) {

				unset( $params['event_name'] );
			}

			/**
			 * If possible, send the event ID to avoid duplication.
			 *
			 * @see https://developers.facebook.com/docs/marketing-api/server-side-api/deduplicate-pixel-and-server-side-events#deduplication-best-practices
			 */
			if ( isset( $params['event_id'] ) ) {

				$event_id = $params['event_id'];
				unset( $params['event_id'] );
			}

			// If custom data is set, send only the custom data.
			if ( isset( $params['custom_data'] ) ) {

				$params = $params['custom_data'];
			}

			if ( ! empty( $event_id ) ) {
				$event = sprintf(
					"/* %s Facebook Integration Event Tracking */\n" .
					"fbq('set', 'agent', '%s', '%s');\n" .
					"fbq('%s', '%s', %s, %s);",
					WC_Facebookcommerce_Utils::getIntegrationName(),
					Event::get_platform_identifier(),
					self::get_pixel_id(),
					esc_js( $method ),
					esc_js( $event_name ),
					json_encode( self::build_params( $params, $event_name ), JSON_PRETTY_PRINT | JSON_FORCE_OBJECT ),
					json_encode( array( 'eventID' => $event_id ), JSON_PRETTY_PRINT | JSON_FORCE_OBJECT )
				);

			} else {

				$event = sprintf(
					"/* %s Facebook Integration Event Tracking */\n" .
					"fbq('set', 'agent', '%s', '%s');\n" .
					"fbq('%s', '%s', %s);",
					WC_Facebookcommerce_Utils::getIntegrationName(),
					Event::get_platform_identifier(),
					self::get_pixel_id(),
					esc_js( $method ),
					esc_js( $event_name ),
					json_encode( self::build_params( $params, $event_name ), JSON_PRETTY_PRINT | JSON_FORCE_OBJECT )
				);
			}

			return $event;
		}


		/**
		 * Gets an array with version_info for pixel fires.
		 *
		 * Parameters provided by users should not be overwritten by this function.
		 *
		 * @since 1.10.2
		 *
		 * @param array  $params User defined parameters.
		 * @param string $event  The event name the params are for.
		 * @return array
		 */
		private static function build_params( $params = array(), $event = '' ) {

			$params = array_replace( Event::get_version_info(), $params );

			/**
			 * Filters the parameters for the pixel code.
			 *
			 * @since 1.10.2
			 *
			 * @param array $params User defined parameters.
			 * @param string $event The event name.
			 */
			return (array) apply_filters( 'wc_facebook_pixel_params', $params, $event );
		}


		/**
		 * Gets script tag attributes.
		 *
		 * @since 1.10.2
		 *
		 * @return string
		 */
		private static function get_script_attributes() {

			$script_attributes = '';

			/**
			 * Filters Facebook Pixel script attributes.
			 *
			 * @since 1.10.2
			 *
			 * @param array $custom_attributes
			 */
			$custom_attributes = (array) apply_filters( 'wc_facebook_pixel_script_attributes', array( 'type' => 'text/javascript' ) );

			foreach ( $custom_attributes as $tag => $value ) {
				$script_attributes .= ' ' . $tag . '="' . esc_attr( $value ) . '"';
			}

			return $script_attributes;
		}

		/**
		 * Get the PixelId.
		 */
		public static function get_pixel_id() {
			$fb_options = self::get_options();
			if ( ! $fb_options ) {
				return '';
			}
			return $fb_options[self::PIXEL_ID_KEY] ?? '';
		}

		/**
		 * Set the PixelId.
		 *
		 * @param string $pixel_id PixelId.
		 */
		public static function set_pixel_id( $pixel_id ) {
			$fb_options = self::get_options();

			if ( isset( $fb_options[ self::PIXEL_ID_KEY ] ) && $fb_options[ self::PIXEL_ID_KEY ] == $pixel_id ) {
				return;
			}

			$fb_options[ self::PIXEL_ID_KEY ] = $pixel_id;
			update_option( self::SETTINGS_KEY, $fb_options );
		}

		/**
		 * Check if PII key use is enabled.
		 */
		public static function get_use_pii_key() {
			$fb_options = self::get_options();
			if ( ! $fb_options ) {
				return null;
			}
			return $fb_options[self::USE_PII_KEY] ?? null;
		}

		/**
		 * Enable or disable use of PII key.
		 *
		 * @param string $use_pii PII key.
		 */
		public static function set_use_pii_key( $use_pii ) {
			$fb_options = self::get_options();

			if ( isset( $fb_options[ self::USE_PII_KEY ] ) && $fb_options[ self::USE_PII_KEY ] == $use_pii ) {
				return;
			}

			$fb_options[ self::USE_PII_KEY ] = $use_pii;
			update_option( self::SETTINGS_KEY, $fb_options );
		}

		/**
		 * Check if S2S is set.
		 */
		public static function get_use_s2s() {
			$fb_options = self::get_options();
			if ( ! $fb_options ) {
				return false;
			}
			return $fb_options[self::USE_S2S_KEY] ?? false;
		}

		/**
		 * Enable or disable use of S2S key.
		 *
		 * @param string $use_s2s S2S setting.
		 */
		public static function set_use_s2s( $use_s2s ) {
			$fb_options = self::get_options();

			if ( isset( $fb_options[ self::USE_S2S_KEY ] )
				&& $fb_options[ self::USE_S2S_KEY ] == $use_s2s ) {
				return;
			}

			$fb_options[ self::USE_S2S_KEY ] = $use_s2s;
			update_option( self::SETTINGS_KEY, $fb_options );
		}

		/**
		 * Get access token.
		 */
		public static function get_access_token() {
			$fb_options = self::get_options();
			if ( ! $fb_options ) {
				return '';
			}
			return $fb_options[self::ACCESS_TOKEN_KEY] ?? '';
		}

		/**
		 * Set access token.
		 *
		 * @param string $access_token Access token.
		 */
		public static function set_access_token( $access_token ) {
			$fb_options = self::get_options();

			if ( isset( $fb_options[ self::ACCESS_TOKEN_KEY ] ) && $fb_options[ self::ACCESS_TOKEN_KEY ] == $access_token ) {
				return;
			}

			$fb_options[ self::ACCESS_TOKEN_KEY ] = $access_token;
			update_option( self::SETTINGS_KEY, $fb_options );
		}

		/**
		 * Get WooCommerce/Wordpress information.
		 */
		private static function get_version_info() {
			global $wp_version;

			if ( WC_Facebookcommerce_Utils::isWoocommerceIntegration() ) {
				return array(
					'source'        => 'woocommerce',
					'version'       => WC()->version,
					'pluginVersion' => WC_Facebookcommerce_Utils::PLUGIN_VERSION,
				);
			}

			return array(
				'source'        => 'wordpress',
				'version'       => $wp_version,
				'pluginVersion' => WC_Facebookcommerce_Utils::PLUGIN_VERSION,
			);
		}

		/**
		 * Get PixelID related settings.
		 */
		public static function get_options() {
			return get_option(
				self::SETTINGS_KEY,
				array(
					self::PIXEL_ID_KEY     => '0',
					self::USE_PII_KEY      => false,
					self::USE_S2S_KEY      => false,
					self::ACCESS_TOKEN_KEY => '',
				)
			);
		}


		/**
		 * Gets Facebook Pixel base code.
		 *
		 * @deprecated since 1.10.2
		 *
		 * @return string
		 */
		public static function get_basecode() {

			wc_deprecated_function( __METHOD__, '1.10.2' );

			return '';
		}

		/**
		 * Gets the logged in user info
		 *
		 * @return string[]
		 */
		public function get_user_info() {
			return $this->user_info;
		}
	}

endif;
