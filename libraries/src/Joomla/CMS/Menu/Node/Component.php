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
 * A Component type of node for MenuTree
 *
 * @see     Node
 *
 * @since   __DEPLOY_VERSION__
 */
class Component extends Node
{
	/**
	 * The type of node
	 *
	 * @var  string
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected $type = 'component';

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
	protected $link = null;

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
	 * The component name for this node link
	 *
	 * @var  string
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	protected $element = null;

	/**
	 * Constructor for the class.
	 *
	 * @param   string  $title    The title of the node
	 * @param   string  $link     The node link
	 * @param   string  $element  The component name
	 * @param   string  $class    The CSS class for the node
	 * @param   bool    $active   The node active state
	 * @param   string  $id       The node id
	 * @param   string  $target   The link target
	 * @param   string  $tIcon    The title icon for the node
	 * @param   array   $params   The additional custom parameters for the node
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public function __construct($title, $link = null, $element, $class = null,
	                            $active = false, $id = null, $target = null, $tIcon = null, $params = array())
	{
		if ($link)
		{
			$link = \JFilterOutput::ampReplace($link);
		}
		else
		{
			$link = 'index.php?option=' . $element;
		}

		$this->id      = $id;
		$this->title   = $tIcon ? $title . $tIcon : $title;
		$this->link    = $link;
		$this->element = $element;
		$this->class   = $class;
		$this->active  = $active;
		$this->target  = $target;
		$this->params  = $params;

		parent::__construct();
	}
}
