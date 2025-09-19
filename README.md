# Mai Embed Fixer

A WordPress plugin that automatically fixes Twitter/X and Instagram embeds that aren't working properly in WordPress.

## Description

Mai Embed Fixer is designed to solve common issues with social media embeds in WordPress. It specifically targets Twitter/X and Instagram embeds that may not be rendering correctly due to the changes in various social media platform API's which affect their integrations with oEmbed.

## Features

- **Automatic Embed Conversion**: Converts problematic Twitter/X and Instagram embeds to their proper social media equivalents
- **Script Management**: Automatically adds and manages the required JavaScript files for Twitter and Instagram embeds
- **Duplicate Prevention**: Prevents duplicate script tags from being added to the same page
- **Smart Detection**: Only adds scripts when social media embeds are actually present in the content
- **Update System**: Includes automatic update functionality via GitHub

## How It Works

### Embed Conversion
The plugin hooks into WordPress's `render_block_core/embed` filter to intercept embed blocks and convert them to the proper social media embed format:

- **Instagram**: Converts to Instagram's official embed format with proper data attributes
- **Twitter/X**: Converts to Twitter's official embed format with proper data attributes

### Script Management
The plugin automatically adds the necessary JavaScript files to render social media embeds:

- **Twitter**: Adds `https://platform.twitter.com/widgets.js`
- **Instagram**: Adds `//www.instagram.com/embed.js`

The plugin intelligently detects when these scripts are already present and prevents duplicates.

## Installation

1. Download the plugin files
2. Upload the `mai-embed-fixer` folder to your `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher

## Dependencies

- `yahnis-elsts/plugin-update-checker` (^5.6) - For automatic updates

## Usage

Once activated, the plugin works automatically. No configuration is required.

### For Content Creators
Simply embed Twitter/X or Instagram URLs using WordPress's standard embed functionality. The plugin will automatically convert them to the proper format.

### For Developers
The plugin provides several hooks and filters for customization:

- `render_block_core/embed` filter - Modify embed conversion logic
- `the_content` filter - Modify script injection behavior

## Configuration

### GitHub API Token (Optional)
To get around GitHub's API limitations, you can define a GitHub API token:

```php
define( 'MAI_GITHUB_API_TOKEN', 'your-github-token-here' );
```

## Support

For support, please visit [BizBudding](https://bizbudding.com/) or create an issue on the GitHub repository.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## Credits

- Developed by [BizBudding](https://bizbudding.com/)
- Uses [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) by YahnisElsts for automatic updates
