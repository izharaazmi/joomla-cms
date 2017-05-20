<?php
/**
 * Joomla! Content Management System
 *
 * @copyright  Copyright (C) 2005 - 2017 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\CMS\Menu;

defined('JPATH_PLATFORM') or die;

use Joomla\Registry\Registry;

/**
 * Menu Helper utility
 *
 * @since  __DEPLOY_VERSION__
 */
class MenuHelper
{
	/**
	 * List of preset include paths
	 *
	 * @var  array
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected static $presets = null;

	/**
	 * Private constructor
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	private function __construct()
	{
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
			$preset = new \stdClass;

			$preset->name  = $name;
			$preset->title = $title;
			$preset->path  = $path;

			static::$presets[$name] = $preset;
		}
	}

	/**
	 * Get a list of available presets.
	 *
	 * @return  \stdClass[]
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public static function getPresets()
	{
		if (static::$presets === null)
		{
			// Important: 'null' will cause infinite recursion.
			static::$presets = array();

			static::addPreset('joomla', 'JLIB_MENUS_PRESET_JOOMLA', JPATH_ADMINISTRATOR . '/components/com_menus/presets/joomla.xml');
			static::addPreset('modern', 'JLIB_MENUS_PRESET_MODERN', JPATH_ADMINISTRATOR . '/components/com_menus/presets/modern.xml');

			// Load from template folder automatically
			$app = \JFactory::getApplication();
			$tpl = JPATH_THEMES . '/' . $app->getTemplate() . '/html/com_menus/presets';

			if (is_dir($tpl))
			{
				jimport('joomla.filesystem.folder');

				$files = \JFolder::files($tpl, '\.xml$');

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
	 * Method to resolve the menu item alias type menu item
	 *
	 * @param   \stdClass  &$item  The alias object
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public static function resolveAlias(&$item)
	{
		$obj = $item;

		while ($obj->type == 'alias')
		{
			$params  = new Registry($obj->params);
			$aliasTo = $params->get('aliasoptions');

			$db = \JFactory::getDbo();
			$query = $db->getQuery(true);
			$query->select('a.id, a.link, a.type, e.element')
				->from('#__menu a')
				->where('a.id = ' . (int) $aliasTo)
				->join('left', '#__extensions e ON e.id = a.component_id = e.id');

			try
			{
				$obj = $db->setQuery($query)->loadObject();

				if (!$obj)
				{
					$item->link = '';

					return;
				}
			}
			catch (\Exception $e)
			{
				$item->link = '';

				return;
			}
		}

		$item->id      = $obj->id;
		$item->link    = $obj->link;
		$item->type    = $obj->type;
		$item->element = $obj->element;
	}

	/**
	 * Parse the flat list of menu items and prepare the hierarchy of then using parent-child relationship.
	 *
	 * @param   \stdClass[]  $menuItems  List of loaded components
	 *
	 * @return  \stdClass[]
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public static function createLevels($menuItems)
	{
		$result    = array();
		$result[1] = array();

		foreach ($menuItems as $i => &$item)
		{
			// Resolve the alias item
			if ($item->type == 'alias')
			{
				static::resolveAlias($item);
			}

			if ($item->link = in_array($item->type, array('separator', 'heading', 'container')) ? '#' : trim($item->link))
			{
				$item->submenu = array();
				$item->text    = \JText::_($item->title);
				$item->class   = isset($item->img) ? $item->img : $item->class;
				$item->scope   = isset($item->scope) ? $item->scope : 'default';

				$result[$item->parent_id][$item->id] = $item;
			}
		}

		// Move each of the items under respective parent menu items.
		if (count($result[1]))
		{
			foreach ($result as $parentId => &$mItems)
			{
				foreach ($mItems as &$mItem)
				{
					if (isset($result[$mItem->id]))
					{
						$mItem->submenu = &$result[$mItem->id];
					}
				}
			}
		}

		// Return only top level items, subtree follows
		return $result[1];
	}

	/**
	 * Load the menu items from a preset file into a hierarchical list of objects
	 *
	 * @param   string  $name  The preset name
	 *
	 * @return  \stdClass[]
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public static function loadPreset($name)
	{
		$items   = array();
		$presets = static::getPresets();

		if (isset($presets[$name]) && ($xml = simplexml_load_file($presets[$name]->path)) && $xml instanceof \SimpleXMLElement)
		{
			static::loadXml($xml, $items);
		}
		elseif (isset($presets['joomla']) && ($xml = simplexml_load_file($presets['joomla']->path)) && $xml instanceof \SimpleXMLElement)
		{
			static::loadXml($xml, $items);
		}

		return $items;
	}

	/**
	 * Load a menu tree from an XML file
	 *
	 * @param   \SimpleXMLElement[]  $elements  Name of the xml file to load
	 * @param   \stdClass[]          $items     The menu hierarchy list to be populated
	 * @param   string[]             $replace   The substring replacements for iterator type items
	 *
	 * @return  void
	 *
	 * @since  __DEPLOY_VERSION__
	 */
	protected static function loadXml($elements, &$items, $replace = array())
	{
		foreach ($elements as $element)
		{
			$select = (string) $element['sql_select'];
			$from   = (string) $element['sql_from'];

			/**
			 * Following is a repeatable group based on simple database query. This requires sql_* attributes (sql_select and sql_from are required)
			 * The values can be used like - "{sql:columnName}" in any attribute of repeated elements.
			 * The repeated elements are place inside this xml node but they will be populated in the same level in the rendered menu
			 */
			if ($select && $from)
			{
				$hidden = $element['hidden'] == 'true';
				$where  = (string) $element['sql_where'];
				$order  = (string) $element['sql_order'];
				$group  = (string) $element['sql_group'];

				$db    = \JFactory::getDbo();
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

				if ($group)
				{
					$query->order($group);
				}

				$results = $db->setQuery($query)->loadObjectList();

				// Skip the entire group if no items to iterate over.
				if ($results)
				{
					// Show the repeatable group heading node only if not set as hidden.
					if (!$hidden)
					{
						$items[] = static::parseXmlNode($element, $replace);
					}

					// Iterate over the matching records, items goes in the same level as this node.
					foreach ($results as $result)
					{
						static::loadXml($element->menuitem, $items, $result);
					}
				}
			}
			else
			{
				$item = static::parseXmlNode($element, $replace);

				// Process the child nodes
				static::loadXml($element->menuitem, $item->submenu, $replace);

				$items[] = $item;
			}
		}
	}

	/**
	 * Create a menu node from xml element
	 *
	 * @param   \SimpleXMLElement  $node
	 * @param   string[]           $replace
	 *
	 * @return  \stdClass
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected static function parseXmlNode($node, $replace = array())
	{
		$item = new \stdClass;

		$item->type       = (string) $node['type'];
		$item->title      = (string) $node['title'];
		$item->link       = (string) $node['link'];
		$item->element    = (string) $node['element'];
		$item->class      = (string) $node['class'];
		$item->browserNav = (string) $node['target'];
		$item->scope      = (string) $node['scope'] ?: 'default';
		$item->access     = (int) $node['access'];
		$item->params     = new Registry(trim($node->params));
		$item->submenu    = array();

		// Translate attributes for iterator values
		foreach ($replace as $var => $val)
		{
			$item->title   = str_replace("{sql:$var}", $val, $item->title);
			$item->element = str_replace("{sql:$var}", $val, $item->element);
			$item->link    = str_replace("{sql:$var}", $val, $item->link);
		}

		return $item;
	}
}
