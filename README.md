# Hyperf ORM's SqlServer driver.

[![Latest Stable Version](http://poser.pugx.org/chance-fyi/hyperf-database-sqlserver/v)](https://packagist.org/packages/chance-fyi/hyperf-database-sqlserver)
[![Total Downloads](http://poser.pugx.org/chance-fyi/hyperf-database-sqlserver/downloads)](https://packagist.org/packages/chance-fyi/hyperf-database-sqlserver)
[![License](http://poser.pugx.org/chance-fyi/hyperf-database-sqlserver/license)](https://packagist.org/packages/chance-fyi/hyperf-database-sqlserver)

Extending the SqlServer driver for Hyperf ORM through AOP and simulating coroutines using the Task component.

## Installation

```sh
composer require chance-fyi/hyperf-database-sqlserver
```

## Configuration

Configure a SQL Server connection in `config/autoload/databases.php` as usual.
Every query is dispatched to a task worker (to simulate coroutines over the
blocking PDO/ODBC driver), and that task has an execute timeout.

### Task timeout

By default the task execute timeout is **10 seconds** (the Hyperf Task default).
A heavy query that legitimately runs longer than 10s would otherwise fail with
`Task [N] execute timeout`. Raise it per connection with the `task_timeout`
option (in seconds):

```php
// config/autoload/databases.php
return [
    'sqlsrv' => [
        'driver'       => 'sqlsrv',
        // host / database / username / password / ...
        'task_timeout' => 60, // optional, seconds, default 10
    ],
];
```
