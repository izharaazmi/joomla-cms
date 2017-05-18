<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  mod_menu
 *
 * @copyright   Copyright (C) 2005 - 2016 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Menu\Node;
use Joomla\Utilities\ArrayHelper;

/* @var  $this    JAdminCSSMenu */
/* @var  $params  Joomla\Registry\Registry */

$shownew  = (boolean) $params->get('shownew', 1);
$showhelp = (boolean) $params->get('showhelp', 1);
$user     = JFactory::getUser();
$lang     = JFactory::getLanguage();

/**
 * Site Submenu
 */
$tree = $this->getTree();

$tree->addChild(new Node(JText::_('MOD_MENU_SYSTEM'), '#'), true);
$tree->addChild(new Node(JText::_('MOD_MENU_CONTROL_PANEL'), 'index.php', 'class:cpanel'));

if ($user->authorise('core.admin'))
{
	$tree->addSeparator();
	$tree->addChild(new Node(JText::_('MOD_MENU_CONFIGURATION'), 'index.php?option=com_config', 'class:config'));
}

if ($user->authorise('core.manage', 'com_checkin'))
{
	$tree->addSeparator();
	$tree->addChild(new Node(JText::_('MOD_MENU_GLOBAL_CHECKIN'), 'index.php?option=com_checkin', 'class:checkin'));
}

if ($user->authorise('core.manage', 'com_cache'))
{
	$tree->addChild(new Node(JText::_('MOD_MENU_CLEAR_CACHE'), 'index.php?option=com_cache', 'class:clear'));
	$tree->addChild(new Node(JText::_('MOD_MENU_PURGE_EXPIRED_CACHE'), 'index.php?option=com_cache&view=purge', 'class:purge'));
}

if ($user->authorise('core.admin'))
{
	$tree->addSeparator();
	$tree->addChild(new Node(JText::_('MOD_MENU_SYSTEM_INFORMATION'), 'index.php?option=com_admin&view=sysinfo', 'class:info'));
}

$tree->getParent();

/**
 * Users Submenu
 */
if ($user->authorise('core.manage', 'com_users'))
{
	$tree->addChild(new Node(JText::_('MOD_MENU_COM_USERS_USERS'), '#'), true);
	$createUser = $shownew && $user->authorise('core.create', 'com_users');
	$createGrp  = $user->authorise('core.admin', 'com_users');

	$tree->addChild(new Node(JText::_('MOD_MENU_COM_USERS_USER_MANAGER'), 'index.php?option=com_users&view=users', 'class:user'), $createUser);

	if ($createUser)
	{
		$tree->addChild(new Node(JText::_('MOD_MENU_COM_USERS_ADD_USER'), 'index.php?option=com_users&task=user.add', 'class:newarticle'));
		$tree->getParent();
	}

	if ($createGrp)
	{
		$tree->addChild(new Node(JText::_('MOD_MENU_COM_USERS_GROUPS'), 'index.php?option=com_users&view=groups', 'class:groups'), $createUser);

		if ($createUser)
		{
			$tree->addChild(new Node(JText::_('MOD_MENU_COM_USERS_ADD_GROUP'), 'index.php?option=com_users&task=group.add', 'class:newarticle'));
			$tree->getParent();
		}

		$tree->addChild(new Node(JText::_('MOD_MENU_COM_USERS_LEVELS'), 'index.php?option=com_users&view=levels', 'class:levels'), $createUser);

		if ($createUser)
		{
			$tree->addChild(new Node(JText::_('MOD_MENU_COM_USERS_ADD_LEVEL'), 'index.php?option=com_users&task=level.add', 'class:newarticle'));
			$tree->getParent();
		}
	}

	$tree->addSeparator();
	$tree->addChild(new Node(JText::_('MOD_MENU_COM_USERS_NOTES'), 'index.php?option=com_users&view=notes', 'class:user-note'), $createUser);

	if ($createUser)
	{
		$tree->addChild(new Node(JText::_('MOD_MENU_COM_USERS_ADD_NOTE'), 'index.php?option=com_users&task=note.add', 'class:newarticle'));
		$tree->getParent();
	}

	$tree->addChild(
		new Node(
			JText::_('MOD_MENU_COM_USERS_NOTE_CATEGORIES'), 'index.php?option=com_categories&view=categories&extension=com_users', 'class:category'),
		$createUser
	);

	if ($createUser)
	{
		$tree->addChild(
			new Node(
				JText::_('MOD_MENU_COM_CONTENT_NEW_CATEGORY'), 'index.php?option=com_categories&task=category.add&extension=com_users',
				'class:newarticle'
			)
		);
		$tree->getParent();
	}

	if (JFactory::getApplication()->get('massmailoff') != 1)
	{
		$tree->addSeparator();
		$tree->addChild(new Node(JText::_('MOD_MENU_MASS_MAIL_USERS'), 'index.php?option=com_users&view=mail', 'class:massmail'));
	}

	$tree->getParent();
}

/**
 * Menus Submenu
 */
if ($user->authorise('core.manage', 'com_menus'))
{
	$tree->addChild(new Node(JText::_('MOD_MENU_MENUS'), '#'), true);
	$createMenu = $shownew && $user->authorise('core.create', 'com_menus');

	$tree->addChild(new Node(JText::_('MOD_MENU_MENU_MANAGER'), 'index.php?option=com_menus&view=menus', 'class:menumgr'), $createMenu);

	if ($createMenu)
	{
		$tree->addChild(new Node(JText::_('MOD_MENU_MENU_MANAGER_NEW_MENU'), 'index.php?option=com_menus&view=menu&layout=edit', 'class:newarticle'));
		$tree->getParent();
	}

	$tree->addSeparator();

	$tree->addChild(new Node(JText::_('MOD_MENU_MENUS_ALL_ITEMS'), 'index.php?option=com_menus&view=items&menutype=', 'class:allmenu'));
	$tree->addSeparator(JText::_('JSITE'));

	// Menu Types
	$menuTypes = ModMenuHelper::getMenus();
	$menuTypes = ArrayHelper::sortObjects($menuTypes, array('client_id', 'title'), 1, false);

	foreach ($menuTypes as $mti => $menuType)
	{
		$alt = '*' . $menuType->sef . '*';

		if ($menuType->home == 0)
		{
			$titleicon = '';
		}
		elseif ($menuType->home == 1 && $menuType->language == '*')
		{
			$titleicon = ' <span class="icon-home"></span>';
		}
		elseif ($menuType->home > 1)
		{
			$titleicon = ' <span>'
				. JHtml::_('image', 'mod_languages/icon-16-language.png', $menuType->home, array('title' => JText::_('MOD_MENU_HOME_MULTIPLE')), true)
				. '</span>';
		}
		elseif ($menuType->image && JHtml::_('image', 'mod_languages/' . $menuType->image . '.gif', null, null, true, true))
		{
			$titleicon = ' <span>' .
				JHtml::_('image', 'mod_languages/' . $menuType->image . '.gif', $alt, array('title' => $menuType->title_native), true) . '</span>';
		}
		else
		{
			$titleicon = ' <span class="label" title="' . $menuType->title_native . '">' . $menuType->sef . '</span>';
		}

		if (isset($menuTypes[$mti - 1]) && $menuTypes[$mti - 1]->client_id != $menuType->client_id)
		{
			$this->tree->addSeparator(JText::_('JADMINISTRATOR'));
		}

		$tree->addChild(
			new Node(
				$menuType->title, 'index.php?option=com_menus&view=items&menutype=' . $menuType->menutype, 'class:menu', null, null, $titleicon
			),
			$user->authorise('core.create', 'com_menus.menu.' . (int) $menuType->id)
		);

		if ($user->authorise('core.create', 'com_menus.menu.' . (int) $menuType->id))
		{
			$tree->addChild(
				new Node(
					JText::_('MOD_MENU_MENU_MANAGER_NEW_MENU_ITEM'),
					'index.php?option=com_menus&view=item&layout=edit&menutype=' . $menuType->menutype, 'class:newarticle'
				)
			);

			$tree->getParent();
		}
	}

	$tree->getParent();
}

/**
 * Content Submenu
 */
if ($user->authorise('core.manage', 'com_content'))
{
	$tree->addChild(new Node(JText::_('MOD_MENU_COM_CONTENT'), '#'), true);
	$createContent = $shownew && $user->authorise('core.create', 'com_content');

	$tree->addChild(new Node(JText::_('MOD_MENU_COM_CONTENT_ARTICLE_MANAGER'), 'index.php?option=com_content', 'class:article'), $createContent);

	if ($createContent)
	{
		$tree->addChild(
			new Node(JText::_('MOD_MENU_COM_CONTENT_NEW_ARTICLE'), 'index.php?option=com_content&task=article.add', 'class:newarticle')
		);
		$tree->getParent();
	}

	$tree->addChild(
		new Node(
			JText::_('MOD_MENU_COM_CONTENT_CATEGORY_MANAGER'), 'index.php?option=com_categories&extension=com_content', 'class:category'),
		$createContent
	);

	if ($createContent)
	{
		$tree->addChild(
			new Node(JText::_('MOD_MENU_COM_CONTENT_NEW_CATEGORY'), 'index.php?option=com_categories&task=category.add&extension=com_content', 'class:newarticle')
		);
		$tree->getParent();
	}

	$tree->addChild(new Node(JText::_('MOD_MENU_COM_CONTENT_FEATURED'), 'index.php?option=com_content&view=featured', 'class:featured'));

	if ($user->authorise('core.manage', 'com_media'))
	{
		$tree->addSeparator();
		$tree->addChild(new Node(JText::_('MOD_MENU_MEDIA_MANAGER'), 'index.php?option=com_media', 'class:media'));
	}

	$tree->getParent();
}

/**
 * Components Submenu
 */

// Get the authorised components and sub-menus.
$components = ModMenuHelper::getComponents(true);

// Check if there are any components, otherwise, don't render the menu
$ju = false;
$pi = false;

if ($components)
{
	$tree->addChild(new Node(JText::_('MOD_MENU_COMPONENTS'), '#'), true);

	foreach ($components as &$component)
	{
		if ($component->title == 'com_postinstall')
		{
			$pi = true;
		}
		elseif ($component->title == 'com_joomlaupdate')
		{
			$ju = true;
		}
		elseif (!empty($component->submenu))
		{
			// This component has a db driven submenu.
			$tree->addChild(new Node($component->text, $component->link, $component->img), true);

			foreach ($component->submenu as $sub)
			{
				$tree->addChild(new Node($sub->text, $sub->link, $sub->img));
			}

			$tree->getParent();
		}
		else
		{
			$tree->addChild(new Node($component->text, $component->link, $component->img));
		}
	}

	$tree->getParent();
}

/**
 * Extensions Submenu
 */
$im = $user->authorise('core.manage', 'com_installer');
$mm = $user->authorise('core.manage', 'com_modules');
$pm = $user->authorise('core.manage', 'com_plugins');
$tm = $user->authorise('core.manage', 'com_templates');
$lm = $user->authorise('core.manage', 'com_languages');

if ($ju || $pi || $im || $mm || $pm || $tm || $lm)
{
	$tree->addChild(new Node(JText::_('MOD_MENU_EXTENSIONS_EXTENSION_MANAGER'), '#'), true);

	if ($ju)
	{
		$tree->addChild(new Node(JText::_('COM_JOOMLAUPDATE'), 'index.php?option=com_joomlaupdate', 'class:install'));
	}

	if ($pi)
	{
		$tree->addChild(new Node(JText::_('COM_POSTINSTALL'), 'index.php?option=com_postinstall', 'class:install'));
	}

	if ($im)
	{
		$tree->addChild(new Node(JText::_('MOD_MENU_SYSTEM'), 'index.php?option=com_installer&view=database', 'class:install'), true);

		$tree->addChild(new Node(JText::_('MOD_MENU_INSTALLER_SUBMENU_DATABASE'), 'index.php?option=com_installer&view=database', 'class:install'));
		$tree->addChild(new Node(JText::_('MOD_MENU_INSTALLER_SUBMENU_WARNINGS'), 'index.php?option=com_installer&view=warnings', 'class:install'));
		$tree->addChild(new Node(JText::_('MOD_MENU_INSTALLER_SUBMENU_UPDATESITES'), 'index.php?option=com_installer&view=updatesites', 'class:install'));
		$tree->getParent();

		$tree->addChild(new Node(JText::_('MOD_MENU_EXTENSIONS_EXTENSIONS'), 'index.php?option=com_installer', 'class:install'), true);

		$tree->addChild(new Node(JText::_('MOD_MENU_INSTALLER_SUBMENU_INSTALL'), 'index.php?option=com_installer', 'class:install'));
		$tree->addChild(new Node(JText::_('MOD_MENU_INSTALLER_SUBMENU_UPDATE'), 'index.php?option=com_installer&view=update', 'class:install'));
		$tree->addChild(new Node(JText::_('MOD_MENU_INSTALLER_SUBMENU_MANAGE'), 'index.php?option=com_installer&view=manage', 'class:install'));
		$tree->addChild(new Node(JText::_('MOD_MENU_INSTALLER_SUBMENU_DISCOVER'), 'index.php?option=com_installer&view=discover', 'class:install'));
		$tree->addSeparator();

		$tree->addChild(new Node(JText::_('MOD_MENU_INSTALLER_SUBMENU_LANGUAGES'), 'index.php?option=com_installer&view=languages', 'class:install'));
		$tree->getParent();
	}

	if (($ju || $pi || $im) && ($mm || $pm || $tm || $lm))
	{
		$tree->addSeparator();
	}

	if ($mm)
	{
		$tree->addChild(new Node(JText::_('MOD_MENU_EXTENSIONS_MODULE_MANAGER'), 'index.php?option=com_modules', 'class:module'));
	}

	if ($pm)
	{
		$tree->addChild(new Node(JText::_('MOD_MENU_EXTENSIONS_PLUGIN_MANAGER'), 'index.php?option=com_plugins', 'class:plugin'));
	}

	if ($tm)
	{
		$tree->addChild(new Node(JText::_('MOD_MENU_EXTENSIONS_TEMPLATE_MANAGER'), 'index.php?option=com_templates', 'class:themes'), $tm);

		$tree->addChild(new Node(JText::_('MOD_MENU_COM_TEMPLATES_SUBMENU_STYLES'), 'index.php?option=com_templates&view=styles', 'class:themes'));
		$tree->addChild(new Node(JText::_('MOD_MENU_COM_TEMPLATES_SUBMENU_TEMPLATES'), 'index.php?option=com_templates&view=templates', 'class:themes'));
		$tree->getParent();
	}

	if ($lm)
	{
		$tree->addChild(new Node(JText::_('MOD_MENU_EXTENSIONS_LANGUAGE_MANAGER'), 'index.php?option=com_languages', 'class:language'), $lm);

		$tree->addChild(new Node(JText::_('MOD_MENU_COM_LANGUAGES_SUBMENU_INSTALLED'), 'index.php?option=com_languages&view=installed', 'class:language'));
		$tree->addChild(new Node(JText::_('MOD_MENU_COM_LANGUAGES_SUBMENU_CONTENT'), 'index.php?option=com_languages&view=languages', 'class:language'));
		$tree->addChild(new Node(JText::_('MOD_MENU_COM_LANGUAGES_SUBMENU_OVERRIDES'), 'index.php?option=com_languages&view=overrides', 'class:language'));
		$tree->getParent();
	}

	$tree->getParent();
}

/**
 * Help Submenu
 */
if ($showhelp == 1)
{
	$tree->addChild(new Node(JText::_('MOD_MENU_HELP'), '#'), true);
	$tree->addChild(new Node(JText::_('MOD_MENU_HELP_JOOMLA'), 'index.php?option=com_admin&view=help', 'class:help'));
	$tree->addSeparator();

	$tree->addChild(new Node(JText::_('MOD_MENU_HELP_SUPPORT_OFFICIAL_FORUM'), 'http://forum.joomla.org', 'class:help-forum', false, '_blank'));

	if ($forum_url = $params->get('forum_url'))
	{
		$tree->addChild(new Node(JText::_('MOD_MENU_HELP_SUPPORT_CUSTOM_FORUM'), $forum_url, 'class:help-forum', false, '_blank'));
	}

	$debug = $lang->setDebug(false);

	if ($lang->hasKey('MOD_MENU_HELP_SUPPORT_OFFICIAL_LANGUAGE_FORUM_VALUE') && JText::_('MOD_MENU_HELP_SUPPORT_OFFICIAL_LANGUAGE_FORUM_VALUE') != '')
	{
		$forum_url = 'http://forum.joomla.org/viewforum.php?f=' . (int) JText::_('MOD_MENU_HELP_SUPPORT_OFFICIAL_LANGUAGE_FORUM_VALUE');
		$lang->setDebug($debug);
		$tree->addChild(new Node(JText::_('MOD_MENU_HELP_SUPPORT_OFFICIAL_LANGUAGE_FORUM'), $forum_url, 'class:help-forum', false, '_blank'));
	}

	$lang->setDebug($debug);
	$tree->addChild(new Node(JText::_('MOD_MENU_HELP_DOCUMENTATION'), 'https://docs.joomla.org', 'class:help-docs', false, '_blank'));
	$tree->addSeparator();

	$tree->addChild(new Node(JText::_('MOD_MENU_HELP_EXTENSIONS'), 'https://extensions.joomla.org', 'class:help-jed', false, '_blank'));
	$tree->addChild(
		new Node(JText::_('MOD_MENU_HELP_TRANSLATIONS'), 'https://community.joomla.org/translations.html', 'class:help-trans', false, '_blank')
	);
	$tree->addChild(new Node(JText::_('MOD_MENU_HELP_RESOURCES'), 'http://resources.joomla.org', 'class:help-jrd', false, '_blank'));
	$tree->addChild(new Node(JText::_('MOD_MENU_HELP_COMMUNITY'), 'https://community.joomla.org', 'class:help-community', false, '_blank'));
	$tree->addChild(
		new Node(JText::_('MOD_MENU_HELP_SECURITY'), 'https://developer.joomla.org/security-centre.html', 'class:help-security', false, '_blank')
	);
	$tree->addChild(new Node(JText::_('MOD_MENU_HELP_DEVELOPER'), 'https://developer.joomla.org', 'class:help-dev', false, '_blank'));
	$tree->addChild(new Node(JText::_('MOD_MENU_HELP_XCHANGE'), 'https://joomla.stackexchange.com', 'class:help-dev', false, '_blank'));
	$tree->addChild(new Node(JText::_('MOD_MENU_HELP_SHOP'), 'https://community.joomla.org/the-joomla-shop.html', 'class:help-shop', false, '_blank'));
	$tree->getParent();
}
