<?php
/**
 * Joomla! Content Management System
 *
 * @copyright  Copyright (C) 2005 - 2017 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */
namespace Joomla\CMS\Menu\Node;

defined('JPATH_PLATFORM') or die;

/**
 * A Container type of node for MenuTree
 *
 * @see     Node
 *
 * @since   __DEPLOY_VERSION__
 */
class Container extends Heading
{
	/**
	 * The type of node
	 *
	 * @var  string
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected $type = 'container';

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
	public function __construct($title, $class = null, $active = false, $id = null, $tIcon = null, array $params = array())
	{
		parent::__construct($title, $class, $active, $id, $tIcon, $params);
	}
}
