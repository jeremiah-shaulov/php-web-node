<?php

namespace PhpWebNode;

class Server extends _\Server {}
class Request extends _\Request {}

function php_web_node_version()
{	return '0.1.0';
}

function is_php_web_node()
{	return _\Child::$is_php_web_node;
}

function get_pool_id()
{	return _\Child::$the_pool_id;
}

/**	$for_filename must be absolute path.
 **/
function set_request_handler(string $for_filename, callable $func)
{	if (!_\Child::$is_php_web_node)
	{	$func();
	}
	else
	{	_\Child::$request_handlers[$for_filename] = $func;
	}
}

function send_message($message)
{	if (_\Child::$is_php_web_node)
	{	_\Child::$messages[] = $message;
	}
}

function file_get_contents($filename, $use_include_path=false, $context=null, $offset=0, $maxlen=null)
{	if (_\Child::$is_php_web_node or $filename!='php://input')
	{	return substr(_\Child::$stdin, $offset, $maxlen===null ? 0x7FFFFFFF : $maxlen);
	}
	else if ($maxlen === null)
	{	return \file_get_contents($filename, $use_include_path, $context, $offset);
	}
	else
	{	return \file_get_contents($filename, $use_include_path, $context, $offset, $maxlen);
	}
}

function header($header, $replace=true, $http_response_code=0)
{	if (!_\Child::$is_php_web_node)
	{	\header($header, $replace, $http_response_code);
	}
	else
	{	$header = trim($header);
		$pos = strpos($header, ':');
		$key = strtolower(trim(substr($header, 0, $pos===false ? strlen($header) : $pos)));
		if ($replace)
		{	_\Child::$headers[$key] = [$header];
		}
		else
		{	_\Child::$headers[$key][] = $header;
		}
		if ($http_response_code > 0)
		{	http_response_code($http_response_code);
		}
	}
}

function header_remove($header)
{	if (!_\Child::$is_php_web_node)
	{	\header_remove($header);
	}
	else
	{	$key = strtolower(trim($header));
		unset(_\Child::$headers[$key]);
	}
}

function headers_sent(&$file, &$line)
{	if (!_\Child::$is_php_web_node)
	{	return \headers_sent($file, $line);
	}
	else if (_\Child::$headers === null)
	{	$file = __FILE__; // fake
		$line = __LINE__; // fake
		return true;
	}
	else
	{	$file = '';
		$line = 0;
		return false;
	}
}

function headers_list()
{	if (!_\Child::$is_php_web_node)
	{	return \headers_list();
	}
	else
	{	$list = [];
		foreach (_\Child::$headers as $hh)
		{	foreach ($hh as $h)
			{	$list[] = $h;
			}
		}
		return $list;
	}
}

function header_register_callback(callable $callback=null)
{	if (!_\Child::$is_php_web_node)
	{	return \header_register_callback($callback);
	}
	else
	{	_\Child::$header_callback = $callback;
		return true;
	}
}

function setcookie($name, $value='', $expires=0, $path='', $domain='', $secure=false, $httponly=false)
{	if (!_\Child::$is_php_web_node)
	{	return \setcookie($name, $value, $expires, $path, $domain, $secure, $httponly);
	}
	else
	{	return setrawcookie($name, urlencode($value), $expires, $path, $domain, $secure, $httponly);
	}
}

function setrawcookie($name, $value='', $expires=0, $path='', $domain='', $secure=false, $httponly=false)
{	if (!_\Child::$is_php_web_node)
	{	return \setrawcookie($name, $value, $expires, $path, $domain, $secure, $httponly);
	}
	else
	{	if (strpos($name, '=')!==false or strpos($name, ';')!==false or strpos($value, ';')!==false or strpos($path, ';')!==false or strpos($domain, ';')!==false)
		{	return false;
		}
		$header = "$name=$value";
		if ($expires > 0)
		{	$header .= "; expires=".gmdate('D, d M Y H:i:s T', $expires)."; Max-Age=".max(0, $expires-time());
		}
		if (strlen($path) > 0)
		{	$header .= "; path=$path";
		}
		if (strlen($domain) > 0)
		{	$header .= "; domain=$domain";
		}
		if ($secure)
		{	$header .= "; secure";
		}
		if ($httponly)
		{	$header .= "; HttpOnly";
		}
		header($header, false);
		return true;
	}
}

function is_uploaded_file($filename): bool
{	if (!_\Child::$is_php_web_node)
	{	return \is_uploaded_file($filename);
	}
	else
	{	return _\Child::is_uploaded_file($filename);
	}
}


namespace PhpWebNode\_;

use Throwable, Exception;

const BUFFER_SIZE             = 8*1024;
const TASKS_DEFAULT_EACH      = 10.0; // sec
const FORCE_KILL_ZOMBIE_AFTER = 2.0; // sec
const MAX_PENDING_REQS_FACTOR = 2; // max pending requests will be pm.max_children*MAX_PENDING_REQS_FACTOR, then "503 Server busy" will be returned

const CHILD_STATE_ALIVE       = 0;
const CHILD_STATE_DEAD        = 1;
const CHILD_STATE_TIMEOUT     = 2;
const CHILD_STATE_IDLE        = 3;
const CHILD_STATES            = ['retired', 'dead', 'timeout', 'idle'];

const EINTR = 4;
const EWOULDBLOCK = 11;
const EINPROGRESS = 115;

const FCGI_BEGIN_REQUEST      =  1;
const FCGI_ABORT_REQUEST      =  2;
const FCGI_END_REQUEST        =  3;
const FCGI_PARAMS             =  4;
const FCGI_STDIN              =  5;
const FCGI_STDOUT             =  6;
const FCGI_STDERR             =  7;
const FCGI_DATA               =  8;
const FCGI_GET_VALUES         =  9;
const FCGI_GET_VALUES_RESULT  = 10;
const FCGI_UNKNOWN_TYPE       = 11;

const FCGI_REQUEST_COMPLETE   =  0;
const FCGI_OVERLOADED         =  2;
const FCGI_UNKNOWN_ROLE       =  3;

const FCGI_RESPONDER          =  1;
const FCGI_AUTHORIZER         =  2;
const FCGI_FILTER             =  3;

const FCGI_KEEP_CONN          =  1;

/**	According to: http://www.mit.edu/~yandros/doc/specs/fcgi-spec.html#S1
 **/
class Server
{	// Settings given in constructor
	private $sock_domain, $sock_type, $sock_protocol, $address, $port, $backlog, $pm_max_children, $pm_process_idle_timeout, $pm_max_requests, $listen_owner, $listen_group, $listen_mode, $user, $group, $request_terminate_timeout;

	// Settings set after construction
	private $onerror_func='error_log', $onrequest_func, $onrequestcomplete_func;
	private int $onrequest_catch_input_limit = 0;

	// Other private vars
	private array $server_accepted_socks = []; // I will add elements here when i get socket_accept($server), and when server will close the communication with me, i will delete corresponding elements
	private ChildrenPool $children_pool;
	private array $employed_children = []; // children picked from $children_pool
	private $tasks=[], $next_task_time=0.0;

	public function __construct(array $options=null)
	{	$listen = $options['listen'] ?? '127.0.0.1:10000';
		$is_unix = strpos($listen, '/') !== false;
		$port = 0;
		$sock_domain = AF_UNIX;
		if (!$is_unix)
		{	$sock_domain = AF_INET;
			$pos = strrpos($listen, ':');
			if ($pos>0 and $listen[$pos-1]!=':')
			{	$port = intval(substr($listen, $pos+1));
				if ($listen[0]=='[' and $listen[$pos-1]==']')
				{	$listen = substr($listen, 1, $pos-2); // assume: IPv6 address, like [::1]:10000
					$sock_domain = AF_INET6;
				}
				else
				{	$listen = substr($listen, 0, $pos);
				}
			}
			else if (is_numeric($listen))
			{	$port = intval($listen);
				$listen = '127.0.0.1';
			}
		}

		$this->sock_domain = $sock_domain;
		$this->sock_type = SOCK_STREAM;
		$this->sock_protocol = $is_unix ? 0 : SOL_TCP;
		$this->address = $listen;
		$this->port = $port;
		$this->backlog = max(0, intval($options['listen.backlog'] ?? 0));
		$this->pm_max_children = max(1, intval($options['pm.max_children'] ?? 8));
		$this->pm_process_idle_timeout = max(0.1, conv_units($options['pm.process_idle_timeout'] ?? 30.0, true));
		$this->pm_max_requests = max(0, intval($options['pm.max_requests'] ?? 0));
		$this->listen_owner = $options['listen.owner'] ?? null;
		$this->listen_group = $options['listen.group'] ?? null;
		$this->listen_mode = is_int($options['listen.mode'] ?? null) ? $options['listen.mode'] : base_convert($options['listen.mode'] ?? '770', 8, 10);
		$this->user = $options['user'] ?? null;
		$this->group = $options['group'] ?? null;
		$this->request_terminate_timeout = max(0, conv_units($options['request_terminate_timeout'] ?? 0, true));

		// TODO: fork_call

		$this->children_pool = new ChildrenPool($this->pm_max_children, $this->pm_process_idle_timeout, $this->pm_max_requests);
	}

	/**	If we're going to create a socket node, we need to prepare it's parent directory.
		Need to remove current node, if exists, and need to create all the parent directories, if they don't exist.
		I will always call this function without the argument.
	 **/
	private function prepare_socket_dir($dir=null)
	{	if ($this->sock_domain == AF_UNIX)
		{	if ($dir !== null)
			{	if (!is_dir($dir))
				{	$this->prepare_socket_dir(dirname($dir));
					mkdir($dir);
				}
			}
			else if (file_exists($this->address))
			{	unlink($this->address);
			}
			else
			{	$this->prepare_socket_dir(dirname($this->address));
			}
		}
	}

	/**	If 'user' and/or 'group' settings were provided, i will posix_setuid() and/or posix_setgid().
	 **/
	private function set_user_and_group()
	{	if (strlen($this->user) or strlen($this->group))
		{	if (strlen($this->user) and !is_numeric($this->user))
			{	$info = posix_getpwnam($this->user);
				if (!$info)
				{	throw new Exception("User not found: {$this->user}");
				}
				$this->user = $info['uid'];
			}
			if (strlen($this->group) and !is_numeric($this->group))
			{	$info = posix_getpwnam($this->group);
				if (!$info)
				{	throw new Exception("User group not found: {$this->user}");
				}
				$this->group = $info['uid'];
			}
			if (strlen($this->group))
			{	posix_setgid($this->user);
			}
			if (strlen($this->user))
			{	posix_setuid($this->user);
			}
		}
	}

	/**	Start the FastCGI server. This is what we are here for.
		Typical server app, will only create the instance of this object, and call serve() on it.
		This server works like php-fpm, it manages pool of child processes, where each process synchronously serves several or many incoming HTTP requests.
		Each child process will receive requests to serve *.php scripts. It will include each script once in child lifetime.
		Scripts are expected to implement client application by registering request handler callback function with PhpWebNode\set_request_handler().
		The request callback will be called once per each incoming HTTP request that was directed to current child process.
		Before calling the callback, superglobal variables are filled with information about the request.
		Global variables will be preserved between requests.
		Each child process from pool will keep it's own application state between requests that it handles, and there can be several concurrent child processes.
		Each process can be killed at any time, even in the middle of operation, if an error occures (for example communication with server lost), or if it processes a request for too long.

		The serve() function doesn't throw exceptions.
		The serve() function runs the FastCGI server eternally, and returns only in case of fatal error.
	 **/
	public function serve()
	{	try
		{	// Set error handler, that will not throw exceptions on warning. I will handle errors by checking return value for functions like socket_*()
			$error_reporting = error_reporting();
			set_error_handler
			(	function($errno, $errstr, $file, $line) use ($error_reporting)
				{	if (error_reporting($error_reporting) != 0) // error_reporting returns zero if "@" operator was used
					{	console_log($this->onerror_func, "Error in $file:$line: $errstr");
					}
				},
				$error_reporting
			);

			// Set $server
			$this->prepare_socket_dir();
			if (($server = socket_create($this->sock_domain, $this->sock_type, $this->sock_protocol)) === false)
			{	throw new Exception(socket_strerror(socket_last_error()));
			}
			if (socket_bind($server, $this->address, $this->port) === false)
			{	throw new Exception(socket_strerror(socket_last_error($server)));
			}
			if ($this->sock_domain == AF_UNIX)
			{	if (strlen($this->listen_owner))
				{	chown($this->address, $this->listen_owner);
				}
				if (strlen($this->listen_group))
				{	chgrp($this->address, $this->listen_group);
				}
				chmod($this->address, $this->listen_mode);
			}
			$this->set_user_and_group();
			if (socket_listen($server, $this->backlog) === false)
			{	throw new Exception(socket_strerror(socket_last_error($server)));
			}

			// Set other variables
			$next_children_task_time = 0.0; // when next time to run $this->children_pool->children_task()
			$tasks_each = min(TASKS_DEFAULT_EACH, $this->pm_process_idle_timeout, $this->request_terminate_timeout<=0 ? TASKS_DEFAULT_EACH : $this->request_terminate_timeout);
			$post_max_size = conv_units(ini_get('post_max_size'));

			// Function that will be called to initialize a child process. It will close parent sockets, and free parent resources.
			$this->children_pool->onpreparechild_func = function()
			{	// $server_accepted_socks
				foreach ($this->server_accepted_socks as $accepted)
				{	socket_close($accepted->sock);
				}
				$this->server_accepted_socks = [];

				// $children_pool
				$this->children_pool->prepare_child();

				// $employed_children
				foreach ($this->employed_children as $child)
				{	socket_close($child->sock);
				}
				$this->employed_children = [];

				// Free some more resources
				$this->onerror_func = 'error_log';
				$this->onrequest_func = null;
				$this->onrequestcomplete_func = null;
				$this->tasks = [];
			};

			// Listen to "reload" signal
			pcntl_signal // TODO: fix
			(	SIGUSR1,
				function()
				{	console_log($this->onerror_func, "Asked reload");
					foreach ($this->employed_children as $child)
					{	$child->n_requests = -1;
					}
				}
			);

			// The main loop
			set_time_limit(0);
			while (true)
			{	$time = microtime(true);

				// Select
				$read = [];
				$write = [];
				$except = null;
				foreach ($this->server_accepted_socks as $accepted)
				{	$read[] = $accepted->sock;
					if (strlen($accepted->buffer_write) >= 8)
					{	$write[] = $accepted->sock;
					}
				}
				foreach ($this->employed_children as $child)
				{	if ($child->is_reading_back) // stage 2: reading $child->sock to $child->buffer (to redirect them to $accepted)
					{	$read[] = $child->sock;
					}
					else if (strlen($child->buffer) >= 8) // stage 1: writing $child->buffer (that came from $accepted) to $child->sock
					{	$write[] = $child->sock;
					}
				}
				if (count($this->server_accepted_socks) < $this->pm_max_children*MAX_PENDING_REQS_FACTOR)
				{	$read[] = $server;
				}
				if (socket_select($read, $write, $except, floor(max(0.0, $this->next_task_time-$time)), floor(max(0.0, $this->next_task_time-$time)*1e6)) === false)
				{	if (socket_last_error() != EINTR)
					{	throw new Exception(socket_strerror(socket_last_error()));
					}
					$read = [];
					$write = [];
				}

				// Read
				foreach ($read as $r)
				{	if ($r === $server)
					{	if (($sock = socket_accept($r)) === false)
						{	throw new Exception(socket_strerror(socket_last_error($r)));
						}
						if (socket_set_nonblock($sock) === false)
						{	throw new Exception(socket_strerror(socket_last_error($sock)));
						}
						$this->server_accepted_socks[] = new ServerAcceptedSock($sock);
						$r = $sock;
					}

					// If this socket accepted from the $server, find it in $this->server_accepted_socks
					foreach ($this->server_accepted_socks as $n_accepted => $accepted)
					{	if ($accepted->sock == $r)
						{	// Yes, this socket accepted from the $server
							$chunk = socket_read($r, BUFFER_SIZE);
							if ($chunk === false)
							{	$errno = socket_last_error($r);
								if ($errno==EWOULDBLOCK || $errno==EINPROGRESS)
								{	continue 2;
								}
								console_log($this->onerror_func, "Failed to read from server socket: ".socket_strerror($errno));
								$chunk = '';
							}
							if ($chunk === '')
							{	$this->close_accepted($n_accepted);
								continue 2;
							}
							$accepted->buffer_read .= $chunk;
							// Read records from server, and put them to children
							$offset = 0;
							while (($header = fcgi_get_record_header($accepted->buffer_read, $offset)) !== null)
							{	switch ($header['type'])
								{	case FCGI_BEGIN_REQUEST:
										list('R' => $role, 'F' => $flags) = unpack('nR/CF', $accepted->buffer_read, $offset-$header['padding']-$header['length']);
										if ($flags&FCGI_KEEP_CONN == 0)
										{	$accepted->no_keep_conn = true;
										}
										if ($role != FCGI_RESPONDER)
										{	$accepted->buffer_write .= new FcgiRecordEndRequest($header['request_id'], FCGI_UNKNOWN_ROLE);
										}
										else if (count($accepted->read_request_buffers) > $this->pm_max_children*MAX_PENDING_REQS_FACTOR)
										{	$accepted->buffer_write .= new FcgiRecordEndRequest($header['request_id'], FCGI_OVERLOADED);
											console_log($this->onerror_func, "503 Server busy");
										}
										else
										{	$accepted->read_request_buffers[] = new ReadRequestBuffer($n_accepted, $header['request_id']);
											$accepted->n_mux_children++;
										}
										break;
									case FCGI_ABORT_REQUEST:
										console_log($this->onerror_func, "Request aborted");
										$request_id = $header['request_id'];
										// Find read_request_buffer
										foreach ($accepted->read_request_buffers as $n_buffer => $read_request_buffer) // $read_request_buffer will not exist in $accepted if i rejected this request with FCGI_OVERLOADED
										{	if ($read_request_buffer->request_id == $request_id)
											{	$this->end_request($accepted, $request_id, 500, true);
												array_splice($accepted->read_request_buffers, $n_buffer, 1);
												break 2;
											}
										}
										// Find child
										foreach ($this->employed_children as $child) // $child will not exist in $this->employed_children if i rejected this request with FCGI_OVERLOADED
										{	if ($child->n_accepted==$n_accepted and $child->request_id==$request_id)
											{	$child->is_aborted = true;
												break 2;
											}
										}
										break;
									case FCGI_PARAMS:
										$request_id = $header['request_id'];
										// Find read_request_buffer
										foreach ($accepted->read_request_buffers as $n_buffer => $read_request_buffer) // $read_request_buffer will not exist in $accepted if i rejected this request with FCGI_OVERLOADED
										{	if ($read_request_buffer->request_id == $request_id)
											{	$len = $header['length'];
												$rec_len = 8 + $len + $header['padding'];
												$read_request_buffer->buffer .= substr($accepted->buffer_read, $offset-$rec_len, $rec_len);
												if ($len == 0)
												{	$read_request_buffer->params_written = true;
													$this->add_request_if_complete($accepted, $read_request_buffer, $n_buffer, $write);
												}
												break 2;
											}
										}
										break;
									case FCGI_STDIN:
										$request_id = $header['request_id'];
										// Find read_request_buffer
										foreach ($accepted->read_request_buffers as $n_buffer => $read_request_buffer) // $read_request_buffer will not exist in $accepted if i rejected this request with FCGI_OVERLOADED
										{	if ($read_request_buffer->request_id == $request_id)
											{	$len = $header['length'];
												$read_request_buffer->stdin_len += $len;
												if ($read_request_buffer->stdin_len>$post_max_size and $post_max_size>0)
												{	$read_request_buffer->stdin_len = -1;
												}
												$rec_len = 8 + $len + $header['padding'];
												if ($read_request_buffer->stdin_len != -1)
												{	$read_request_buffer->buffer .= substr($accepted->buffer_read, $offset-$rec_len, $rec_len);
												}
												if ($len == 0)
												{	$read_request_buffer->stdin_written = true;
												}
												$this->add_request_if_complete($accepted, $read_request_buffer, $n_buffer, $write);
												break 2;
											}
										}
										// Find child
										foreach ($this->employed_children as $child) // $child will not exist in $this->employed_children if i rejected this request with FCGI_OVERLOADED
										{	if ($child->n_accepted==$n_accepted and $child->request_id==$request_id)
											{	$len = $header['length'];
												$child->stdin_len += $len;
												if ($child->stdin_len>$post_max_size and $post_max_size>0)
												{	$child->stdin_len = -1;
												}
												$rec_len = 8 + $len + $header['padding'];
												if ($child->stdin_len != -1)
												{	$child->buffer .= substr($accepted->buffer_read, $offset-$rec_len, $rec_len);
												}
												if ($len == 0)
												{	$child->stdin_written = true;
													if ($child->stdin_len == -1)
													{	// stdin cut
														$child->buffer .= pack("C2x6", 1, FCGI_STDIN); // i signal to the child about incomplete FCGI_STDIN by zero request_id in the last FCGI_STDIN packet. see below
													}
												}
												break 2;
											}
										}
										break;
									case FCGI_GET_VALUES:
										$nvp_offset = $offset - $header['padding'] - $header['length'];
										$nvp = new FcgiNvp($accepted->buffer_read, $nvp_offset, $header['length']);
										if (isset($nvp->params['FCGI_MAX_CONNS']))
										{	$nvp->params['FCGI_MAX_CONNS'] = 1;
										}
										if (isset($nvp->params['FCGI_MAX_REQS']))
										{	$nvp->params['FCGI_MAX_REQS'] = $this->pm_max_children;
										}
										if (isset($nvp->params['FCGI_MPXS_CONNS']))
										{	$nvp->params['FCGI_MPXS_CONNS'] = 1;
										}
										$accepted->buffer_write .= new FcgiRecordGetValuesResult($nvp);
										$nvp = null; // free memory
										break;
									default:
										$accepted->buffer_write .= new FcgiRecordUnknownType($header['type']);
								}
							}
							if ($offset > 0)
							{	$accepted->buffer_read = substr($accepted->buffer_read, $offset);
							}
							continue 2;
						}
					}

					// This socket belongs to a child (this is not a socket accepted from $server). So find the child
					foreach ($this->employed_children as $n_child => $child)
					{	if ($child->sock == $r)
						{	break;
						}
					}
					assert($child and $child->sock==$r);
					assert(isset($this->server_accepted_socks[$child->n_accepted]));
					$accepted = $this->server_accepted_socks[$child->n_accepted];
					$chunk = socket_read($r, BUFFER_SIZE);

					if ($chunk===false or $chunk==='')
					{	console_log($this->onerror_func, $chunk===false ? "Failed to read from child socket: ".socket_strerror(socket_last_error($r)) : "Unexpected end of stream, while reading from child socket");
						$this->recycle_child($n_child, CHILD_STATE_DEAD);
						continue;
					}

					$child->buffer .= $chunk;

					$offset = 0;
					while (($header = fcgi_get_record_header($child->buffer, $offset)))
					{	assert($header['request_id'] === $child->request_id);
						if ($header['type'] == FCGI_END_REQUEST)
						{	if (!$child->is_aborted)
							{	// add all records
								$accepted->buffer_write .= $child->buffer;
							}
							else
							{	// add only the last FCGI_END_REQUEST record
								$len = 8 + $header['length'] + $header['padding'];
								$accepted->buffer_write .= substr($child->buffer, $offset-$len, $len);
							}
							$this->recycle_child($n_child, CHILD_STATE_ALIVE);
							continue 2;
						}
						else if ($header['type'] == FCGI_STDERR)
						{	// i exploit FCGI_STDERR to pass messages from child to parent. i will cut this record, so it will not be directed to server
							$len = 8 + $header['length'] + $header['padding'];
							$child->stderr_buffer .= substr($child->buffer, $offset-$len+8, $header['length']);
							$accepted->buffer_write .= substr($child->buffer, 0, $offset-$len);
							$child->buffer = substr($child->buffer, $offset);
							$offset = 0;
						}
						else
						{	assert($header['type'] == FCGI_STDOUT);
							$child->read_one_stdout_packet = true;
						}
					}
					if ($offset > 0)
					{	if (!$child->is_aborted)
						{	$accepted->buffer_write .= substr($child->buffer, 0, $offset);
						}
						$child->buffer = substr($child->buffer, $offset);
					}
				}

				// Write
				foreach ($write as $w)
				{	// If this socket accepted from the $server, find it in $this->server_accepted_socks
					foreach ($this->server_accepted_socks as $n_accepted => $accepted)
					{	if ($accepted->sock == $w)
						{	// Yes, this socket accepted from the $server
							$n = socket_write($w, $accepted->buffer_write);
							if ($n === false)
							{	console_log($this->onerror_func, "Failed to write to server socket: ".socket_strerror(socket_last_error($w)));
								$this->close_accepted($n_accepted);
							}
							else
							{	$accepted->buffer_write = substr($accepted->buffer_write, $n);
								if (strlen($accepted->buffer_write)==0 and $accepted->no_keep_conn and $accepted->n_mux_children===0)
								{	$this->close_accepted($n_accepted);
								}
							}
							continue 2;
						}
					}

					// This socket belongs to a child (this is not a socket accepted from $server). So find the child
					foreach ($this->employed_children as $n_child => $child)
					{	if ($child->sock == $w)
						{	break;
						}
					}
					assert($child and $child->sock==$w);
					$n = socket_write($w, $child->buffer);
					if ($n === false)
					{	$errno = socket_last_error($w);
						if ($errno==EWOULDBLOCK || $errno==EINPROGRESS)
						{	continue; // when i pick a new child, i do "$write[] = $child->sock;" (see above)
						}
						console_log($this->onerror_func, "Failed to write to child socket: ".socket_strerror($errno));
						$this->recycle_child($n_child, CHILD_STATE_DEAD);
						continue;
					}
					$child->buffer = substr($child->buffer, $n);
					if ($child->stdin_written and strlen($child->buffer)==0)
					{	$child->is_reading_back = true;
					}
				}

				// Tasks
				if ($time >= $this->next_task_time)
				{	if ($time >= $next_children_task_time)
					{	$this->children_pool->children_task($time);
						$next_children_task_time = $time + $tasks_each;
						// Kill stuck children
						if ($this->request_terminate_timeout > 0)
						{	while (true)
							{	foreach ($this->employed_children as $n_child => $child)
								{	if ($time - $child->since > $this->request_terminate_timeout)
									{	$this->recycle_child($n_child, CHILD_STATE_TIMEOUT);
										console_log($this->onerror_func, "Killed child process {$child->pid} ".($child->pool_id ? "({$child->pool_id}) " : "")."that took longer than {$this->request_terminate_timeout} sec");
										continue 2;
									}
								}
								break;
							}
						}
					}
					$this->next_task_time = $next_children_task_time;
					for ($i=0; $i<count($this->tasks); $i++)
					{	[$task_callback_func, $task_at, $task_interval] = $this->tasks[$i];
						if ($time >= $task_at)
						{	try
							{	$task_callback_func();
							}
							catch (Throwable $e)
							{	console_log($this->onerror_func, "Error in task", $e);
							}
							if ($task_interval <= 0)
							{	array_splice($this->tasks, $i--, 1);
								continue;
							}
							else
							{	$task_at += $task_interval;
								$this->tasks[$i][1] = $task_at;
							}
						}
						if ($task_at < $this->next_task_time)
						{	$this->next_task_time = $task_at;
						}
					}
				}
			}

			socket_close($server);
		}
		catch (Throwable $e)
		{	console_log($this->onerror_func, "Server failed", $e);
			return false;
		}
		finally
		{	while ($this->employed_children)
			{	$this->recycle_child(0, CHILD_STATE_DEAD);
			}
			$this->children_pool->children_task($time, true);
			pcntl_signal(SIGUSR1, SIG_DFL);
			restore_error_handler();
		}
		return true;
	}

	/**	Set callback function that will be called to log an error message.
		This function will get 1 string parameter.
		By default error_log() is used.
		This function will be called both from parent process, and from children.
		This function must not change error handler (set_error_handler()). If you load some library from within this function, make sure it doesn't do so.
		This function must not perform blocking operations, because they will freeze handling of HTTP requests.
	 **/
	public function onerror(callable $onerror_func)
	{	$this->onerror_func = $onerror_func;
		$this->children_pool->onerror_func = $onerror_func;
	}

	/**	Set callback function that will be called on child process to convert $_SERVER['SCRIPT_FILENAME'] reported by FastCGI server to file path.
		If you don't set this callback, i will convert by finding first occurance of $_SERVER['DOCUMENT_ROOT'], and cutting off what precedes it.
	 **/
	public function onresolvepath(callable $onresolvepath_func=null)
	{	$this->children_pool->onresolvepath_func = $onresolvepath_func;
	}

	/**	Set callback function that will be called on parent process when a new HTTP request arrives from server, before it was directed to a child process.
		This function will get 1 parameter which will be PhpWebNode\Request object that contains headers forwarded from server, but dousn't contain POST body.
		This function can decide which pool of processes to use by returning pool name, that can be any string.
		By default there is onlt 1 pool.
		Number of pools is limited by available resources in system.
		Each pool can have up to pm.max_children child processes.
		If you want to limit number of child processes to 8 divided to 2 pools, you need to set pm.max_children to 4, and always return from this function 1 of 2 possible string values, for example "Main" and "Secondary".
		If you cease returning some value, it's pool will be eventually freed when requests complete and pm.process_idle_timeout passes.
		Dividing to pools is reasonable if you know that different requests will use different resources (for example, will connect to different database servers), so processes in each pool will have different resource, and not both resources.
		The PhpWebNode\Request object has $request->server array, which is equivalent to $_SERVER in child process, and $request->get array ($_GET).
		You can cancel this request by throwing exception, so this request will not be forwarded to child process.
		Before throwing exception you can call functions like http_response_code(), PhpWebNode\header(), etc., to set headers of the cancelled request.
		Child process can get it's pool name by calling PhpWebNode\get_pool_id().
		This function must not change error handler (set_error_handler()). If you load some library from within this function, make sure it doesn't do so.
		This function must not perform blocking operations, because they will freeze handling of HTTP requests.
	 **/
	public function onrequest(callable $onrequest_func=null, int $catch_input_limit=0)
	{	$this->onrequest_func = $onrequest_func;
		$this->onrequest_catch_input_limit = $onrequest_func===null || $catch_input_limit<=0 ? 0 : $catch_input_limit;
	}

	/**	Set callback function that will be called once for each completed HTTP request.
		This function will get 3 parameters: string $pool_id, array $messages, float $time_took.
		The $messages is array of values sent from child through PhpWebNode\send_message($message).
		The $time_took is how much time this request took to process in seconds.
		This function must not change error handler (set_error_handler()). If you load some library from within this function, make sure it doesn't do so.
		This function must not perform blocking operations, because they will freeze handling of HTTP requests.
	 **/
	public function onrequestcomplete(callable $onrequestcomplete_func=null)
	{	$this->onrequestcomplete_func = $onrequestcomplete_func;
	}

	/**	Set callback function that will be called when a new child process is spawned.
		Function gets 2 arguments: string $pool_id, int $process_id.
		This function must not change error handler (set_error_handler()). If you load some library from within this function, make sure it doesn't do so.
		This function must not perform blocking operations, because they will freeze handling of HTTP requests.
	 **/
	public function onchildstart(callable $onchildstart_func=null)
	{	$this->children_pool->onchildstart_func = $onchildstart_func;
	}

	/**	Set callback function that will be called when a child process terminates.
		Function gets 5 arguments: string $pool_id, int $process_id, string $reason, int $n_requests, float $since.
		The $reason can be: 'idle' (the child was not used for pm.process_idle_timeout), 'retired' (child has served pm.max_requests), 'dead' (error in communication with child), 'timeout' (request is working already for request_terminate_timeout seconds or more, and i'm going to kill this process).
		The $n_requests is number of requests served by this child.
		The $since is timestamp (microtime(true)) when current operation started. For 'idle' - since whence it's idle, for 'timeout' - since whence the current request is running, for 'retired' and 'dead' this value is not specified.
		This function must not change error handler (set_error_handler()). If you load some library from within this function, make sure it doesn't do so.
		This function must not perform blocking operations, because they will freeze handling of HTTP requests.
	 **/
	public function onchildend(callable $onchildend_func=null)
	{	$this->children_pool->onchildend_func = $onchildend_func;
	}

	/**	Schedule a callback to be called once after specified $delay.
		The $delay is in seconds, and can be fractional.
		The callback must not perform blocking operations, because they will freeze handling of HTTP requests.
		It's possible to call set_timeout() recursively.
	 **/
	public function set_timeout(callable $callback_func, float $delay)
	{	$at = microtime(true) + $delay;
		$this->tasks[] = [$callback_func, $at, 0];
		$this->next_task_time = min($this->next_task_time, $at);
	}

	/**	Schedule a callback to be called every specified $period.
		The $period is in seconds, and can be fractional.
		The callback must not perform blocking operations, because they will freeze handling of HTTP requests.
	 **/
	public function set_interval(callable $callback_func, float $period)
	{	$at = microtime(true) + $period;
		$this->tasks[] = [$callback_func, $at, $period];
		$this->next_task_time = min($this->next_task_time, $at);
	}

	private function add_request_if_complete(ServerAcceptedSock $accepted, ReadRequestBuffer $read_request_buffer, int $n_buffer, &$write)
	{	assert($accepted->read_request_buffers[$n_buffer] === $read_request_buffer);
		if ($read_request_buffer->params_written and $read_request_buffer->stdin_written || $read_request_buffer->stdin_len>=$this->onrequest_catch_input_limit and !$read_request_buffer->is_added)
		{	if ($this->onrequest_func !== null)
			{	$request = new \PhpWebNode\Request($read_request_buffer->buffer, $read_request_buffer->stdin_written);
				try
				{	// Prepare environment for function calls like http_response_code(), PhpWebNode\header(), etc.
					http_response_code(500);
					Child::$headers = [];
					$read_request_buffer->pool_id = (string)call_user_func($this->onrequest_func, $request);
				}
				catch (Throwable $e)
				{	// $onrequest_func can throw exception to cancel this request
					$accepted->buffer_write .= fcgi_pack_data(FCGI_STDOUT, $read_request_buffer->request_id, Child::get_headers_str().$e->getMessage()) . new FcgiRecordEndRequest($read_request_buffer->request_id, FCGI_REQUEST_COMPLETE);
					$accepted->n_mux_children--;
					array_splice($accepted->read_request_buffers, $n_buffer, 1);
					console_log($this->onerror_func, "Request cancelled: {$request->server['REQUEST_METHOD']} {$request->server['REQUEST_URI']}");
					return;
				}
			}
			$child = $this->children_pool->add_request($read_request_buffer);
			if ($child === null)
			{	$read_request_buffer->is_added = true;
				return;
			}
			if ($child === false)
			{	$this->end_request($accepted, $read_request_buffer->request_id, 503, false);
				array_splice($accepted->read_request_buffers, $n_buffer, 1);
				console_log($this->onerror_func, "503 Server busy");
			}
			else
			{	$this->employed_children[] = $child;
				$write[] = $child->sock;
				array_splice($accepted->read_request_buffers, $n_buffer, 1);
			}
		}
	}

	private function end_request(ServerAcceptedSock $accepted, int $request_id, int $status, bool $read_one_stdout_packet)
	{	if (!$read_one_stdout_packet)
		{	http_response_code($status);
			Child::$headers = [];
			$default_message = "";
			// TODO: custom handler
			$accepted->buffer_write .= fcgi_pack_data(FCGI_STDOUT, $request_id, Child::get_headers_str().$default_message);
		}
		$accepted->buffer_write .= new FcgiRecordEndRequest($request_id, $status==503 ? FCGI_OVERLOADED : FCGI_REQUEST_COMPLETE);
		$accepted->n_mux_children--;
	}

	private function recycle_child(int $n_child, int $child_state)
	{	$child = $this->employed_children[$n_child];
		$accepted = $this->server_accepted_socks[$child->n_accepted];
		if ($child_state != CHILD_STATE_ALIVE)
		{	$this->end_request($accepted, $child->request_id, 500, $child->read_one_stdout_packet);
		}
		else
		{	$accepted->n_mux_children--;
		}
		if ($this->onrequestcomplete_func!==null and $child_state==CHILD_STATE_ALIVE)
		{	try
			{	call_user_func($this->onrequestcomplete_func, $child->pool_id, unserialize($child->stderr_buffer), microtime(true)-$child->since);
			}
			catch (Throwable $e)
			{	console_log($this->onerror_func, "Error in onrequestcomplete function", $e);
			}
		}
		$child->stderr_buffer = '';
		$read_request_buffer = $this->children_pool->recycle($child, $child_state);
		if ($read_request_buffer !== null) // if child reemployed, we need to remove its temporary $read_request_buffer
		{	foreach ($this->server_accepted_socks as $accepted)
			{	$n_buffer = array_search($read_request_buffer, $accepted->read_request_buffers);
				if ($n_buffer !== false)
				{	array_splice($accepted->read_request_buffers, $n_buffer, 1);
					break;
				}
			}
		}
		else
		{	array_splice($this->employed_children, $n_child, 1);
		}
	}

	private function close_accepted($n_accepted)
	{	for ($n_child=count($this->employed_children)-1; $n_child>=0; $n_child--)
		{	$child = $this->employed_children[$n_child];
			if ($child->n_accepted >= $n_accepted)
			{	if ($child->n_accepted == $n_accepted)
				{	$this->recycle_child($n_child, CHILD_STATE_DEAD);
				}
				else
				{	$child->n_accepted--;
				}
			}
		}
		assert($this->server_accepted_socks[$n_accepted]->n_mux_children === 0);
		socket_close($this->server_accepted_socks[$n_accepted]->sock);
		array_splice($this->server_accepted_socks, $n_accepted, 1);
		for ($i=count($this->server_accepted_socks)-1; $i>=$n_accepted; $i--)
		{	foreach ($this->server_accepted_socks[$i]->read_request_buffers as $read_request_buffer)
			{	$read_request_buffer->n_accepted--;
				assert($read_request_buffer->n_accepted == $i);
			}
		}
	}
}

class ServerAcceptedSock
{	public $sock = null;
	public string $buffer_read = ''; // data read from $sock
	public string $buffer_write = ''; // data queued to be written to $sock. i will add only whole FCGI records, but data will be sent to the socket with no respect to record boundaries, and sent data will be removed from the beginning of the buffer
	public int $n_mux_children = 0; // how many children this connection is multiplexing. those children will have corresponding $child->n_accepted
	public bool $no_keep_conn = false; // if true, the server wants me to close the connection after sending the $buffer_write
	public array $read_request_buffers = [];

	public function __construct($sock)
	{	$this->sock = $sock;
	}
}

class ReadRequestBuffer
{	public int $n_accepted;
	public int $request_id;
	public string $buffer = '';
	public int $stdin_len = 0;
	public bool $params_written = false;
	public bool $stdin_written = false;
	public bool $is_added = false;
	public string $pool_id = '';

	public function __construct(int $n_accepted, int $request_id)
	{	$this->n_accepted = $n_accepted;
		$this->request_id = $request_id;
	}
}

class ChildrenPool
{	private array $pools = []; // array of Child - idle children
	private array $n_children = []; // array of int - how many children there are running in each pool (some of them are present in $pools, and some are taken to serve requests)
	private array $zombies = []; // array of Child - when a child is idle, retired or dead, i socket_close() it (and SIGINT it if it's dead), and put it here. $n_children will be decremented when i reap or SIGKILL a zombie from reap()
	private array $pending_requests = []; // array of ReadRequestBuffer - if pool empty, i will put requests here, and pick them when a child returns back to pool
	private int $pm_max_children;
	private float $pm_process_idle_timeout;
	private int $pm_max_requests;
	public $onerror_func='error_log', $onresolvepath_func, $onpreparechild_func, $onchildstart_func, $onchildend_func;

	public function __construct(int $pm_max_children, float $pm_process_idle_timeout, int $pm_max_requests)
	{	$this->pm_max_children = $pm_max_children;
		$this->pm_process_idle_timeout = $pm_process_idle_timeout;
		$this->pm_max_requests = $pm_max_requests;
	}

	/**	When a child process starts, free parent resources.
	 **/
	public function prepare_child()
	{	foreach ($this->pools as $pool)
		{	foreach ($pool as $child)
			{	socket_close($child->sock);
			}
		}
		$this->pools = [];
		$this->n_children = [];
		$this->zombies = [];
	}

	public function add_request(ReadRequestBuffer $read_request_buffer)
	{	$pool_id = $read_request_buffer->pool_id;
		if (!isset($this->pools[$pool_id]))
		{	// Pool is empty
			$this->pools[$pool_id] = [];
			$this->n_children[$pool_id] = 0;
			$this->pending_requests[$pool_id] = [];
			$child = null;
		}
		else
		{	$child = array_pop($this->pools[$pool_id]);
		}
		if ($child === null)
		{	if ($this->n_children[$pool_id] < $this->pm_max_children)
			{	$child = new Child($pool_id, $this->onpreparechild_func, $this->onerror_func, $this->onresolvepath_func);
				$this->n_children[$pool_id]++;
			}
			else
			{	foreach ($this->zombies as $i => $zombie_child)
				{	if ($zombie_child->pool_id == $pool_id)
					{	posix_kill($zombie_child->pid, SIGKILL);
						array_splice($this->zombies, $i, 1);
						$child = new Child($pool_id, $this->onpreparechild_func, $this->onerror_func, $this->onresolvepath_func);
						break;
					}
				}
			}
			if ($this->onchildstart_func!==null and $child!==null)
			{	try
				{	call_user_func($this->onchildstart_func, $pool_id, $child->pid);
				}
				catch (Throwable $e)
				{	console_log($this->onerror_func, "Error in onchildstart function", $e);
				}
			}
		}
		if ($child !== null)
		{	$child->employ($read_request_buffer);
		}
		else if (count($this->pending_requests[$pool_id]) < $this->pm_max_children*MAX_PENDING_REQS_FACTOR)
		{	$this->pending_requests[$pool_id][] = $read_request_buffer;
		}
		else
		{	return false;
		}
		return $child;
	}

	public function recycle(Child $child=null, int $child_state): ?ReadRequestBuffer
	{	$child->buffer = '';
		$child->stdin_written = false;
		$child->is_reading_back = false;
		$child->read_one_stdout_packet = false;
		$child->is_aborted = false;
		$child->since = microtime(true);
		$child->n_requests++;
		if ($child_state==CHILD_STATE_ALIVE and $child->n_requests!=0 and $child->n_requests<$this->pm_max_requests || $this->pm_max_requests==0) // $child->n_requests==-1 means "asked reload service"
		{	$pending = array_shift($this->pending_requests[$child->pool_id]);
			if ($pending !== null)
			{	$child->employ($pending);
				return $pending;
			}
			else
			{	$this->pools[$child->pool_id][] = $child;
			}
		}
		else
		{	socket_close($child->sock);
			$this->zombies[] = $child;
			if ($this->onchildend_func !== null)
			{	try
				{	call_user_func($this->onchildend_func, $child->pool_id, $child->pid, CHILD_STATES[$child_state], $child->n_requests, $child->since);
				}
				catch (Throwable $e)
				{	console_log($this->onerror_func, "Error in onchildend function: ", $e);
				}
			}
			if ($child_state != CHILD_STATE_ALIVE)
			{	posix_kill($child->pid, SIGINT);
			}
		}
		return null;
	}

	public function children_task(float $time, bool $force_kill_all=false)
	{	$this->reap($time, $force_kill_all);

		// Stop idle children
		foreach ($this->pools as &$pool)
		{	for ($i=count($pool)-1; $i>=0; $i--)
			{	$child = $pool[$i];
				if ($time-$child->since >= $this->pm_process_idle_timeout or $force_kill_all)
				{	socket_close($child->sock);
					array_splice($pool, $i, 1);
					$child->since = microtime(true);
					$this->zombies[] = $child;
					if ($this->onchildend_func !== null)
					{	try
						{	call_user_func($this->onchildend_func, $child->pool_id, $child->pid, CHILD_STATES[CHILD_STATE_IDLE], $child->n_requests, $child->since);
						}
						catch (Throwable $e)
						{	console_log($this->onerror_func, "Error in onchildend function: ", $e);
						}
					}
				}
			}
		}
	}

	/**	Find what zombies (child processes that i closed communication with them) exited, and kill bad zombies (that didn't exit for too long).
	 **/
	private function reap(float $time, bool $force_kill_all=false)
	{	// Reap
		while ($this->zombies)
		{	$pid = pcntl_waitpid(0, $status, WNOHANG);
			if ($pid <= 0)
			{	break;
			}
			foreach ($this->zombies as $i => $child)
			{	if ($child->pid == $pid)
				{	array_splice($this->zombies, $i, 1);
					$this->dec_n_children($child->pool_id);
					if ($status != 0)
					{	console_log($this->onerror_func, "Child {$child->pid} exited with status $status");
					}
					break;
				}
			}
		}

		// Kill bad zombies
		for ($i=count($this->zombies)-1; $i>=0; $i--)
		{	$child = $this->zombies[$i];
			if ($time-$child->since >= FORCE_KILL_ZOMBIE_AFTER or $force_kill_all)
			{	posix_kill($child->pid, SIGKILL);
				array_splice($this->zombies, $i, 1);
				$this->dec_n_children($child->pool_id);
			}
		}
	}

	private function dec_n_children($pool_id)
	{	if (--$this->n_children[$pool_id] == 0)
		{	assert(!$this->pools[$pool_id]);
			unset($this->n_children[$pool_id]);
			unset($this->pools[$pool_id]);
			unset($this->pending_requests[$pool_id]);
		}
	}
}

class Child
{	// all the nonstatic fields are used by parent process only
	public int $pid; // the process id of this spawned child
	public $sock; // parent process will communicate with this child through this socket
	public float $since = 0.0; // i set this to microtime(true) when this child begins handling a request, and when this child finished the request and became idle or zombie
	public int $n_requests = 0; // how many requests did this child handle till now
	public string $pool_id = ''; // what $onrequest_func() in parent process returned
	public int $request_id = 0; // request_id field from FCGI header for current request
	public int $n_accepted = -1; // index of this child in $server_accepted_socks array
	public string $buffer = ''; // when !$is_reading_back, this is used to store data scheduled to be written to $sock. it can contain 2 types of records: FCGI_PARAMS and FCGI_STDIN. when $is_reading_back, this stores what is read from $sock. this can contain 3 types of records: FCGI_STDOUT, FCGI_STDERR and FCGI_END_REQUEST. the FCGI_STDERR will be filtered out to $stderr_buffer
	public string $stderr_buffer = ''; // FCGI_STDERR records read from child. they are used to send messages from child to parent
	public bool $is_aborted = false; // is set when FCGI server sends FCGI_ABORT_REQUEST. if this is set, the stdout must not be sent back to the server
	public bool $stdin_written = false; // is set when both FCGI_PARAMS and FCGI_STDIN records are read from the server to $buffer
	public int $stdin_len = 0;
	public bool $is_reading_back = false; // is set when $stdin_written and strlen($buffer)==0, meaning that the whole request is written to $sock, and now we are reading the response back from $sock to $buffer
	public bool $read_one_stdout_packet = false; // at least 1 FCGI_STDOUT packet is read back from child process

	// all the static fields are used by child process only
	public static bool $is_php_web_node = false;
	public static string $the_pool_id = '';
	public static array $request_handlers = [];
	public static array $messages = [];
	public static $headers = [];
	public static $header_callback = null;
	public static $stdin = '';
	private static $uploaded_files = [];

	public function __construct(string $pool_id, callable $onpreparechild_func, callable $onerror_func, callable $onresolvepath_func=null)
	{	$sockets = [];
		if (socket_create_pair(AF_UNIX, SOCK_STREAM, 0, $sockets) === false)
		{	throw new Exception("socket_create_pair() error: ".socket_strerror(socket_last_error()));
		}
		$child_pid = pcntl_fork();
		if ($child_pid == -1)
		{	throw new Exception("pcntl_fork() failed");
		}
		if ($child_pid != 0)
		{	// Parent
			$this->pid = $child_pid;
			$this->sock = $sockets[0];
			$this->pool_id = $pool_id;
			socket_close($sockets[1]);
			if (socket_set_nonblock($this->sock) === false)
			{	throw new Exception(socket_strerror(socket_last_error($this->sock)));
			}
		}
		else
		{	// Child
			restore_error_handler(); // i installed a new handler in parent process, so restore user's one
			socket_close($sockets[0]);
			$onpreparechild_func();
			try
			{	self::$the_pool_id = $pool_id;
				self::$is_php_web_node = true;
				self::process_sequential_requests_till_socket_closes($sockets[1], $onerror_func, $onresolvepath_func);
			}
			catch (Throwable $e)
			{	console_log($onerror_func, "Exception in child process", $e);
			}
			socket_close($sockets[1]);
			exit;
		}
	}

	public function employ(ReadRequestBuffer $read_request_buffer)
	{	$this->n_accepted = $read_request_buffer->n_accepted;
		$this->request_id = $read_request_buffer->request_id;
		$this->buffer = $read_request_buffer->buffer;
		$this->stdin_written = $read_request_buffer->stdin_written;
		$this->stdin_len = $read_request_buffer->stdin_len;
		$this->since = microtime(true);
	}

	public static function is_uploaded_file($filename): bool
	{	return in_array($filename, self::$uploaded_files);
	}

	private static function get_gzip_ctx()
	{	if (!isset(self::$headers['content-encoding'])) // if user script doesn't compress manually
		{	$want_comp = ini_get('zlib.output_compression');
			if (strcasecmp($want_comp, 'On')==0 or $want_comp>0) // if we want compression
			{	$agent_wants_enc = array_map('trim', explode(',', $_SERVER['HTTP_ACCEPT_ENCODING'] ?? ''));
				if (in_array('gzip', $agent_wants_enc)) // if agent wants gzip
				{	self::$headers['content-encoding'][] = 'Content-Encoding: gzip';
					self::$headers['vary'][] = 'Vary: Accept-Encoding';
					return deflate_init(ZLIB_ENCODING_GZIP);
				}
				else if (in_array('deflate', $agent_wants_enc)) // if agent wants deflate
				{	self::$headers['content-encoding'][] = 'Content-Encoding: deflate';
					self::$headers['vary'][] = 'Vary: Accept-Encoding';
					return deflate_init(ZLIB_ENCODING_DEFLATE);
				}
			}
		}
	}

	public static function get_headers_str(): string
	{	$str = '';
		if (self::$header_callback !== null)
		{	call_user_func(self::$header_callback);
		}
		if (isset(self::$headers['status']))
		{	$str .= self::$headers['status'][0]."\r\n";
			unset(self::$headers['status']);
		}
		else
		{	$code = http_response_code();
			if (isset(self::$headers['location']) and $code!=301 and $code!=302 and $code!=303 and $code!=307 and $code!=308)
			{	$code = 302;
			}
			$str .= "Status: $code\r\n";
		}
		if (!isset(self::$headers['content-type']))
		{	self::$headers['content-type'][] = "Content-Type: text/html";
		}
		foreach (self::$headers as $hh)
		{	foreach ($hh as $h)
			{	$str .= "$h\r\n";
			}
		}
		return "$str\r\n";
	}

	protected static function process_sequential_requests_till_socket_closes($sock, $onerror_func, $onresolvepath_func)
	{	$request_order = ini_get('request_order');
		$request_id = 0;
		$stdout = '';
		$buffer_write = ''; // packeted $stdout
		$ob_done = false;
		$gzip_ctx = null;

		$ob_handler = function($buffer) use($sock, &$request_id, &$stdout, &$buffer_write, &$ob_done, &$gzip_ctx)
		{	if (self::$headers !== null)
			{	$gzip_ctx = self::get_gzip_ctx();
				$stdout .= self::get_headers_str();
				self::$headers = null; // null means headers sent
			}
			$stdout .= !$gzip_ctx ? $buffer : deflate_add($gzip_ctx, $buffer, ZLIB_NO_FLUSH);
			$length = strlen($stdout);
			if ($length > 0)
			{	while ($length>=0xFFF8 or strlen($buffer_write)==0)
				{	if ($length > 0xFFF8) // 0xFFF9 .. 0xFFFF will be padded to 0x10000
					{	$buffer_write .= pack("C2n2x2a65528", 1, FCGI_STDOUT, $request_id, 0xFFF8, $stdout); // assume: 0xFFF8 == 65528
						$stdout = substr($stdout, 0xFFF8);
						$length -= 0xFFF8;
					}
					else
					{	$padding = (8 - $length%8) % 8;
						$full_length = $length + $padding;
						$buffer_write .= pack("C2n2Cxa$full_length", 1, FCGI_STDOUT, $request_id, $length, $padding, $stdout);
						$stdout = '';
						break;
					}
				}
			}
			if (strlen($buffer_write)>0 and !$ob_done) // if $ob_done, i don't want to flush for now. i will append a little more data
			{	do
				{	$n = socket_write($sock, $buffer_write);
					if ($n === false)
					{	throw new Exception("Failed to send data to client");
					}
					if ($n===0 and strlen($buffer_write)<0xFFF8) // they don't want data so far, and i don't have too much data
					{	break;
					}
					$buffer_write = substr($buffer_write, $n);
				}	while (strlen($buffer_write) >= 0xFFF8);
			}
		};

		while (true)
		{	// Serve one request

			// 1. Reinit
			self::$headers = [];
			self::$header_callback = null;
			$gzip_ctx = null;
			http_response_code(200);
			clearstatcache();
			$_GET = [];
			$_POST = [];
			$_COOKIE = [];
			$_REQUEST = [];
			$_FILES = [];
			$_SESSION = []; // TODO: implement
			assert(strlen($stdout)==0 and strlen($buffer_write)==0 and count(self::$messages)==0);

			// 2. Read the request
			$request = self::fcgi_read_request_sync($sock);
			if ($request === null) // parent disconnected prematurely
			{	console_log($onerror_func, "Unexpected end of stream while reading from parent process (".posix_getpid().")");
				return;
			}
			if (!$request->stdin_complete) // parent disconnected after sending a complete request
			{	return;
			}
			$request_id = $request->request_id;
			$_SERVER = $request->nvp->params;
			if (!$request->stdin_success)
			{	console_log($onerror_func, "Incomplete POST body");
				$ob_handler(''); // output default headers
			}
			else
			{	self::$stdin = $request->stdin;
				self::$uploaded_files = $request->uploaded_files;
				// Set $_GET
				parse_str($_SERVER['QUERY_STRING'], $_GET);
				// Set $_POST
				$_POST = $request->post;
				// Set $_FILES
				$_FILES = $request->files;
				// Set $_COOKIE
				if (!empty($_SERVER['HTTP_COOKIE']))
				{	parse_str(strtr($_SERVER['HTTP_COOKIE'], ['&' => '%26', '+' => '%2B', ';' => '&']), $_COOKIE);
				}
				// Set $_REQUEST
				for ($i=0, $i_end=strlen($request_order); $i<$i_end; $i++)
				{	switch ($request_order[$i])
					{	case 'G':
							foreach ($_GET as $k => $v)
							{	$_REQUEST[$k] = $v;
							}
							break;
						case 'P':
							foreach ($_POST as $k => $v)
							{	$_REQUEST[$k] = $v;
							}
							break;
						case 'C':
							foreach ($_COOKIE as $k => $v)
							{	$_REQUEST[$k] = $v;
							}
							break;
					}
				}

				// 3. Create stdout buffer
				$ob_done = false;
				ob_start($ob_handler, BUFFER_SIZE);

				// 4. Run the client script
				try
				{	$request = null; // free memory
					$filename = self::get_script_filename($onerror_func, $onresolvepath_func);
					if ($filename !== null)
					{	if (!isset(self::$request_handlers[$filename]))
						{	require $filename;
						}
						if (isset(self::$request_handlers[$filename]))
						{	call_user_func(self::$request_handlers[$filename]);
						}
					}
				}
				catch (Throwable $e)
				{	http_response_code(500);
					console_log($onerror_func, "Exception while executing request", $e);
				}
				finally
				{	$ob_done = true;
					ob_end_flush();
				}
			}

			// 5. Flush the stdout buffer
			if ($gzip_ctx)
			{	$stdout .= deflate_add($gzip_ctx, '', ZLIB_FINISH);
			}
			$buffer_write .= fcgi_pack_data(FCGI_STDOUT, $request_id, $stdout);
			$stdout = '';

			// 6. Pack messages. I exploit FCGI_STDERR to send messages. The parent will filter them out from stream, and they will not be directed to the FCGI server.
			if (self::$messages)
			{	try
				{	$buffer_write .= fcgi_pack_data(FCGI_STDERR, $request_id, serialize(self::$messages));
				}
				catch (Throwable $e)
				{	console_log($onerror_func, "Couldn't serialize messages", $e);
				}
				self::$messages = [];
			}

			// 7. Terminate the request, and send the buffered data to parent process
			$buffer_write .= new FcgiRecordEndRequest($request_id, FCGI_REQUEST_COMPLETE);
			while (strlen($buffer_write) > 0)
			{	$n = socket_write($sock, $buffer_write);
				if ($n === false)
				{	console_log($onerror_func, "Failed to send data to parent process");
					return;
				}
				$buffer_write = substr($buffer_write, $n);
			}
		}
	}

	protected static function get_script_filename(callable $onerror_func, callable $onresolvepath_func=null)
	{	$filename = $_SERVER['SCRIPT_FILENAME'] ?? '';
		if (strlen($filename) == 0)
		{	throw new Exception("No SCRIPT_FILENAME given");
		}
		if ($onresolvepath_func !== null)
		{	$filename = $onresolvepath_func($filename);
		}
		else if (!empty($_SERVER['CONTEXT_DOCUMENT_ROOT']) and ($pos = strpos($filename, $_SERVER['CONTEXT_DOCUMENT_ROOT'])) !== false)
		{	// apache2 uses CONTEXT_DOCUMENT_ROOT when "Alias" directive is given
			$filename = substr($filename, $pos);
		}
		else if (!empty($_SERVER['DOCUMENT_ROOT']) and ($pos = strpos($filename, $_SERVER['DOCUMENT_ROOT'])) !== false)
		{	$filename = substr($filename, $pos);
		}
		$orig_filename = $filename;
		$filename = realpath($filename);
		if ($filename === false)
		{	http_response_code(404); // TODO: custom handler for 404
			console_log($onerror_func, "File not found: $orig_filename");
			return null;
		}
		return $filename;
	}

	protected static function fcgi_read_request_sync($sock): ?FcgiRequest
	{	$request = new FcgiRequest;
		$data = '';
		$is_empty = true;

		while (!$request->stdin_complete or !$request->params_complete)
		{	$chunk = socket_read($sock, BUFFER_SIZE);
			if ($chunk === false)
			{	throw new Exception(socket_strerror(socket_last_error($sock)));
			}
			if ($chunk === '')
			{	if (strlen($data)==0 and $is_empty)
				{	break; // socket sent no data, and disconnected (return !$request->stdin_complete)
				}
				else
				{	return null; // socket disconnected prematurely
				}
			}
			$data .= $chunk;
			$offset = 0;
			$request->read($data, $offset);
			if ($offset > 0)
			{	$is_empty = false;
				$data = substr($data, $offset);
			}
			else
			{	$data = '';
			}
		}

		assert(strlen($data) == 0);
		return $request;
	}
}

class FcgiRecordEndRequest
{	public int $request_id, $protocol_status;

	public function __construct(int $request_id, int $protocol_status)
	{	$this->request_id = $request_id;
		$this->protocol_status = $protocol_status;
	}

	public function __toString()
	{	return pack("C2n2x6Cx3", 1, FCGI_END_REQUEST, $this->request_id, 8, $this->protocol_status);
	}
}

class FcgiRecordUnknownType
{	public int $type;

	public function __construct(int $type)
	{	$this->type = $type;
	}

	public function __toString()
	{	return pack("C2x2nx2Cx7", 1, FCGI_UNKNOWN_TYPE, 8, $this->type);
	}
}

class FcgiRecordGetValuesResult
{	public FcgiNvp $nvp;

	public function __construct(FcgiNvp $nvp)
	{	$this->nvp = $nvp;
	}

	public function __toString()
	{	$nvp_str = "{$this->nvp}";
		$length = strlen($nvp_str);
		if ($length > 0xFFFF)
		{	throw new Exception("Record was too long");
		}
		$padding = (8 - $length%8) % 8;
		$full_length = $length + $padding;
		return pack("C2x2nCxa$full_length", 1, FCGI_GET_VALUES_RESULT, $length, $padding, $nvp_str);
	}
}

class FcgiNvp
{	public array $params = [];

	public function __construct($data, &$offset, $len)
	{	$this->read($data, $offset, $len);
	}

	public function read($data, &$offset, $len)
	{	$end_offset = $offset + $len;
		while ($offset+1 < $end_offset)
		{	$new_offset = $offset;
			// Read $name_len, $value_len
			$name_len = unpack('CL', $data, $new_offset)['L'];
			if ($name_len <= 127)
			{	$new_offset++;
			}
			else
			{	if ($new_offset+4 >= $end_offset)
				{	return;
				}
				$name_len = unpack('NL', $data, $new_offset)['L'];
				$name_len &= 0x7FFFFFFF;
				$new_offset += 4;
			}
			$value_len = unpack('CL', $data, $new_offset)['L'];
			if ($value_len <= 127)
			{	$new_offset++;
			}
			else
			{	if ($new_offset+(4+127) >= $end_offset)
				{	return;
				}
				$value_len = unpack('NL', $data, $new_offset)['L'];
				$value_len &= 0x7FFFFFFF;
				$new_offset += 4;
			}
			if ($new_offset+$name_len+$value_len > $end_offset)
			{	return;
			}
			$offset = $new_offset;
			// Read $name, $value
			$name = substr($data, $offset, $name_len);
			$offset += $name_len;
			$value = substr($data, $offset, $value_len);
			$offset += $value_len;
			// Set $this->params
			$this->params[$name] = $value;
		}
	}

	public function __toString()
	{	ob_start();
		foreach ($this->params as $name => $value)
		{	$name_len = strlen($name);
			$value_len = strlen($value);
			if ($name_len<=127 and $value_len<=127)
			{	echo pack('CC', $name_len, $value_len);
			}
			else if ($name_len <= 127)
			{	echo pack('CN', $name_len, $value_len);
			}
			else if ($value_len <= 127)
			{	echo pack('NC', $name_len, $value_len);
			}
			else
			{	echo pack('NN', $name_len, $value_len);
			}
			echo $name, $value;
		}
		return ob_get_clean();
	}
}

class FcgiRequest
{	public int $request_id = 0;
	public FcgiNvp $nvp;
	public string $stdin = '';
	public bool $params_complete = false;
	public bool $stdin_complete = false;
	public bool $stdin_success = true;
	public ?string $content_type = null;
	public array $post = [];
	public array $files = [];
	public array $uploaded_files = [];

	private string $params_buffer = '';
	private bool $is_urlencoded = false;
	private ?MulpipartFormData $form_data = null;

	public function __construct()
	{	$nvp_offset = 0;
		$this->nvp = new FcgiNvp('', $nvp_offset, 0);
	}

	public function read($data, &$offset)
	{	while (($header = fcgi_get_record_header($data, $offset)) !== null)
		{	if ($header['type'] == FCGI_PARAMS)
			{	$length = $header['length'];
				if ($length == 0)
				{	$this->params_complete = true;
					$this->request_id = $header['request_id'];

					// Set PHP_AUTH_USER, PHP_AUTH_PW and PHP_AUTH_DIGEST
					$this->http_authorization();

					// Set $this->is_urlencoded or $this->form_data
					$type = $this->nvp->params['CONTENT_TYPE'] ?? null;
					if ($type !== null)
					{	$boundary = null;
						$pos = strpos($type, ';');
						if ($pos !== false)
						{	$pos2 = strpos($type, 'boundary=', $pos+1);
							if ($pos2 !== false)
							{	$boundary = substr($type, $pos2+9);
								$pos2 = strpos($boundary, ';');
								if ($pos2 !== false)
								{	$boundary = substr($boundary, 0, $pos2);
								}
							}
							$type = substr($type, 0, $pos);
						}
						$this->content_type = strtolower(trim($type));
						if ($this->content_type === 'application/x-www-form-urlencoded')
						{	$this->is_urlencoded = true;
						}
						else if ($this->content_type==='multipart/form-data' and strlen($boundary)>0)
						{	$this->form_data = new MulpipartFormData($boundary, $_SERVER['CONTENT_LENGTH'] ?? 0, $this->stdin);
							$this->stdin = '';
						}
					}
				}
				else
				{	$nvp_offset = $offset - $header['padding'] - $length;
					if (strlen($this->params_buffer) != 0)
					{	$this->params_buffer .= substr($data, $nvp_offset, $length);
						$nvp_offset = 0;
						$this->nvp->read($this->params_buffer, $nvp_offset, strlen($this->params_buffer));
						if ($nvp_offset > 0)
						{	$this->params_buffer = substr($this->params_buffer, $nvp_offset);
						}
					}
					else
					{	$nvp_offset_from = $nvp_offset;
						$this->nvp->read($data, $nvp_offset, $length);
						if ($nvp_offset < $nvp_offset_from+$length)
						{	$this->params_buffer = substr($data, $nvp_offset, $nvp_offset_from+$length-$nvp_offset);
						}
					}
				}
			}
			else
			{	assert($header['type'] == FCGI_STDIN);
				$length = $header['length'];
				if ($length == 0)
				{	$this->stdin_complete = true;
					if ($header['request_id'] == 0) // when POST body exceeds ini_get('post_max_size'), the parent signals me about this by sending zero request_id in the last FCGI_STDIN packet. see above
					{	$this->stdin = '';
						$this->post = [];
						$this->files = [];
						$this->uploaded_files = [];
					}
					else
					{	// Interpret the STDIN according to CONTENT_TYPE
						if ($this->is_urlencoded)
						{	parse_str($this->stdin, $this->post);
						}
						else if ($this->form_data !== null)
						{	$this->stdin_success = $this->form_data->take_result($this->post, $this->files, $this->uploaded_files);
						}
					}
				}
				else if ($this->form_data === null)
				{	$this->stdin .= substr($data, $offset-$header['padding']-$length, $length);
				}
				else
				{	$this->form_data->read(substr($data, $offset-$header['padding']-$length, $length));
				}
			}
		}
	}

	private function http_authorization()
	{	$auth = $this->nvp->params['HTTP_AUTHORIZATION'] ?? null;
		if ($auth !== null)
		{	$pos = strpos($auth, ' ');
			if ($pos === 5)
			{	if (strcasecmp(substr($auth, 0, 5), 'Basic') === 0)
				{	list($this->nvp->params['PHP_AUTH_USER'], $this->nvp->params['PHP_AUTH_PW']) = explode(':', base64_decode(substr($auth, 6)));
				}
			}
			else if ($pos === 6)
			{	if (strcasecmp(substr($auth, 0, 6), 'Digest') === 0)
				{	$this->nvp->params['PHP_AUTH_DIGEST'] = substr($auth, 7);
				}
			}
		}
	}
}

class Request
{	private bool $stdin_written;
	private FcgiRequest $request;
	private $m_get = null;
	private $m_post = null;

	public function __construct(string $data, bool $stdin_written)
	{	$this->stdin_written = $stdin_written;
		$this->request = new FcgiRequest;
		$offset = 0;
		$this->request->read($data, $offset);
	}

	public function __get($name)
	{	switch ($name)
		{	case 'server':
				return $this->request->nvp->params;

			case 'get':
				if ($this->m_get === null)
				{	parse_str($this->request->nvp->params['QUERY_STRING'], $this->m_get);
				}
				return $this->m_get;

			case 'content_type':
				return $this->request->content_type; // lowercased substring of $_SERVER['CONTENT_TYPE'] before first ';'

			case 'post':
				if ($this->m_post === null)
				{	if ($this->stdin_written or $this->request->content_type==='multipart/form-data')
					{	// if stdin is complete or if form-data. in case of form-data $this->request->post contains parameters read so far, and $this->stdin is empty
						$this->m_post = $this->request->post;
					}
					else if ($this->request->content_type === 'application/x-www-form-urlencoded')
					{	// if incomplete x-www-form-urlencoded data, i will cut partial parameter at the end, and parse the rest
						$stdin = $this->request->stdin;
						$pos = strrpos('&', $stdin);
						if ($pos > 0)
						{	parse_str(substr($stdin, 0, $pos), $this->m_post);
						}
						else
						{	$this->m_post = [];
						}
					}
				}
				return $this->m_post;

			case 'input':
				return $this->request->stdin; // can be cut, and in case of form-data, it will be empty (and $this->post will contain parameters read so far)

			case 'input_complete':
				return $this->stdin_written;
		}
		return null;
	}
}

/**	According to: https://www.w3.org/Protocols/rfc1341/7_2_Multipart.html
 **/
class MulpipartFormData
{	/*	Parse:

		------------------------------b2449e94a11c
		Content-Disposition: form-data; name="user_id"

		3
		------------------------------b2449e94a11c
		Content-Disposition: form-data; name="post_id"

		5
		------------------------------b2449e94a11c
		Content-Disposition: form-data; name="image"; filename="/tmp/current_file"
		Content-Type: application/octet-stream

		...
		...
	*/

	private const S_INITIAL = 0;
	private const S_HEADER = 1;
	private const S_HEADER_VALUE = 2;
	private const S_BODY = 3;
	private const S_DONE = 4;
	private const S_ERROR = 5;

	private string $boundary; // from CONTENT_TYPE HTTP header
	private int $content_length = 0; // the CONTENT_LENGTH HTTP header
	private string $data = ''; // buffer for input data
	private int $read_content_length = 0; // how many bytes passed to read()
	private string $header_name = ''; // is valid in S_HEADER_VALUE
	private string $name = ''; // is valid in S_BODY
	private string $filename = ''; // is valid in S_BODY
	private string $content_type = ''; // is valid in S_BODY
	private string $tmp_name = ''; // is valid in S_BODY
	private string $value = ''; // is used in S_BODY
	private $value_fh = null; // is used in S_BODY
	private array $post = []; // input data will be parsed to $post, $files and $uploaded_files
	private array $files = [];
	private array $uploaded_files = []; // flat array with all tmp_names
	private int $state = self::S_INITIAL; // parser state

	private static float $upload_max_filesize = -1.0; // the ini_get('upload_max_filesize')
	private static float $max_file_uploads = -1.0; // the ini_get('max_file_uploads')

	public function __construct(string $boundary, int $content_length, string $data)
	{	if (self::$upload_max_filesize === -1.0)
		{	self::$upload_max_filesize = conv_units(ini_get('upload_max_filesize')); // TODO: respect
			self::$max_file_uploads = conv_units(ini_get('max_file_uploads')); // TODO: respect
		}
		$this->boundary = $boundary;
		$this->content_length = $content_length;
		$this->read($data);
	}

	public function read(string $data, bool $is_last=false)
	{	$this->read_content_length += strlen($data);
		$data = $this->data.$data;
		$data_len = strlen($data);
		$i = 0;
		while ($i < $data_len)
		{	switch ($this->state)
			{	case self::S_ERROR:
				case self::S_DONE:
					return;

				case self::S_INITIAL:
					$i = strpos($data, $this->boundary."\r\n"); // skip anything before first boundary (it must be ignored according to RFC)
					if ($i === false)
					{	if ($is_last)
						{	$this->state = self::S_ERROR; // no first line
						}
						return;
					}
					$i += strlen($this->boundary) + 2;
					$this->state = self::S_HEADER;
					// fallthrough

				case self::S_HEADER:
					$len = strcspn($data, ":\r\n", $i);
					if ($i+$len === $data_len)
					{	break 2;
					}
					if ($len == 0)
					{	// empty line terminates headers
						if ($data[$i] != "\r")
						{	$this->data = '';
							$this->state = self::S_ERROR; // line starts with ":" or "\n"
							return;
						}
						$i += 2; // after "\r\n"
						if (strlen($this->filename) > 0)
						{	$this->tmp_name = tempnam(null, null);
							if ($this->tmp_name !== false)
							{	$this->value_fh = fopen($this->tmp_name, 'w+');
							}
						}
						$this->state = self::S_BODY;
						break;
					}
					else
					{	// header
						if ($len>256 or $data[$i + $len]!=':')
						{	$this->data = '';
							$this->state = self::S_ERROR; // header name is too long, or no header name
							return;
						}
						$this->header_name = trim(substr($data, $i, $len));
						$i += $len + 1; // after ':'
						$this->state = self::S_HEADER_VALUE;
						// fallthrough
					}

				case self::S_HEADER_VALUE:
					$len = strcspn($data, "\r", $i); // at "\r"
					if ($i+$len === $data_len)
					{	break 2;
					}
					if (strcasecmp($this->header_name, 'Content-Disposition') == 0)
					{	$i2 = strpos($data, ';', $i);
						if ($i2===false or $i2>$i+$len)
						{	$this->data = '';
							$this->state = self::S_ERROR; // no ';' in "Content-Disposition: form-data; ..."
							return;
						}
						$i2++; // after ';'
						if (preg_match_all('~\s*(\w+)="([^"]+)"(?:;|$)~', substr($data, $i2, $len - ($i2 - $i)), $matches, PREG_SET_ORDER))
						{	foreach ($matches as $m)
							{	if (strcasecmp($m[1], 'name') == 0)
								{	$this->name = urldecode($m[2]);
								}
								else if (strcasecmp($m[1], 'filename') == 0)
								{	$this->filename = urldecode($m[2]);
								}
							}
						}
					}
					else if (strcasecmp($this->header_name, 'Content-Type') == 0)
					{	$this->content_type = trim(substr($data, $i, $len));
					}
					$i += $len + 2; // after "\r\n"
					$this->header_name = '';
					$this->state = self::S_HEADER;
					break;

				case self::S_BODY:
					$this->value .= substr($data, $i);
					$i = $data_len;
					$i2 = strpos($this->value, $this->boundary);
					if ($i2 === false)
					{	if ($is_last)
						{	$this->data = '';
							$this->state = self::S_DONE;
						}
					}
					else
					{	$i -= strlen($this->value) - $i2;
						$this->value = substr($this->value, 0, $i2);
						$i3 = strrpos($this->value, "\r\n");
						if ($i3 === false)
						{	$this->data = '';
							$this->state = self::S_ERROR; // boundary is not on it's own line
							return;
						}
						$this->value = substr($this->value, 0, $i3);
						$i += strlen($this->boundary) + 2; // strlen($this->boundary) + strlen("\r\n"); or: strlen($this->boundary) + strlen("--")
						$this->state = self::S_HEADER;
					}
					if ($this->state != self::S_BODY)
					{	if (strlen($this->filename) == 0)
						{	// is regular field
							$this->post[$this->name] = $this->value;
						}
						else
						{	// is file
							$len = 0;
							if ($this->write($this->value))
							{	$len = ftell($this->value_fh);
								fclose($this->value_fh);
								$this->uploaded_files[] = $this->tmp_name;
							}
							$this->files[$this->name]['name'] = $this->filename;
							$this->files[$this->name]['type'] = $this->content_type ?? 'text/plain';
							$this->files[$this->name]['size'] = $len;
							$this->files[$this->name]['tmp_name'] = $this->value_fh===null ? '' : $this->tmp_name;
							$this->files[$this->name]['error'] = $this->value_fh===null ? UPLOAD_ERR_CANT_WRITE : UPLOAD_ERR_OK; // TODO: other codes
							$this->tmp_name = '';
							$this->filename = '';
							$this->value_fh = null;
						}
						$this->name = '';
						$this->content_type = '';
						$this->value = '';
					}
					else if (strlen($this->filename) > 0)
					{	// is file
						$this->write(substr($this->value, 0, -100));
						$this->value = substr($this->value, -100); // always leave 100 chars in buffer, assuming the boundary line (with filler chars) is shorter, so i can find whole boundary
					}
					break;
			}
		}
		$this->data = substr($data, $i);
	}

	private function write($data)
	{	if ($this->value_fh !== null)
		{	if (fwrite($this->value_fh, $data) === strlen($data))
			{	return true;
			}
			ftruncate($this->value_fh, 0);
			fclose($this->value_fh);
			unlink($this->tmp_name);
			$this->value_fh = null;
		}
		return false;
	}

	public function take_result(&$post, &$files, &$uploaded_files): bool
	{	$this->read('', true);
		// According to FastCGI specification, FastCGI server can send partial body, and client must validate against CONTENT_LENGTH
		if ($this->state==self::S_ERROR or $this->content_length>0 and $this->read_content_length!=$this->content_length)
		{	return false;
		}
		$post = $this->post;
		$files = $this->files;
		$uploaded_files = $this->uploaded_files;
		return true;
	}
}

function fcgi_get_record_header(string $data, &$offset)
{	$len = strlen($data) - $offset;
	if ($len >= 8)
	{	$header = unpack('Ctype/nrequest_id/nlength/Cpadding', $data, $offset+1);
		$record_len = 8 + $header['length'] + $header['padding'];
		if ($len >= $record_len)
		{	$offset += $record_len;
			return $header;
		}
	}
}

function fcgi_pack_data(int $type, int $request_id, string $data): string
{	$packaged_data = '';
	$length = strlen($data);
	if ($length > 0)
	{	while (true)
		{	if ($length > 0xFFF8) // 0xFFF9 .. 0xFFFF will be padded to 0x10000
			{	$packaged_data .= pack("C2n2x2a65528", 1, $type, $request_id, 0xFFF8, $data); // assume: 0xFFF8 == 65528
				$data = substr($data, 0xFFF8);
				$length -= 0xFFF8;
			}
			else
			{	$padding = (8 - $length%8) % 8;
				$full_length = $length + $padding;
				$packaged_data .= pack("C2n2Cxa$full_length", 1, $type, $request_id, $length, $padding, $data);
				break;
			}
		}
	}
	$packaged_data .= pack("C2nx4", 1, $type, $request_id); // terminate stream
	return $packaged_data;
}

function conv_units($value, bool $is_time=false)
{	$units = null;
	if (preg_match('~[a-z]+$~i', $value, $match))
	{	$units = strtolower($match[0]);
		$value = substr($value, 0, -strlen($units));
	}
	$value = trim($value);
	if (!is_numeric($value))
	{	return false;
	}
	$value = (float)$value;
	switch ($units)
	{	case 'k': return $value*1024; // kilobytes (kibibytes)
		case 'm': return $is_time ? $value*60 : $value*(1024*1024); // minutes, megabytes (mebibytes)
		case 'g': return $value*(1024*1024*1024); // gigabytes (gibibytes)
		case 'h': return $value*(60*60); // hours
		case 'd': return $value*(24*60*60); // days
	}
	return $value;
}

function console_log(callable $onerror_func, $msg, Throwable $exception=null)
{	try
	{	$onerror_func(!$exception ? $msg : "$msg - ".$exception->getMessage()."\n".$exception->getTraceAsString());
	}
	catch (Throwable $e)
	{	error_log($msg);
		error_log($e->getMessage()."\n".$e->getTraceAsString());
	}
}
