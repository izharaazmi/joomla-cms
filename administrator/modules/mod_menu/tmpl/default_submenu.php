<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  mod_menu
 *
 * @copyright   Copyright (C) 2005 - 2017 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */
defined('_JEXEC') or die;

/**
 * =========================================================================================================
 * IMPORTANT: The scope of this layout file is the `Joomla\CMS\Menu\Tree` object and NOT the module context.
 * =========================================================================================================
 */
$enabled = !JFactory::getApplication()->input->get('hidemainmenu');

/** @var  JAdminCssMenu  $this */
$current = $this->tree->getCurrent();

// Build the CSS class suffix
$class = '';

if ($enabled && $current->hasChildren())
{
	$class = ' class="dropdown"';
}

if ($current->class == 'separator')
{
	$class = $current->title ? ' class="menuitem-group"' : ' class="divider"';
}

if ($enabled && $current->hasChildren() && $current->class)
{
	$class = ' class="dropdown-submenu"';

	if ($current->class == 'scrollable-menu')
	{
		$class = ' class="dropdown scrollable-menu"';
	}
}

if ($current->class == 'disabled')
{
	$class = ' class="disabled"';
}

// Print the item
echo '<li' . $class . '>';

// Print a link if it exists
$linkClass     = array();
$dataToggle    = '';
$dropdownCaret = '';

if ($enabled && $current->hasChildren())
{
	$linkClass[] = 'dropdown-toggle';
	$dataToggle  = ' data-toggle="dropdown"';

	if (!$current->getParent()->hasParent())
	{
		$dropdownCaret = ' <span class="caret"></span>';
	}
}
else
{
	$linkClass[] = 'no-dropdown';
}

if ($current->link != null && $current->getParent()->title != 'ROOT')
{
	$iconClass = $this->tree->getIconClass();

	if (!empty($iconClass))
	{
		$linkClass[] = $iconClass;
	}
}

// Implode out $linkClass for rendering
$linkClass = ' class="' . implode(' ', $linkClass) . '"';
$title     = JText::_($current->title);

if ($current->link != null && $current->target != null)
{
	echo '<a' . $linkClass . ' ' . $dataToggle . ' href="' . $current->link . '" target="' . $current->target . '">'
		. $title . $dropdownCaret . '</a>';
}
elseif ($current->link != null && $current->target == null)
{
	echo '<a' . $linkClass . ' ' . $dataToggle . ' href="' . $current->link . '">' . $title . $dropdownCaret . '</a>';
}
elseif ($current->title != null && $current->class != 'separator')
{
	echo '<a' . $linkClass . ' ' . $dataToggle . '>' . $title . $dropdownCaret . '</a>';
}
else
{
	echo '<span>' . $title . '</span>';
}

// Recurse through children if they exist
if ($enabled && $current->hasChildren())
{
	if ($current->class)
	{
		$id = '';

		if (!empty($current->id))
		{
			$id = ' id="menu-' . strtolower($current->id) . '"';
		}

		echo '<ul' . $id . ' class="dropdown-menu menu-scrollable">' . "\n";
	}
	else
	{
		echo '<ul class="dropdown-menu scroll-menu">' . "\n";
	}

	// WARNING: Do not use direct 'include' or 'require' as it is important to isolate the scope for each call
	$this->renderSubmenu($depth++, JModuleHelper::getLayoutPath('mod_menu', 'default_submenu'));

	echo "</ul>\n";
}

echo "</li>\n";
