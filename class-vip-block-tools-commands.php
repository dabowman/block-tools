<?php
/**
 * This file contains the VIP_Block_Tools_Commands class, which is responsible for adding and executing WP-CLI commands related to block tools.
 *
 * @package block-tools
 */

if ( ! class_exists( 'VIP_Block_Tools_Commands' ) ) {
	/**
	 * Add WP CLI commands
	 */
	class VIP_Block_Tools_Commands {

		/**
		 * Constructor for the VIP_Block_Tools_Commands class.
		 * This add the parent block-tools command and search and remove subcommands.
		 */
		public function __construct() {
			WP_CLI::add_command( 'block-tools search', array( $this, 'search' ) );
			WP_CLI::add_command( 'block-tools remove', array( $this, 'remove' ) );
		}

		/**
		 * Searches for a specific Gutenberg block in your WordPress database and outputs the results to a csv.
		 *
		 * --block-slug     The slug of the Gutenberg block to search for. (required)
		 * --site-id        The ID of the site to search in. If not provided, the command will search in all sites. (optional)
		 * --post-type      The type of the posts to search in. Defaults to 'post'. (optional)
		 * --file           The name of the file to write the search results to. Defaults to 'block-search.csv'. (optional)
		 *
		 * Example:
		 * wp block-tools search --block-slug:paragraph --site-id:2 --post-type:page,post --file:custom-filename
		 *
		 * @param array $args the arguments.
		 * @param array $assoc_args block-slug, site-id, post-type, file.
		 */
		public function search( $args, $assoc_args ) {
			$block_slug = isset( $assoc_args['block-slug'] ) ? $assoc_args['block-slug'] : null;
			if ( empty( $block_slug ) ) {
				WP_CLI::error( 'Please provide a block slug using --block-slug parameter.' );
				return;
			}
			$site_id = isset( $assoc_args['site-id'] ) ? $assoc_args['site-id'] : null;
			if ( $site_id ) {
				if ( function_exists( 'switch_to_blog' ) ) {
					switch_to_blog( $site_id );
				} else {
					WP_CLI::error( 'The switch_to_blog function is not available. This script can only be run on a WordPress Multisite installation.' );
					return;
				}
			}
			$post_type  = isset( $assoc_args['post-type'] ) ? $assoc_args['post-type'] : 'post';
			$upload_dir = wp_upload_dir();
			$filename   = isset( $assoc_args['file'] ) ? $assoc_args['file'] : 'block-search.csv';
			$filename   = $upload_dir['basedir'] . '/' . $filename;
			$handle     = fopen( $filename, 'w' );
			if ( ! $handle ) {
				WP_CLI::error( "Could not open {$filename} for writing. Check file permissions." );
				return;
			}

			/**
			 * Convert an array to a string.
			 * Used to convert block attribute data to a readable json format.
			 *
			 * @param array  $data    The array to convert.
			 * @param string $prefix  The prefix to add to each key in the array.
			 * @return string         The converted string.
			 */
			function array_to_string( $data, $prefix = '' ) {
				$output = array();
				foreach ( $data as $key => $value ) {
					if ( is_array( $value ) ) {
						$output[] = array_to_string( $value, $prefix . $key . '.' );
					} else {
						$output[] = $prefix . $key . ": '" . $value . "'";
					}
				}
				return implode( ', ', $output );
			}

			// Write CSV header.
			fputcsv( $handle, array( 'Post ID', 'Post URL', 'Post Title', 'Block Type', 'Content Excerpt', 'Block Attributes' ) );

			// Loop through all subsites or a specific site.
			$sites = $site_id ? array( $site_id ) : ( function_exists( 'get_sites' ) ? get_sites( array( 'fields' => 'ids' ) ) : array( get_current_blog_id() ) );
			foreach ( $sites as $site_id ) {
				if ( function_exists( 'switch_to_blog' ) ) {
					switch_to_blog( $site_id );
				}

				global $wpdb;
				$results = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT ID, post_title, post_content, post_type
              FROM {$wpdb->prefix}posts
              WHERE post_status = %s AND post_content LIKE %s AND post_type = %s",
						'publish',
						'%' . $wpdb->esc_like( "wp:{$block_slug}" ) . '%',
						$post_type
					)
				);
				foreach ( $results as $result ) {
					preg_match_all( "/<!-- wp:{$block_slug}\s*(\{.*?\})?\s*-->(.*?)<!-- \/wp:{$block_slug} -->/s", $result->post_content, $matches );
					foreach ( $matches[1] as $index => $match ) {

						// Decode the block attributes.
						$attributes = json_decode( $match, true );

						// Convert the attributes to a string.
						$attributes_string = $attributes ? array_to_string( $attributes ) : '';

						// Strip HTML tags from the block's content.
						$content_text = strip_tags( $matches[2][ $index ] );

						// Get the first 50 characters of the text content.
						$content_excerpt = substr( $content_text, 0, 50 );

						// Write to CSV.
						fputcsv( $handle, array( $result->ID, get_permalink( $result->ID ), $result->post_title, $block_slug, $content_excerpt, $attributes_string ) );
					}
				}

				if ( function_exists( 'restore_current_blog' ) ) {
					restore_current_blog();
				}
			}

			// If site_id was provided, switch back to the main blog.
			if ( $site_id && function_exists( 'restore_current_blog' ) ) {
				restore_current_blog();
			}
			fclose( $handle );
			WP_CLI::success( "Data extracted to {$filename}" );
		}

		/**
		 * Removes a specific Gutenberg block from your WordPress posts.
		 *
		 * --block-slug       (required) The slug of the Gutenberg block to remove.
		 * --site-id          (required in multisite installations) The ID of the site to remove the block from. If not provided, the command will remove the block from all sites.
		 * --post-type        (required) The type of the posts to remove the block from. Multiple post types can be provided as a comma-separated list.
		 *
		 * Example:
		 * wp block-tools remove --block-slug:paragraph --site-id:3 --post-type:post,page
		 *
		 * @param array $args        The arguments.
		 * @param array $assoc_args  The associative arguments.
		 */
		public function remove( $args, $assoc_args ) {
			global $wpdb;

			// Retrieve the arguments.
			$block_slug = isset( $assoc_args['block-slug'] ) ? $assoc_args['block-slug'] : null;
			$site_id    = isset( $assoc_args['site-id'] ) ? intval( $assoc_args['site-id'] ) : null;
			$post_types = isset( $assoc_args['post-type'] ) ? explode( ',', $assoc_args['post-type'] ) : null;

			// Validate the arguments.
			if ( ! $block_slug ) {
				WP_CLI::error( 'You must provide a block slug.' );
				return;
			}

			if ( is_multisite() && ( ! $site_id || $site_id <= 0 ) ) {
				WP_CLI::error( 'You must provide a valid site ID.' );
				return;
			}

			if ( ! $post_types || ! is_array( $post_types ) ) {
				WP_CLI::error( 'You must provide at least one post type.' );
				return;
			}

			// Determine the table name based on whether this is a multisite or not.
			$table_name = is_multisite() ? "wp_{$site_id}_posts" : $wpdb->posts;

			foreach ( $post_types as $post_type ) {
				if ( ! post_type_exists( $post_type ) ) {
					WP_CLI::warning( "Post type '{$post_type}' does not exist. Skipping." );
					continue;
				}

				$posts = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT ID, post_content FROM {$wpdb->prefix}posts WHERE post_type = %s AND post_status = 'publish' AND post_content LIKE %s",
						$post_type,
						'%<!-- wp:' . $block_slug . '%'
					)
				);

				foreach ( $posts as $post ) {

					// Use regex to remove the block.
					$updated_content = preg_replace( '/<!-- wp:' . preg_quote( $block_slug, '/' ) . '.*?<!-- \/wp:' . preg_quote( $block_slug, '/' ) . ' -->/s', '', $post->post_content );

					// Update the post in the database.
					$wpdb->update(
						$table_name,
						array( 'post_content' => $updated_content ),
						array( 'ID' => $post->ID )
					);
				}
			}
			WP_CLI::success( "Removed '{$block_slug}' block from all specified post types in site {$site_id}." );
		}
	}
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	new VIP_Block_Tools_Commands();
}
