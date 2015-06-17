# Kirchbergerknorr GoogleBase

Export CSV data feed with products for Google Base.

## Installation

Add `require` and `repositories` sections to your composer.json as shown in example below and run `composer update`.

*composer.json example*

```
{
    "minimum-stability": "dev",
        
    "repositories": [
        {"type": "composer", "url": "http://packages.firegento.com"},
        {"type": "git", "url": "https://github.com/kirchbergerknorr/Kirchbergerknorr_GoogleBase"}
    ],
    
    "require": {
        "kirchbergerknorr/Kirchbergerknorr_GoogleBase": "*"
    },
    
    "extra": {
        "magento-root-dir": "src"
    }
}
```

Read how to [Install Magento via Composer](https://medium.com/magento-development/magento-and-composer-44af0883abd9).

## Command Line

Run those commands inside `shell` folder:

| Command                         | Description                                                              |
| ------------------------------- | ------------------------------------------------------------------------ |
| `php kk_googlebase`             | Start or continue export and initiate background process.                |
| `php kk_googlebase restart`     | Restart export and initiate background process.                          |
| `php kk_googlebase debug [sku]` | Run export for first 100 products (or given sku) with debug information. |
| `php kk_googlebase stop`        | Kill processes.                                                          |
 
Process should exit in case if the last page reached. 

## File Structure

* sku (simple)
* name (configurable with - color - size)
* short_description (simple, if empty: configurable)
* description (simple, if empty: configurable)
* category (simple, if empty: configurable)
* category_url (simple, if empty: configurable)
* manufacturer (simple, if empty: configurable)
* ean (simple)
* size (simple)
* color (simple)
* price (magento price logic)
* special_price (magento price logic)
* image_small (simple, if configurable: configurable)
* image_big (simple, if configurable: configurable)
* deeplink (simple, if configurable: configurable)
* delivery_time  (simple)
* shipping_costs_de (simple)
* shipping_costs_at (simple)
* shipping_costs_ch (simple)

See example in `examples/googlebase.csv`

## File Names

 * `filename.csv` - Exported CSV
 * `filename.csv.processing` - Partly exported CSV (currently under process)
 * `filename.csv.last`- Last exported ProductId and statistics in json format
 * `filename.csv.locked`- Lock to block parallel processes. File content is datetime of start
 * `filename.csv.pid`- Id of running process 

## Support

If you have any issues with this extension, 
open an issue on [GitHub](https://github.com/kirchbergerknorr/Kirchbergerknorr_GoogleBase/issues/new).


## Contribution

Any contribution is highly appreciated. The best way to contribute code is 
to open a [pull request on GitHub](https://help.github.com/articles/using-pull-requests).

## Developer

Aleksey Razbakov
[@razbakov](https://twitter.com/razbakov)

License
-------
[OSL - Open Software Licence 3.0](http://opensource.org/licenses/osl-3.0.php)

Copyright
---------
(c) 2015 [kirchbergerknorr.de](https://kirchbergerknorr.de)
