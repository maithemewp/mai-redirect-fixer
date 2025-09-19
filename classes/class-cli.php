<?php

namespace Mai\RedirectFixer;
use Exception;

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
	 * [--host=<host>]
	 * : The host to check. The home_url() will be used if not provided.
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
	 * Check redirects.
	 *
	 * ## OPTIONS
	 *
	 * [--file=<file>]
	 * : The CSV file to check. This should be in the WP root directory or include the full path.
	 * The first column should be the url to check.
	 * The second column should be the existing redirect, if any.
	 *
	 * [--host=<host>]
	 * : The host to check. The home_url() will be used if not provided.
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
		$file   = $assoc_args['file'] ? ABSPATH . ltrim( $assoc_args['file'], '/' ) : '';
		$limit  = $assoc_args['limit'] ? absint( $assoc_args['limit'] ) : 0;
		$offset = $assoc_args['offset'] ? absint( $assoc_args['offset'] ) : 0;
		$delay  = $assoc_args['delay'] ? floatval( $assoc_args['delay'] ) : 0;

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

				// Log the url we're checking.
				$this->logger->log( sprintf( '[%d/%d]', $count, $total, $url ) );

				// Parse the fetcher args.
				$fetcher_args = wp_parse_args( $assoc_args, [
					'url'      => $url,
					'existing' => $existing,
					'host'     => $assoc_args['host'] ?? '',
					'username' => $assoc_args['username'] ?? '',
					'password' => $assoc_args['password'] ?? '',
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