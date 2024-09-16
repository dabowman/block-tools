# Block Tools Commands

This WordPress plugin provides two WP-CLI commands for managing Gutenberg blocks in your WordPress installation.

## Commands

### `wp block-tools search`

Searches for a specific Gutenberg block in your WordPress posts.

#### Parameters

- `--block-slug=<slug>`: (required) The slug of the Gutenberg block to search for.
- `--site-id=<id>`: (optional) The ID of the site to search in. If not provided, the command will search in all sites.
- `--post-type=<type>`: (optional) The type of the posts to search in. Defaults to 'post'.
- `--file=<filename>`: (optional) The name of the file to write the search results to. Defaults to 'block-search.csv'.

#### Usage

```bash
wp block-tools search --block-slug=paragraph
```

### `wp block-tools remove`

Removes a specific Gutenberg block from your WordPress posts.

#### Parameters

- `--block-slug=<slug>`: (required) The slug of the Gutenberg block to remove.
- `--site-id=<id>`: (required in multisite installations) The ID of the site to remove the block from. If not provided, the command will remove the block from all sites.
- `--post-type=<type>`: (required) The type of the posts to remove the block from. Multiple post types can be provided as a comma-separated list.

#### Usage

```bash
wp block-tools remove --block-slug=paragraph --site-id=1 --post-type=post,page
```
### `wp block-tools audit`

Scans posts of a specified post type or all post types and lists every unique block that has been used.

#### Parameters

- `--post-type=<slug>`: (optional) The slug of the post type you want to audit or `all` which will scan all post types as well as 'wp_block', 'wp_template' and 'wp_template_part'. Defaults to `post`.

#### Usage

`wp block-tools audit [--post-type=<post-type>]`


## Installation

1. Download the plugin and extract it to your `wp-content/plugins` directory.
2. Activate the plugin in your WordPress admin dashboard.
3. The WP-CLI commands will be available in your command line.

## Notes

- The `block-tools search` command writes the search results to a CSV file in the WordPress uploads directory. The CSV file includes the post ID, post URL, post title, block type, content excerpt, and block attributes.
- The `block-tools search` command can only be run on a WordPress Multisite installation if the `switch_to_blog` function is available.
- The `block-tools search` command uses the `LIKE` SQL operator to search for the block slug in the post content, so it may return false positives if the block slug is found in the content of other blocks or in the text content.
- The `block-tools remove` command uses a regular expression to remove the block from the post content. It removes the entire block, including the block attributes and inner content.
- The `block-tools remove` command only removes the block from published posts. Drafts, scheduled posts, and other post statuses are not affected.
- The `block-tools remove` command outputs a success message indicating that the block has been removed from all specified post types in the given site. If a post type does not exist, it skips that post type and outputs a warning message.
- The `block-tools remove` command can only be run on a WordPress Multisite installation if the `switch_to_blog` function is available.
- The `block-tools remove` command requires a valid site ID in multisite installations. If a site ID is not provided or is not valid, it outputs an error message and does not run the command.
