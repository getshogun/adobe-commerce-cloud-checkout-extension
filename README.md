# adobe-commerce-cloud-checkout-extension
Adobe Commerce Cloud extension to checkout orders from Shogun storefronts.

## Usage

Clone this repo into your Magento's instance `src/app/code/Shogun/` and run:

```bash
bin/magento module:enable Shogun_FrontendCheckout --clear-static-content

bin/magento setup:upgrade

bin/magento cache:flush
```

## Injector

Magento uses a tool named "Injector" to inject dependencies into constructors.
Hence, if you ever change your constructor signature, you need to recompile
in order to inject the necessary dependencies by running:

```bash
bin/magento setup:upgrade && bin/magento cache:clean && bin/magento setup:di:compile
```
