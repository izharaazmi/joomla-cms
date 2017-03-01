<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_admin
 *
 * @copyright   Copyright (C) 2005 - 2017 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

/**
 * Script file of Joomla CMS
 *
 * @since  1.6.4
 */
class JoomlaInstallerScript
{
	/**
	 * The Joomla Version we are updating from
	 *
	 * @var    string
	 * @since  3.7
	 */
	protected $fromVersion = null;

	/**
	 * Function to act prior to installation process begins
	 *
	 * @param   string      $action     Which action is happening (install|uninstall|discover_install|update)
	 * @param   JInstaller  $installer  The class calling this method
	 *
	 * @return  boolean  True on success
	 *
	 * @since   3.7.0
	 */
	public function preflight($action, $installer)
	{
		if ($action === 'update')
		{
			// Get the version we are updating from
			if (!empty($installer->extension->manifest_cache))
			{
				$manifestValues = json_decode($installer->extension->manifest_cache, true);

				if ((array_key_exists('version', $manifestValues)))
				{
					$this->fromVersion = $manifestValues['version'];

					return true;
				}
			}

			return false;
		}

		return true;
	}

	/**
	 * Method to update Joomla!
	 *
	 * @param   JInstaller  $installer  The class calling this method
	 *
	 * @return  void
	 */
	public function update($installer)
	{
		$options['format']    = '{DATE}\t{TIME}\t{LEVEL}\t{CODE}\t{MESSAGE}';
		$options['text_file'] = 'joomla_update.php';

		JLog::addLogger($options, JLog::INFO, array('Update', 'databasequery', 'jerror'));

		try
		{
			JLog::add(JText::_('COM_JOOMLAUPDATE_UPDATE_LOG_DELETE_FILES'), JLog::INFO, 'Update');
		}
		catch (RuntimeException $exception)
		{
			// Informational log only
		}

		// This needs to stay for 2.5 update compatibility
		$this->deleteUnexistingFiles();
		$this->updateManifestCaches();
		$this->updateDatabase();
		$this->clearRadCache();
		$this->updateAssets($installer);
		$this->clearStatsCache();
		$this->convertTablesToUtf8mb4(true);
		$this->cleanJoomlaCache();

		// VERY IMPORTANT! THIS METHOD SHOULD BE CALLED LAST, SINCE IT COULD
		// LOGOUT ALL THE USERS
		$this->flushSessions();
	}

	/**
	 * Called after any type of action
	 *
	 * @param   string      $action     Which action is happening (install|uninstall|discover_install|update)
	 * @param   JInstaller  $installer  The class calling this method
	 *
	 * @return  boolean  True on success
	 *
	 * @since   3.7.0
	 */
	public function postflight($action, $installer)
	{
		if ($action === 'update')
		{
			if (!empty($this->fromVersion) && version_compare($this->fromVersion, '3.7.0', 'lt'))
			{
				/*
				 * Do a check if the menu item exists, skip if it does. Only needed when we are in pre stable state.
				 */
				$db = JFactory::getDbo();

				$query = $db->getQuery(true)
					->select('id')
					->from($db->quoteName('#__menu'))
					->where($db->quoteName('menutype') . ' = ' . $db->quote('main'))
					->where($db->quoteName('title') . ' = ' . $db->quote('com_associations'))
					->where($db->quoteName('client_id') . ' = 1')
					->where($db->quoteName('component_id') . ' = 34');

				$result = $db->setQuery($query)->loadResult();

				if (!empty($result))
				{
					return true;
				}

				/*
				 * Add a menu item for com_associations, we need to do that here because with a plain sql statement we
				 * damage the nested set structure for the menu table
				 */
				$newMenuItem = JTable::getInstance('Menu');

				$data              = array();
				$data['menutype']  = 'main';
				$data['title']     = 'com_associations';
				$data['alias']     = 'Multilingual Associations';
				$data['path']      = 'Multilingual Associations';
				$data['link']      = 'index.php?option=com_associations';
				$data['type']      = 'component';
				$data['published'] = 1;
				$data['parent_id'] = 1;

				// We have used a SQL Statement to add the extension so using 34 is safe (fingers crossed)
				$data['component_id'] = 34;
				$data['img']          = 'class:associations';
				$data['language']     = '*';
				$data['client_id']    = 1;

				$newMenuItem->setLocation($data['parent_id'], 'last-child');

				if (!$newMenuItem->save($data))
				{
					// Install failed, roll back changes
					$installer->abort(JText::sprintf('JLIB_INSTALLER_ABORT_COMP_INSTALL_ROLLBACK', $newMenuItem->getError()));

					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Method to clear our stats plugin cache to ensure we get fresh data on Joomla Update
	 *
	 * @return  void
	 *
	 * @since   3.5
	 */
	protected function clearStatsCache()
	{
		$db = JFactory::getDbo();

		try
		{
			// Get the params for the stats plugin
			$params = $db->setQuery(
				$db->getQuery(true)
					->select($db->quoteName('params'))
					->from($db->quoteName('#__extensions'))
					->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
					->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
					->where($db->quoteName('element') . ' = ' . $db->quote('stats'))
			)->loadResult();
		}
		catch (Exception $e)
		{
			echo JText::sprintf('JLIB_DATABASE_ERROR_FUNCTION_FAILED', $e->getCode(), $e->getMessage()) . '<br />';

			return;
		}

		$params = json_decode($params, true);

		// Reset the last run parameter
		if (isset($params['lastrun']))
		{
			$params['lastrun'] = '';
		}

		$params = json_encode($params);

		$query = $db->getQuery(true)
			->update($db->quoteName('#__extensions'))
			->set($db->quoteName('params') . ' = ' . $db->quote($params))
			->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
			->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
			->where($db->quoteName('element') . ' = ' . $db->quote('stats'));

		try
		{
			$db->setQuery($query)->execute();
		}
		catch (Exception $e)
		{
			echo JText::sprintf('JLIB_DATABASE_ERROR_FUNCTION_FAILED', $e->getCode(), $e->getMessage()) . '<br />';

			return;
		}
	}

	/**
	 * Method to update Database
	 *
	 * @return  void
	 */
	protected function updateDatabase()
	{
		if (JFactory::getDbo()->getServerType() === 'mysql')
		{
			$this->updateDatabaseMysql();
		}

		$this->uninstallEosPlugin();
		$this->removeJedUpdateserver();
	}

	/**
	 * Method to update MySQL Database
	 *
	 * @return  void
	 */
	protected function updateDatabaseMysql()
	{
		$db = JFactory::getDbo();

		$db->setQuery('SHOW ENGINES');

		try
		{
			$results = $db->loadObjectList();
		}
		catch (Exception $e)
		{
			echo JText::sprintf('JLIB_DATABASE_ERROR_FUNCTION_FAILED', $e->getCode(), $e->getMessage()) . '<br />';

			return;
		}

		foreach ($results as $result)
		{
			if ($result->Support != 'DEFAULT')
			{
				continue;
			}

			$db->setQuery('ALTER TABLE #__update_sites_extensions ENGINE = ' . $result->Engine);

			try
			{
				$db->execute();
			}
			catch (Exception $e)
			{
				echo JText::sprintf('JLIB_DATABASE_ERROR_FUNCTION_FAILED', $e->getCode(), $e->getMessage()) . '<br />';

				return;
			}

			break;
		}
	}

	/**
	 * Uninstall the 2.5 EOS plugin
	 *
	 * @return  void
	 */
	protected function uninstallEosPlugin()
	{
		$db = JFactory::getDbo();

		// Check if the 2.5 EOS plugin is present and uninstall it if so
		$id = $db->setQuery(
			$db->getQuery(true)
				->select('extension_id')
				->from('#__extensions')
				->where('name = ' . $db->quote('PLG_EOSNOTIFY'))
		)->loadResult();

		if (!$id)
		{
			return;
		}

		// We need to unprotect the plugin so we can uninstall it
		$db->setQuery(
			$db->getQuery(true)
				->update('#__extensions')
				->set('protected = 0')
				->where($db->quoteName('extension_id') . ' = ' . $id)
		)->execute();

		$installer = new JInstaller;
		$installer->uninstall('plugin', $id);
	}

	/**
	 * Remove the never used JED Updateserver
	 *
	 * @return  void
	 *
	 * @since   3.7.0
	 */
	protected function removeJedUpdateserver()
	{
		$db = JFactory::getDbo();

		try
		{
			// Get the update site ID of the JED Update server
			$id = $db->setQuery(
				$db->getQuery(true)
					->select('update_site_id')
					->from($db->quoteName('#__update_sites'))
					->where($db->quoteName('location') . ' = ' . $db->quote('https://update.joomla.org/jed/list.xml'))
			)->loadResult();

            if (!$id)
            {
                return;
            }

			// Delete from update sites
			$db->setQuery(
				$db->getQuery(true)
					->delete($db->quoteName('#__update_sites'))
					->where($db->quoteName('update_site_id') . ' = ' . $id)
			)->execute();

			// Delete from update sites extensions
			$db->setQuery(
				$db->getQuery(true)
					->delete($db->quoteName('#__update_sites_extensions'))
					->where($db->quoteName('update_site_id') . ' = ' . $id)
			)->execute();
		}
		catch (Exception $e)
		{
			echo JText::sprintf('JLIB_DATABASE_ERROR_FUNCTION_FAILED', $e->getCode(), $e->getMessage()) . '<br />';

			return;
		}
	}

	/**
	 * Update the manifest caches
	 *
	 * @return  void
	 *
	 * @since   1.7.3
	 */
	protected function updateManifestCaches()
	{
		$extensions = array(
			// Components
			// `type`, `element`, `folder`, `client_id`
			array('component', 'com_mailto', '', 0),
			array('component', 'com_wrapper', '', 0),
			array('component', 'com_admin', '', 1),
			array('component', 'com_ajax', '', 1),
			array('component', 'com_banners', '', 1),
			array('component', 'com_cache', '', 1),
			array('component', 'com_categories', '', 1),
			array('component', 'com_checkin', '', 1),
			array('component', 'com_contact', '', 1),
			array('component', 'com_cpanel', '', 1),
			array('component', 'com_installer', '', 1),
			array('component', 'com_languages', '', 1),
			array('component', 'com_login', '', 1),
			array('component', 'com_media', '', 1),
			array('component', 'com_menus', '', 1),
			array('component', 'com_messages', '', 1),
			array('component', 'com_modules', '', 1),
			array('component', 'com_newsfeeds', '', 1),
			array('component', 'com_plugins', '', 1),
			array('component', 'com_search', '', 1),
			array('component', 'com_templates', '', 1),
			array('component', 'com_content', '', 1),
			array('component', 'com_config', '', 1),
			array('component', 'com_redirect', '', 1),
			array('component', 'com_users', '', 1),
			array('component', 'com_finder', '', 1),
			array('component', 'com_tags', '', 1),
			array('component', 'com_contenthistory', '', 1),
			array('component', 'com_postinstall', '', 1),
			array('component', 'com_joomlaupdate', '', 1),
			array('component', 'com_fields', '', 1),
			array('component', 'com_associations', '', 1),

			// Libraries
			array('library', 'phputf8', '', 0),
			array('library', 'joomla', '', 0),
			array('library', 'idna_convert', '', 0),
			array('library', 'fof', '', 0),
			array('library', 'phpass', '', 0),

			// Modules
			// - Site
			array('module', 'mod_articles_archive', '', 0),
			array('module', 'mod_articles_latest', '', 0),
			array('module', 'mod_articles_popular', '', 0),
			array('module', 'mod_banners', '', 0),
			array('module', 'mod_breadcrumbs', '', 0),
			array('module', 'mod_custom', '', 0),
			array('module', 'mod_feed', '', 0),
			array('module', 'mod_footer', '', 0),
			array('module', 'mod_login', '', 0),
			array('module', 'mod_menu', '', 0),
			array('module', 'mod_articles_news', '', 0),
			array('module', 'mod_random_image', '', 0),
			array('module', 'mod_related_items', '', 0),
			array('module', 'mod_search', '', 0),
			array('module', 'mod_stats', '', 0),
			array('module', 'mod_syndicate', '', 0),
			array('module', 'mod_users_latest', '', 0),
			array('module', 'mod_whosonline', '', 0),
			array('module', 'mod_wrapper', '', 0),
			array('module', 'mod_articles_category', '', 0),
			array('module', 'mod_articles_categories', '', 0),
			array('module', 'mod_languages', '', 0),
			array('module', 'mod_tags_popular', '', 0),
			array('module', 'mod_tags_similar', '', 0),

			// - Administrator
			array('module', 'mod_custom', '', 1),
			array('module', 'mod_feed', '', 1),
			array('module', 'mod_latest', '', 1),
			array('module', 'mod_logged', '', 1),
			array('module', 'mod_login', '', 1),
			array('module', 'mod_menu', '', 1),
			array('module', 'mod_popular', '', 1),
			array('module', 'mod_quickicon', '', 1),
			array('module', 'mod_stats_admin', '', 1),
			array('module', 'mod_status', '', 1),
			array('module', 'mod_submenu', '', 1),
			array('module', 'mod_title', '', 1),
			array('module', 'mod_toolbar', '', 1),
			array('module', 'mod_multilangstatus', '', 1),

			// Plugins
			array('plugin', 'gmail', 'authentication', 0),
			array('plugin', 'joomla', 'authentication', 0),
			array('plugin', 'ldap', 'authentication', 0),
			array('plugin', 'contact', 'content', 0),
			array('plugin', 'emailcloak', 'content', 0),
			array('plugin', 'loadmodule', 'content', 0),
			array('plugin', 'pagebreak', 'content', 0),
			array('plugin', 'pagenavigation', 'content', 0),
			array('plugin', 'vote', 'content', 0),
			array('plugin', 'codemirror', 'editors', 0),
			array('plugin', 'none', 'editors', 0),
			array('plugin', 'tinymce', 'editors', 0),
			array('plugin', 'article', 'editors-xtd', 0),
			array('plugin', 'image', 'editors-xtd', 0),
			array('plugin', 'pagebreak', 'editors-xtd', 0),
			array('plugin', 'readmore', 'editors-xtd', 0),
			array('plugin', 'categories', 'search', 0),
			array('plugin', 'contacts', 'search', 0),
			array('plugin', 'content', 'search', 0),
			array('plugin', 'newsfeeds', 'search', 0),
			array('plugin', 'tags', 'search', 0),
			array('plugin', 'languagefilter', 'system', 0),
			array('plugin', 'p3p', 'system', 0),
			array('plugin', 'cache', 'system', 0),
			array('plugin', 'debug', 'system', 0),
			array('plugin', 'log', 'system', 0),
			array('plugin', 'redirect', 'system', 0),
			array('plugin', 'remember', 'system', 0),
			array('plugin', 'sef', 'system', 0),
			array('plugin', 'logout', 'system', 0),
			array('plugin', 'contactcreator', 'user', 0),
			array('plugin', 'joomla', 'user', 0),
			array('plugin', 'profile', 'user', 0),
			array('plugin', 'joomla', 'extension', 0),
			array('plugin', 'joomla', 'content', 0),
			array('plugin', 'languagecode', 'system', 0),
			array('plugin', 'joomlaupdate', 'quickicon', 0),
			array('plugin', 'extensionupdate', 'quickicon', 0),
			array('plugin', 'recaptcha', 'captcha', 0),
			array('plugin', 'categories', 'finder', 0),
			array('plugin', 'contacts', 'finder', 0),
			array('plugin', 'content', 'finder', 0),
			array('plugin', 'newsfeeds', 'finder', 0),
			array('plugin', 'tags', 'finder', 0),
			array('plugin', 'totp', 'twofactorauth', 0),
			array('plugin', 'yubikey', 'twofactorauth', 0),
			array('plugin', 'updatenotification', 'system', 0),
			array('plugin', 'module', 'editors-xtd', 0),
			array('plugin', 'stats', 'system', 0),
			array('plugin', 'packageinstaller', 'installer', 0),
			array('plugin', 'folderinstaller', 'installer', 0),
			array('plugin', 'urlinstaller', 'installer', 0),
			array('plugin', 'phpversioncheck', 'quickicon', 0),
			array('plugin', 'menu', 'editors-xtd', 0),
			array('plugin', 'contact', 'editors-xtd', 0),
			array('plugin', 'fields', 'system', 0),
			array('plugin', 'calendar', 'fields', 0),
			array('plugin', 'checkboxes', 'fields', 0),
			array('plugin', 'color', 'fields', 0),
			array('plugin', 'editor', 'fields', 0),
			array('plugin', 'gallery', 'fields', 0),
			array('plugin', 'imagelist', 'fields', 0),
			array('plugin', 'integer', 'fields', 0),
			array('plugin', 'list', 'fields', 0),
			array('plugin', 'media', 'fields', 0),
			array('plugin', 'radio', 'fields', 0),
			array('plugin', 'sql', 'fields', 0),
			array('plugin', 'text', 'fields', 0),
			array('plugin', 'textarea', 'fields', 0),
			array('plugin', 'url', 'fields', 0),
			array('plugin', 'user', 'fields', 0),
			array('plugin', 'usergrouplist', 'fields', 0),
			array('plugin', 'fields', 'content', 0),
			array('plugin', 'fields', 'editors-xtd', 0),

			// Templates
			array('template', 'beez3', '', 0),
			array('template', 'hathor', '', 1),
			array('template', 'protostar', '', 0),
			array('template', 'isis', '', 1),

			// Languages
			array('language', 'en-GB', '', 0),
			array('language', 'en-GB', '', 1),

			// Files
			array('file', 'joomla', '', 0),

			// Packages
			array('package', 'pkg_en-GB', '', 0),
		);

		// Attempt to refresh manifest caches
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true)
			->select('*')
			->from('#__extensions');

		foreach ($extensions as $extension)
		{
			$query->where(
				'type=' . $db->quote($extension[0])
				. ' AND element=' . $db->quote($extension[1])
				. ' AND folder=' . $db->quote($extension[2])
				. ' AND client_id=' . $extension[3], 'OR'
			);
		}

		$db->setQuery($query);

		try
		{
			$extensions = $db->loadObjectList();
		}
		catch (Exception $e)
		{
			echo JText::sprintf('JLIB_DATABASE_ERROR_FUNCTION_FAILED', $e->getCode(), $e->getMessage()) . '<br />';

			return;
		}

		$installer = new JInstaller;

		foreach ($extensions as $extension)
		{
			if (!$installer->refreshManifestCache($extension->extension_id))
			{
				echo JText::sprintf('FILES_JOOMLA_ERROR_MANIFEST', $extension->type, $extension->element, $extension->name, $extension->client_id) . '<br />';
			}
		}
	}

	/**
	 * Delete files that should not exist
	 *
	 * @return  void
	 *
	 * @since   1.7.3
	 */
	public function deleteUnexistingFiles()
	{
		jimport('joomla.filesystem.file');
		jimport('joomla.filesystem.folder');

		$files = $this->getFilesToRemove();

		foreach ($files as $file)
		{
			if (JFile::exists(JPATH_ROOT . '/' . $file) && !JFile::delete(JPATH_ROOT . '/' . $file))
			{
				echo JText::sprintf('FILES_JOOMLA_ERROR_FILE_FOLDER', $file) . '<br />';
			}
		}

		// Fetch folders list only after processing files list.
		$folders = $this->getFoldersToRemove();

		// TODO There is an issue while deleting folders using the ftp mode
		foreach ($folders as $folder)
		{
			if (JFolder::exists(JPATH_ROOT . '/' . $folder) && !JFolder::delete(JPATH_ROOT . '/' . $folder))
			{
				echo JText::sprintf('FILES_JOOMLA_ERROR_FILE_FOLDER', $folder) . '<br />';
			}
		}

		/*
		 * Needed for updates post-3.4
		 * If com_weblinks doesn't exist then assume we can delete the weblinks package manifest (included in the update packages)
		 */
		if (!JFile::exists(JPATH_ROOT . '/administrator/components/com_weblinks/weblinks.php')
			&& JFile::exists(JPATH_ROOT . '/administrator/manifests/packages/pkg_weblinks.xml'))
		{
			JFile::delete(JPATH_ROOT . '/administrator/manifests/packages/pkg_weblinks.xml');
		}
	}

	/**
	 * Retrieve the list of files that should be removed from this version of Joomla if existing from an older version
	 *
	 * @return  string[]
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected function getFilesToRemove()
	{
		static $files = array();

		if (!$files)
		{
			/**
			 * Files that should not be deleted on updates.
			 * If we remove any file from repository that should still not be removed in the users Joomla installation we'd need to add it here.
			 */
			$doNotDelete = array(
				'administrator/logs/index.html',
				'configuration.php',
				'CONTRIBUTING.md',
				'logs/index.html',
				'robots.txt',
			);

			// Load the file list from delete.lst file if not already
			if ($tmp = file(__DIR__ . '/files/delete.lst'))
			{
				$files = array_map('rtrim', $tmp);
			}

			$files = array_diff($files, $doNotDelete);
		}

		return $files;
	}

	/**
	 * Retrieve the list of files that should be removed from this version of Joomla if existing from an older version
	 *
	 * @return  string[]
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected function getFoldersToRemove()
	{
		static $folders = array();

		if (!$folders)
		{
			$files = $this->getFilesToRemove();
			$paths = array_unique(array_map('dirname', $files));

			/**
			 * Files that should not be deleted on updates.
			 * If we remove any file from repository that should still not be removed in the users Joomla installation we'd need to add it here.
			 */
			$doNotDelete = array(
				'administrator/logs',
				'logs',
			);

			foreach ($paths as $path)
			{
				// Only add  empty folders to delete queue
				if (is_dir($path) && count(scandir($path)) == 2)
				{
					$folders[] = $path;
				}
			}

			$folders = array_diff($folders, $doNotDelete);
		}

		return $folders;
	}

	/**
	 * Clears the RAD layer's table cache.
	 *
	 * The cache vastly improves performance but needs to be cleared every time you update the database schema.
	 *
	 * @return  void
	 *
	 * @since   3.2
	 */
	protected function clearRadCache()
	{
		jimport('joomla.filesystem.file');

		if (JFile::exists(JPATH_ROOT . '/cache/fof/cache.php'))
		{
			JFile::delete(JPATH_ROOT . '/cache/fof/cache.php');
		}
	}

	/**
	 * Method to create assets for newly installed components
	 *
	 * @param   JInstaller  $installer  The class calling this method
	 *
	 * @return  boolean
	 *
	 * @since   3.2
	 */
	public function updateAssets($installer)
	{
		// List all components added since 1.6
		$newComponents = array(
			'com_finder',
			'com_joomlaupdate',
			'com_tags',
			'com_contenthistory',
			'com_ajax',
			'com_postinstall',
			'com_fields',
			'com_associations',
		);

		foreach ($newComponents as $component)
		{
			/** @var JTableAsset $asset */
			$asset = JTable::getInstance('Asset');

			if ($asset->loadByName($component))
			{
				continue;
			}

			$asset->name      = $component;
			$asset->parent_id = 1;
			$asset->rules     = '{}';
			$asset->title     = $component;
			$asset->setLocation(1, 'last-child');

			if (!$asset->store())
			{
				// Install failed, roll back changes
				$installer->abort(JText::sprintf('JLIB_INSTALLER_ABORT_COMP_INSTALL_ROLLBACK', $asset->stderr(true)));

				return false;
			}
		}

		return true;
	}

	/**
	 * If we migrated the session from the previous system, flush all the active sessions.
	 * Otherwise users will be logged in, but not able to do anything since they don't have
	 * a valid session
	 *
	 * @return  boolean
	 *
	 * @since   3.4.8
	 */
	public function flushSessions()
	{
		/**
		 * The session may have not been started yet (e.g. CLI-based Joomla! update scripts). Let's make sure we do
		 * have a valid session.
		 */
		$session = JFactory::getSession();

		/**
		 * Restarting the Session require a new login for the current user so lets check if we have an active session
		 * and only restart it if not.
		 * For B/C reasons we need to use getState as isActive is not available in 2.5
		 */
		if ($session->getState() !== 'active')
		{
			$session->restart();
		}

		// If $_SESSION['__default'] is no longer set we do not have a migrated session, therefore we can quit.
		if (!isset($_SESSION['__default']))
		{
			return true;
		}

		$db = JFactory::getDbo();

		try
		{
			switch ($db->getServerType())
			{
				// MySQL database, use TRUNCATE (faster, more resilient)
				case 'mysql':
					$db->truncateTable('#__session');
					break;

				// Non-MySQL databases, use a simple DELETE FROM query
				default:
					$query = $db->getQuery(true)
						->delete($db->qn('#__session'));
					$db->setQuery($query)->execute();
					break;
			}
		}
		catch (Exception $e)
		{
			echo JText::sprintf('JLIB_DATABASE_ERROR_FUNCTION_FAILED', $e->getCode(), $e->getMessage()) . '<br />';

			return false;
		}

		return true;
	}

	/**
	 * Converts the site's database tables to support UTF-8 Multibyte.
	 *
	 * @param   boolean  $doDbFixMsg  Flag if message to be shown to check db fix
	 *
	 * @return  void
	 *
	 * @since   3.5
	 */
	public function convertTablesToUtf8mb4($doDbFixMsg = false)
	{
		$db = JFactory::getDbo();

		// This is only required for MySQL databases
		$serverType = $db->getServerType();

		if ($serverType != 'mysql')
		{
			return;
		}

		// Set required conversion status
		if ($db->hasUTF8mb4Support())
		{
			$converted = 2;
		}
		else
		{
			$converted = 1;
		}

		// Check conversion status in database
		$db->setQuery('SELECT ' . $db->quoteName('converted')
			. ' FROM ' . $db->quoteName('#__utf8_conversion')
		);

		try
		{
			$convertedDB = $db->loadResult();
		}
		catch (Exception $e)
		{
			// Render the error message from the Exception object
			JFactory::getApplication()->enqueueMessage($e->getMessage(), 'error');

			if ($doDbFixMsg)
			{
				// Show an error message telling to check database problems
				JFactory::getApplication()->enqueueMessage(JText::_('JLIB_DATABASE_ERROR_DATABASE_UPGRADE_FAILED'), 'error');
			}

			return;
		}

		// Nothing to do, saved conversion status from DB is equal to required
		if ($convertedDB == $converted)
		{
			return;
		}

		// Step 1: Drop indexes later to be added again with column lengths limitations at step 2
		$fileName1 = JPATH_ROOT . '/administrator/components/com_admin/sql/others/mysql/utf8mb4-conversion-01.sql';

		if (is_file($fileName1))
		{
			$fileContents1 = @file_get_contents($fileName1);
			$queries1      = $db->splitSql($fileContents1);

			if (!empty($queries1))
			{
				foreach ($queries1 as $query1)
				{
					try
					{
						$db->setQuery($query1)->execute();
					}
					catch (Exception $e)
					{
						// If the query fails we will go on. It just means the index to be dropped does not exist.
					}
				}
			}
		}

		// Step 2: Perform the index modifications and conversions
		$fileName2 = JPATH_ROOT . '/administrator/components/com_admin/sql/others/mysql/utf8mb4-conversion-02.sql';

		if (is_file($fileName2))
		{
			$fileContents2 = @file_get_contents($fileName2);
			$queries2      = $db->splitSql($fileContents2);

			if (!empty($queries2))
			{
				foreach ($queries2 as $query2)
				{
					try
					{
						$db->setQuery($db->convertUtf8mb4QueryToUtf8($query2))->execute();
					}
					catch (Exception $e)
					{
						$converted = 0;

						// Still render the error message from the Exception object
						JFactory::getApplication()->enqueueMessage($e->getMessage(), 'error');
					}
				}
			}
		}

		if ($doDbFixMsg && $converted == 0)
		{
			// Show an error message telling to check database problems
			JFactory::getApplication()->enqueueMessage(JText::_('JLIB_DATABASE_ERROR_DATABASE_UPGRADE_FAILED'), 'error');
		}

		// Set flag in database if the update is done.
		$db->setQuery('UPDATE ' . $db->quoteName('#__utf8_conversion')
			. ' SET ' . $db->quoteName('converted') . ' = ' . $converted . ';')->execute();
	}

	/**
	 * This method clean the Joomla Cache using the method `clean` from the com_cache model
	 *
	 * @return  void
	 *
	 * @since   3.5.1
	 */
	private function cleanJoomlaCache()
	{
		JModelLegacy::addIncludePath(JPATH_ROOT . '/administrator/components/com_cache/models');
		$model = JModelLegacy::getInstance('cache', 'CacheModel');

		// Clean frontend cache
		$model->clean();

		// Clean admin cache
		$model->setState('client_id', 1);
		$model->clean();
	}
}
