# Php Library Router

A flexible router with multiple options

## Introduction ##

Unlike other routes, this router supports also database driven routes

## Features ##

- Retrieve routes from database, or specify programatically
- Parametrized routes
- Middleware


## Installation (est. 1 min) ##

```php
composer require sinevia/php-library-router
```

Word of warning. Do use a stable package, as "dev-master" is a work in progress.

Create the database table:

```php
$router = (new Plugins/Router);
$router->setDatabase($db);
$router->install();
```

Alternatively create the table manually with the following structure (default table name is snv_actions_action):

Id - integer
Status - string / enum (Active|Inactive)
ActionName - string
Middleware - string
Response - string
Memo - text
CreatedAt - datetime
UpdatedAt - datetime
DeletedAt - datetime


## Uninstall (est. 1 min) ##

Removal of the package is a breeze:

```php
composer require sinevia/php-library-router
```

Optionally, delete the route tables (the default table is snv_actions_action)

## Configuration
$db = new Sinevia\SqlDb(your_database_options);
die((new Plugins\Router())->setDatabase($db)->run()));

## Example Routes

|  Id |  Status  |   ActionName   |    Middleware   |  Response  |    Memo    |
| --- | -------- | -------------- |  -------------- |  --------- | ---------- |
|  1  |  Active  | /              |                 |  home      |  will excute the function home() |
|  2  | Inactive | /hello/:string |  verifyUser,setLastLogin   |  hello     |  will excute the function verifyUser,setLastLogin then hello($name) |
|  3  |  Active  | /admin         |                 |  App\Admin@dashboard     |  will excute the method dashboard from class Admin in namespace App |



## Human Friendly Action Names ##
The following shortcuts can be used to create human friendly routes (actions):

|Shortcut | Regex |
| ------- |-------|
| :any    | ([^/]+) |
| :num    | ([0-9]+) |
| :all    | (.*) |
| :string | ([a-zA-Z]+) |
| :number | ([0-9]+) |
| :numeric | ([0-9-.]+) |
| :alpha' | ([a-zA-Z0-9-_]+) |

Example action name: /article/:num/:string
