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
use Joomla\CMS\Menu\MenuHelper;
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
	 * The module options
	 *
	 * @var   Registry
	 *
	 * @since   _DEPLOY_VERSION__
	 */
	protected $params;

	/**
	 * The menu bar state
	 *
	 * @var   bool
	 *
	 * @since   _DEPLOY_VERSION__
	 */
	protected $enabled;

	/**
	 * Get the current menu tree
	 *
	 * @return  Tree
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function getTree()
	{
		if (!$this->tree)
		{
			$this->tree = new Tree;
		}

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
		$this->tree    = $this->getTree();
		$this->params  = $params;
		$this->enabled = $enabled;
		$menutype      = $this->params->get('menutype', '*');

		if ($menutype == '*')
		{
			$name   = $this->params->get('preset', 'joomla');
			$levels = MenuHelper::loadPreset($name);
			$levels = $this->filter($levels);
		}
		else
		{
			$items = MenusHelper::getMenuItems($menutype, true);

			if ($this->enabled && $this->params->get('check'))
			{
				if ($this->check($items, $this->params))
				{
					$this->params->set('recovery', true);

					// In recovery mode, load the preset inside a special root node.
					$this->tree->addChild(new Node\Heading('MOD_MENU_RECOVERY_MENU_ROOT'), true);

					$levels = MenuHelper::loadPreset('joomla');
					$levels = $this->filter($levels);

					$this->populateTree($levels);

					$this->tree->addSeparator();

					// Add link to exit recovery mode
					$uri = clone JUri::getInstance();
					$uri->setVar('recover_menu', 0);

					$this->tree->addChild(new Node\Url('MOD_MENU_RECOVERY_EXIT', $uri->toString()));

					$this->tree->getParent();
				}
			}

			$levels = MenuHelper::createLevels($items);
			$levels = $this->filter($levels);
			$levels = $this->cleanup($levels);
		}

		$this->populateTree($levels);
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
	public function renderLevel($depth, $layoutFile)
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

	/**
	 * Check the flat list of menu items for important links
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
	 * Filter the loaded menu items based on access rights and module configurations
	 *
	 * @param   \stdClass[]  $items
	 *
	 * @return  array|mixed
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected function filter($items)
	{
		$result = array();
		$user   = JFactory::getUser();
		$levels = $user->getAuthorisedViewLevels();

		foreach ($items as $i => &$item)
		{
			// Exclude item with menu item option set to exclude from menu modules
			if ($item->params->get('menu_show', 1) == 0)
			{
				continue;
			}

			$item->scope = isset($item->scope) ? $item->scope : 'default';

			if (($item->scope == 'help' && !$this->params->get('showhelp')) || ($item->scope == 'edit' && !$this->params->get('shownew')))
			{
				continue;
			}

			 // Exclude item if the component is not authorised or menu item set access level is not met
			if ($item->element && !$user->authorise($item->scope == 'edit' ? 'core.create' : 'core.manage', $item->element))
			{
				continue;
			}

			if ($item->access && !in_array($item->access, $levels))
			{
				continue;
			}

			// Exclude if link is invalid
			if (!in_array($item->type, array('separator', 'heading', 'container')) && trim($item->link) == '')
			{
				continue;
			}

			// Process any children if exists
			if (!empty($item->submenu))
			{
				$item->submenu = $this->filter($item->submenu);
			}

			$result[$i] = $item;
		}

		return $result;
	}

	/**
	 * Get a list of components menu items for the container item
	 *
	 * @param   array  $exclude  The items to be excluded by id or component name
	 *
	 * @return  stdClass[]
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected function getComponents($exclude)
	{
		$components = MenusHelper::getMenuItems('main', false, $exclude);
		$components = $this->filter($components);

		// Load language for each element
		$elements = ArrayHelper::getColumn($components, 'element');
		$elements = array_unique($elements);
		$language = \JFactory::getLanguage();

		foreach ($elements as $element)
		{
			$language->load($element .'.sys', JPATH_ADMINISTRATOR, null, false, true) ||
			$language->load($element .'.sys', JPATH_ADMINISTRATOR . '/components/' . $element, null, false, true);
		}

		$components = MenuHelper::createLevels($components);
		$components = ArrayHelper::sortObjects($components, 'text', 1, false, true);

		return $components;
	}

	/**
	 * Load the menu items from a hierarchical list of items into the menu tree
	 *
	 * @param   stdClass[]  $levels  Menu items as a hierarchical list format
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected function populateTree($levels)
	{
		foreach ($levels as $item)
		{
			$class = $this->enabled ? $item->class : 'disabled';

			if ($item->type == 'separator')
			{
				$this->tree->addSeparator($item->title);
			}
			elseif ($item->type == 'heading' && count($item->submenu))
			{
				// Ignore a heading type menu item with no children.
				$this->tree->addChild(new Node\Heading($item->title, $class), $this->enabled);

				if ($this->enabled)
				{
					$this->populateTree($item->submenu);
					$this->tree->getParent();
				}
			}
			elseif ($item->type == 'url')
			{
				$cNode = new Node\Url($item->title, $item->link, $class, false, null, (bool) $item->browserNav);
				$this->tree->addChild($cNode, $this->enabled);

				if ($this->enabled)
				{
					$this->populateTree($item->submenu);
					$this->tree->getParent();
				}
			}
			elseif ($item->type == 'component')
			{
				$cNode = new Node\Component($item->title, $item->link, $item->element, $class, false, null, (bool) $item->browserNav);
				$this->tree->addChild($cNode, $this->enabled);

				if ($this->enabled)
				{
					$this->populateTree($item->submenu);
					$this->tree->getParent();
				}
			}
			elseif ($item->type == 'container')
			{
				$exclude    = (array) $item->params->get('hideitems') ?: array();
				$components = $this->getComponents($exclude);

				// Exclude if it is a container type menu item, and has no children.
				if (count($item->submenu) || count($components))
				{
					$this->tree->addChild(new Node\Container($item->title, $item->class), $this->enabled);

					if ($this->enabled)
					{
						$this->populateTree($item->submenu);

						// Add a separator between dynamic menu items and components menu items
						if (count($item->submenu) && count($components))
						{
							$this->tree->addSeparator();
						}

						$this->populateTree($components);

						$this->tree->getParent();
					}
				}
			}
		}
	}

	/**
	 * Method to cleanup the menu items for repeated, leading or trailing separators in a given menu level
	 *
	 * @param   stdClass[]  $items  The list of menu items in the selected level
	 *
	 * @return  stdClass[]
	 *
	 * @since   __DEPLOY_VERSION__
	 * @deprecated
	 */
	protected function cleanup($items)
	{
		$b = true;

		foreach ($items as $k => &$item)
		{
			// First cleanup the child items
			// Fixme: There may be an issue when dealing with heading/container type items with no children
			$item->submenu = $this->cleanup($item->submenu);

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

		return array_filter($items);
	}
}
