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
        {"type": "git", "url": "https://github.com/kirchbergerknorr/Kirchbergerknorr_GoogleBase"},
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

## File Structure

 * `filename.csv` - Exported CSV
 * `filename.csv.processing` - Partly exported CSV (currently under process)
 * `filename.csv.run` - File shows that process is running. File content is amount of found products.
 * `filename.csv.thread` - Log file
 * `filename.csv.last`- Last exported ProductId
 * `filename.csv.locked`- Lock to block parallel processes. File content is datetime of start.

## Maintenance

Process should exit in case if there is no log file or the last page reached.
If you want to kill this process in a system use the following command:

    export pid=`ps aux | grep kk_googlebase | awk 'NR==1{print $2}'`; kill -9 $pid

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
