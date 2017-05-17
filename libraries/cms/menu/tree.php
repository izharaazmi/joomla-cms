<?php
/**
 * @package     Joomla.Libraries
 * @subpackage  Menu
 *
 * @copyright   Copyright (C) 2005 - 2017 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
defined('JPATH_PLATFORM') or die;

/**
 * Class JMenuTree to represent a menu tree depending on the menutype provided
 *
 * @since   __DEPLOY_VERSION__
 */
class JMenuTree
{
	/**
	 * The root menu node
	 *
	 * @var  JMenuNode
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected $root = null;

	/**
	 * The current working menu node
	 *
	 * @var  JMenuNode
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected $current = null;

	/**
	 * The CSS style array
	 *
	 * @var  string[]
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected $css = array();

	/**
	 * Constructor
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function __construct()
	{
		$this->root    = new JMenuNode('ROOT');
		$this->current = $this->root;
	}

	/**
	 * Get the root node
	 *
	 * @return  JMenuNode
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function getRoot()
	{
		return $this->root;
	}

	/**
	 * Get the current node
	 *
	 * @return  JMenuNode
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function getCurrent()
	{
		return $this->current;
	}

	/**
	 * Get the current node
	 *
	 * @param   JMenuNode  $node
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function setCurrent($node)
	{
		if ($node)
		{
			$this->current = $node;
		}
	}

	/**
	 * Method to get the parent and set it as active optionally
	 *
	 * @param   bool  $setCurrent  Set that parent as the current node for further working
	 *
	 * @return  JMenuNode
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function getParent($setCurrent = true)
	{
		$parent = $this->current->getParent();

		if ($setCurrent)
		{
			$this->setCurrent($parent);
		}

		return $parent;
	}

	/**
	 * Method to reset the working pointer to the root node and optionally clear all menu nodes
	 *
	 * @param   bool  $clear  Whether to clear the existing menu items or just reset the pointer to root element
	 *
	 * @return  JMenuNode  The root node
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function reset($clear = false)
	{
		if ($clear)
		{
			$this->root = new JMenuNode('ROOT');
			$this->css  = array();
		}

		$this->current = $this->root;

		return  $this->current;
	}

	/**
	 * Method to add a child
	 *
	 * @param   JMenuNode  $node        The node to process
	 * @param   bool       $setCurrent  Set this new child as the current node for further working
	 *
	 * @return  JMenuNode  The newly added node
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function addChild(JMenuNode $node, $setCurrent = false)
	{
		$this->current->addChild($node);

		if ($setCurrent)
		{
			$this->setCurrent($node);
		}

		return $node;
	}

	/**
	 * Method to add a child by parameters
	 *
	 * @param   string  $type     The menu item type
	 * @param   string  $title    The title of the node
	 * @param   string  $link     The node link
	 * @param   string  $element  The element name
	 * @param   string  $class    The CSS class for the node
	 * @param   string  $target   The link target
	 * @param   int     $access   The access level for this link
	 * @param   array   $params   The additional custom parameters for the node
	 *
	 * @return  JMenuNode  The newly added node
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function addItem($type, $title, $link, $element = null, $class = null, $target = null, $access = 0, $params = null)
	{
		if ($type == 'separator')
		{
			$node = $this->addSeparator($title);
		}
		else
		{
			$class = $this->current->title == 'ROOT' ? null : $class;
			$node  = new JMenuNode($title, $link, $class, false, null, $target, null, $params);

			$node->type    = $type;
			$node->element = $element;
			$node->access  = $access;

			$this->addChild($node);
		}

		return $node;
	}

	/**
	 * Method to add a separator node
	 *
	 * @param   string  $title  The separator label text. A dash "-" can be used to use a horizontal bar instead of text label.
	 *
	 * @return  JMenuNode
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function addSeparator($title = null)
	{
		$title = ($title == '-' || $title == '') ? null : $title;

		return $this->addChild(new JMenuNode($title, null, 'separator', false));
	}

	/**
	 * Method to get the CSS class name for an icon identifier or create one if
	 * a custom image path is passed as the identifier
	 *
	 * @param   string  $identifier  Icon identification string
	 *
	 * @return  string	CSS class name
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function getIconClass($identifier = null)
	{
		static $classes = array();

		if ($identifier == null)
		{
			$identifier = $this->current->class;
		}

		if (!isset($classes[$identifier]))
		{
			// We were passed a class name
			if (substr($identifier, 0, 6) == 'class:')
			{
				$class = substr($identifier, 6);
			}
			// We were passed background icon url. Build the CSS class for the icon
			else
			{
				$class = preg_replace('#\.[^.]*$#', '', basename($identifier));
				$class = preg_replace('#\.\.[^A-Za-z0-9\.\_\- ]#', '', $class);

				$this->css[] = ".menu-$class {background: url($identifier) no-repeat;}";
			}

			$classes[$identifier] = "menu-$class";
		}

		return $classes[$identifier];
	}

	/**
	 * Get the CSS declarations for this tree
	 *
	 * @return  string[]
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function getCss()
	{
		return $this->css;
	}
}
