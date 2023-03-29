<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Content.MenuInherit
 *
 * @copyright   Copyright (C) NPEU 2019.
 * @license     MIT License; see LICENSE.md
 */

defined('_JEXEC') or die;

$lang = JFactory::getLanguage();
$extension = 'com_modules';
$base_dir = JPATH_ADMINISTRATOR;
$language_tag = 'en-GB';
$reload = true;
$lang->load($extension, $base_dir, $language_tag, $reload);

JHtml::_('formbehavior.chosen', '#jform_position', null, array('disable_search_threshold' => 0 ));
JHtml::_('formbehavior.chosen', 'select');


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

        // Don't run if menu inheritance is disabled for this menu item:
        $item_params = new JRegistry($item->params);
        $disable_inheritance = (bool) $item_params->get('disable_inheritance', false);
        if ($disable_inheritance == true) {
            return;
        }

        // Only run for NEW items where there is a parent to inherit from:
        //if (!$isNew || $item->parent_id == 1) {

        // Only run for items where there is a parent to inherit from:
        if ($item->parent_id == 1) {
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

        // Don't run if menu inheritance is disabled for this menu item:
        $item_params = new JRegistry($item->params);
        $disable_inheritance = (bool) $item_params->get('disable_inheritance', false);
        if ($disable_inheritance == true) {
            return;
        }

        // Only run for NEW items where there is a parent to inherit from:
        //if (!$isNew || $item->parent_id == 1) {

        // Only run for items where there is a parent to inherit from:
        if ($item->parent_id == 1) {
            return;
        }


        $params = new JRegistry($this->params);
        $inherit_module_positions = $params->get('inherit_module_positions', false);

        // If there are no positions specified, we need go no further:
        if (!$inherit_module_positions) {
            return;
        }

        foreach ($inherit_module_positions as $position) {
            $inherit_module_positions[] = $position['position'];
        }

        // Check that we want to inherit modules:
        // (needs to be AfterSave as we need the new menuitem id)
        if ($params->get('inherit_modules') == 1) {
            $db = JFactory::getDBO();

            $query = '
                SELECT moduleid, m.position
                FROM #__modules_menu
                JOIN #__modules m ON moduleid = m.id
                WHERE menuid = ' . $item->parent_id;

            $db->setQuery($query);
            $modules = $db->loadAssocList();

            if (empty($modules)) {
                return;
            }

            $query = 'INSERT IGNORE INTO #__modules_menu (moduleid,menuid) VALUES ';
            foreach ($modules as $module) {
                if (!in_array($module['position'], $inherit_module_positions)) {
                    continue;
                }
                $query .= '(' . $module['moduleid'] . ',' . $item->id . '),';
            }
            $query = trim($query, ',');

            $db->setQuery($query);
            $db->execute();
        }
	}

    /**
     * Prepare form and add my field.
     *
     * @param   JForm  $form  The form to be altered.
     * @param   mixed  $data  The associated data for the form.
     *
     * @return  boolean
     *
     * @since   <your version>
     */
    public function onContentPrepareForm($form, $data)
    {
        $app    = JFactory::getApplication();
        $option = $app->input->get('option');

        if ($app->isClient('administrator') && $option == 'com_menus') {
            JForm::addFormPath(__DIR__ . '/forms');
            $form->loadFile('menu_item', false);
        }

        return true;
    }

}
