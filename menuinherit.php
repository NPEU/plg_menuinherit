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
     * The save event.
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

        // Only run for new items:
        if (!$isNew) {
            return;
        }

        // Check that a template_style hasn't already been specified:
        if (!empty($item->template_style_id)) {
            return;
        }

        // Get the parent menu item:
        $site = new JApplicationSite;
        $menu = $site->getMenu();

        $parent_item = $menu->getItem($item->parent_id);
        
        $config = array();
        
        if ($config['template'] == true) {
            $this->inheritTemplate($item, $parent_item);
        }


        return true;
    }
    
        /**
     * The save event.
     *
     * @param   Object   $item          The item
     * @param   Object   $parent_item   The parent
     *
     * @return  boolean
     */
    protected function inheritTemplate(&$item, &$parent_item) {
        
        $item->template_style_id = $parent_item->template_style_id;
        
        return true;
    }
}