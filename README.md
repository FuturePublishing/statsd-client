# Introduction #

This is a PHP API for sending stats to [Etsy's statsd PHP client](https://github.com/etsy/statsd/blob/master/examples/php-example.php).

# Installation #

The easiest way to install is to use [Composer](http://getcomposer.org). You can reference it in your project like this:

    {
        "repositories": [
            {
                "type": "vcs",
                "url": "https://github.com/FuturePublishing/statsd-client"
            }
        ],
        "require": {
            "FuturePublishing/statsd-client": ">=2.0.0"
        }
    }

It conforms to the [PSR-0](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-0.md) standard so can be autoloaded easily. (If you use Composer, including its `autoloader.php` will do all the work for you.)

## Requirements ##

* PHP 5.3+
* PHPUnit 3.6+ (for running unit tests)
* php-network (Set of classes for handling network connections, referenced in composer.json)

# Usage #

To set up the client, create a new `\Future\Network\Connection` (for example, `\Future\Network\Connection\SocketConnection`) and pass it into the constructor:

```php
<?php
$connection = new \Future\Network\Connection\SocketConnection('127.0.0.1', 8125);
$client = new \Future\Statsd\Client($connection, true, 'www.mysite.com.');
```

The constructor takes three arguments:
* The connection object
* Whether to enable stats or not (true or false)
* An optional prefix to apply to all stats sent to the Metricsd server.

You can then call the stats methods on the client to send metrics. For example:

```php
<?php
if ($user->save()) {
    $client->increment('registration.success');
} else {
    $client->increment('registration.fail');
}
```

## Sampling ##

All stats methods take an optional parameter which allow you to sample the requests that are sent. By default it has a value of 1 meaning all requests are sent to the Metricsd server. You can give a value of between 0 and 1 to only send a percentage of stats calls instead, so for example a value of 0.2 would mean that roughly 20% of calls made to the stats method would actually be sent to the Metricsd server.