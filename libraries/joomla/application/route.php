<?php
/**
 * @package     Joomla.Platform
 * @subpackage  Application
 *
 * @copyright   Copyright (C) 2005 - 2017 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

defined('JPATH_PLATFORM') or die;

/**
 * Route handling class
 *
 * @method  static string site($url, $xhtml = true, $ssl = null)           Create a frontend route.
 * @method  static string administrator($url, $xhtml = true, $ssl = null)  Create a backend route.
 *
 * @since  11.1
 */
class JRoute
{
	/**
	 * The route object so we don't have to keep fetching it.
	 *
	 * @var    JRouter[]
	 * @since  12.2
	 */
	private static $_router = array();

	/**
	 * Translates an internal Joomla URL to a humanly readable URL. This method builds links for the current active client.
	 *
	 * @param   string   $url    Absolute or Relative URI to Joomla resource.
	 * @param   boolean  $xhtml  Replace & by &amp; for XML compliance.
	 * @param   integer  $ssl    Secure state for the resolved URI.
	 *                             0: (default) No change, use the protocol currently used in the request
	 *                             1: Make URI secure using global secure site URI.
	 *                             2: Make URI unsecure using the global unsecure site URI.
	 *
	 * @return  string  The translated humanly readable URL.
	 *
	 * @since   11.1
	 */
	public static function _($url, $xhtml = true, $ssl = null)
	{
		try
		{
			return static::link(null, $url, $xhtml, $ssl);
		}
		catch (RuntimeException $e)
		{
			// Before __DEPLOY_VERSION__ this method failed silently on router error. This B/C will be removed in Joomla 4.0.
			return null;
		}
	}

	/**
	 * Translates an internal Joomla URL to a humanly readable URL.
	 *
	 * @param   string   $client  The client name for which to build the link. NULL to use active client.
	 * @param   string   $url     Absolute or Relative URI to Joomla resource.
	 * @param   boolean  $xhtml   Replace & by &amp; for XML compliance.
	 * @param   integer  $ssl     Secure state for the resolved URI.
	 *                              0: (default) No change, use the protocol currently used in the request
	 *                              1: Make URI secure using global secure site URI.
	 *                              2: Make URI unsecure using the global unsecure site URI.
	 *
	 * @return  string  The translated humanly readable URL.
	 *
	 * @throws  RuntimeException
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public static function link($client, $url, $xhtml = true, $ssl = null)
	{
		// If we cannot process this $url exit early.
		if (!is_array($url) && (strpos($url, '&') !== 0) && (strpos($url, 'index.php') !== 0))
		{
			return $url;
		}

		// Get the router instance.
		$app    = JFactory::getApplication();
		$client = $client ?: $app->getName();

		if (!isset(self::$_router[$client]))
		{
			self::$_router[$client] = $app->getRouter($client);
		}

		// Make sure that we have our router
		if (!self::$_router[$client])
		{
			throw new RuntimeException(JText::sprintf('JLIB_APPLICATION_ERROR_ROUTER_LOAD', $client), 500);
		}

		// Build route.
		$uri    = self::$_router[$client]->build($url);
		$scheme = array('path', 'query', 'fragment');

		/*
		 * Get the secure/unsecure URLs.
		 *
		 * If the first 5 characters of the BASE are 'https', then we are on an ssl connection over
		 * https and need to set our secure URL to the current request URL, if not, and the scheme is
		 * 'http', then we need to do a quick string manipulation to switch schemes.
		 */
		if ((int) $ssl || $uri->isSsl())
		{
			static $host_port;

			if (!is_array($host_port))
			{
				$uri2      = JUri::getInstance();
				$host_port = array($uri2->getHost(), $uri2->getPort());
			}

			// Determine which scheme we want.
			$uri->setScheme(((int) $ssl === 1 || $uri->isSsl()) ? 'https' : 'http');
			$uri->setHost($host_port[0]);
			$uri->setPort($host_port[1]);
			$scheme = array_merge($scheme, array('host', 'port', 'scheme'));
		}

		$url = $uri->toString($scheme);

		// Replace spaces.
		$url = preg_replace('/\s/u', '%20', $url);

		if ($xhtml)
		{
			$url = htmlspecialchars($url, ENT_COMPAT, 'UTF-8');
		}

		return $url;
	}

	/**
	 * Magic method to provide short access to the clients' route functions "site" and "administrator".
	 * - To build a route for site:          <var>JRoute::site($url)</var>.
	 * - To build a route for administrator: <var>JRoute::administrator($url)</var>
	 *
	 * @param   string  $name       The called method name
	 * @param   array   $arguments  The method arguments
	 *
	 * @return  string
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public static function __callStatic($name, $arguments)
	{
		array_unshift($arguments, $name);

		return forward_static_call_array(array('JRoute', 'link'), $arguments);
	}
}
