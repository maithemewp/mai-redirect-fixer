<?php

/**
 * Plugin Name:     Mai Redirect Fixer
 * Plugin URI:      https://bizbudding.com/
 * Description:     Attempts to find/fix broken redirects from a CSV file or links in post content.
 * Version:         0.1.0
 *
 * Author:          BizBudding
 * Author URI:      https://bizbudding.com
 */

namespace Mai\RedirectFixer;

use WP_CLI;
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

// Autoload dependencies.
require_once __DIR__ . '/vendor/autoload.php';

add_action( 'cli_init', __NAMESPACE__ . '\register_cli_commands' );
/**
 * Register WP-CLI commands.
 *
 * @since 0.1.0
 *
 * @return void
 */
function register_cli_commands() {
	WP_CLI::add_command( 'mai-redirect-fixer', __NAMESPACE__ . '\CLI' );
}

add_action( 'plugins_loaded', __NAMESPACE__ . '\updater' );
/**
 * Setup the updater.
 *
 * composer require yahnis-elsts/plugin-update-checker
 *
 * @since 0.1.0
 *
 * @uses https://github.com/YahnisElsts/plugin-update-checker/
 *
 * @return void
 */
function updater() {
	// Setup the updater.
	$updater = PucFactory::buildUpdateChecker( 'https://github.com/maithemewp/mai-redirect-fixer/', __FILE__, 'mai-redirect-fixer' );

	// Maybe set github api token.
	if ( defined( 'MAI_GITHUB_API_TOKEN' ) ) {
		$updater->setAuthentication( MAI_GITHUB_API_TOKEN );
	}

	// Add icons for Dashboard > Updates screen.
	if ( function_exists( 'mai_get_updater_icons' ) && $icons = mai_get_updater_icons() ) {
		$updater->addResultFilter(
			function ( $info ) use ( $icons ) {
				$info->icons = $icons;
				return $info;
			}
		);
	}
}