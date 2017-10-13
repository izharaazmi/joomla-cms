<?php
/**
 * Joomla! Content Management System
 *
 * @copyright  Copyright (C) 2005 - 2017 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\CMS\Client\Ftp;

defined('JPATH_PLATFORM') or die;

use Joomla\CMS\Log\Log;

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
 * FTP client abstract class
 *
 * @since   __DEPLOY_VERSION__
 */
abstract class AbstractFtpClient
{
	/**
	 * @var    resource  Socket resource
	 * @since  __DEPLOY_VERSION__
	 */
	protected $_conn = null;

	/**
	 * @var    integer  Timeout limit
	 * @since  __DEPLOY_VERSION__
	 */
	protected $_timeout = 15;

	/**
	 * @var    integer  Transfer Type
	 * @since  __DEPLOY_VERSION__
	 */
	protected $_type = null;

	/**
	 * @var    array  Array to hold ascii format file extensions
	 * @since  __DEPLOY_VERSION__
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
	 * FtpClient object constructor
	 *
	 * @param   array  $options  Associative array of options to set
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function __construct(array $options = array())
	{
		// If default transfer type is not set, set it to autoAscii detect
		if (!isset($options['type']))
		{
			$options['type'] = FTP_BINARY;
		}

		$this->setOptions($options);
	}

	/**
	 * FtpClient object destructor
	 *
	 * Closes an existing connection, if we have one
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function __destruct()
	{
		if ($this->isConnected())
		{
			$this->quit();
		}
	}

	/**
	 * Set client options
	 *
	 * @param   array  $options  Associative array of options to set
	 *
	 * @return  boolean  True if successful
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function setOptions(array $options)
	{
		if (isset($options['type']))
		{
			$this->_type = $options['type'];
		}

		if (isset($options['timeout']))
		{
			$this->_timeout = $options['timeout'];
		}

		return true;
	}

	/**
	 * Method to connect to a FTP server
	 *
	 * @param   string  $host  Host to connect to [Default: 127.0.0.1]
	 * @param   int     $port  Port to connect on [Default: port 21]
	 *
	 * @return  boolean  True if successful
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	abstract public function connect($host = '127.0.0.1', $port = 21);

	/**
	 * Method to determine if the object is connected to an FTP server
	 *
	 * @return  boolean  True if connected
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	abstract public function isConnected();

	/**
	 * Method to login to a server once connected
	 *
	 * @param   string  $user  Username to login to the server
	 * @param   string  $pass  Password to login to the server
	 *
	 * @return  boolean  True if successful
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	abstract public function login($user = 'anonymous', $pass = 'jftp@joomla.org');

	/**
	 * Method to quit and close the connection
	 *
	 * @return  boolean  True if successful
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	abstract public function quit();

	/**
	 * Method to retrieve the current working directory on the FTP server
	 *
	 * @return  string   Current working directory
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	abstract public function pwd();

	/**
	 * Method to system string from the FTP server
	 *
	 * @return  string   System identifier string
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	abstract public function syst();

	/**
	 * Method to change the current working directory on the FTP server
	 *
	 * @param   string  $path  Path to change into on the server
	 *
	 * @return  boolean True if successful
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	abstract public function chdir($path);

	/**
	 * Method to reinitialise the server, ie. need to login again
	 *
	 * NOTE: This command not available on all servers
	 *
	 * @return  boolean  True if successful
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	abstract public function reinit();

	/**
	 * Method to rename a file/folder on the FTP server
	 *
	 * @param   string  $from  Path to change file/folder from
	 * @param   string  $to    Path to change file/folder to
	 *
	 * @return  boolean  True if successful
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	abstract public function rename($from, $to);

	/**
	 * Method to change mode for a path on the FTP server
	 *
	 * @param   string  $path  Path to change mode on
	 * @param   mixed   $mode  Octal value to change mode to, e.g. '0777', 0777 or 511 (string or integer)
	 *
	 * @return  boolean  True if successful
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	abstract public function chmod($path, $mode);

	/**
	 * Method to delete a path [file/folder] on the FTP server
	 *
	 * @param   string  $path  Path to delete
	 *
	 * @return  boolean  True if successful
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	abstract public function delete($path);

	/**
	 * Method to create a directory on the FTP server
	 *
	 * @param   string  $path  Directory to create
	 *
	 * @return  boolean  True if successful
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	abstract public function mkdir($path);

	/**
	 * Method to restart data transfer at a given byte
	 *
	 * @param   integer  $point  Byte to restart transfer at
	 *
	 * @return  boolean  True if successful
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	abstract public function restart($point);

	/**
	 * Method to create an empty file on the FTP server
	 *
	 * @param   string  $path  Path local file to store on the FTP server
	 *
	 * @return  boolean  True if successful
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	abstract public function create($path);

	/**
	 * Method to read a file from the FTP server's contents into a buffer
	 *
	 * @param   string  $remote   Path to remote file to read on the FTP server
	 * @param   string  &$buffer  Buffer variable to read file contents into
	 *
	 * @return  boolean  True if successful
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	abstract public function read($remote, &$buffer);

	/**
	 * Method to get a file from the FTP server and save it to a local file
	 *
	 * @param   string  $local   Local path to save remote file to
	 * @param   string  $remote  Path to remote file to get on the FTP server
	 *
	 * @return  boolean  True if successful
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	abstract public function get($local, $remote);

	/**
	 * Method to store a file to the FTP server
	 *
	 * @param   string  $local   Path to local file to store on the FTP server
	 * @param   string  $remote  FTP path to file to create
	 *
	 * @return  boolean  True if successful
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	abstract public function store($local, $remote = null);

	/**
	 * Method to write a string to the FTP server
	 *
	 * @param   string  $remote  FTP path to file to write to
	 * @param   string  $buffer  Contents to write to the FTP server
	 *
	 * @return  boolean  True if successful
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	abstract public function write($remote, $buffer);

	/**
	 * Method to append a string to the FTP server
	 *
	 * @param   string  $remote  FTP path to file to append to
	 * @param   string  $buffer  Contents to append to the FTP server
	 *
	 * @return  boolean  True if successful
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	abstract public function append($remote, $buffer);

	/**
	 * Get the size of the remote file.
	 *
	 * @param   string  $remote  FTP path to file whose size to get
	 *
	 * @return  mixed  number of bytes or false on error
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	abstract public function size($remote);

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
	 * @since   __DEPLOY_VERSION__
	 */
	abstract public function listNames($path = null);

	/**
	 * Method to list the contents of a directory on the FTP server
	 *
	 * @param   string  $path  Path to the local file to be stored on the FTP server
	 * @param   string  $type  Return type [raw|all|folders|files]
	 *
	 * @return  mixed  If $type is raw: string Directory listing, otherwise array of string with file-names
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	abstract public function listDetails($path = null, $type = 'all');

	/**
	 * Parse the raw list of files and folders in a standard array structure
	 *
	 * @param   array   $contents  The array containing lines from the ftp server output
	 * @param   string  $type      Return type [raw|all|folders|files]
	 *
	 * @return  array|boolean
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected function parseList($contents, $type)
	{
		// If we received the listing of an empty directory, we are done as well
		if (empty($contents[0]))
		{
			return array();
		}

		// If the server returned the number of results in the first response, let's dump it
		if (strtolower(substr($contents[0], 0, 6)) == 'total ')
		{
			array_shift($contents);

			if (!isset($contents[0]) || empty($contents[0]))
			{
				return array();
			}
		}

		// Regular expressions for the directory listing parsing.
		$regexps = array(
			'UNIX' => '#([-dl][rwxstST-]+).* ([0-9]*) ([a-zA-Z0-9]+).* ([a-zA-Z0-9]+).* ([0-9]*)'
				. ' ([a-zA-Z]+[0-9: ]*[0-9])[ ]+(([0-9]{1,2}:[0-9]{2})|[0-9]{4}) (.+)#',
			'MAC'  => '#([-dl][rwxstST-]+).* ?([0-9 ]*)?([a-zA-Z0-9]+).* ([a-zA-Z0-9]+).* ([0-9]*)'
				. ' ([a-zA-Z]+[0-9: ]*[0-9])[ ]+(([0-9]{2}:[0-9]{2})|[0-9]{4}) (.+)#',
			'WIN'  => '#([0-9]{2})-([0-9]{2})-([0-9]{2}) +([0-9]{2}):([0-9]{2})(AM|PM) +([0-9]+|<DIR>) +(.+)#',
		);

		// Find out the format of the directory listing by matching one of the regexps
		$osType = null;
		$regexp = null;

		foreach ($regexps as $k => $v)
		{
			if (@preg_match($v, $contents[0]))
			{
				$osType = $k;
				$regexp = $v;
				break;
			}
		}

		if (!$osType)
		{
			Log::add(\JText::_('JLIB_CLIENT_ERROR_JFTP_LISTDETAILS_UNRECOGNISED'), Log::WARNING, 'jerror');

			return false;
		}

		$dir_list = array();

		// Here is where it is going to get dirty....
		foreach ($contents as $file)
		{
			$tmp_array = null;
			$regs      = null;

			if (@preg_match($regexp, $file, $regs))
			{
				if ($osType == 'UNIX' || $osType == 'MAC')
				{
					$fType = (int) strpos('-dl', $regs[1]{0});

					// $tmp_array['line'] = $regs[0];
					$tmp_array['type']   = $fType;
					$tmp_array['rights'] = $regs[1];

					// $tmp_array['number'] = $regs[2];
					$tmp_array['user']  = $regs[3];
					$tmp_array['group'] = $regs[4];
					$tmp_array['size']  = $regs[5];
					$tmp_array['date']  = @date('m-d', strtotime($regs[6]));
					$tmp_array['time']  = $regs[7];
					$tmp_array['name']  = $regs[9];
				}
				else
				{
					$fType     = (int) ($regs[7] == '<DIR>');
					$timestamp = strtotime("$regs[3]-$regs[1]-$regs[2] $regs[4]:$regs[5]$regs[6]");

					// $tmp_array['line'] = $regs[0];
					$tmp_array['type']   = $fType;
					$tmp_array['rights'] = '';

					// $tmp_array['number'] = 0;
					$tmp_array['user']  = '';
					$tmp_array['group'] = '';
					$tmp_array['size']  = (int) $regs[7];
					$tmp_array['date']  = date('m-d', $timestamp);
					$tmp_array['time']  = date('H:i', $timestamp);
					$tmp_array['name']  = $regs[8];
				}
			}

			if (!is_array($tmp_array))
			{
				continue;
			}

			// If we just want files, do not add a folder
			if ($type == 'files' && $tmp_array['type'] == 1)
			{
				continue;
			}

			// If we just want folders, do not add a file
			if ($type == 'folders' && $tmp_array['type'] == 0)
			{
				continue;
			}

			// Exclude dot items
			if ($tmp_array['name'] == '.' || $tmp_array['name'] == '..')
			{
				continue;
			}

			$dir_list[] = $tmp_array;
		}

		return $dir_list;
	}

	/**
	 * Method to find out the correct transfer mode for a specific file
	 *
	 * @param   string  $fileName  Name of the file
	 *
	 * @return  integer Transfer-mode for this filetype [FTP_ASCII|FTP_BINARY]
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected function findMode($fileName)
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
}
