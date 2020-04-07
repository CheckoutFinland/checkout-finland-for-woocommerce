# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.3.0] - 2020-04-03
### Changed
- New layout for the payment method selection

## [1.2.8] - 2020-01-28
### Changed
- Bump SDK version to composer.lock 

## [1.2.7] - 2020-01-28
### Changed
- Simplified error handling
- Strict comparison to some validations
- Use mb_substr() to truncate the description

## [1.2.6] - 2020-01-08
### Changed
- Updated to the latest PHP-SDK version
- Version bump to plugin.php 

## [1.2.2] - 2020-01-08
### Changed
- Updated to the latest PHP-SDK version

### Added
- Custom title and description to be used on the Payment provider section.

## [1.2.1] - 2019-10-24
### Changed
- Small fixes

## [1.2.0] - 2019-10-22
### Changed
- Rebranding from Checkout Finland to OP Payment Services for WooCommerce

## [1.1.7] - 2019-10-04
### Fixed
- A bug where an error message from the provider list API would throw a fatal error to user.
- Better PHP version checking for plugin activation.
- Better error handling for when payment provider is not selected.

## [1.1.6] - 2019-09-24
### Fixed
- Order items without taxes no longer cause 500 error on checkout. [Fixes: #9](https://github.com/CheckoutFinland/woocommerce-checkout-finland-gateway/issues/9).

## [1.1.5] - 2019-08-28
### Removed
- Removed the manual installation guide from readme. Manual installation is not yet supported.

## [1.1.4] - 2019-08-28
### Added
- Pass the current order object for WooCommerce when creating the return url. This enables displaying the order data on a "thank you" page.

## [1.1.3] - 2019-06-12
### Changed
- Updated correct dependencies for composer/packagist.

## [1.1.2] - 2019-06-4
### Changed
- Use 1.0.0 tag for php-sdk instead of dev-master.

## [1.1.1] - 2019-05-16
### Added
- A feature that truncates the product description to a maximum of 1000 characaters when sending it to the Checkout Finland API.

## [1.1.0-beta] - 2019-04-24
### Added
- A feature to add a rounding row to the order in case the roundings are off by a few cents.

### Changed
- Better handling for the situation where WooCommerce is not activated when the user tries to activate the gateway.

## [1.0.7-beta] - 2019-04-15
### Fixed
- Correct version number to be passed to the API.
- Another small stylistical fix for the payment provider logo grid.

## [1.0.6-beta] - 2019-04-09
### Fixed
- A small stylistical fix for the payment provider logo grid.

## [1.0.5-beta] - 2019-04-09
### Fixed
- Payment gateway icon disabled due to size issues.
- Added max-width: 300px for the payment provider logos.

## [1.0.4-beta] - 2019-04-08
### Added
- A setting to choose between showing the payment provider selection in-store or rather in an external view by Checkout Finland.

## [1.0.2-beta] - 2019-03-29
### Added
- Plugin version header to be sent with all requests.

## [1.0.1-beta] - 2019-03-26
### Added
- Payment method groupings.
- Better order notes from the payment process.
- Pending payment status support.

## [1.0.0-beta] - 2019-03-25
### Added
- All initial plugin functionalities.
