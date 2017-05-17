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
 * A Node for JMenuTree
 *
 * @see     JMenuTree
 *
 * @since   __DEPLOY_VERSION__
 */
class JMenuNode
{
	/**
	 * Node Title
	 *
	 * @var  string
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public $title = null;

	/**
	 * Node Id
	 *
	 * @var  string
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public $id = null;

	/**
	 * Node Link
	 *
	 * @var  string
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public $link = null;

	/**
	 * Link Target
	 *
	 * @var  string
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public $target = null;

	/**
	 * CSS Class for node
	 *
	 * @var  string
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public $class = null;

	/**
	 * Whether this node is active
	 *
	 * @var  bool
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public $active = false;

	/**
	 * The type of link for this node
	 *
	 * @var  string
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public $type;

	/**
	 * The component name for this node link
	 *
	 * @var  string
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public $element;

	/**
	 * The access level for this node
	 *
	 * @var  int
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public $access = 0;

	/**
	 * Additional custom node params
	 *
	 * @var  array
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public $params = array();

	/**
	 * Parent node object
	 *
	 * @var  JMenuNode
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected $parent = null;

	/**
	 * Array of Children node objects
	 *
	 * @var  JMenuNode[]
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected $children = array();

	/**
	 * Constructor for the class.
	 *
	 * @param   string  $title   The title of the node
	 * @param   string  $link    The node link
	 * @param   string  $class   The CSS class for the node
	 * @param   bool    $active  The node active state
	 * @param   string  $id      The node id
	 * @param   string  $target  The link target
	 * @param   string  $tIcon   The title icon for the node
	 * @param   array   $params  The additional custom parameters for the node
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function __construct($title, $link = null, $class = null, $active = false, $id = null, $target = null, $tIcon = null, $params = array())
	{
		$this->title  = $tIcon ? $title . $tIcon : $title;
		$this->link   = JFilterOutput::ampReplace($link);
		$this->class  = $class;
		$this->active = $active;
		$this->id     = $id;
		$this->target = $target;
		$this->params = $params;
	}

	/**
	 * Add child to this node
	 *
	 * If the child already has a parent, the link is unset
	 *
	 * @param   JMenuNode  $child  The child to be added
	 *
	 * @return  JMenuNode  The new added child
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function addChild(JMenuNode $child)
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
	 * @param   JMenuNode  $child  The child to be added
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function removeChild(JMenuNode $child)
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
	 * Get the children of this node
	 *
	 * @return  JMenuNode[]  The children
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function getChildren()
	{
		return $this->children;
	}

	/**
	 * Get the parent of this node
	 *
	 * @return  JMenuNode  JMenuNode object with the parent or null for no parent
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
}
