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
 * IMPORTANT: The scope of this layout file is the `JAdminCssMenu` object and NOT the module context.
 * =========================================================================================================
 */
/** @var  JAdminCssMenu  $this */
$current = $this->tree->getCurrent();

// Build the CSS class suffix
if (!$this->enabled)
{
	$class = ' class="disabled"';
}
elseif ($current->type == 'separator')
{
	$class = $current->title ? ' class="menuitem-group"' : ' class="divider"';
}
elseif ($current->hasChildren())
{
	if ($current->getParent()->hasParent())
	{
		if ($current->class == 'scrollable-menu')
		{
			$class = ' class="dropdown scrollable-menu"';
		}
		else
		{
			$class = ' class="dropdown-submenu"';
		}
	}
	else
	{
		$class = ' class="dropdown"';
	}
}
else
{
	$class = '';
}

// Print the item
echo '<li' . $class . '>';

// Print a link if it exists
$linkClass     = array();
$dataToggle    = '';
$dropdownCaret = '';

if ($current->hasChildren())
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

if ($current->type != 'separator' && $current->getParent()->hasParent())
{
	$iconClass = $this->tree->getIconClass();

	if (!empty($iconClass))
	{
		$linkClass[] = $iconClass;
	}
}

// Implode out $linkClass for rendering
$linkClass = ' class="' . implode(' ', $linkClass) . '"';

// Links: component/container/url
if ($current->link)
{
	$target = $current->target ? 'target="' . $current->target . '"' : '';

	echo '<a' . $linkClass . ' ' . $dataToggle . ' href="' . $current->link . '" ' . $target . '>' . JText::_($current->title) . $dropdownCaret . '</a>';
}
// Separator
else
{
	echo '<span>' . JText::_($current->title) . '</span>';
}

// Recurse through children if they exist
if ($this->enabled && $current->hasChildren())
{
	if ($current->getParent()->hasParent())
	{
		$id = $current->id ? ' id="menu-' . strtolower($current->id) . '"' : '';

		echo '<ul' . $id . ' class="dropdown-menu menu-scrollable">' . "\n";
	}
	else
	{
		echo '<ul class="dropdown-menu scroll-menu">' . "\n";
	}

	// WARNING: Do not use direct 'include' or 'require' as it is important to isolate the scope for each call
	$this->renderLevel($depth++, __FILE__);

	echo "</ul>\n";
}

echo "</li>\n";
