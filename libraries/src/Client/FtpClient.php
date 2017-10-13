<?php
/**
 * Joomla! Content Management System
 *
 * @copyright  Copyright (C) 2005 - 2017 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\CMS\Client;

defined('JPATH_PLATFORM') or die;

use Joomla\CMS\Log\Log;
use Joomla\CMS\Client\Ftp\AbstractFtpClient;

/** Error Codes:
 * - 30 : Unable to connect to host
 * - 31 : Not connected
 * - 32 : Unable to send command to server
 * - 33 : Bad username
 * - 34 : Bad password
 * - 35 : Bad response
 * - 36 : Passive mode failed
 * - 37 : Data transfer error
 * - 38 : Local filesystem error
 */

if (!defined('CRLF'))
{
	define('CRLF', "\r\n");
}

if (!defined('FTP_AUTOASCII'))
{
	define('FTP_AUTOASCII', -1);
}

if (!defined('FTP_BINARY'))
{
	define('FTP_BINARY', 1);
}

if (!defined('FTP_ASCII'))
{
	define('FTP_ASCII', 0);
}

if (!defined('FTP_NATIVE'))
{
	define('FTP_NATIVE', (function_exists('ftp_connect')) ? 1 : 0);
}

/**
 * FTP client class
 *
 * @since  12.1
 */
class FtpClient
{
	/**
	 * @var    resource  Socket resource
	 * @since  12.1
	 */
	protected $_conn = null;

	/**
	 * @var    resource  Data port connection resource
	 * @since  12.1
	 */
	protected $_dataconn = null;

	/**
	 * @var    array  Passive connection information
	 * @since  12.1
	 */
	protected $_pasv = null;

	/**
	 * @var    string  Response Message
	 * @since  12.1
	 */
	protected $_response = null;

	/**
	 * @var    integer  Timeout limit
	 * @since  12.1
	 */
	protected $_timeout = 15;

	/**
	 * @var    integer  Transfer Type
	 * @since  12.1
	 */
	protected $_type = null;

	/**
	 * @var    array  Array to hold ascii format file extensions
	 * @since  12.1
	 */
	protected $_autoAscii = array(
		'asp',
		'bat',
		'c',
		'cpp',
		'csv',
		'h',
		'htm',
		'html',
		'shtml',
		'ini',
		'inc',
		'log',
		'php',
		'php3',
		'pl',
		'perl',
		'sh',
		'sql',
		'txt',
		'xhtml',
		'xml',
	);

	/**
	 * Array to hold native line ending characters
	 *
	 * @var    array
	 * @since  12.1
	 */
	protected $_lineEndings = array('UNIX' => "\n", 'WIN' => "\r\n");

	/**
	 * @var    AbstractFtpClient[]  FtpClient instances container.
	 * @since  12.1
	 */
	protected static $instances = array();

	/**
	 * @var  AbstractFtpClient
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	private $instance;

	/**
	 * FtpClient object constructor
	 *
	 * @param   array  $options  Associative array of options to set
	 *
	 * @since   12.1
	 *
	 * @deprecated   Use NativeFtpClient or SocketFtpClient
	 */
	public function __construct(array $options = array())
	{
		// Earlier FtpClient could be instantiated directly.
		// We'd now use this as decorator for the new objects to provide B/C.
		$class = FTP_NATIVE ? 'Joomla\CMS\Client\Ftp\NativeFtpClient' : 'Joomla\CMS\Client\Ftp\SocketFtpClient';

		$this->instance = new $class($options);
	}

	/**
	 * FtpClient object destructor
	 *
	 * Closes an existing connection, if we have one
	 *
	 * @since   12.1
	 */
	public function __destruct()
	{
		// The decorated instance will handle destruct
	}

	/**
	 * Returns the global FTP connector object, only creating it
	 * if it doesn't already exist.
	 *
	 * You may optionally specify a username and password in the parameters. If you do so,
	 * you may not login() again with different credentials using the same object.
	 * If you do not use this option, you must quit() the current connection when you
	 * are done, to free it for use by others.
	 *
	 * @param   string  $host     Host to connect to
	 * @param   string  $port     Port to connect to
	 * @param   array   $options  Array with any of these options: type=>[FTP_AUTOASCII|FTP_ASCII|FTP_BINARY], timeout=>(int)
	 * @param   string  $user     Username to use for a connection
	 * @param   string  $pass     Password to use for a connection
	 *
	 * @return  AbstractFtpClient  The FTP Client object.
	 *
	 * @since   12.1
	 */
	public static function getInstance($host = '127.0.0.1', $port = '21', array $options = array(), $user = null, $pass = null)
	{
		$signature = $user . ':' . $pass . '@' . $host . ':' . $port;

		// Create a new instance, or set the options of an existing one
		if (!isset(static::$instances[$signature]) || !is_object(static::$instances[$signature]))
		{
			$class = FTP_NATIVE ? 'Joomla\CMS\Client\Ftp\NativeFtpClient' : 'Joomla\CMS\Client\Ftp\SocketFtpClient';

			static::$instances[$signature] = new $class($options);
		}
		else
		{
			static::$instances[$signature]->setOptions($options);
		}

		// Connect to the server, and login, if requested
		if (!static::$instances[$signature]->isConnected())
		{
			$return = static::$instances[$signature]->connect($host, $port);

			if ($return && $user !== null && $pass !== null)
			{
				static::$instances[$signature]->login($user, $pass);
			}
		}

		return static::$instances[$signature];
	}

	/**
	 * Set client options
	 *
	 * @param   array  $options  Associative array of options to set
	 *
	 * @return  boolean  True if successful
	 *
	 * @since   12.1
	 *
	 * @deprecated   Use NativeFtpClient or SocketFtpClient
	 */
	public function setOptions(array $options)
	{
		return $this->instance->setOptions($options);
	}

	/**
	 * Method to connect to a FTP server
	 *
	 * @param   string  $host  Host to connect to [Default: 127.0.0.1]
	 * @param   int     $port  Port to connect on [Default: port 21]
	 *
	 * @return  boolean  True if successful
	 *
	 * @since   12.1
	 *
	 * @deprecated   Use NativeFtpClient or SocketFtpClient
	 */
	public function connect($host = '127.0.0.1', $port = 21)
	{
		return $this->instance->connect($host, $port);
	}

	/**
	 * Method to determine if the object is connected to an FTP server
	 *
	 * @return  boolean  True if connected
	 *
	 * @since   12.1
	 *
	 * @deprecated   Use NativeFtpClient or SocketFtpClient
	 */
	public function isConnected()
	{
		return $this->instance->isConnected();
	}

	/**
	 * Method to login to a server once connected
	 *
	 * @param   string  $user  Username to login to the server
	 * @param   string  $pass  Password to login to the server
	 *
	 * @return  boolean  True if successful
	 *
	 * @since   12.1
	 *
	 * @deprecated   Use NativeFtpClient or SocketFtpClient
	 */
	public function login($user = 'anonymous', $pass = 'jftp@joomla.org')
	{
		return $this->instance->login($user, $pass);
	}

	/**
	 * Method to quit and close the connection
	 *
	 * @return  boolean  True if successful
	 *
	 * @since   12.1
	 *
	 * @deprecated   Use NativeFtpClient or SocketFtpClient
	 */
	public function quit()
	{
		return $this->instance->quit();
	}

	/**
	 * Method to retrieve the current working directory on the FTP server
	 *
	 * @return  string   Current working directory
	 *
	 * @since   12.1
	 *
	 * @deprecated   Use NativeFtpClient or SocketFtpClient
	 */
	public function pwd()
	{
		return $this->instance->pwd();
	}

	/**
	 * Method to system string from the FTP server
	 *
	 * @return  string   System identifier string
	 *
	 * @since   12.1
	 *
	 * @deprecated   Use NativeFtpClient or SocketFtpClient
	 */
	public function syst()
	{
		return $this->instance->syst();
	}

	/**
	 * Method to change the current working directory on the FTP server
	 *
	 * @param   string  $path  Path to change into on the server
	 *
	 * @return  boolean True if successful
	 *
	 * @since   12.1
	 *
	 * @deprecated   Use NativeFtpClient or SocketFtpClient
	 */
	public function chdir($path)
	{
		return $this->instance->chdir($path);
	}

	/**
	 * Method to reinitialise the server, ie. need to login again
	 *
	 * NOTE: This command not available on all servers
	 *
	 * @return  boolean  True if successful
	 *
	 * @since   12.1
	 *
	 * @deprecated   Use NativeFtpClient or SocketFtpClient
	 */
	public function reinit()
	{
		return $this->instance->reinit();
	}

	/**
	 * Method to rename a file/folder on the FTP server
	 *
	 * @param   string  $from  Path to change file/folder from
	 * @param   string  $to    Path to change file/folder to
	 *
	 * @return  boolean  True if successful
	 *
	 * @since   12.1
	 *
	 * @deprecated   Use NativeFtpClient or SocketFtpClient
	 */
	public function rename($from, $to)
	{
		return $this->instance->rename($from, $to);
	}

	/**
	 * Method to change mode for a path on the FTP server
	 *
	 * @param   string  $path  Path to change mode on
	 * @param   mixed   $mode  Octal value to change mode to, e.g. '0777', 0777 or 511 (string or integer)
	 *
	 * @return  boolean  True if successful
	 *
	 * @since   12.1
	 *
	 * @deprecated   Use NativeFtpClient or SocketFtpClient
	 */
	public function chmod($path, $mode)
	{
		return $this->instance->chmod($path, $mode);
	}

	/**
	 * Method to delete a path [file/folder] on the FTP server
	 *
	 * @param   string  $path  Path to delete
	 *
	 * @return  boolean  True if successful
	 *
	 * @since   12.1
	 *
	 * @deprecated   Use NativeFtpClient or SocketFtpClient
	 */
	public function delete($path)
	{
		return $this->instance->delete($path);
	}

	/**
	 * Method to create a directory on the FTP server
	 *
	 * @param   string  $path  Directory to create
	 *
	 * @return  boolean  True if successful
	 *
	 * @since   12.1
	 *
	 * @deprecated   Use NativeFtpClient or SocketFtpClient
	 */
	public function mkdir($path)
	{
		return $this->instance->mkdir($path);
	}

	/**
	 * Method to restart data transfer at a given byte
	 *
	 * @param   integer  $point  Byte to restart transfer at
	 *
	 * @return  boolean  True if successful
	 *
	 * @since   12.1
	 *
	 * @deprecated   Use NativeFtpClient or SocketFtpClient
	 */
	public function restart($point)
	{
		return $this->instance->restart($point);
	}

	/**
	 * Method to create an empty file on the FTP server
	 *
	 * @param   string  $path  Path local file to store on the FTP server
	 *
	 * @return  boolean  True if successful
	 *
	 * @since   12.1
	 *
	 * @deprecated   Use NativeFtpClient or SocketFtpClient
	 */
	public function create($path)
	{
		return $this->instance->create($path);
	}

	/**
	 * Method to read a file from the FTP server's contents into a buffer
	 *
	 * @param   string  $remote   Path to remote file to read on the FTP server
	 * @param   string  &$buffer  Buffer variable to read file contents into
	 *
	 * @return  boolean  True if successful
	 *
	 * @since   12.1
	 *
	 * @deprecated   Use NativeFtpClient or SocketFtpClient
	 */
	public function read($remote, &$buffer)
	{
		return $this->instance->read($remote, $buffer);
	}

	/**
	 * Method to get a file from the FTP server and save it to a local file
	 *
	 * @param   string  $local   Local path to save remote file to
	 * @param   string  $remote  Path to remote file to get on the FTP server
	 *
	 * @return  boolean  True if successful
	 *
	 * @since   12.1
	 *
	 * @deprecated   Use NativeFtpClient or SocketFtpClient
	 */
	public function get($local, $remote)
	{
		return $this->instance->get($local, $remote);
	}

	/**
	 * Method to store a file to the FTP server
	 *
	 * @param   string  $local   Path to local file to store on the FTP server
	 * @param   string  $remote  FTP path to file to create
	 *
	 * @return  boolean  True if successful
	 *
	 * @since   12.1
	 *
	 * @deprecated   Use NativeFtpClient or SocketFtpClient
	 */
	public function store($local, $remote = null)
	{
		return $this->instance->store($local, $remote);
	}

	/**
	 * Method to write a string to the FTP server
	 *
	 * @param   string  $remote  FTP path to file to write to
	 * @param   string  $buffer  Contents to write to the FTP server
	 *
	 * @return  boolean  True if successful
	 *
	 * @since   12.1
	 *
	 * @deprecated   Use NativeFtpClient or SocketFtpClient
	 */
	public function write($remote, $buffer)
	{
		return $this->instance->write($remote, $buffer);
	}

	/**
	 * Method to append a string to the FTP server
	 *
	 * @param   string  $remote  FTP path to file to append to
	 * @param   string  $buffer  Contents to append to the FTP server
	 *
	 * @return  boolean  True if successful
	 *
	 * @since   3.6.0
	 *
	 * @deprecated   Use NativeFtpClient or SocketFtpClient
	 */
	public function append($remote, $buffer)
	{
		return $this->instance->append($remote, $buffer);
	}

	/**
	 * Get the size of the remote file.
	 *
	 * @param   string  $remote  FTP path to file whose size to get
	 *
	 * @return  mixed  number of bytes or false on error
	 *
	 * @since   3.6.0
	 *
	 * @deprecated   Use NativeFtpClient or SocketFtpClient
	 */
	public function size($remote)
	{
		return $this->instance->size($remote);
	}

	/**
	 * Method to list the filenames of the contents of a directory on the FTP server
	 *
	 * Note: Some servers also return folder names. However, to be sure to list folders on all
	 * servers, you should use listDetails() instead if you also need to deal with folders
	 *
	 * @param   string  $path  Path local file to store on the FTP server
	 *
	 * @return  string  Directory listing
	 *
	 * @since   12.1
	 *
	 * @deprecated   Use NativeFtpClient or SocketFtpClient
	 */
	public function listNames($path = null)
	{
		return $this->instance->listNames($path);
	}

	/**
	 * Method to list the contents of a directory on the FTP server
	 *
	 * @param   string  $path  Path to the local file to be stored on the FTP server
	 * @param   string  $type  Return type [raw|all|folders|files]
	 *
	 * @return  mixed  If $type is raw: string Directory listing, otherwise array of string with file-names
	 *
	 * @since   12.1
	 *
	 * @deprecated   Use NativeFtpClient or SocketFtpClient
	 */
	public function listDetails($path = null, $type = 'all')
	{
		return $this->instance->listDetails($path, $type);
	}

	/**
	 * Method to find out the correct transfer mode for a specific file
	 *
	 * @param   string  $fileName  Name of the file
	 *
	 * @return  integer Transfer-mode for this filetype [FTP_ASCII|FTP_BINARY]
	 *
	 * @since   12.1
	 *
	 * @deprecated   Use NativeFtpClient or SocketFtpClient
	 */
	protected function _findMode($fileName)
	{
		if ($this->_type == FTP_AUTOASCII)
		{
			$dot = strrpos($fileName, '.') + 1;
			$ext = substr($fileName, $dot);

			if (in_array($ext, $this->_autoAscii))
			{
				$mode = FTP_ASCII;
			}
			else
			{
				$mode = FTP_BINARY;
			}
		}
		elseif ($this->_type == FTP_ASCII)
		{
			$mode = FTP_ASCII;
		}
		else
		{
			$mode = FTP_BINARY;
		}

		return $mode;
	}

	/**
	 * Send command to the FTP server and validate an expected response code
	 *
	 * @param   string  $cmd               Command to send to the FTP server
	 * @param   mixed   $expectedResponse  Integer response code or array of integer response codes
	 *
	 * @return  boolean  True if command executed successfully
	 *
	 * @since   12.1
	 *
	 * @deprecated   Use SocketFtpClient object
	 */
	protected function _putCmd($cmd, $expectedResponse)
	{
		// Make sure we have a connection to the server
		if (!$this->isConnected())
		{
			Log::add(\JText::_('JLIB_CLIENT_ERROR_JFTP_PUTCMD_UNCONNECTED'), Log::WARNING, 'jerror');

			return false;
		}

		// Send the command to the server
		if (!fwrite($this->_conn, $cmd . "\r\n"))
		{
			Log::add(\JText::sprintf('DDD', \JText::sprintf('JLIB_CLIENT_ERROR_JFTP_PUTCMD_SEND', $cmd)), Log::WARNING, 'jerror');
		}

		return $this->_verifyResponse($expectedResponse);
	}

	/**
	 * Verify the response code from the server and log response if flag is set
	 *
	 * @param   mixed  $expected  Integer response code or array of integer response codes
	 *
	 * @return  boolean  True if response code from the server is expected
	 *
	 * @since   12.1
	 *
	 * @deprecated   Use SocketFtpClient object
	 */
	protected function _verifyResponse($expected)
	{
		$parts = null;

		// Wait for a response from the server, but timeout after the set time limit
		$endTime = time() + $this->_timeout;
		$this->_response = '';

		do
		{
			$this->_response .= fgets($this->_conn, 4096);
		}
		while (!preg_match('/^([0-9]{3})(-(.*' . CRLF . ')+\1)? [^' . CRLF . ']+' . CRLF . "$/", $this->_response, $parts) && time() < $endTime);

		// Catch a timeout or bad response
		if (!isset($parts[1]))
		{
			Log::add(\JText::sprintf('JLIB_CLIENT_ERROR_JFTP_VERIFYRESPONSE', $this->_response), Log::WARNING, 'jerror');

			return false;
		}

		// Separate the code from the message
		$this->_responseCode = $parts[1];
		$this->_responseMsg = $parts[0];

		// Did the server respond with the code we wanted?
		if (is_array($expected))
		{
			if (in_array($this->_responseCode, $expected))
			{
				$retval = true;
			}
			else
			{
				$retval = false;
			}
		}
		else
		{
			if ($this->_responseCode == $expected)
			{
				$retval = true;
			}
			else
			{
				$retval = false;
			}
		}

		return $retval;
	}

	/**
	 * Set server to passive mode and open a data port connection
	 *
	 * @return  boolean  True if successful
	 *
	 * @since   12.1
	 *
	 * @deprecated   Use NativeFtpClient or SocketFtpClient
	 */
	protected function _passive()
	{
		$match = array();
		$parts = array();
		$errno = null;
		$err = null;

		// Make sure we have a connection to the server
		if (!$this->isConnected())
		{
			Log::add(\JText::_('JLIB_CLIENT_ERROR_JFTP_PASSIVE_CONNECT_PORT'), Log::WARNING, 'jerror');

			return false;
		}

		// Request a passive connection - this means, we'll talk to you, you don't talk to us.
		@ fwrite($this->_conn, "PASV\r\n");

		// Wait for a response from the server, but timeout after the set time limit
		$endTime = time() + $this->_timeout;
		$this->_response = '';

		do
		{
			$this->_response .= fgets($this->_conn, 4096);
		}
		while (!preg_match('/^([0-9]{3})(-(.*' . CRLF . ')+\1)? [^' . CRLF . ']+' . CRLF . "$/", $this->_response, $parts) && time() < $endTime);

		// Catch a timeout or bad response
		if (!isset($parts[1]))
		{
			Log::add(\JText::sprintf('JLIB_CLIENT_ERROR_JFTP_PASSIVE_RESPONSE', $this->_response), Log::WARNING, 'jerror');

			return false;
		}

		// Separate the code from the message
		$this->_responseCode = $parts[1];
		$this->_responseMsg = $parts[0];

		// If it's not 227, we weren't given an IP and port, which means it failed.
		if ($this->_responseCode != '227')
		{
			Log::add(\JText::sprintf('JLIB_CLIENT_ERROR_JFTP_PASSIVE_IP_OBTAIN', $this->_responseMsg), Log::WARNING, 'jerror');

			return false;
		}

		// Snatch the IP and port information, or die horribly trying...
		if (preg_match('~\((\d+),\s*(\d+),\s*(\d+),\s*(\d+),\s*(\d+)(?:,\s*(\d+))\)~', $this->_responseMsg, $match) == 0)
		{
			Log::add(\JText::sprintf('JLIB_CLIENT_ERROR_JFTP_PASSIVE_IP_VALID', $this->_responseMsg), Log::WARNING, 'jerror');

			return false;
		}

		// This is pretty simple - store it for later use ;).
		$this->_pasv = array('ip' => $match[1] . '.' . $match[2] . '.' . $match[3] . '.' . $match[4], 'port' => $match[5] * 256 + $match[6]);

		// Connect, assuming we've got a connection.
		$this->_dataconn = @fsockopen($this->_pasv['ip'], $this->_pasv['port'], $errno, $err, $this->_timeout);

		if (!$this->_dataconn)
		{
			Log::add(
				\JText::sprintf('JLIB_CLIENT_ERROR_JFTP_PASSIVE_CONNECT', $this->_pasv['ip'], $this->_pasv['port'], $errno, $err),
				Log::WARNING,
				'jerror'
			);

			return false;
		}

		// Set the timeout for this connection
		socket_set_timeout($this->_conn, $this->_timeout, 0);

		return true;
	}

	/**
	 * Set transfer mode
	 *
	 * @param   integer  $mode  Integer representation of data transfer mode [1:Binary|0:Ascii]
	 * Defined constants can also be used [FTP_BINARY|FTP_ASCII]
	 *
	 * @return  boolean  True if successful
	 *
	 * @since   12.1
	 *
	 * @deprecated   Use NativeFtpClient or SocketFtpClient
	 */
	protected function _mode($mode)
	{
		if ($mode == FTP_BINARY)
		{
			if (!$this->_putCmd('TYPE I', 200))
			{
				Log::add(\JText::sprintf('JLIB_CLIENT_ERROR_JFTP_MODE_BINARY', $this->_response), Log::WARNING, 'jerror');

				return false;
			}
		}
		else
		{
			if (!$this->_putCmd('TYPE A', 200))
			{
				Log::add(\JText::sprintf('JLIB_CLIENT_ERROR_JFTP_MODE_ASCII', $this->_response), Log::WARNING, 'jerror');

				return false;
			}
		}

		return true;
	}
}
