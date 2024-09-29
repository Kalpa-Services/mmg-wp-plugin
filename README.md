# MMG Checkout Payment Plugin - Developer Contribution Guide

[![FOSSA Status](https://app.fossa.com/api/projects/git%2Bgithub.com%2FKalpa-Services%2Fmmg-wp-plugin.svg?type=shield&issueType=security)](https://app.fossa.com/projects/git%2Bgithub.com%2FKalpa-Services%2Fmmg-wp-plugin?ref=badge_shield&issueType=security)

## Project Overview

MMG Checkout Payment is a WordPress plugin that integrates with WooCommerce to provide a secure payment gateway for MMG Merchants. This guide is for developers who want to contribute to the project.

## Development Prerequisites

- WordPress 6.0+
- WooCommerce 7.0+
- PHP 7.4+
- mbstring extension
- Familiarity with WordPress plugin development
- Understanding of payment gateway integrations
- Knowledge of RSA encryption/decryption

## Setting Up the Development Environment

1. Configure [devbox](https://www.jetify.com/devbox/docs/quickstart/) on your local machine.

1. Clone the repository:
   ```sh
   git clone https://github.com/Kalpa-Services/mmg-wp-plugin.git
   ```
1. Navigate to project directory. Doing so should automatically install all the required environment dependencies. If not run `devbox install`.
2. Run `composer install` project dependencies.
1. Run `devbox services up` to start all services.
1. Run `mysql -u root` to connect to the database and create a new database for wordpress.
1. Create a new directory called `wordpress` in `devbox.d` and run the following commands inside it:

   ```sh
   curl -O https://wordpress.org/latest.tar.gz
   tar -xzvf latest.tar.gz
   mv wordpress/* .
   rm -rf wordpress latest.tar.gz
   ```

1. Configure your wp-config.php file and navigate to `http://localhost:8082` to complete the WordPress setup.
1. Navigae to `devbox.d/wordpress/wp-content/plugins` and run the following command:

   ```sh
   ln -s /path/to/project/mmg-wp-plugin mmg-checkout-payment
   ```

1. Activate plugin in the WordPress admin panel.

## Code Structure

- `includes/`: Contains the core plugin files.
- `tests/`: Contains unit tests for the plugin.
- `vendor/`: Contains third-party libraries and dependencies.
- `js/`: Contains the JavaScript files for the plugin.
- `assets/`: Contains the images and other assets for the plugin.
- `main.php`: The main plugin file.
- `uninstall.php`: The uninstallation script for the plugin.
- `readme.txt`: The readme file for the plugin used by WordPress.

## Coding Standards

- Follow WordPress Coding Standards (https://developer.wordpress.org/coding-standards/wordpress-coding-standards/)
- Use meaningful variable and function names
- Comment your code thoroughly, especially complex logic
- Run `composer lint` to check for coding standards violations

## Testing

- Ensure all new features or bug fixes include appropriate unit tests
- Test the plugin with various WordPress and WooCommerce versions
- Verify compatibility with popular WordPress themes

## Submitting Changes

1. Create a new branch for your feature or bug fix
2. Make your changes and commit them with clear, concise commit messages
3. Push your branch and create a pull request
4. Ensure your PR description clearly explains the changes and their purpose

## Security Considerations

- Never commit sensitive information (API keys, passwords) to the repository
- Follow WordPress security best practices (https://developer.wordpress.org/plugins/security/)
- Be cautious when handling user data and payment information

## Documentation

- Update the README.md file with any new features or changes to installation/configuration steps
- Document any new functions or classes using PHPDoc standards

## Roadmap and Future Development

- Implement processing of refunds

## Getting Help

For questions or support, please open an issue on the GitHub repository or contact the maintainers directly.

## License

This project is licensed under GPL v2 or later. Ensure all contributions comply with this license.

[![FOSSA Status](https://app.fossa.com/api/projects/git%2Bgithub.com%2FKalpa-Services%2Fmmg-wp-plugin.svg?type=large)](https://app.fossa.com/projects/git%2Bgithub.com%2FKalpa-Services%2Fmmg-wp-plugin?ref=badge_large)
