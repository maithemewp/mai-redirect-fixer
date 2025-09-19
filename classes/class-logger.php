<?php

namespace Mai\RedirectFixer;

use WP_CLI;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Logger class.
 *
 * @version 0.3.0
 *
 * @since 0.1.0
 */
class Logger {
	/**
	 * The singleton instance.
	 *
	 * @since 0.1.0
	 *
	 * @var Logger|null
	 */
	private static $instance = null;

	/**
	 * The plugin name.
	 *
	 * @since 0.1.0
	 *
	 * @var string
	 */
	private $plugin_name;

	/**
	 * Get the singleton instance.
	 *
	 * @since 0.1.0
	 *
	 * @return Logger
	 */
	public static function get_instance(): Logger {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Private constructor to prevent direct instantiation.
	 *
	 * @since 0.1.0
	 *
	 * @return void
	 */
	private function __construct() {
		$this->set_plugin_name();
	}

	/**
	 * Log a message.
	 *
	 * @since 0.1.0
	 * @since 0.3.0 Renamed from log() to output().
	 *
	 * @param string $message The message to log.
	 * @param string $type    The type of message (error, warning, info, success).
	 *
	 * @return void
	 */
	 private function output( string $message, string $type = '' ): void {
		// Get the debug settings.
		$cli           = defined( 'WP_CLI' ) && WP_CLI;
		$debug         = defined( 'WP_DEBUG' ) && WP_DEBUG;
		$debug_log     = defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG;
		$debug_display = defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY;

		// If running in WP-CLI, always log and output directly to console.
		if ( $cli ) {
			switch ( $type ) {
				case 'error':
					WP_CLI::error( $message );
					break;
				case 'success':
					WP_CLI::success( $message );
					break;
				case 'warning':
					WP_CLI::warning( $message );
					break;
				default:
					WP_CLI::log( $message );
					break;
			}
		}

		// If not debugging and not an error, return.
		// We don't want to log anything else unless debugging is enabled.
		if ( ! $debug && 'error' !== $type ) {
			return;
		}

		// Format the message.
		$formatted = $type ? " [$type]" : '';
		$formatted = sprintf( '%s%s: %s', $this->plugin_name, $formatted, $message );

		// If logging.
		if ( $debug_log ) {
			// Log the message.
			error_log( $formatted );
		}

		// If logging or displaying.
		if ( $debug_log || $debug_display ) {
			// If ray is available, use it for additional debugging.
			if ( function_exists( '\ray' ) ) {
				/** @disregard P1010 */
				\ray( $formatted )->label( $this->plugin_name );
			}
		}
	}

	/**
	 * Log a message.
	 *
	 * @since 0.1.0
	 * @since 0.3.0 Made public for a general logging method.
	 *
	 * @param string $message The message to log.
	 *
	 * @return void
	 */
	public function log( string $message ): void {
		$this->output( $message );
	}

	/**
	 * Log an error message.
	 * Always logs regardless of debug settings.
	 *
	 * @since 0.1.0
	 *
	 * @param string $message The message to log.
	 *
	 * @return void
	 */
	public function error( string $message ): void {
		$this->output( $message, 'error' );
	}

	/**
	 * Log a warning message.
	 * Only logs if debugging is enabled.
	 *
	 * @since 0.1.0
	 *
	 * @param string $message The message to log.
	 *
	 * @return void
	 */
	public function warning( string $message ): void {
		$this->output( $message, 'warning' );
	}

	/**
	 * Log a success message.
	 * Only logs if debugging is enabled.
	 *
	 * @since 0.1.0
	 *
	 * @param string $message The message to log.
	 *
	 * @return void
	 */
	public function success( string $message ): void {
		$this->output( $message, 'success' );
	}

	/**
	 * Log an info message.
	 * Only logs if debugging is enabled.
	 *
	 * @since 0.1.0
	 *
	 * @param string $message The message to log.
	 *
	 * @return void
	 */
	public function info( string $message ): void {
			$this->output( $message, 'info' );
	}

	/**
	 * Set the plugin name.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function set_plugin_name(): void {
		$this->plugin_name = plugin_basename( dirname( dirname( __FILE__ ) ) );
	}
}