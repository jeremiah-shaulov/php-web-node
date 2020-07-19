# php-web-node
PhpWebNode is PHP-FPM implementation written in PHP that allows to preserve resources (global variables) between requests, so database connections pool is possible. Applications based on this library can be used instead of PHP-FPM. PhpWebNode acts like FastCGI server, to which web server (like Apache) will send HTTP requests. It will spawn child processes that will handle the requests synchronously. Several (up to 'pm.max_children') children will run in parallel, and each child will sequentially process incoming requests, preserving global (and static) variables.

## How fast is php-web-node

It's written in PHP, so i expected that my application will slow down a little comparing to PHP-FPM. I was surprised that the application became a little faster. Actually php-web-node removes need to reinitialize resources, and reconnect to database.

## What's supported

Most of PHP features that i know, except `$_SESSION` are supported. Most existing PHP scripts will work as with PHP-FPM, except principal distinction explained below, in "Step 3. Update PHP scripts".

PhpWebNode implements complete FastCGI protocol, including connection multiplexing, however as far as i know, currently none of popular web servers support this feature. [More info](https://stackoverflow.com/questions/25556168/nginx-fastcgi-uses-management-records-if-not-then-what).

## Installation

Create a directory for your application, `cd` to it, and issue:

```
composer require jeremiah-shaulov/php-web-node
```

## How to use php-web-node

To use php-web-node we need to pass 3 steps:

1. Create master application
2. Set up web server to use our application
3. Update PHP scripts that we want to serve with php-web-node

### Step 1. Create master application

We need a master application that will work as PHP-FPM service. Let's call it server.php:

```php
<?php

require_once 'vendor/autoload.php';
use PhpWebNode\Server;

$server = new Server
(	[	'listen' => '/run/php-web-node/main.sock', // or: '127.0.0.1:10000', '[::1]:10000'
		'listen.owner' => 'www-data',
		'listen.group' => 'johnny',
		'listen.mode' => 0700,
		'listen.backlog' => 0,
		'user' => 'johnny',
		'group' => null,
		'pm.max_requests' => 1000,
		'pm.max_children' => 2,
		'pm.process_idle_timeout' => 30,
		'request_terminate_timeout' => 10,
	]
);
$server->serve();
```

The `Server` constructor takes array with configuration parameters. Their meaning is the same as in PHP-FPM, see [here](https://www.php.net/manual/en/install.fpm.configuration.php). Only parameters shown in the above example are supported.

Change `johnny` to your user name, or create dedicated user to run the application from it.

Now we need to start this script from console.

```
sudo php server.php
```

This script requires superuser rights, because it's going to create 'listen' socket, and it's parent directories. Then it switches user to one specified in the given configuration.

If we want to daemonize this service, we can either implement daemonization in the script, or we can use external software. For example in Ubuntu we can:

```
sudo daemon --name=php-web-node --respawn --stdout=/tmp/php-web-node.log --stderr=/tmp/php-web-node-err.log -- php server.php
```

### Step 2. Set up web server

If you have a web server like Apache or Nginx that is already configured to work with PHP-FPM, you only need to change the socket node name (or port) to that used in our server.php. In the example above it's `/run/php-web-node/main.sock`. Here's the simplest Apache example:

```
<VirtualHost *:80>
	ServerName wntest.com
	DocumentRoot /var/www/wntest.com

	<FilesMatch \.php$>
		SetHandler "proxy:unix:/run/php-web-node/main.sock|fcgi://localhost"
		# or IPv4: SetHandler "proxy:fcgi://127.0.0.1:10000"
		# or IPv6: SetHandler "proxy:fcgi://[::1]:10000"
	</FilesMatch>
</VirtualHost>
```

The DocumentRoot directory must exist, so create `/var/www/wntest.com`, or different directory on your choice, and put some test file in it, like `index.php`. Later you will be able to access it through `ServerName` URL. In example above, it's `wntest.com`, so the full URL will be `http://wntest.com/index.php`, or maybe `http://wntest.com/`. Also include `wntest.com` in your `/etc/hosts` file, like:

```
127.0.0.1	wntest.com
```

### Step 3. Update PHP scripts

There's important difference between PHP-FPM and php-web-node. Let's say we have such `index.php` script:

```php
<?php

if (!isset($n_request)) $n_request = 1;

echo "Request $n_request from ", posix_getpid();
$n_request++;
```

If we'll serve this script with PHP-FPM, it will always show "Request 1", and process Id can change from request to request. From bunch of requests a percent of them will show the same process Id, because each child process (by default) processes many incoming requests.

If we'll serve it with php-web-node, we'll see that `$n_request` is incrementing. Probably you will see the same process Id, because your server can handle all the requests that come when you refresh the page with 1 child process. But there can be up to 'pm.max_children' (2 in the above example) processes, and request counter will increment in each child process independently.

Child process executes `require 'index.php'` each request within the same environment. This puts limitations on what `index.php` can do. For example:

```php
<?php

function get_n_request()
{	static $n_request = 1;
	return $n_request++;
}

echo "Request ", get_n_request(), " from ", posix_getpid();
```

To run this new script, you need to stop the `server.php` application, and rerun it again. This new script does the same thing, but it declares a function in global namespace. Doing `require 'index.php'` second time will give error:

```
PHP Fatal error:  Cannot redeclare get_n_request()
```

There are 2 solutions to this problem.

1. Put all functions and classes to external files, and `require_once` them.
2. Use `PhpWebNode\set_request_handler()`

```php
<?php

require_once 'vendor/autoload.php';

function get_n_request()
{	static $n_request = 1;
	return $n_request++;
}

PhpWebNode\set_request_handler
(	__FILE__,
	function()
	{	echo "Request ", get_n_request(), " from ", posix_getpid();
	}
);
```

If we call `PhpWebNode\set_request_handler()` giving it a filename (usually `__FILE__`), requests to this file will be processed by calling specified callback function, and `require` for this file will not happen.

If you'll execute the above script from PHP-FPM, it will behave the same, because php-web-node determines that its not running from `PhpWebNode\Server` class, and executes the given callback function immediately.

Another important difference between php-web-node and PHP-FPM is that with php-web-node you cannot use built-in functions like `header()`, `setcookie()`, etc. Instead you need to use corresponding functions from `PhpWebNode` namespace: `PhpWebNode\header()`, `PhpWebNode\setcookie()`, etc.

```php
<?php

require_once 'vendor/autoload.php';
use function PhpWebNode\header;

function get_n_request()
{	static $n_request = 1;
	return $n_request++;
}

PhpWebNode\set_request_handler
(	__FILE__,
	function()
	{	header("Expires: 0"); // this is PhpWebNode\header()
		echo "Request ", get_n_request(), " from ", posix_getpid();
	}
);
```

## Understanding the master application

The master application configures and starts the FastCGI server. Also it can perform another operations, but it's important to understand, that no blocking system calls must be executed from it, because blocking calls, like connecting to database, will pause handling incoming HTTP requests. The following master application is alright, it only performs nonblocking operations.

```php
<?php

require_once 'vendor/autoload.php';
use PhpWebNode\Server;

$n_requests = 0;
$requests_time_took = 0.0;

echo "Started\n";

$server = new Server
(	[	'listen' => '/run/php-web-node/main.sock',
		'listen.owner' => 'www-data',
		'listen.group' => 'johnny',
		'listen.mode' => 0700,
		'listen.backlog' => 0,
		'user' => 'johnny',
		'group' => null,
		'pm.max_requests' => 1000,
		'pm.max_children' => 10,
		'pm.process_idle_timeout' => 30,
		'request_terminate_timeout' => 10,
	]
);
$server->onerror
(	function($msg)
	{	echo $msg, "\n";
	}
);
$server->onrequestcomplete
(	function($pool_id, $messages, $time_took) use(&$n_requests, &$requests_time_took)
	{	$n_requests++;
		$requests_time_took += $time_took;
	}
);
$server->set_interval
(	function() use(&$n_requests, &$requests_time_took)
	{	echo "$n_requests requests", ($n_requests==0 ? "" : " (".round($requests_time_took/$n_requests, 3)." sec avg)"), "\n";
		$n_requests = 0;
		$requests_time_took = 0.0;
	},
	6
);
$server->serve();
```

The `$server->serve()` function starts the server main loop, that runs forever, so this function doesn't return or throw exceptions.

This application prints each 6 sec (10 times a minute) how many requests were completed, and average request time. This time is measured since a child process took a request job, and till a complete response was received from the child. Real request time is longer.

## Process pools

As we already mentioned, php-web-node manages child processes just like PHP-FPM, and each child process has it's own persistant resources. An example of resource is a database connection. So a PDO object stored in a global variable will not be reinitialized. If we set 'pm.max_children' to 10, we will get database connections pool with up to 10 slots.

What if we want that half of HTTP requests connect to one database, and a half to another? By default HTTP requests will be directed to random child processes, so each child will sometimes process requests that connect to database A, and sometimes to database B. So we will get 20 persistent connections from 10 child processes to 2 database servers.

PhpWebNode allows us to examine each incoming HTTP request in master application before it's directed to a child process, and the master application can choose to what group of children to direct it. Each group of child processes is called a process pool. We can have as many process pools as we want, and as many child processes in each pool as we want.

By default there's only 1 pool called `''` (empty string). The 'pm.max_children' setting is maximal number of processes in each pool.

To catch and examine incoming HTTP requests we can set `$server->onrequest()` callback.

```php
public function onrequest(callable $onrequest_func=null, int $catch_input_limit=0)
```

`$onrequest_func` callback receives a `Request` object, that has the following fields:

- `$request->server` - the `$_SERVER` of the request.
- `$request->get` - the `$_GET` of the request.
- `$request->post` - the `$_POST` of the request. PhpWebNode will read and buffer up to `$catch_input_limit` bytes of request POST body before calling `$onrequest_func`. If the body is longer, `$request->post` will contain only complete parameters read so far. If Content-Type of the body is not one of `application/x-www-form-urlencoded` or `multipart/form-data`, the `$request->post` will be empty array.
- `$request->input` - the `file_get_contents('php://input')` of the request. If POST body was longer than `$catch_input_limit`, it will be incomplete. If Content-Type was `multipart/form-data`, this will be empty string.
- `$request->input_complete` - true if `$request->input` is the complete POST body.
- `$request->content_type` - lowercased substring of $_SERVER['CONTENT_TYPE'] of the request before first semicolon.

`$onrequest_func` allows you to decide to which pool to forward the incoming HTTP request by returning pool Id or name (string).
```php
<?php

require_once 'vendor/autoload.php';
use PhpWebNode\{Server, Request};

$server = new Server
(	[	'listen' => '/run/php-web-node/main.sock',
		'listen.owner' => 'www-data',
		'listen.group' => 'johnny',
		'listen.mode' => 0700,
		'listen.backlog' => 0,
		'user' => 'johnny',
		'group' => null,
		'pm.max_requests' => 1000,
		'pm.max_children' => 5,
		'pm.process_idle_timeout' => 30,
		'request_terminate_timeout' => 10,
	]
);
$server->onrequest
(	function(Request $request)
	{	$db_id = $request->get['db-id'] ?? null;
		return $db_id=='a' ? 'A' : 'B';
	},
	8*1024
);
$server->serve();
```

It's important to return only fixed number of value alternatives. In the example above we return 2 alternatives: 'A' and 'B', so we will get 2 pools with 5 ('pm.max_children') processes in each pool. If you cease returning some value from `$onrequest_func` callback, the corresponding pool will be eventually freed.


Child process can check it's pool Id by calling `PhpWebNode\get_pool_id()`.

Another thing that `$onrequest_func` callback can do, is throwing exception to cancel the request without forwarding it to a child process.

```php
$server->onrequest
(	function(Request $request)
	{	if (empty($request->get['page-id']))
		{	http_response_code(404);
			PhpWebNode\header('Expires: '.gmdate('r', time()+60*60));
			throw new Exception("Page doesn't exist"); // cancel the request
		}
	}
);
```

## PHP MySQL connections pool implementation

As we saw above, process pool can act like database connections pool.

Please, keep in mind, that there's no way in PHP to reset a MySQL connection using [mysql_reset_connection()](https://dev.mysql.com/doc/refman/8.0/en/mysql-reset-connection.html), at least i'm not aware of such. Therefore in the beginning of each request we'll need to clean up what we can, and we can rollback ongoing transaction if it was not committed by previous request.

Also you need to know that depending on what queries you execute, memory consumption on MySQL end can decline with every query. Eventually this can make MySQL server unresponsive. So there's limit on how many times we can reuse our connection, and we need to reconnect periodically anyway. Even reusing 10 times each connection, will dramatically release network pressure in our system.

In my experiments, with PHP-FPM i saw 2500 open sockets all the time, where almost all of them were in TIME_WAIT state.

```
sudo netstat -putnw | wc -l
```

With php-web-node reusing each connection 10 times, this number reduced to 700.

Example of client script that implements DB connections pool:

```php
<?php

const DB_DSN = 'mysql:host=localhost;dbname=information_schema';
const DB_USER = 'root';
const DB_PASSWORD = 'root';
const RECONNECT_EACH_N_REQUESTS = 10;

function get_pdo()
{	static $pdo = null;
	static $n_request = 0;

	if ($n_request++ % RECONNECT_EACH_N_REQUESTS == 0)
	{	$pdo = new PDO(DB_DSN, DB_USER, DB_PASSWORD);
		$n_request = 1;
	}
	else
	{	// Reset the connection
		$pdo->exec("ROLLBACK");
	}

	return $pdo;
}

PhpWebNode\set_request_handler
(	__FILE__,
	function()
	{	$pdo = get_pdo();
		$cid = $pdo->query("SELECT Connection_id()")->fetchColumn();
		echo "Connection ID = $cid";
	}
);
```

And as usual, in master application we specify pool parameters:

```php
$server = new Server
(	[	'listen' => '/run/php-web-node/main.sock',
		'listen.owner' => 'www-data',
		'listen.group' => 'johnny',
		'listen.mode' => 0700,
		'listen.backlog' => 0,
		'user' => 'johnny',
		'group' => null,
		'pm.max_requests' => 1000,
		'pm.max_children' => 5,
		'pm.process_idle_timeout' => 30,
		'request_terminate_timeout' => 10,
	]
);
```

The pool will have up to `pm.max_children` concurrent database connections. If one of them remains idle for more than `pm.process_idle_timeout` seconds, it will be closed. Each `pm.max_requests` requests child process will be retired, so we could set `pm.max_requests` to 10, and database connection would reconnect each 10 requests, but it's better to set `pm.max_requests` to a big value because this will save CPU spent on stopping child process, and forking it again.
