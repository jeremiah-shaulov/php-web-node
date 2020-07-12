# php-web-node
PhpWebNode is PHP-FPM implementation in PHP that allows to preserve resources (global variables) between requests, so DB connections pool is possible. Applications based on this library can be used instead of PHP-FPM. PhpWebNode acts like FastCGI server, to which web server (like Apache) will send HTTP requests. It will spawn child processes that will handle the requests synchronously. Several (up to 'pm.max_children') children will run in parallel, and each child will sequentially process incoming requests, preserving global (and static) variables.

## How fast is php-web-node

It's written in PHP, so i expected that my application will slow down a little comparing to PHP-FPM. I was surprised that the application became a little faster. Actually php-web-node removes need to reinitialize resources, and reconnect to database.

## How to use php-web-node

To use php-web-node we need to pass 3 steps:

1. Create master application
2. Set up web server to use our application
3. Update PHP scripts that we want to serve with php-web-node

### Step 1. Create master application

We need a master application that will work as PHP-FPM service. Let's call it server.php:

```php
<?php

require_once 'php-web-node/php-web-node.php';
use PhpWebNode\Server;

$server = new Server
(	[	'listen' => '/run/php-web-node/main.sock',
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

Now we need to start this script from console.

```
sudo php server.php
```

This script requires superuser rights, because it's going to create 'listen' socket, and it's parent directories. Then it switches user to one specified in the given configuration.

If we want to daemonize this service, we can either implement daemonization in the script, or we can use external software. For example in Ubuntu we can:

```
daemon --name=php-web-node --respawn --stdout=/tmp/php-web-node.log --stderr=/tmp/php-web-node-err.log -- php server.php
```

### Step 2. Set up web server

If you have a web server like Apache or Nginx that is already configured to work with PHP-FPM, you only need to change the socket node name (or port) to that used in our server.php. In the example above it's `/run/php-web-node/main.sock`. Here's the simplest Apache example:

```
<VirtualHost *:80>
	ServerName johnny.com
	DocumentRoot /var/www/johnny.com

	<FilesMatch \.php$>
		SetHandler "proxy:unix:/run/php-web-node/main.sock|fcgi://localhost"
	</FilesMatch>
</VirtualHost>
```

The DocumentRoot directory must exist.

### Step 3. Update PHP scripts

There's important difference between PHP-FPM and php-web-node. Let's say we have such `hello.php` script:

```php
<?php

if (!isset($n_request)) $n_request = 1;

echo "Request $n_request from ", posix_getpid();
$n_request++;
```

If we'll serve this script with PHP-FPM, it will always show "Request 1", and process Id can change from request to request. From bunch of requests a percent of them will show the same process Id, because each child process (by default) processes many incoming requests.

If we'll serve it with php-web-node, we'll see that `$n_request` is incrementing in each child process independently. If 'pm.max_children' is set to 2, as in example above, there will be up to 2 child processes where each of them preserves it's global variables state.

Child process executes `require 'hello.php'` each request within the same environment. This puts limitations on what `hello.php` can do. For example:

```php
<?php

function get_n_request()
{	static $n_request = 1;
	return $n_request++;
}

echo "Request ", get_n_request(), " from ", posix_getpid();
```

This script does the same, but it declares a function in global namespace. Doing `require 'hello.php'` second time will give error

```
PHP Fatal error:  Cannot redeclare get_n_request()
```

There are 2 solutions to this problem.

1. Put all functions and classes to external files, and `require_once` them.
2. Use `PhpWebNode\set_request_handler()`

```php
<?php

require_once 'php-web-node/php-web-node.php';

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

require_once 'php-web-node/php-web-node.php';
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

The master application configures and starts the FastCGI server. Also it can perform another operations, but it's important to understand, that no blocking system calls must be executed from it, because the blocking calls, like connecting to database, will pause handling incoming HTTP requests. The following master application is alright, it only performs nonblocking operations.

```php
<?php

require_once 'php-web-node/php-web-node.php';
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
(	function($messages, $time_took) use(&$n_requests, &$requests_time_took)
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

require_once 'php-web-node/php-web-node.php';
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
