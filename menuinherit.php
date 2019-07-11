<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Content.MenuInherit
 *
 * @copyright   Copyright (C) NPEU 2019.
 * @license     MIT License; see LICENSE.md
 */

defined('_JEXEC') or die;

/**
 * New menu items inherit various settings from it's parent.
 */
class plgContentMenuInherit extends JPlugin
{
    protected $autoloadLanguage = true;

    /**
     * Before save event.
     *
     * @param   string   $context  The context
     * @param   JTable   $item     The table
     * @param   boolean  $isNew    Is new item
     * @param   array    $data     The validated data
     *
     * @return  boolean
     */
    public function onContentBeforeSave($context, $item, $isNew, $data = array())
    {
        // Check if we're saving a menu item:
        if ($context != 'com_menus.item') {
            return;
        }

        // Only run for new items where there is a parent to inherit from:
        if (!$isNew || empty($item->parent_id)) {
            return;
        }

        // Get the parent menu item:
        $site = new JApplicationSite;
        $menu = $site->getMenu();

        $parent_item = $menu->getItem($item->parent_id);
        $params = new JRegistry($this->params);

        // Check the want to inherit the template style and one hasn't already been specified:
        if ($params->get('inherit_templates') == 1 && empty($item->template_style_id)) {
            $item->template_style_id = $parent_item->template_style_id;
        }

        // Check the want to inherit access and that it hasn't already been specified:
        if ($params->get('inherit_access') == 1 && $item->access == 1) {
            $item->access = $parent_item->access;
        }

        // Check the want to inherit the language and that a one hasn't already been specified:
        if ($params->get('inherit_lang') == 1 && $item->language == '*') {
            $item->language = $parent_item->language;
        }

        return true;
    }

    /**
	 * After save event.
	 * Content is passed by reference, but after the save, so no changes will be saved.
	 * Method is called right after the content is saved.
	 *
	 * @param   string  $context  The context of the content passed to the plugin (added in 1.6)
	 * @param   object  $item  A JTableContent object
	 * @param   bool    $isNew    If the content has just been created
	 *
	 * @return  void
	 */
	public function onContentAfterSave($context, $item, $isNew)
	{
        // Check if we're saving a menu item:
        if ($context != 'com_menus.item') {
            return;
        }

        // Only run for new items where there is a parent to inherit from:
        if (!$isNew || empty($item->parent_id)) {
            return;
        }

        $params = new JRegistry($this->params);

        // Check the want to inherit modules: (needs to be here so we have the new menuitem id)
        if ($params->get('inherit_modules') == 1) {
            $db = JFactory::getDBO();

            $query = '
                SELECT moduleid
                FROM #__modules_menu
                WHERE menuid = ' . $item->parent_id;

            $db->setQuery($query);
            $module_ids = $db->loadColumn();

            $query = 'INSERT INTO #__modules_menu (moduleid,menuid) VALUES ';
            foreach ($module_ids as $module_id) {
                $query .= '(' . $module_id . ',' . $item->id . '),';
            }
            $query = trim($query, ',');
            $db->setQuery($query);
            $db->loadResult();
        }
	}

}