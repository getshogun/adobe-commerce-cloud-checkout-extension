# Adobe Commerce Cloud (Magento) module

Shogun's Frontend checkout extension for Adobe Commerce Cloud.

## Compatibility

Magento 2.4 or higher.

## Install

### Composer

1. Install the extension with composer:

```bash
composer require shogun/frontend-checkout
```

2. Run the following command on your Magento instance:

```
php bin/magento setup:upgrade
```

### Manual

Clone this repo into your Magento's instance `src/app/code/Shogun/FrontendCheckout` and run:

```bash
bin/magento module:enable Shogun_FrontendCheckout --clear-static-content
bin/magento setup:upgrade
bin/magento cache:flush
```

## Development

### Dependency Injection

Magento uses a tool named "Injector" to inject dependencies into constructors.
Hence, if you ever change your constructor signature, you need to recompile
in order to inject the necessary dependencies by running:

```bash
bin/magento setup:upgrade && bin/magento cache:clean && bin/magento setup:di:compile
```

## Release

Here's how to release a new version of the module:

1. The module is available as a composer package on [Packagist](https://packagist.org/packages/shogun/frontend-checkout).
2. Create [a new GitHub release](https://github.com/getshogun/adobe-commerce-cloud-checkout-extension/releases).
3. Packagist will automatically update the package through Github's hooks.

Note that Packagist also manages the `composer.json` version automatically.
