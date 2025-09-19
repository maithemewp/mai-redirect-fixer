<?php

namespace Mai\RedirectFixer;
use Exception;
use WP_Query;
use WP_HTML_Tag_Processor;

defined( 'ABSPATH' ) || exit;

/**
 * WP-CLI commands class.
 *
 * @since 0.1.0
 */
class CLI {
	/**
	 * The logger.
	 *
	 * @since 0.1.0
	 * @var Logger
	 */
	private $logger;

	/**
	 * Check a single URL.
	 *
	 * ## OPTIONS
	 *
	 * [--target=<url>]
	 * : The URL to check.
	 *
	 * [--home_path=<home_path>]
	 * : The host to check. The home_path() will be used if not provided.
	 * You must used `www.` if the host url has it. Default is the home_path().
	 *
	 * [--host_path=<host_path>]
	 * : The home path to replace the home_path() with. Works when on staging but links point to the live site.
	 * You must used `www.` if the host url has it.
	 *
	 * [--username=<username>]
	 * : Username for basic authentication.
	 *
	 * [--password=<password>]
	 * : Password for basic authentication.
	 *
	 * ## EXAMPLES
	 *
	 * wp mai-redirect-fixer check-url --url=https://bizbudding.com/contact-us/ --host=bizbudding.com --username=admin --password=secret
	 *
	 * @since 0.1.0
	 *
	 * @subcommand check-url
	 * @param array $args
	 * @param array $assoc_args
	 *
	 * @return void
	 */
	public function check_url( $args, $assoc_args ) {
		// Set the logger.
		$this->logger = Logger::get_instance();

		// Get the url.
		$url = $assoc_args['target'] ?? '';

		// Bail if no url.
		if ( ! $url ) {
			$this->logger->error( 'Missing --target argument.' );
			return;
		}

		try {
			// Set up the fetcher.
			$fetcher = new UrlFetcher( $assoc_args );

			// Fetch the url. Handles logging internally.
			$fetcher->fetch_url( $url );

		} catch ( Exception $e ) {
			$this->logger->error( $e->getMessage() );
		}
	}

	/**
	 * Check posts.
	 *
	 * ## OPTIONS
	 *
	 * [--post_type=<post_type>]
	 * : The post type to check.
	 *
	 * [--post_status=<post_status>]
	 * : The post status to check.
	 *
	 * [--per_page=<per_page>]
	 * : The number of posts to check per page.
	 *
	 * [--offset=<offset>]
	 * : The offset to start from.
	 *
	 * [--post_in=<post_in>]
	 * : The posts to check.
	 *
	 * [--home_path=<home_path>]
	 * : The existing home url. When local this may be example.local but the urls in content may be example.com.
	 * You must used `www.` if the live site url has it. Default is the home_path().
	 *
	 * [--host_path=<host_path>]
	 * : The home url to replace the home_path() with. Works when on staging but links point to the live site.
	 * You must used `www.` if the host url has it. Default is the home_path().
	 *
	 * [--username=<username>]
	 * : Username for basic authentication.
	 *
	 * [--password=<password>]
	 * : Password for basic authentication.
	 *
	 * [--delay=<delay>]
	 * : The delay between requests in seconds.
	 *
	 * [--dry-run]
	 * : Only list the posts that would be checked. No changes will be made.
	 *
	 * ## EXAMPLES
	 *
	 * wp mai-redirect-fixer check-posts --post_type=post --post_status=publish --per_page=100 --offset=0 --post_in=1,2,3
	 * wp mai-redirect-fixer check-posts --post_type=post --post_status=publish --per_page=100 --offset=0 --post_in=1,2,3 --dry-run
	 *
	 * @since 0.1.0
	 *
	 * @subcommand check-posts
	 * @param array $args
	 * @param array $assoc_args
	 *
	 * @return void
	 */
	public function check_posts( $args, $assoc_args ) {
		// Set the logger.
		$this->logger = Logger::get_instance();

		// Set the params we need here.
		$dry_run     = isset( $assoc_args['dry-run'] );
		$delay       = isset( $assoc_args['delay'] ) ? (int) $assoc_args['delay'] : 0;
		$post_type   = isset( $assoc_args['post_type'] ) ? $assoc_args['post_type'] : 'post';
		$post_status = isset( $assoc_args['post_status'] ) ? $assoc_args['post_status'] : 'any';
		$per_page    = isset( $assoc_args['per_page'] ) ? (int) $assoc_args['per_page'] : 100;
		$offset      = isset( $assoc_args['offset'] ) ? (int) $assoc_args['offset'] : 0;
		$post_in     = isset( $assoc_args['post_in'] ) ? explode( ',', $assoc_args['post_in'] ) : [];
		$home_path   = isset( $assoc_args['home_path'] ) ? $assoc_args['home_path'] : wp_parse_url( home_url(), PHP_URL_PATH );
		$host_path   = isset( $assoc_args['host_path'] ) ? $assoc_args['host_path'] : '';
		$username    = isset( $assoc_args['username'] ) ? $assoc_args['username'] : '';
		$password    = isset( $assoc_args['password'] ) ? $assoc_args['password'] : '';

		// Log the start.
		$this->logger->log( 'Loading posts...' );

		$query_args = [
			'post_type'              => $post_type,
			'post_status'            => $post_status,
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		];

		// Handle post_in.
		if ( ! empty( $post_in ) ) {
			$query_args['post__in']       = array_map( 'intval', $post_in );
			$query_args['posts_per_page'] = count( $post_in );
		} else {
			$query_args['posts_per_page'] = $per_page;
			$query_args['offset']         = $offset;
		}

		try {
			$query     = new WP_Query( $query_args );
			$found     = 0;
			$skipped   = 0;
			$redirects = [];

			// Loop through the posts.
			if ( $query->have_posts() ) {
				// Log the number of posts found.
				$this->logger->log( sprintf( 'Found %d posts starting from offset %d. Processing...', $query->post_count, $offset ) );

				// Start with the basic fetcher args.
				$fetcher_args = [];

				// Add the username and password if provided.
				if ( $username && $password ) {
					$fetcher_args['username'] = $username;
					$fetcher_args['password'] = $password;
				}

				// Set up the fetcher.
				$fetcher = new UrlFetcher( $fetcher_args );

				// Loop through the posts.
				while ( $query->have_posts() ) : $query->the_post();
					global $post;

					// Get the content.
					$content = $post->post_content;

					// Skip if no content.
					if ( empty( $content ) ) {
						$skipped++;
						continue;
					}

					// If we have redirects.
					$has_redirects = false;

					// Set up tag processor.
					$tags = new WP_HTML_Tag_Processor( $content );

					// Loop through tags.
					while ( $tags->next_tag( [ 'tag_name' => 'a' ] ) ) {
						$url = (string) $tags->get_attribute( 'href' );

						// Skip if no url.
						if ( empty( $url ) ) {
							continue;
						}

						// Bail if not an absolute url.
						if ( ! wp_http_validate_url( $url ) ) {
							continue;
						}

						// Replace the home path with host path if provided (only for absolute URLs).
						$url = UrlFetcher::replace_host( $url, $home_path, $host_path );

						// Fetch the url.
						$redirect = $fetcher->fetch_url( $url );

						// Add to redirects.
						$redirects[] = $redirect;

						// If we have redirects.
						$has_redirects = true;

						// Add a small delay between requests to be respectful.
						if ( $delay ) {
							sleep( $delay );
						}
					}

					// If we have redirects.
					if ( $has_redirects ) {
						$found++;
					} else {
						$skipped++;
					}
				endwhile;
			} else {
				$this->logger->warning( 'No posts found matching the criteria.' );
			}
			wp_reset_postdata();

			// Log the redirects.
			$this->logger->success( sprintf( 'Found %d redirects. Skipped %d posts.', $found, $skipped ) );

			ray( $redirects );

		} catch ( Exception $e ) {
			$this->logger->error( $e->getMessage() );
		}
	}

	/**
	 * Check redirects.
	 *
	 * ## OPTIONS
	 *
	 * [--file=<file>]
	 * : The CSV file to check. This should be in the WP root directory or include the full path.
	 * The first column should be the url to check.
	 * The second column should be the existing redirect, if any.
	 *
	 * [--home_path=<home_path>]
	 * : The existing home url. When local this may be example.local but the urls in content may be example.com.
	 * You must used `www.` if the live site url has it. Default is the home_path().
	 *
	 * [--host_path=<host_path>]
	 * : The home url to replace the home_path() with. Works when on staging but links point to the live site.
	 * You must used `www.` if the host url has it. Default is the home_path().
	 *
	 * [--username=<username>]
	 * : Username for basic authentication.
	 *
	 * [--password=<password>]
	 * : Password for basic authentication.
	 *
	 * [--limit=<limit>]
	 * : The maximum number of urls to check.
	 *
	 * [--offset=<offset>]
	 * : The offset to start from.
	 *
	 * [--delay=<delay>]
	 * : The delay between requests in seconds.
	 *
	 *
	 * ## EXAMPLES
	 *
	 * wp mai-redirect-fixer check-csv --file=redirects.csv
	 * wp mai-redirect-fixer check-csv --file=jamie-geller-redirects.csv --host=jamiegeller.com
	 * wp mai-redirect-fixer check-csv --file=wp-content/uploads/redirects.csv
	 * wp mai-redirect-fixer check-csv --file=wp-content/uploads/redirects.csv --host=example.com
	 * wp mai-redirect-fixer check-csv --file=redirects.csv --username=admin --password=secret
	 * wp mai-redirect-fixer check-csv --file=redirects.csv --limit=10
	 *
	 * @since 0.1.0
	 *
	 * @subcommand check-csv
	 * @param array $args
	 * @param array $assoc_args
	 *
	 * @return void
	 */
	public function check_csv( $args, $assoc_args ) {
		// Set the logger.
		$this->logger = Logger::get_instance();

		// Get the params we need here.
		$file      = $assoc_args['file'] ? ABSPATH . ltrim( $assoc_args['file'], '/' ) : '';
		$limit     = $assoc_args['limit'] ? absint( $assoc_args['limit'] ) : 0;
		$offset    = $assoc_args['offset'] ? absint( $assoc_args['offset'] ) : 0;
		$delay     = $assoc_args['delay'] ? floatval( $assoc_args['delay'] ) : 0;
		$home_path = isset( $assoc_args['home_path'] ) ? $assoc_args['home_path'] : wp_parse_url( home_url(), PHP_URL_HOST );
		$host_path = isset( $assoc_args['host_path'] ) ? $assoc_args['host_path'] : '';

		// Bail if no file provided.
		if ( empty( $file ) ) {
			$this->logger->error( 'Missing --file argument.' );
			return;
		}

		// Bail if the file does not exist.
		if ( ! file_exists( $file ) ) {
			$this->logger->error( sprintf( 'File %s does not exist. Please check the path and try again.', $file ) );
			return;
		}

		try {
			// Initialize the arrays.
			$skipped   = [];
			$redirects = [];

			// Parse the csv file.
			$csv = file_get_contents( $file );
			$csv = explode( "\n", $csv );
			$csv = array_map( 'str_getcsv', $csv );
			$csv = array_map( 'array_filter', $csv );
			$csv = array_map( 'array_values', $csv );

			// Filter out empty rows.
			$csv = array_filter( $csv, function( $row ) {
				return ! empty( $row[0] );
			});

			// If we have a limit.
			if ( $limit ) {
				// Filter the csv to the limit.
				$csv = array_slice( $csv, $offset, $limit );
			}

			// Set total count.
			$total = count( $csv );

			// Log the number of urls to process.
			$this->logger->info( sprintf( 'Processing %d URLs from CSV', $total ) );

			// Loop through the csv rows.
			$count = 0;
			foreach ( $csv as $row ) {
				$count++;

				// Get the url and existing redirect.
				$url      = $row[0] ?? '';
				$existing = $row[1] ?? '';

				// Convert relative URLs to absolute URLs using host path.
				$url = UrlFetcher::convert_relative_url( $url, $host_path );

				// Log the url we're checking.
				$this->logger->log( sprintf( '[%d/%d]', $count, $total, $url ) );

				// Parse the fetcher args.
				$fetcher_args = wp_parse_args( $assoc_args, [
					'url'       => $url,
					'existing'  => $existing,
					'home_path' => $home_path,
					'host_path' => $host_path,
					'username'  => $assoc_args['username'] ?? '',
					'password'  => $assoc_args['password'] ?? '',
				]);

				// Set up the fetcher.
				$fetcher = new UrlFetcher( $fetcher_args );

				// Fetch the url.
				$redirect = $fetcher->fetch_url( $url );

				// Log if there is an error.
				if ( is_wp_error( $redirect ) ) {
					$this->logger->error( 'WP_Error: ' . $url . ' - ' . $redirect->get_error_code() . ' - ' . $redirect->get_error_message() );
					continue;
				}

				// Skip if not successful. This is already logged by the fetcher.
				if ( 200 !== $redirect['code'] ) {
					continue;
				}

				// Log the response.
				$redirects[] = [
					'old_url' => $url,
					'new_url' => $redirect['location'],
					'code'    => $redirect['code'],
				];

				// Add a small delay between requests to be respectful.
				if ( $delay ) {
					sleep( $delay );
				}
			}

			$this->logger->success( sprintf( 'Found %d redirects.', count( $redirects ) ) );

		} catch ( Exception $e ) {
			$this->logger->error( $e->getMessage() );
		}
	}
}