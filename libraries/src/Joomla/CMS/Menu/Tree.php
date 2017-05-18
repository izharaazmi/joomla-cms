<?php
/**
 * Joomla! Content Management System
 *
 * @copyright  Copyright (C) 2005 - 2017 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Joomla\CMS\Menu;

use Joomla\CMS\Menu\Node\Separator;

defined('JPATH_PLATFORM') or die;

/**
 * Menu Tree class to represent a menu tree hierarchy
 *
 * @since   __DEPLOY_VERSION__
 */
class Tree
{
	/**
	 * The root menu node
	 *
	 * @var  Node
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected $root = null;

	/**
	 * The current working menu node
	 *
	 * @var  Node
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
		$this->root    = new Node('ROOT');
		$this->current = $this->root;
	}

	/**
	 * Get the root node
	 *
	 * @return  Node
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
	 * @return  Node
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
	 * @param   Node  $node
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
	 * @return  Node
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
	 * @return  Node  The root node
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function reset($clear = false)
	{
		if ($clear)
		{
			$this->root = new Node('ROOT');
			$this->css  = array();
		}

		$this->current = $this->root;

		return  $this->current;
	}

	/**
	 * Method to add a child
	 *
	 * @param   Node  $node        The node to process
	 * @param   bool  $setCurrent  Set this new child as the current node for further working
	 *
	 * @return  Node  The newly added node
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function addChild(Node $node, $setCurrent = false)
	{
		$this->current->addChild($node);

		if ($setCurrent)
		{
			$this->setCurrent($node);
		}

		return $node;
	}

	/**
	 * Method to add a separator node
	 *
	 * @param   string  $title  The separator label text. A dash "-" can be used to use a horizontal bar instead of text label.
	 *
	 * @return  Node
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function addSeparator($title = null)
	{
		return $this->addChild(new Separator($title));
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
