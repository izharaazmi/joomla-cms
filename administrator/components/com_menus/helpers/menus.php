<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_menus
 *
 * @copyright   Copyright (C) 2005 - 2017 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;

defined('_JEXEC') or die;

/**
 * Menus component helper.
 *
 * @since  1.6
 */
class MenusHelper
{
	/**
	 * Defines the valid request variables for the reverse lookup.
	 *
	 * @since   1.6
	 */
	protected static $_filter = array('option', 'view', 'layout');

	/**
	 * List of preset include paths
	 *
	 * @var  array
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected static $presets = null;

	/**
	 * Configure the Linkbar.
	 *
	 * @param   string  $vName  The name of the active view.
	 *
	 * @return  void
	 *
	 * @since   1.6
	 */
	public static function addSubmenu($vName)
	{
		JHtmlSidebar::addEntry(
			JText::_('COM_MENUS_SUBMENU_MENUS'),
			'index.php?option=com_menus&view=menus',
			$vName == 'menus'
		);
		JHtmlSidebar::addEntry(
			JText::_('COM_MENUS_SUBMENU_ITEMS'),
			'index.php?option=com_menus&view=items',
			$vName == 'items'
		);
	}

	/**
	 * Gets a list of the actions that can be performed.
	 *
	 * @param   integer  $parentId  The menu ID.
	 *
	 * @return  JObject
	 *
	 * @since   1.6
	 * @deprecated  3.2  Use JHelperContent::getActions() instead
	 */
	public static function getActions($parentId = 0)
	{
		// Log usage of deprecated function
		try
		{
			JLog::add(
				sprintf('%s() is deprecated. Use JHelperContent::getActions() with new arguments order instead.', __METHOD__),
				JLog::WARNING,
				'deprecated'
			);
		}
		catch (RuntimeException $exception)
		{
			// Informational log only
		}

		// Get list of actions
		return JHelperContent::getActions('com_menus');
	}

	/**
	 * Gets a standard form of a link for lookups.
	 *
	 * @param   mixed  $request  A link string or array of request variables.
	 *
	 * @return  mixed  A link in standard option-view-layout form, or false if the supplied response is invalid.
	 *
	 * @since   1.6
	 */
	public static function getLinkKey($request)
	{
		if (empty($request))
		{
			return false;
		}

		// Check if the link is in the form of index.php?...
		if (is_string($request))
		{
			$args = array();

			if (strpos($request, 'index.php') === 0)
			{
				parse_str(parse_url(htmlspecialchars_decode($request), PHP_URL_QUERY), $args);
			}
			else
			{
				parse_str($request, $args);
			}

			$request = $args;
		}

		// Only take the option, view and layout parts.
		foreach ($request as $name => $value)
		{
			if ((!in_array($name, self::$_filter)) && (!($name == 'task' && !array_key_exists('view', $request))))
			{
				// Remove the variables we want to ignore.
				unset($request[$name]);
			}
		}

		ksort($request);

		return 'index.php?' . http_build_query($request, '', '&');
	}

	/**
	 * Get the menu list for create a menu module
	 *
	 * @param   int  $clientId  Optional client id - viz 0 = site, 1 = administrator, can be NULL for all
	 *
	 * @return  array  The menu array list
	 *
	 * @since    1.6
	 */
	public static function getMenuTypes($clientId = 0)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true)
			->select('a.menutype')
			->from('#__menu_types AS a');

		if (isset($clientId))
		{
			$query->where('a.client_id = ' . (int) $clientId);
		}

		$db->setQuery($query);

		return $db->loadColumn();
	}

	/**
	 * Get a list of menu links for one or all menus.
	 *
	 * @param   string   $menuType   An option menu to filter the list on, otherwise all menu with given client id links
	 *                               are returned as a grouped array.
	 * @param   integer  $parentId   An optional parent ID to pivot results around.
	 * @param   integer  $mode       An optional mode. If parent ID is set and mode=2, the parent and children are excluded from the list.
	 * @param   array    $published  An optional array of states
	 * @param   array    $languages  Optional array of specify which languages we want to filter
	 * @param   int      $clientId   Optional client id - viz 0 = site, 1 = administrator, can be NULL for all (used only if menutype not givein)
	 *
	 * @return  array
	 *
	 * @since   1.6
	 */
	public static function getMenuLinks($menuType = null, $parentId = 0, $mode = 0, $published = array(), $languages = array(), $clientId = 0)
	{
		$db = JFactory::getDbo();
		$query = $db->getQuery(true)
			->select('DISTINCT(a.id) AS value,
					  a.title AS text,
					  a.alias,
					  a.level,
					  a.menutype,
					  a.client_id,
					  a.type,
					  a.published,
					  a.template_style_id,
					  a.checked_out,
					  a.language,
					  a.lft')
			->from('#__menu AS a');

		$query->select('e.name as componentname, e.element')
			->join('left', '#__extensions e ON e.extension_id = a.component_id');

		if (JLanguageMultilang::isEnabled())
		{
			$query->select('l.title AS language_title, l.image AS language_image, l.sef AS language_sef')
				->join('LEFT', $db->quoteName('#__languages') . ' AS l ON l.lang_code = a.language');
		}

		// Filter by the type if given, this is more specific than client id
		if ($menuType)
		{
			$query->where('(a.menutype = ' . $db->quote($menuType) . ' OR a.parent_id = 0)');
		}
		elseif (isset($clientId))
		{
			$query->where('a.client_id = ' . (int) $clientId);
		}

		// Prevent the parent and children from showing if requested.
		if ($parentId && $mode == 2)
		{
			$query->join('LEFT', '#__menu AS p ON p.id = ' . (int) $parentId)
				->where('(a.lft <= p.lft OR a.rgt >= p.rgt)');
		}

		if (!empty($languages))
		{
			if (is_array($languages))
			{
				$languages = '(' . implode(',', array_map(array($db, 'quote'), $languages)) . ')';
			}

			$query->where('a.language IN ' . $languages);
		}

		if (!empty($published))
		{
			if (is_array($published))
			{
				$published = '(' . implode(',', $published) . ')';
			}

			$query->where('a.published IN ' . $published);
		}

		$query->where('a.published != -2');
		$query->order('a.lft ASC');

		// Get the options.
		$db->setQuery($query);

		try
		{
			$links = $db->loadObjectList();
		}
		catch (RuntimeException $e)
		{
			JError::raiseWarning(500, $e->getMessage());

			return false;
		}

		if (empty($menuType))
		{
			// If the menutype is empty, group the items by menutype.
			$query->clear()
				->select('*')
				->from('#__menu_types')
				->where('menutype <> ' . $db->quote(''))
				->order('title, menutype');

			if (isset($clientId))
			{
				$query->where('client_id = ' . (int) $clientId);
			}

			$db->setQuery($query);

			try
			{
				$menuTypes = $db->loadObjectList();
			}
			catch (RuntimeException $e)
			{
				JError::raiseWarning(500, $e->getMessage());

				return false;
			}

			// Create a reverse lookup and aggregate the links.
			$rlu = array();

			foreach ($menuTypes as &$type)
			{
				$rlu[$type->menutype] = & $type;
				$type->links = array();
			}

			// Loop through the list of menu links.
			foreach ($links as &$link)
			{
				if (isset($rlu[$link->menutype]))
				{
					$rlu[$link->menutype]->links[] = & $link;

					// Cleanup garbage.
					unset($link->menutype);
				}
			}

			return $menuTypes;
		}
		else
		{
			return $links;
		}
	}

	/**
	 * Get the items associations
	 *
	 * @param   integer  $pk  Menu item id
	 *
	 * @return  array
	 *
	 * @since   3.0
	 */
	public static function getAssociations($pk)
	{
		$langAssociations = JLanguageAssociations::getAssociations('com_menus', '#__menu', 'com_menus.item', $pk, 'id', '', '');
		$associations     = array();

		foreach ($langAssociations as $langAssociation)
		{
			$associations[$langAssociation->language] = $langAssociation->id;
		}

		return $associations;
	}

	/**
	 * Add a custom preset externally via plugin or any other means.
	 * WARNING: Presets with same name will replace previously added preset *except* Joomla's default preset (joomla)
	 *
	 * @param   string  $name     The unique identifier for the preset.
	 * @param   string  $title    The display label for the preset.
	 * @param   string  $path     The path to the preset file.
	 * @param   bool    $replace  Whether to replace the preset with the same name if any (except 'joomla').
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public static function addPreset($name, $title, $path, $replace = true)
	{
		if (static::$presets === null)
		{
			static::getPresets();
		}

		if ($name == 'joomla')
		{
			$replace = false;
		}

		if (($replace || !array_key_exists($name, static::$presets)) && is_file($path))
		{
			$preset = new stdClass;

			$preset->name  = $name;
			$preset->title = $title;
			$preset->path  = $path;

			static::$presets[$name] = $preset;
		}
	}

	/**
	 * Get a list of available presets.
	 *
	 * @return  stdClass[]
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public static function getPresets()
	{
		if (static::$presets === null)
		{
			static::$presets = array();

			static::addPreset('joomla', 'COM_MENUS_PRESET_JOOMLA', dirname(__DIR__) . '/presets/joomla.xml');
			static::addPreset('modern', 'COM_MENUS_PRESET_MODERN', dirname(__DIR__) . '/presets/modern.php');

			// Load from template folder automatically
			$app = JFactory::getApplication();
			$tpl = JPATH_THEMES . '/' . $app->getTemplate() . '/html/com_menus/presets';

			if (is_dir($tpl))
			{
				jimport('joomla.filesystem.folder');

				$files = JFolder::files($tpl, '\.(xml|php)$');

				foreach ($files as $file)
				{
					$name  = substr($file, 0, -4);
					$title = str_replace('-', ' ', $name);

					static::addPreset(strtolower($name), ucwords($title), $tpl . '/' . $file);
				}
			}
		}

		return static::$presets;
	}

	/**
	 * Load the menu items from database for the given menutype
	 *
	 * @param   string   $menutype     The selected menu type
	 * @param   boolean  $enabledOnly  Whether to load only enabled/published menu items.
	 * @param   int[]    $exclude      The menu items to exclude from the list
	 *
	 * @return  array
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public static function getMenuItems($menutype, $enabledOnly = false, $exclude = array())
	{
		$db    = JFactory::getDbo();
		$query = $db->getQuery(true);

		// Prepare the query.
		$query->select('m.*')
			->from('#__menu AS m')
			->where('m.menutype = ' . $db->q($menutype))
			->where('m.client_id = 1')
			->where('m.id > 1');

		if ($enabledOnly)
		{
			$query->where('m.published = 1');
		}

		if (count($exclude))
		{
			$query->where('m.id NOT IN (' . implode(', ', array_map('intval', $exclude)) . ')');
			$query->where('m.parent_id NOT IN (' . implode(', ', array_map('intval', $exclude)) . ')');
		}

		// Filter on the enabled states.
		$query->select('e.element')
			->join('LEFT', '#__extensions AS e ON m.component_id = e.extension_id')
			->where('(e.enabled = 1 OR e.enabled IS NULL)');

		// Order by lft.
		$query->order('m.lft');

		$db->setQuery($query);

		try
		{
			$menuItems = $db->loadObjectList();

			foreach ($menuItems as &$menuitem)
			{
				$menuitem->params = new Registry($menuitem->params);
			}
		}
		catch (RuntimeException $e)
		{
			$menuItems = array();

			JFactory::getApplication()->enqueueMessage(JText::_('JERROR_AN_ERROR_HAS_OCCURRED'), 'error');
		}

		return $menuItems;
	}

	/**
	 * Load a menu tree from an XML file
	 *
	 * @param   SimpleXMLElement[]  $nodes    Name of the xml file to load
	 * @param   stdClass[]          $items    The menu hierarchy list to be populated
	 * @param   array               $replace  The substring replacements for iterator type items
	 *
	 * @return  void
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	public static function loadXml($nodes, &$items, $replace = array())
	{
		foreach ($nodes as $node)
		{
			$select = (string) $node['sql_select'];
			$from   = (string) $node['sql_from'];

			if ($select && $from)
			{
				// This is a dynamic iterator group
				$hidden = (int) $node['hidden'];
				$where  = (string) $node['sql_where'];
				$order  = (string) $node['sql_order'];

				$db    = JFactory::getDbo();
				$query = $db->getQuery(true);
				$query->select($select)->from($from);

				if ($where)
				{
					$query->where($where);
				}

				if ($order)
				{
					$query->order($order);
				}

				$results = $db->setQuery($query)->loadObjectList();

				if ($results)
				{
					// Show the iterator 'group' node only if not hidden and contains something to iterate over
					if (!$hidden)
					{
						$item = new stdClass;

						$item->type       = (string) $node['type'];
						$item->text       = (string) $node['title'];
						$item->link       = (string) $node['link'];
						$item->element    = (string) $node['element'];
						$item->class      = (string) $node['class'];
						$item->browserNav = (string) $node['target'];
						$item->access     = (int) $node['access'];
						$item->access     = (int) $node['access'];
						$item->params     = new Registry(trim($node->params));
						$item->submenu    = array();

						// Translate attributes for iterator values
						foreach ($replace as $var => $val)
						{
							$item->text    = str_replace("{sql:$var}", $val, $item->text);
							$item->element = str_replace("{sql:$var}", $val, $item->element);
							$item->link    = str_replace("{sql:$var}", $val, $item->link);
						}

						$items[] = $item;
					}

					// Iterate over the matching records
					foreach ($results as $result)
					{
						static::loadXml($node->xpath('menuitem'), $items, $result);
					}
				}
			}
			else
			{
				// Add each node as a record
				$item = new stdClass;

				$item->type       = (string) $node['type'];
				$item->text       = (string) $node['title'];
				$item->link       = (string) $node['link'];
				$item->element    = (string) $node['element'];
				$item->class      = (string) $node['class'];
				$item->browserNav = (string) $node['target'];
				$item->access     = (int) $node['access'];
				$item->access     = (int) $node['access'];
				$item->params     = new Registry(trim($node->params));
				$item->submenu    = array();

				// Translate attributes for iterator values
				foreach ($replace as $var => $val)
				{
					$item->text    = str_replace("{sql:$var}", $val, $item->text);
					$item->element = str_replace("{sql:$var}", $val, $item->element);
					$item->link    = str_replace("{sql:$var}", $val, $item->link);
				}

				// Process the child nodes
				static::loadXml($node->xpath('menuitem'), $item->submenu, $replace);
				static::cleanup($item->submenu);

				$items[] = $item;
			}
		}
	}

	/**
	 * Parse the list of extensions.
	 *
	 * @param   array  $menuItems  List of loaded components
	 * @param   bool   $authCheck  An optional switch to turn off the auth check (to support custom layouts 'grey out' behaviour).
	 *
	 * @return  array
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public static function createLevels($menuItems, $authCheck = true)
	{
		$result = array();
		$user   = JFactory::getUser();
		$lang   = JFactory::getLanguage();
		$levels = $user->getAuthorisedViewLevels();

		// Process each item
		foreach ($menuItems as $i => &$menuitem)
		{
			/*
			 * Exclude item with menu item option set to exclude from menu modules
			 * Exclude item if the component is not authorised
			 * Exclude item if menu item set access level is not met
			 */
			if (($menuitem->params->get('menu_show', 1) == 0)
				|| ($menuitem->element && $authCheck && !$user->authorise('core.manage', $menuitem->element))
				|| ($menuitem->access && !in_array($menuitem->access, $levels)))
			{
				continue;
			}

			$menuitem->link = trim($menuitem->link);

			// Evaluate link url
			switch ($menuitem->type)
			{
				case 'component':
					$menuitem->link = $menuitem->link ?: 'index.php?option=' . $menuitem->element;
					break;
				case 'url':
					break;
				case 'separator':
				case 'heading':
				case 'container':
					$menuitem->link = '#';
					break;
				case 'alias':
					$aliasTo        = $menuitem->params->get('aliasoptions');
					$menuitem->link = static::getLink($aliasTo);
					break;
				default:
			}

			if ($menuitem->link == '')
			{
				continue;
			}

			// Translate Menu item label, if needed
			if (!empty($menuitem->element))
			{
				$lang->load($menuitem->element . '.sys', JPATH_BASE, null, false, true)
				|| $lang->load($menuitem->element . '.sys', JPATH_ADMINISTRATOR . '/components/' . $menuitem->element, null, false, true);
			}

			$menuitem->text    = $lang->hasKey($menuitem->title) ? JText::_($menuitem->title) : $menuitem->title;
			$menuitem->submenu = array();

			$result[$menuitem->parent_id][$menuitem->id] = $menuitem;
		}

		// Do an early exit if there are no top level menu items.
		if (!isset($result[1]))
		{
			return array();
		}

		// Put the items under respective parent menu items.
		foreach ($result as $parentId => &$mItems)
		{
			foreach ($mItems as &$mItem)
			{
				if (isset($result[$mItem->id]))
				{
					static::cleanup($result[$mItem->id]);

					$mItem->submenu = &$result[$mItem->id];
				}
			}
		}

		// Return only top level items, subtree follows
		return $result[1];
	}

	/**
	 * Load the menu items from an array
	 *
	 * @param   JMenuTree  $menuTree  Menu Tree object to populate with the given items
	 * @param   array      $items     Menu items loaded from database
	 * @param   bool       $enabled   Whether the menu should be enabled or disabled
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public static function loadItems(JMenuTree $menuTree, $items, $enabled = true)
	{
		$class = $menuTree->getCurrent()->hasParent() ? 'class:' : null;

		foreach ($items as $item)
		{
			if ($item->type == 'separator')
			{
				$menuTree->addSeparator($item->text);
			}
			elseif ($item->type == 'heading' && !count($item->submenu))
			{
				// Exclude if it is a heading type menu item, and has no children.
			}
			elseif ($item->type == 'container')
			{
				$exclude    = (array) $item->params->get('hideitems') ?: array();
				$components = MenusHelper::getMenuItems('main', false, $exclude);
				$components = MenusHelper::createLevels($components, true);
				$components = ArrayHelper::sortObjects($components, 'text', 1, false, true);

				// Exclude if it is a container type menu item, and has no children.
				if (count($item->submenu) || count($components))
				{
					$menuTree->addChild(new JMenuNode($item->text, $item->link, $class), true);

					if ($enabled)
					{
						// Load explicitly assigned child items first.
						static::loadItems($menuTree, $item->submenu);

						// Add a separator between dynamic menu items and components menu items
						if (count($item->submenu) && count($components))
						{
							$menuTree->addSeparator($item->text);
						}

						// Adding component submenu the old way, this assumes 2-level menu only
						foreach ($components as $component)
						{
							if (empty($component->submenu))
							{
								$menuTree->addChild(new JMenuNode($component->text, $component->link, $component->img));
							}
							else
							{
								$menuTree->addChild(new JMenuNode($component->text, $component->link, $component->img), true);

								foreach ($component->submenu as $sub)
								{
									$menuTree->addChild(new JMenuNode($sub->text, $sub->link, $sub->img));
								}

								$menuTree->getParent();
							}
						}
					}

					$menuTree->getParent();
				}
			}
			elseif (!$enabled)
			{
				$menuTree->addChild(new JMenuNode($item->text, $item->link, 'disabled'));
			}
			else
			{
				$target = $item->browserNav ? '_blank' : null;

				$menuTree->addChild(new JMenuNode($item->text, $item->link, $class, false, $target), true);
				static::loadItems($menuTree, $item->submenu);
				$menuTree->getParent();
			}
		}
	}

	/**
	 * Method to get a link to the aliased menu item
	 *
	 * @param   int  $menuId  The record id of the referencing menu item
	 *
	 * @return  string
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected static function getLink($menuId)
	{
		$table = JTable::getInstance('Menu');
		$table->load($menuId);

		// Look for an alias-to-alias
		if ($table->get('type') == 'alias')
		{
			$params  = new Registry($table->get('params'));
			$aliasTo = $params->get('aliasoptions');

			return static::getLink($aliasTo);
		}

		return $table->get('link');
	}

	/**
	 * Method to cleanup the menu items for repeated, leading or trailing separators in a given menu level
	 *
	 * @param   array  &$items  The list of menu items in the selected level
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public static function cleanup(&$items)
	{
		$b = true;

		foreach ($items as $k => &$item)
		{
			if ($item->type == 'separator')
			{
				if ($b)
				{
					$item = false;
				}

				$b = true;
			}
			else
			{
				$b = false;
			}
		}

		if ($b)
		{
			$item = false;
		}

		$items = array_filter($items);
	}
}
