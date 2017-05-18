<?php
/**
 * Joomla! Content Management System
 *
 * @copyright  Copyright (C) 2005 - 2017 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Joomla\CMS\Menu\Node;

defined('JPATH_PLATFORM') or die;

use Joomla\CMS\Menu\Node;

/**
 * A Heading type of node for MenuTree
 *
 * @see     Node
 *
 * @since   __DEPLOY_VERSION__
 */
class Heading extends Node
{
	/**
	 * The type of node
	 *
	 * @var  string
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected $type = 'heading';

	/**
	 * Node Title
	 *
	 * @var  string
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected $title = null;

	/**
	 * Node Link
	 *
	 * @var  string
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected $link = '#';

	/**
	 * Link Target
	 *
	 * @var  string
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected $target = null;

	/**
	 * CSS Class for node
	 *
	 * @var  string
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected $class = null;

	/**
	 * Whether this node is active
	 *
	 * @var  bool
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected $active = false;

	/**
	 * Constructor for the class.
	 *
	 * @param   string  $title   The title of the node
	 * @param   string  $class   The CSS class for the node
	 * @param   bool    $active  The node active state
	 * @param   string  $id      The node id
	 * @param   string  $tIcon   The title icon for the node
	 * @param   array   $params  The additional custom parameters for the node
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function __construct($title, $class = null, $active = false, $id = null, $tIcon = null, $params = array())
	{
		$this->id      = $id;
		$this->title   = $tIcon ? $title . $tIcon : $title;
		$this->class   = $class;
		$this->active  = $active;
		$this->params  = $params;

		parent::__construct();
	}
}
