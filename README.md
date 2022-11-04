# Increazy checkout Magento 2 v2

Module to add Increazy API in Magento 2.3.X, follow the installation steps:

1. Copy the Increazy folder to app/code.
2. Execute:

```bash
php bin/magento module:enable Increazy_CheckoutV2
php bin/magento setup:upgrade
php bin/magento setup:static-content:deploy -f
php bin/magento cache:flush
php bin/magento cache:clean
```