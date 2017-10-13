<?php
/**
 * Joomla! Content Management System
 *
 * @copyright  Copyright (C) 2005 - 2017 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\CMS\Client\Ftp;

defined('JPATH_PLATFORM') or die;

use Joomla\CMS\Client\FtpClient;
use Joomla\CMS\Log\Log;

/**
 * Native FTP client class
 *
 * @since  __DEPLOY_VERSION__
 */
class NativeFtpClient extends FtpClient
{
	/**
	 * FtpClient object constructor
	 *
	 * @param   array  $options  Associative array of options to set
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function __construct(array $options = array())
	{
		// Import the generic buffer stream handler
		\JLoader::import('joomla.utilities.buffer');

		// Auto-loading fails for JBuffer as the class is used as a stream handler
		\JLoader::load('JBuffer');

		parent::__construct($options);
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
	 */
	public function connect($host = '127.0.0.1', $port = 21)
	{
		// If already connected, return
		if ($this->isConnected())
		{
			return true;
		}

		$connect = @ftp_connect($host, $port, $this->_timeout);

		if ($connect === false)
		{
			Log::add(\JText::sprintf('JLIB_CLIENT_ERROR_JFTP_NO_CONNECT', $host, $port), Log::WARNING, 'jerror');

			return false;
		}

		$this->_conn = &$connect;

		// Set the timeout for this connection
		ftp_set_option($this->_conn, FTP_TIMEOUT_SEC, $this->_timeout);

		return true;
	}

	/**
	 * Method to determine if the object is connected to an FTP server
	 *
	 * @return  boolean  True if connected
	 *
	 * @since   12.1
	 */
	public function isConnected()
	{
		return is_resource($this->_conn);
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
	 */
	public function login($user = 'anonymous', $pass = 'jftp@joomla.org')
	{
		if (@ftp_login($this->_conn, $user, $pass) === false)
		{
			Log::add('JFtp::login: Unable to login', Log::WARNING, 'jerror');

			return false;
		}

		return true;
	}

	/**
	 * Method to quit and close the connection
	 *
	 * @return  boolean  True if successful
	 *
	 * @since   12.1
	 */
	public function quit()
	{
		return @ftp_close($this->_conn);
	}

	/**
	 * Method to retrieve the current working directory on the FTP server
	 *
	 * @return  string   Current working directory
	 *
	 * @since   12.1
	 */
	public function pwd()
	{
		$ret = @ftp_pwd($this->_conn);

		if ($ret === false)
		{
			Log::add(\JText::_('JLIB_CLIENT_ERROR_JFTP_PWD_BAD_RESPONSE_NATIVE'), Log::WARNING, 'jerror');

			return false;
		}

		return $ret;
	}

	/**
	 * Method to system string from the FTP server
	 *
	 * @return  string   System identifier string
	 *
	 * @since   12.1
	 */
	public function syst()
	{
		$ret = @ftp_systype($this->_conn);

		if ($ret === false)
		{
			Log::add(\JText::_('JLIB_CLIENT_ERROR_JFTP_SYST_BAD_RESPONSE_NATIVE'), Log::WARNING, 'jerror');

			return false;
		}

		// Match the system string to an OS
		$ret = stripos($ret, 'MAC') !== false ? 'MAC' : (stripos($ret, 'WIN') !== false ? 'WIN' : 'UNIX');

		// Return the os type
		return $ret;
	}

	/**
	 * Method to change the current working directory on the FTP server
	 *
	 * @param   string  $path  Path to change into on the server
	 *
	 * @return  boolean  True if successful
	 *
	 * @since   12.1
	 */
	public function chdir($path)
	{
		if (@ftp_chdir($this->_conn, $path) === false)
		{
			Log::add(\JText::_('JLIB_CLIENT_ERROR_JFTP_CHDIR_BAD_RESPONSE_NATIVE'), Log::WARNING, 'jerror');

			return false;
		}

		return true;
	}

	/**
	 * Method to reinitialise the server, ie. need to login again
	 *
	 * NOTE: This command not available on all servers
	 *
	 * @return  boolean  True if successful
	 *
	 * @since   12.1
	 */
	public function reinit()
	{
		if (@ftp_site($this->_conn, 'REIN') === false)
		{
			Log::add(\JText::_('JLIB_CLIENT_ERROR_JFTP_REINIT_BAD_RESPONSE_NATIVE'), Log::WARNING, 'jerror');

			return false;
		}

		return true;
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
	 */
	public function rename($from, $to)
	{
		if (@ftp_rename($this->_conn, $from, $to) === false)
		{
			Log::add(\JText::_('JLIB_CLIENT_ERROR_JFTP_RENAME_BAD_RESPONSE_NATIVE'), Log::WARNING, 'jerror');

			return false;
		}

		return true;
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
	 */
	public function chmod($path, $mode)
	{
		// If no filename is given, we assume the current directory is the target
		if ($path == '')
		{
			$path = '.';
		}

		// Convert the mode to a string
		if (is_int($mode))
		{
			$mode = decoct($mode);
		}

		if (@ftp_site($this->_conn, 'CHMOD ' . $mode . ' ' . $path) === false)
		{
			if (!IS_WIN)
			{
				Log::add(\JText::_('JLIB_CLIENT_ERROR_JFTP_CHMOD_BAD_RESPONSE_NATIVE'), Log::WARNING, 'jerror');
			}

			return false;
		}

		return true;
	}

	/**
	 * Method to delete a path [file/folder] on the FTP server
	 *
	 * @param   string  $path  Path to delete
	 *
	 * @return  boolean  True if successful
	 *
	 * @since   12.1
	 */
	public function delete($path)
	{
		if (@ftp_delete($this->_conn, $path) === false)
		{
			if (@ftp_rmdir($this->_conn, $path) === false)
			{
				Log::add(\JText::_('JLIB_CLIENT_ERROR_JFTP_DELETE_BAD_RESPONSE_NATIVE'), Log::WARNING, 'jerror');

				return false;
			}
		}

		return true;
	}

	/**
	 * Method to create a directory on the FTP server
	 *
	 * @param   string  $path  Directory to create
	 *
	 * @return  boolean  True if successful
	 *
	 * @since   12.1
	 */
	public function mkdir($path)
	{
		if (@ftp_mkdir($this->_conn, $path) === false)
		{
			Log::add(\JText::_('JLIB_CLIENT_ERROR_JFTP_MKDIR_BAD_RESPONSE_NATIVE'), Log::WARNING, 'jerror');

			return false;
		}

		return true;
	}

	/**
	 * Method to restart data transfer at a given byte
	 *
	 * @param   integer  $point  Byte to restart transfer at
	 *
	 * @return  boolean  True if successful
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function restart($point)
	{
		if (@ftp_site($this->_conn, 'REST ' . $point) === false)
		{
			Log::add(\JText::_('JLIB_CLIENT_ERROR_JFTP_RESTART_BAD_RESPONSE_NATIVE'), Log::WARNING, 'jerror');

			return false;
		}

		return true;
	}

	/**
	 * Method to create an empty file on the FTP server
	 *
	 * @param   string  $path  Path local file to store on the FTP server
	 *
	 * @return  boolean  True if successful
	 *
	 * @since   12.1
	 */
	public function create($path)
	{
		// Turn passive mode on
		if (@ftp_pasv($this->_conn, true) === false)
		{
			Log::add(\JText::_('JLIB_CLIENT_ERROR_JFTP_CREATE_BAD_RESPONSE_PASSIVE'), Log::WARNING, 'jerror');

			return false;
		}

		$buffer = fopen('buffer://tmp', 'r');

		if (@ftp_fput($this->_conn, $path, $buffer, FTP_ASCII) === false)
		{
			Log::add(\JText::_('JLIB_CLIENT_ERROR_JFTP_CREATE_BAD_RESPONSE_BUFFER'), Log::WARNING, 'jerror');
			fclose($buffer);

			return false;
		}

		fclose($buffer);

		return true;
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
	 */
	public function read($remote, &$buffer)
	{
		// Determine file type
		$mode = $this->_findMode($remote);

		// Turn passive mode on
		if (@ftp_pasv($this->_conn, true) === false)
		{
			Log::add(\JText::_('JLIB_CLIENT_ERROR_JFTP_READ_BAD_RESPONSE_PASSIVE'), Log::WARNING, 'jerror');

			return false;
		}

		$tmp = fopen('buffer://tmp', 'br+');

		if (@ftp_fget($this->_conn, $tmp, $remote, $mode) === false)
		{
			fclose($tmp);
			Log::add(\JText::_('JLIB_CLIENT_ERROR_JFTP_READ_BAD_RESPONSE_BUFFER'), Log::WARNING, 'jerror');

			return false;
		}

		// Read tmp buffer contents
		rewind($tmp);
		$buffer = '';

		while (!feof($tmp))
		{
			$buffer .= fread($tmp, 8192);
		}

		fclose($tmp);

		return true;
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
	 */
	public function get($local, $remote)
	{
		// Determine file type
		$mode = $this->_findMode($remote);

		// Turn passive mode on
		if (@ftp_pasv($this->_conn, true) === false)
		{
			Log::add(\JText::_('JLIB_CLIENT_ERROR_JFTP_GET_PASSIVE'), Log::WARNING, 'jerror');

			return false;
		}

		if (@ftp_get($this->_conn, $local, $remote, $mode) === false)
		{
			Log::add(\JText::_('JLIB_CLIENT_ERROR_JFTP_GET_BAD_RESPONSE'), Log::WARNING, 'jerror');

			return false;
		}

		return true;
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
	 */
	public function store($local, $remote = null)
	{
		// If remote file is not given, use the filename of the local file in the current
		// working directory.
		if ($remote == null)
		{
			$remote = basename($local);
		}

		// Determine file type
		$mode = $this->_findMode($remote);

		// Turn passive mode on
		if (@ftp_pasv($this->_conn, true) === false)
		{
			Log::add(\JText::_('JLIB_CLIENT_ERROR_JFTP_STORE_PASSIVE'), Log::WARNING, 'jerror');

			return false;
		}

		if (@ftp_put($this->_conn, $remote, $local, $mode) === false)
		{
			Log::add(\JText::_('JLIB_CLIENT_ERROR_JFTP_STORE_BAD_RESPONSE'), Log::WARNING, 'jerror');

			return false;
		}

		return true;
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
	 */
	public function write($remote, $buffer)
	{
		// Determine file type
		$mode = $this->_findMode($remote);

		// Turn passive mode on
		if (@ftp_pasv($this->_conn, true) === false)
		{
			Log::add(\JText::_('JLIB_CLIENT_ERROR_JFTP_WRITE_PASSIVE'), Log::WARNING, 'jerror');

			return false;
		}

		$tmp = fopen('buffer://tmp', 'br+');
		fwrite($tmp, $buffer);
		rewind($tmp);

		if (@ftp_fput($this->_conn, $remote, $tmp, $mode) === false)
		{
			fclose($tmp);
			Log::add(\JText::_('JLIB_CLIENT_ERROR_JFTP_WRITE_BAD_RESPONSE'), Log::WARNING, 'jerror');

			return false;
		}

		fclose($tmp);

		return true;
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
	 */
	public function append($remote, $buffer)
	{
		// Determine file type
		$mode = $this->_findMode($remote);

		// Turn passive mode on
		if (@ftp_pasv($this->_conn, true) === false)
		{
			throw new \RuntimeException(\JText::_('JLIB_CLIENT_ERROR_JFTP_APPEND_PASSIVE'), 36);
		}

		$tmp = fopen('buffer://tmp', 'bw+');
		fwrite($tmp, $buffer);
		rewind($tmp);

		$size = $this->size($remote);

		if ($size === false)
		{
			// Do something?
		}

		if (@ftp_fput($this->_conn, $remote, $tmp, $mode, $size) === false)
		{
			fclose($tmp);

			throw new \RuntimeException(\JText::_('JLIB_CLIENT_ERROR_JFTP_APPEND_BAD_RESPONSE'), 35);
		}

		fclose($tmp);

		return true;
	}

	/**
	 * Get the size of the remote file.
	 *
	 * @param   string  $remote  FTP path to file whose size to get
	 *
	 * @return  mixed  number of bytes or false on error
	 *
	 * @since   3.6.0
	 */
	public function size($remote)
	{
		$size = ftp_size($this->_conn, $remote);

		// In case ftp_size fails, try the SIZE command directly.
		if ($size === -1)
		{
			$response = ftp_raw($this->_conn, 'SIZE ' . $remote);
			$responseCode = substr($response[0], 0, 3);
			$responseMessage = substr($response[0], 4);

			if ($responseCode != '213')
			{
				throw new \RuntimeException(\JText::_('JLIB_CLIENT_ERROR_JFTP_SIZE_BAD_RESPONSE'), 35);
			}

			$size = (int) $responseMessage;
		}

		return $size;
	}

	/**
	 * Method to list the filenames of the contents of a directory on the FTP server
	 *
	 * Note: Some servers also return folder names. However, to be sure to list folders on all
	 * servers, you should use listDetails() instead if you also need to deal with folders
	 *
	 * @param   string  $path  Path local file to store on the FTP server
	 *
	 * @return  string[]|boolean  Directory listing on success, false on failure
	 *
	 * @since   12.1
	 */
	public function listNames($path = null)
	{
		// Turn passive mode on
		if (@ftp_pasv($this->_conn, true) === false)
		{
			Log::add(\JText::_('JLIB_CLIENT_ERROR_JFTP_LISTNAMES_PASSIVE'), Log::WARNING, 'jerror');

			return false;
		}

		if (($list = @ftp_nlist($this->_conn, $path)) === false)
		{
			// Workaround for empty directories on some servers
			if ($this->listDetails($path, 'files') === array())
			{
				return array();
			}

			Log::add(\JText::_('JLIB_CLIENT_ERROR_JFTP_LISTNAMES_BAD_RESPONSE'), Log::WARNING, 'jerror');

			return false;
		}

		$list = preg_replace('#^' . preg_quote($path, '#') . '[/\\\\]?#', '', $list);

		if ($keys = array_merge(array_keys($list, '.'), array_keys($list, '..')))
		{
			foreach ($keys as $key)
			{
				unset($list[$key]);
			}
		}

		return $list;
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
	 */
	public function listDetails($path = null, $type = 'all')
	{
		// Turn passive mode on
		if (@ftp_pasv($this->_conn, true) === false)
		{
			Log::add(\JText::_('JLIB_CLIENT_ERROR_JFTP_LISTDETAILS_PASSIVE'), Log::WARNING, 'jerror');

			return false;
		}

		if (($contents = @ftp_rawlist($this->_conn, $path)) === false)
		{
			Log::add(\JText::_('JLIB_CLIENT_ERROR_JFTP_LISTDETAILS_BAD_RESPONSE'), Log::WARNING, 'jerror');

			return false;
		}

		// If only raw output is requested we are done, else process the data
		if ($type == 'raw')
		{
			$contents = implode(CRLF, $contents);
		}
		else
		{
			$contents = $this->parseList($contents, $type);
		}

		return $contents;
	}
}
