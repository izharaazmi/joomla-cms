<?php
/**
 * Joomla! Content Management System
 *
 * @copyright  Copyright (C) 2005 - 2017 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Joomla\CMS\Menu;

defined('JPATH_PLATFORM') or die;

/**
 * A Node for MenuTree
 *
 * @see     Tree
 *
 * @since   __DEPLOY_VERSION__
 */
class Node
{
	/**
	 * The type of node
	 *
	 * @var  string
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected $type = 'root';

	/**
	 * Node Id
	 *
	 * @var  string
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public $id = null;

	/**
	 * The access level for this node
	 *
	 * @var  int
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected $access = 0;

	/**
	 * Additional custom node params
	 *
	 * @var  array
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected $params = array();

	/**
	 * Parent node object
	 *
	 * @var  Node
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected $parent = null;

	/**
	 * Array of Children node objects
	 *
	 * @var  Node[]
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected $children = array();

	/**
	 * Constructor
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function __construct()
	{
	}

	/**
	 * Get an attribute value
	 *
	 * @param   string  $name  The attribute name
	 *
	 * @return  mixed
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function __get($name)
	{
		return property_exists($this, $name) ? $this->$name : null;
	}

	/**
	 * Add child to this node
	 *
	 * If the child already has a parent, the link is unset
	 *
	 * @param   Node  $child  The child to be added
	 *
	 * @return  Node  The new added child
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function addChild(Node $child)
	{
		$hash = spl_object_hash($child);

		if (isset($child->parent))
		{
			$child->parent->removeChild($child);
		}

		$child->parent         = $this;
		$this->children[$hash] = $child;

		return $child;
	}

	/**
	 * Remove a child from this node
	 *
	 * If the child exists it is unset
	 *
	 * @param   Node  $child  The child to be added
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function removeChild(Node $child)
	{
		$hash = spl_object_hash($child);

		if (isset($this->children[$hash]))
		{
			$child->parent = null;

			unset($this->children[$hash]);
		}
	}

	/**
	 * Get the param value from the node params
	 *
	 * @param   string  $key  The param name
	 *
	 * @return  mixed
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function getParam($key)
	{
		return isset($this->params[$key]) ? $this->params[$key] : null;
	}

	/**
	 * Test if this node has a parent
	 *
	 * @return  bool  True if there is a parent
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function hasParent()
	{
		return isset($this->parent);
	}

	/**
	 * Get the parent of this node
	 *
	 * @return  Node  The Node object's parent or null for no parent
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function getParent()
	{
		return $this->parent;
	}

	/**
	 * Test if this node has children
	 *
	 * @return  bool
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function hasChildren()
	{
		return count($this->children) > 0;
	}

	/**
	 * Get the children of this node
	 *
	 * @return  Node[]  The children
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function getChildren()
	{
		return $this->children;
	}
}
