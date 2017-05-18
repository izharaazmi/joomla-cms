<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  mod_menu
 *
 * @copyright   Copyright (C) 2005 - 2017 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Menu\Node;
use Joomla\CMS\Menu\Tree;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;

/**
 * Tree based class to render the admin menu
 *
 * @since  1.5
 */
class JAdminCssMenu
{
	/**
	 * The Menu tree object
	 *
	 * @var   Tree
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected $tree;

	/**
	 * Get the current menu tree
	 *
	 * @return  Tree
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function getTree()
	{
		return $this->tree;
	}

	/**
	 * Populate the menu items in the menu tree object
	 *
	 * @param   Registry  $params   Menu configuration parameters
	 * @param   bool      $enabled  Whether the menu should be enabled or disabled
	 *
	 * @return  void
	 *
	 * @since   3.7.0
	 */
	public function load($params, $enabled)
	{
		$this->tree = new Tree;
		$menutype   = $params->get('menutype', '*');

		if ($menutype == '*')
		{
			$name = $params->get('preset', 'joomla');

			$this->loadPreset($name, $enabled, $params);
		}
		else
		{
			$items = MenusHelper::getMenuItems($menutype, true);

			if ($enabled && $params->get('check'))
			{
				if ($this->check($items, $params))
				{
					$params->set('recovery', true);

					// In recovery mode, load the preset inside a special root node.
					$this->tree->addChild(new Node(JText::_('MOD_MENU_RECOVERY_MENU_ROOT'), '#'), true);

					$this->loadPreset('joomla', true, $params);

					$this->tree->addSeparator();

					// Add exit recovery mode link
					$uri = clone JUri::getInstance();
					$uri->setVar('recover_menu', 0);

					$this->tree->addChild(new Node(JText::_('MOD_MENU_RECOVERY_EXIT'), $uri->toString()));

					$this->tree->getParent();
				}
			}

			// Create levels
			$items = MenusHelper::createLevels($items);

			MenusHelper::loadItems($this->tree, $items, $enabled);
		}
	}

	/**
	 * Check the menu items for important links
	 *
	 * @param   array     $items   The menu items array
	 * @param   Registry  $params  Module options
	 *
	 * @return  bool  Whether to show recovery menu
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected function check($items, Registry $params)
	{
		$me          = JFactory::getUser();
		$authMenus   = $me->authorise('core.manage', 'com_menus');
		$authModules = $me->authorise('core.manage', 'com_modules');

		if (!$authMenus && !$authModules)
		{
			return false;
		}

		$app        = JFactory::getApplication();
		$types      = ArrayHelper::getColumn($items, 'type');
		$elements   = ArrayHelper::getColumn($items, 'element');
		$rMenu      = $authMenus && !in_array('com_menus', $elements);
		$rModule    = $authModules && !in_array('com_modules', $elements);
		$rContainer = !in_array('container', $types);

		if ($rMenu || $rModule || $rContainer)
		{
			$recovery = $app->getUserStateFromRequest('mod_menu.recovery', 'recover_menu', 0, 'int');

			if ($recovery)
			{
				return true;
			}

			$missing = array();

			if ($rMenu)
			{
				$missing[] = JText::_('MOD_MENU_IMPORTANT_ITEM_MENU_MANAGER');
			}

			if ($rModule)
			{
				$missing[] = JText::_('MOD_MENU_IMPORTANT_ITEM_MODULE_MANAGER');
			}

			if ($rContainer)
			{
				$missing[] = JText::_('MOD_MENU_IMPORTANT_ITEM_COMPONENTS_CONTAINER');
			}

			$uri = clone JUri::getInstance();
			$uri->setVar('recover_menu', 1);

			$table    = JTable::getInstance('MenuType');
			$menutype = $params->get('menutype');

			$table->load(array('menutype' => $menutype));

			$menutype = $table->get('title', $menutype);
			$message  = JText::sprintf('MOD_MENU_IMPORTANT_ITEMS_INACCESSIBLE_LIST_WARNING', $menutype, implode(', ', $missing), $uri);

			$app->enqueueMessage($message, 'warning');
		}

		return false;
	}

	/**
	 * Load the menu items from an array
	 *
	 * @param   string    $name     The preset name
	 * @param   bool      $enabled  Whether the menu-bar is enabled
	 * @param   Registry  $params   The module options
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected function loadPreset($name, $enabled = true, Registry $params = null)
	{
		$presets = MenusHelper::getPresets();

		if (isset($presets[$name]))
		{
			$path = $presets[$name]->path;

			if (substr($path, -4) == '.xml')
			{
				if (($xml = simplexml_load_file($path)) && $xml instanceof SimpleXMLElement)
				{
					$xmlNodes = $xml->xpath('/menu/menuitem');
					$items    = array();

					MenusHelper::loadFromXml($xmlNodes, $this->tree);
					MenusHelper::loadXml($xmlNodes, $items);
					MenusHelper::loadItems($this->tree, $items, $enabled);
				}
			}
			elseif (substr($path, -4) == '.php')
			{
				unset($presets);

				// The preset file is a PHP script which will populate `$this->tree` object and can also use `$name`, `$enabled`, `$params`
				include $path;
			}
		}
	}

	/**
	 * Method to render a given level of a menu using provided layout file
	 *
	 * @param   integer  $depth       The level of the menu to be rendered
	 * @param   string   $layoutFile  The layout file to be used to render
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function renderSubmenu($depth, $layoutFile)
	{
		if (is_file($layoutFile))
		{
			$children = $this->tree->getCurrent()->getChildren();

			foreach ($children as $child)
			{
				$this->tree->setCurrent($child);

				// This sets the scope to this object for the layout file and also isolates other `include`s
				require $layoutFile;
			}
		}
	}
}
