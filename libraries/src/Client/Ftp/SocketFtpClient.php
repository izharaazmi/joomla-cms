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
 * Non Native FTP client class
 *
 * @since  __DEPLOY_VERSION__
 */
class SocketFtpClient extends FtpClient
{
	/**
	 * @var   int
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected $_responseCode;

	/**
	 * @var   string
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected $_responseMsg;

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

		$errno = null;
		$err   = null;

		// Connect to the FTP server.
		$this->_conn = @fsockopen($host, $port, $errno, $err, $this->_timeout);

		if (!$this->_conn)
		{
			Log::add(\JText::sprintf('JLIB_CLIENT_ERROR_JFTP_NO_CONNECT_SOCKET', $host, $port, $errno, $err), Log::WARNING, 'jerror');

			return false;
		}

		// Set the timeout for this connection
		socket_set_timeout($this->_conn, $this->_timeout, 0);

		// Check for welcome response code
		if (!$this->_verifyResponse(220))
		{
			Log::add(\JText::sprintf('JLIB_CLIENT_ERROR_JFTP_BAD_RESPONSE', $this->_response), Log::WARNING, 'jerror');

			return false;
		}

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
		// Send the username
		if (!$this->_putCmd('USER ' . $user, array(331, 503)))
		{
			Log::add(\JText::sprintf('JLIB_CLIENT_ERROR_JFTP_BAD_USERNAME', $this->_response, $user), Log::WARNING, 'jerror');

			return false;
		}

		// If we are already logged in, continue :)
		if ($this->_responseCode == 503)
		{
			return true;
		}

		// Send the password
		if (!$this->_putCmd('PASS ' . $pass, 230))
		{
			Log::add(\JText::sprintf('JLIB_CLIENT_ERROR_JFTP_BAD_PASSWORD', $this->_response, str_repeat('*', strlen($pass))), Log::WARNING, 'jerror');

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
		// Logout and close connection
		@fwrite($this->_conn, "QUIT\r\n");
		@fclose($this->_conn);

		return true;
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
		$match = array(null);

		// Send print working directory command and verify success
		if (!$this->_putCmd('PWD', 257))
		{
			Log::add(\JText::sprintf('JLIB_CLIENT_ERROR_JFTP_PWD_BAD_RESPONSE', $this->_response), Log::WARNING, 'jerror');

			return false;
		}

		// Match just the path
		preg_match('/"[^"\r\n]*"/', $this->_response, $match);

		// Return the cleaned path
		return preg_replace("/\"/", '', $match[0]);
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
		// Send print working directory command and verify success
		if (!$this->_putCmd('SYST', 215))
		{
			Log::add(\JText::sprintf('JLIB_CLIENT_ERROR_JFTP_SYST_BAD_RESPONSE', $this->_response), Log::WARNING, 'jerror');

			return false;
		}

		$ret = $this->_response;

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
		// Send change directory command and verify success
		if (!$this->_putCmd('CWD ' . $path, 250))
		{
			Log::add(\JText::sprintf('JLIB_CLIENT_ERROR_JFTP_CHDIR_BAD_RESPONSE', $this->_response, $path), Log::WARNING, 'jerror');

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
		// Send reinitialise command to the server
		if (!$this->_putCmd('REIN', 220))
		{
			Log::add(\JText::sprintf('JLIB_CLIENT_ERROR_JFTP_REINIT_BAD_RESPONSE', $this->_response), Log::WARNING, 'jerror');

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
		// Send rename from command to the server
		if (!$this->_putCmd('RNFR ' . $from, 350))
		{
			Log::add(\JText::sprintf('JLIB_CLIENT_ERROR_JFTP_RENAME_BAD_RESPONSE_FROM', $this->_response, $from), Log::WARNING, 'jerror');

			return false;
		}

		// Send rename to command to the server
		if (!$this->_putCmd('RNTO ' . $to, 250))
		{
			Log::add(\JText::sprintf('JLIB_CLIENT_ERROR_JFTP_RENAME_BAD_RESPONSE_TO', $this->_response, $to), Log::WARNING, 'jerror');

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

		// Send change mode command and verify success [must convert mode from octal]
		if (!$this->_putCmd('SITE CHMOD ' . $mode . ' ' . $path, array(200, 250)))
		{
			if (!IS_WIN)
			{
				Log::add(\JText::sprintf('JLIB_CLIENT_ERROR_JFTP_CHMOD_BAD_RESPONSE', $this->_response, $path, $mode), Log::WARNING, 'jerror');
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
		// Send delete file command and if that doesn't work, try to remove a directory
		if (!$this->_putCmd('DELE ' . $path, 250))
		{
			if (!$this->_putCmd('RMD ' . $path, 250))
			{
				Log::add(\JText::sprintf('JLIB_CLIENT_ERROR_JFTP_DELETE_BAD_RESPONSE', $this->_response, $path), Log::WARNING, 'jerror');

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
		// Send change directory command and verify success
		if (!$this->_putCmd('MKD ' . $path, 257))
		{
			Log::add(\JText::sprintf('JLIB_CLIENT_ERROR_JFTP_MKDIR_BAD_RESPONSE', $this->_response, $path), Log::WARNING, 'jerror');

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
	 * @since   12.1
	 */
	public function restart($point)
	{
		// Send restart command and verify success
		if (!$this->_putCmd('REST ' . $point, 350))
		{
			Log::add(\JText::sprintf('JLIB_CLIENT_ERROR_JFTP_RESTART_BAD_RESPONSE', $this->_response, $point), Log::WARNING, 'jerror');

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
		// Start passive mode
		if (!$this->_passive())
		{
			Log::add(\JText::_('JLIB_CLIENT_ERROR_JFTP_CREATE_BAD_RESPONSE_PASSIVE'), Log::WARNING, 'jerror');

			return false;
		}

		if (!$this->_putCmd('STOR ' . $path, array(150, 125)))
		{
			@ fclose($this->_dataconn);
			Log::add(\JText::sprintf('JLIB_CLIENT_ERROR_JFTP_CREATE_BAD_RESPONSE', $this->_response, $path), Log::WARNING, 'jerror');

			return false;
		}

		// To create a zero byte upload close the data port connection
		fclose($this->_dataconn);

		if (!$this->_verifyResponse(226))
		{
			Log::add(\JText::sprintf('JLIB_CLIENT_ERROR_JFTP_CREATE_BAD_RESPONSE_TRANSFER', $this->_response, $path), Log::WARNING, 'jerror');

			return false;
		}

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

		$this->_mode($mode);

		// Start passive mode
		if (!$this->_passive())
		{
			Log::add(\JText::_('JLIB_CLIENT_ERROR_JFTP_READ_BAD_RESPONSE_PASSIVE'), Log::WARNING, 'jerror');

			return false;
		}

		if (!$this->_putCmd('RETR ' . $remote, array(150, 125)))
		{
			@ fclose($this->_dataconn);
			Log::add(\JText::sprintf('JLIB_CLIENT_ERROR_JFTP_READ_BAD_RESPONSE', $this->_response, $remote), Log::WARNING, 'jerror');

			return false;
		}

		// Read data from data port connection and add to the buffer
		$buffer = '';

		while (!feof($this->_dataconn))
		{
			$buffer .= fread($this->_dataconn, 4096);
		}

		// Close the data port connection
		fclose($this->_dataconn);

		// Let's try to cleanup some line endings if it is ascii
		if ($mode == FTP_ASCII)
		{
			$os = 'UNIX';

			if (IS_WIN)
			{
				$os = 'WIN';
			}

			$buffer = preg_replace('/' . CRLF . '/', $this->_lineEndings[$os], $buffer);
		}

		if (!$this->_verifyResponse(226))
		{
			Log::add(\JText::sprintf('JLIB_CLIENT_ERROR_JFTP_READ_BAD_RESPONSE_TRANSFER', $this->_response, $remote), Log::WARNING, 'jerror');

			return false;
		}

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

		$this->_mode($mode);

		// Check to see if the local file can be opened for writing
		$fp = fopen($local, 'wb');

		if (!$fp)
		{
			Log::add(\JText::sprintf('JLIB_CLIENT_ERROR_JFTP_GET_WRITING_LOCAL', $local), Log::WARNING, 'jerror');

			return false;
		}

		// Start passive mode
		if (!$this->_passive())
		{
			Log::add(\JText::_('JLIB_CLIENT_ERROR_JFTP_GET_PASSIVE'), Log::WARNING, 'jerror');

			return false;
		}

		if (!$this->_putCmd('RETR ' . $remote, array(150, 125)))
		{
			@ fclose($this->_dataconn);
			Log::add(\JText::sprintf('JLIB_CLIENT_ERROR_JFTP_GET_BAD_RESPONSE_RETR', $this->_response, $remote), Log::WARNING, 'jerror');

			return false;
		}

		// Read data from data port connection and add to the buffer
		while (!feof($this->_dataconn))
		{
			$buffer = fread($this->_dataconn, 4096);
			fwrite($fp, $buffer, 4096);
		}

		// Close the data port connection and file pointer
		fclose($this->_dataconn);
		fclose($fp);

		if (!$this->_verifyResponse(226))
		{
			Log::add(\JText::sprintf('JLIB_CLIENT_ERROR_JFTP_GET_BAD_RESPONSE_TRANSFER', $this->_response, $remote), Log::WARNING, 'jerror');

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

		$this->_mode($mode);

		// Check to see if the local file exists and if so open it for reading
		if (@ file_exists($local))
		{
			$fp = fopen($local, 'rb');

			if (!$fp)
			{
				Log::add(\JText::sprintf('JLIB_CLIENT_ERROR_JFTP_STORE_READING_LOCAL', $local), Log::WARNING, 'jerror');

				return false;
			}
		}
		else
		{
			Log::add(\JText::sprintf('JLIB_CLIENT_ERROR_JFTP_STORE_FIND_LOCAL', $local), Log::WARNING, 'jerror');

			return false;
		}

		// Start passive mode
		if (!$this->_passive())
		{
			@ fclose($fp);
			Log::add(\JText::_('JLIB_CLIENT_ERROR_JFTP_STORE_PASSIVE'), Log::WARNING, 'jerror');

			return false;
		}

		// Send store command to the FTP server
		if (!$this->_putCmd('STOR ' . $remote, array(150, 125)))
		{
			@ fclose($fp);
			@ fclose($this->_dataconn);
			Log::add(\JText::sprintf('JLIB_CLIENT_ERROR_JFTP_STORE_BAD_RESPONSE_STOR', $this->_response, $remote), Log::WARNING, 'jerror');

			return false;
		}

		// Do actual file transfer, read local file and write to data port connection
		while (!feof($fp))
		{
			$line = fread($fp, 4096);

			do
			{
				if (($result = @ fwrite($this->_dataconn, $line)) === false)
				{
					Log::add(\JText::_('JLIB_CLIENT_ERROR_JFTP_STORE_DATA_PORT'), Log::WARNING, 'jerror');

					return false;
				}

				$line = substr($line, $result);
			}
			while ($line != '');
		}

		fclose($fp);
		fclose($this->_dataconn);

		if (!$this->_verifyResponse(226))
		{
			Log::add(\JText::sprintf('JLIB_CLIENT_ERROR_JFTP_STORE_BAD_RESPONSE_TRANSFER', $this->_response, $remote), Log::WARNING, 'jerror');

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

		// First we need to set the transfer mode
		$this->_mode($mode);

		// Start passive mode
		if (!$this->_passive())
		{
			Log::add(\JText::_('JLIB_CLIENT_ERROR_JFTP_WRITE_PASSIVE'), Log::WARNING, 'jerror');

			return false;
		}

		// Send store command to the FTP server
		if (!$this->_putCmd('STOR ' . $remote, array(150, 125)))
		{
			Log::add(\JText::sprintf('JLIB_CLIENT_ERROR_JFTP_WRITE_BAD_RESPONSE_STOR', $this->_response, $remote), Log::WARNING, 'jerror');
			@ fclose($this->_dataconn);

			return false;
		}

		// Write buffer to the data connection port
		do
		{
			if (($result = @ fwrite($this->_dataconn, $buffer)) === false)
			{
				Log::add(\JText::_('JLIB_CLIENT_ERROR_JFTP_WRITE_DATA_PORT'), Log::WARNING, 'jerror');

				return false;
			}

			$buffer = substr($buffer, $result);
		}
		while ($buffer != '');

		// Close the data connection port [Data transfer complete]
		fclose($this->_dataconn);

		// Verify that the server received the transfer
		if (!$this->_verifyResponse(226))
		{
			Log::add(\JText::sprintf('JLIB_CLIENT_ERROR_JFTP_WRITE_BAD_RESPONSE_TRANSFER', $this->_response, $remote), Log::WARNING, 'jerror');

			return false;
		}

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

		// First we need to set the transfer mode
		$this->_mode($mode);

		// Start passive mode
		if (!$this->_passive())
		{
			throw new \RuntimeException(\JText::_('JLIB_CLIENT_ERROR_JFTP_APPEND_PASSIVE'), 36);
		}

		// Send store command to the FTP server
		if (!$this->_putCmd('APPE ' . $remote, array(150, 125)))
		{
			@fclose($this->_dataconn);

			throw new \RuntimeException(\JText::sprintf('JLIB_CLIENT_ERROR_JFTP_APPEND_BAD_RESPONSE_APPE', $this->_response, $remote), 35);
		}

		// Write buffer to the data connection port
		do
		{
			if (($result = @ fwrite($this->_dataconn, $buffer)) === false)
			{
				throw new \RuntimeException(\JText::_('JLIB_CLIENT_ERROR_JFTP_APPEND_DATA_PORT'), 37);
			}

			$buffer = substr($buffer, $result);
		}
		while ($buffer != '');

		// Close the data connection port [Data transfer complete]
		fclose($this->_dataconn);

		// Verify that the server received the transfer
		if (!$this->_verifyResponse(226))
		{
			throw new \RuntimeException(\JText::sprintf('JLIB_CLIENT_ERROR_JFTP_APPEND_BAD_RESPONSE_TRANSFER', $this->_response, $remote), 37);
		}

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
		// Start passive mode
		if (!$this->_passive())
		{
			throw new \RuntimeException(\JText::_('JLIB_CLIENT_ERROR_JFTP_SIZE_PASSIVE'), 36);
		}

		// Send size command to the FTP server
		if (!$this->_putCmd('SIZE ' . $remote, array(213)))
		{
			@fclose($this->_dataconn);

			throw new \RuntimeException(\JText::sprintf('JLIB_CLIENT_ERROR_JFTP_SIZE_BAD_RESPONSE', $this->_response, $remote), 35);
		}

		return (int) substr($this->_responseMsg, 4);
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
		$data = null;

		// If a path exists, prepend a space
		if ($path != null)
		{
			$path = ' ' . $path;
		}

		// Start passive mode
		if (!$this->_passive())
		{
			Log::add(\JText::_('JLIB_CLIENT_ERROR_JFTP_LISTNAMES_PASSIVE'), Log::WARNING, 'jerror');

			return false;
		}

		if (!$this->_putCmd('NLST' . $path, array(150, 125)))
		{
			@ fclose($this->_dataconn);

			// Workaround for empty directories on some servers
			if ($this->listDetails($path, 'files') === array())
			{
				return array();
			}

			Log::add(\JText::sprintf('JLIB_CLIENT_ERROR_JFTP_LISTNAMES_BAD_RESPONSE_NLST', $this->_response, $path), Log::WARNING, 'jerror');

			return false;
		}

		// Read in the file listing.
		while (!feof($this->_dataconn))
		{
			$data .= fread($this->_dataconn, 4096);
		}

		fclose($this->_dataconn);

		// Everything go okay?
		if (!$this->_verifyResponse(226))
		{
			Log::add(\JText::sprintf('JLIB_CLIENT_ERROR_JFTP_LISTNAMES_BAD_RESPONSE_TRANSFER', $this->_response, $path), Log::WARNING, 'jerror');

			return false;
		}

		$data = preg_split('/[' . CRLF . ']+/', $data, -1, PREG_SPLIT_NO_EMPTY);
		$data = preg_replace('#^' . preg_quote(substr($path, 1), '#') . '[/\\\\]?#', '', $data);

		if ($keys = array_merge(array_keys($data, '.'), array_keys($data, '..')))
		{
			foreach ($keys as $key)
			{
				unset($data[$key]);
			}
		}

		return $data;
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
		$data = null;
		$regs = null;

		// TODO: Deal with recurse -- nightmare
		// For now we will just set it to false
		$recurse = false;

		// Non Native mode

		// Start passive mode
		if (!$this->_passive())
		{
			Log::add(\JText::_('JLIB_CLIENT_ERROR_JFTP_LISTDETAILS_PASSIVE'), Log::WARNING, 'jerror');

			return false;
		}

		// If a path exists, prepend a space
		if ($path != null)
		{
			$path = ' ' . $path;
		}

		// Request the file listing
		if (!$this->_putCmd(($recurse == true) ? 'LIST -R' : 'LIST' . $path, array(150, 125)))
		{
			Log::add(\JText::sprintf('JLIB_CLIENT_ERROR_JFTP_LISTDETAILS_BAD_RESPONSE_LIST', $this->_response, $path), Log::WARNING, 'jerror');
			@ fclose($this->_dataconn);

			return false;
		}

		// Read in the file listing.
		while (!feof($this->_dataconn))
		{
			$data .= fread($this->_dataconn, 4096);
		}

		fclose($this->_dataconn);

		// Everything go okay?
		if (!$this->_verifyResponse(226))
		{
			Log::add(\JText::sprintf('JLIB_CLIENT_ERROR_JFTP_LISTDETAILS_BAD_RESPONSE_TRANSFER', $this->_response, $path), Log::WARNING, 'jerror');

			return false;
		}

		// If only raw output is requested we are done, else process the data
		if ($type == 'raw')
		{
			$contents = $data;
		}
		else
		{
			$contents = $this->parseList(explode(CRLF, $data), $type);
		}

		return $contents;
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
	 */
	protected function _passive()
	{
		$match = array();
		$parts = array();
		$errno = null;
		$err = null;

		// Make sure we have a connection to the server
		if (!is_resource($this->_conn))
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
