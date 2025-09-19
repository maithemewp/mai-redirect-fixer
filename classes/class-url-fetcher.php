<?php

namespace Mai\RedirectFixer;
use Exception;
use WP_Error;
use WP_Query;

defined( 'ABSPATH' ) || exit;

/**
 * Fetch a URL to get the final URL and status code.
 *
 * @since 0.1.0
 */
class UrlFetcher {
	/**
	 * The arguments.
	 *
	 * @since 0.1.0
	 *
	 * @var array
	 */
	private $args;

	/**
	 * The url.
	 *
	 * @since 0.1.0
	 *
	 * @var string
	 */
	private $url;

	/**
	 * The logger.
	 *
	 * @since 0.1.0
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param array $args The arguments.
	 *
	 * @return void
	 */
	public function __construct( $args = [] ) {
		// Set the logger.
		$this->logger = Logger::get_instance();

		// Sanitize and set args.
		$this->args = $this->sanitize_args( $args );
	}

	/**
	 * Recursively check the url(s) for redirects.
	 *
	 * @since 0.1.0
	 *
	 * @param string $url       The url to check.
	 * @param bool   $following Whether this fetch is a recursive fetch.
	 *
	 * @return array|WP_Error The redirect data or WP_Error if there is an error.
	 */
	public function fetch_url( $url, $first_fetch = true ) {
		try {
			// Validate the url.
			$this->url = $this->replace_host( $url, $this->args['home_path'], $this->args['host_path'] );

			// Return error if the url is invalid.
			if ( ! $this->url ) {
				$this->logger->error( sprintf( 'Invalid URL: %s', $url ) );
				return new WP_Error( 'invalid_url', 'Invalid URL.' );
			}

			// If this is the first fetch.
			if ( $first_fetch ) {
				$this->logger->log( sprintf( 'Fetching %s', $this->url ) );
			}
			// This is recursive fetch.
			else {
				$this->logger->log( sprintf( '-- Following %s', $this->url ) );
			}

			// Get the redirect.
			// Prepare request arguments.
			$request_args = [
				'timeout'     => 30, // Default is 5.
				'redirection' => 0,  // Make sure this request doesn't try to follow redirects. This broke things when I tested a postive number briefly.
			];

			// Add basic authentication if provided.
			if ( $this->args['username'] && $this->args['password'] ) {
				$request_args['headers']['Authorization'] = 'Basic ' . base64_encode( $this->args['username'] . ':' . $this->args['password'] );
			}

			// Send the request.
			$response = wp_remote_head( $this->url, $request_args );

			// If error, log and return it.
			if ( is_wp_error( $response ) ) {
				$this->logger->error( sprintf( 'Error fetching url: %s %s', $response->get_error_code(), $response->get_error_message() ) );
				return $response;
			}

			// Get the code and location.
			$code     = wp_remote_retrieve_response_code( $response );
			$location = wp_remote_retrieve_header( $response, 'location' );

			// Handle permanent redirects only (301).
			if ( 301 === $code && $location ) {
				$this->logger->log( sprintf( '-- %s found', $code ) );
				return $this->fetch_url( $location, false );
			}

			// Handle temporary redirects (302, 303, 307, 308) - don't follow, just return the redirect info.
			if ( in_array( $code, [ 302, 303, 307, 308 ], true ) && $location ) {
				$this->logger->log( sprintf( '-- %s found', $code ) );
				return [
					'code'     => $code,
					'location' => $location,
				];
			}

			// If missing or gone, check if the url is a post.
			if ( in_array( $code, [ 404, 410 ] ) ) {
				// If we have an existing redirect, try it.
				if ( $this->args['existing'] ) {
					// If it's a relative url, convert it to an absolute url.
					if ( ! wp_http_validate_url( $this->args['existing'] ) ) {
						$existing = self::convert_relative_url( $this->args['existing'], $this->args['host_path'] );
					}
					// If it's an absolute url, replace the host.
					else {
						$existing = self::replace_host( $this->args['existing'], $this->args['home_path'], $this->args['host_path'] );
					}

					// If the existing redirect is valid and different from current URL, try it.
					if ( $existing && $existing !== $this->url ) {
						$this->logger->log( sprintf( '-- %s, checking existing redirect', $code ) );
						return $this->fetch_url( $existing, false );
					}
				}

				// Log what we're doing.
				$this->logger->log( sprintf( '-- %s found, checking for slug', $code ) );

				// No redirect found, check for a post on the current site from the url slug/path.
				$post_url = $this->check_slug_redirect( $this->url );

				// If the post url is found, set the location to the post url.
				if ( $post_url ) {
					$code     = 200;
					$location = $post_url;
				}
			}

			// If not successful, but we have an existing redirect, try it.
			if ( 200 !== $code  ) {
				// Temporary issues - skip entirely.
				if ( in_array( $code, [ 401, 403, 429, 500, 502, 503, 504 ] ) ) {
					$this->logger->warning( sprintf( '-- %s found, skipping (may be temporary)', $code ) );
					return [
						'code'     => $code,
						'location' => $this->url,
					];
				}

				// Log the error.
				$this->logger->log( sprintf( '-- %s found', $code ) );

				return [
					'code'     => $code,
					'location' => $this->url,
				];
			}

			// Log the final url.
			$this->logger->log( sprintf( '-- %s %s', $code, $this->url ) );

			// For successful responses (200), return the actual URL that was reached.
			return [
				'code'     => $code,
				'location' => $this->url,
			];

		} catch ( Exception $e ) {
			$this->logger->error( $e->getMessage() );
		}
	}

	/**
	 * Sanitize the arguments.
	 *
	 * @since 0.1.0
	 *
	 * @param array $args The arguments.
	 *
	 * @return array
	 */
	public function sanitize_args( $args ) {
		// Parse arguments.
		$args = wp_parse_args( $args, [
			'existing'  => '',
			'home_path' => '',
			'host_path' => '',
			'username'  => '',
			'password'  => '',
		]);

		// Sanitize.
		$args['existing']  = sanitize_text_field( $args['existing'] );
		$args['home_path'] = sanitize_text_field( $args['home_path'] );
		$args['host_path'] = sanitize_text_field( $args['host_path'] );
		$args['username']  = sanitize_text_field( $args['username'] );
		$args['password']  = sanitize_text_field( $args['password'] );

		return $args;
	}

	/**
	 * Attempt to find a post by the url path.
	 *
	 * @since 0.1.0
	 *
	 * @param string $url The url to check.
	 *
	 * @return string
	 */
	private function check_slug_redirect( $url ) {
		// Get data from url.
		$path  = wp_parse_url( $url, PHP_URL_PATH );
		$parts = explode( '/', $path );
		$parts = array_filter( $parts );
		$parts = array_values( $parts );
		$count = count( $parts );

		// Bail if no parts or more than 2 parts.
		if ( ! $parts || $count > 2 ) {
			$this->logger->log( '-- No parts or more than 2 parts' );
			return false;
		}

		// Get the base and slug.
		$base = reset( $parts ); // Currently unused.
		$slug = end( $parts );

		// Get all public post types to search across.
		$post_types = get_post_types( [ 'public' => true ], 'names' );

		// Use WP_Query to search for any post with this slug across all post types.
		$args = [
			'name'                   => $slug,
			'post_type'              => $post_types,
			'post_status'            => 'publish',
			'posts_per_page'         => 1,
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		];

		// Create a new WP_Query.
		$query = new WP_Query( $args );

		// Bail if no post found.
		// No need to log because fetch_url() logs it after this.
		if ( ! $query->have_posts() ) {
			wp_reset_postdata();
			return false;
		}

		// Get the first post.
		$post      = reset( $query->posts );
		$permalink = $post ? get_permalink( $post->ID ) : '';

		// Bail if no permalink found.
		// No need to log because fetch_url() logs it after this.
		if ( ! $permalink ) {
			wp_reset_postdata();
			return false;
		}

		// Set the return value.
		$this->logger->log( sprintf( '-- Post found, returning: %s', $permalink ) );

		return $permalink;
	}

	// /**
	//  * Validate the url.
	//  *
	//  * @since 0.1.0
	//  *
	//  * @param string $url The url to validate.
	//  *
	//  * @return string|false The validated url.
	//  */
	// private function validate_url( $url ) {
	// 	// Bail if no url.
	// 	if ( ! $url ) {
	// 		return false;
	// 	}

	// 	// If we have a host to use for relative URLs
	// 	if ( $this->args['host'] ) {
	// 		// Parse the URL to check if it has a scheme and host
	// 		$parsed = wp_parse_url( $url );

	// 		// If the URL doesn't have a scheme or host, it's a relative URL.
	// 		// Only prepend the host for relative URLs.
	// 		if ( ! isset( $parsed['scheme'] ) || ! isset( $parsed['host'] ) ) {
	// 			// This is a relative URL, prepend the host.
	// 			$url = $this->prepend_host( $url );
	// 		}
	// 	}

	// 	// Validate the final URL
	// 	$url = wp_http_validate_url( $url );

	// 	return $url;
	// }

	// /**
	//  * Prepend a host to a URL path.
	//  *
	//  * @since 0.1.0
	//  *
	//  * @param string $path The URL path to prepend host to.
	//  *
	//  * @return string The URL with host prepended.
	//  */
	// private function prepend_host( $path ) {
	// 	// Remove leading slash if present.
	// 	$path = ltrim( $path, '/' );

	// 	// Force https.
	// 	return sprintf( 'https://%s/%s', untrailingslashit( $this->args['host'] ), $path );
	// }

	/**
	 * Convert relative URL to absolute URL using host path.
	 *
	 * @since 0.1.0
	 *
	 * @param string $url The URL to process.
	 * @param string $host_path The host path to prepend.
	 *
	 * @return string The processed URL.
	 */
	public static function convert_relative_url( $url, $host_path ) {
		// Bail if no host path provided.
		if ( ! $host_path ) {
			return $url;
		}

		// If URL is already absolute, return as-is.
		if ( wp_http_validate_url( $url ) ) {
			return $url;
		}

		// Convert host path to full URL if needed.
		if ( ! str_contains( $host_path, '://' ) ) {
			$host_path = 'https://' . $host_path;
		}

		// Remove leading slash from URL if present.
		$url = ltrim( $url, '/' );

		// Return the full URL.
		return rtrim( $host_path, '/' ) . '/' . $url;
	}

	/**
	 * Replace home path with host path in an absolute URL.
	 *
	 * @since 0.1.0
	 *
	 * @param string $url       The URL to process.
	 * @param string $home_path The home path to replace.
	 * @param string $host_path The host path to replace with.
	 *
	 * @return string|false The processed URL.
	 */
	public static function replace_host( $url, $home_path, $host_path ) {
		// Bail if no host path provided.
		if ( ! $host_path ) {
			return $url;
		}

		// Bail if URL is not absolute.
		if ( ! wp_http_validate_url( $url ) ) {
			return $url;
		}

		// Bail if URL doesn't contain the home path.
		if ( ! str_contains( $url, $home_path ) ) {
			return $url;
		}

		// Convert hostnames to full URLs if needed.
		if ( ! str_contains( $home_path, '://' ) ) {
			$home_path = 'https://' . $home_path;
		}
		if ( ! str_contains( $host_path, '://' ) ) {
			$host_path = 'https://' . $host_path;
		}

		// Parse both URLs to get just the host parts.
		$old_host = wp_parse_url( $home_path, PHP_URL_HOST );
		$new_host = wp_parse_url( $host_path, PHP_URL_HOST );

		// Bail if we can't parse the hosts.
		if ( ! $old_host || ! $new_host ) {
			return $url;
		}

		// Replace the base hostname (without www).
		$old_base = preg_replace( '/^www\./', '', $old_host );
		$new_base = preg_replace( '/^www\./', '', $new_host );

		// If we have a base hostname, replace it.
		if ( $old_base && $new_base ) {
			$url = str_replace( $old_base, $new_base, $url );
		}

		// Handle www differences.
		if ( str_starts_with( $old_host, 'www.' ) && ! str_starts_with( $new_host, 'www.' ) ) {
			// Old has www, new doesn't - remove www from URL.
			$url = str_replace( 'www.', '', $url );
		} elseif ( ! str_starts_with( $old_host, 'www.' ) && str_starts_with( $new_host, 'www.' ) ) {
			// Old doesn't have www, new does - add www to URL.
			$url = str_replace( 'https://', 'https://www.', $url );
			$url = str_replace( 'http://', 'http://www.', $url );
		}

		return wp_http_validate_url( $url );
	}
}