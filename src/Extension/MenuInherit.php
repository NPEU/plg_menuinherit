<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Content.MenuInherit
 *
 * @copyright   Copyright (C) NPEU 2024.
 * @license     MIT License; see LICENSE.md
 */

namespace NPEU\Plugin\Content\MenuInherit\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Modules\Administrator\Service\HTML\Modules;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use Joomla\Registry\Registry;

/*
NOTE - This plugin uses Joomla\Component\Modules\Administrator\Field\ModulesPositioneditField in
it's config to allow specific module positions to be selected.
However simply adding the field path to the for isn't enough as it fails:
/administrator/components/com_modules/src/Field/ModulesPositioneditField.php line 129
`positions = HTMLHelper::_('modules.positions', $clientId, 1, $this->value);`

The HTMLHelper doesn't have the required file/service registered as we havn't loaded the
com_modules component.
I managed to load the required service, see [1] below.
*/

/**
 * New menu items inherit various settings from it's parent.
 */
class MenuInherit extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;

    /**
     * An internal flag whether plugin should listen any event.
     *
     * @var bool
     *
     * @since   4.3.0
     */
    protected static $enabled = false;

    /**
     * Constructor
     *
     */
    public function __construct($subject, array $config = [], bool $enabled = true)
    {
        // The above enabled parameter was taken from teh Guided Tour plugin but it ir always seems
        // to be false so I'm not sure where this param is passed from. Overriding it for now.
        $enabled = true;


        #$this->loadLanguage();
        $this->autoloadLanguage = $enabled;
        self::$enabled          = $enabled;

        parent::__construct($subject, $config);

        $lang = Factory::getLanguage();
        $extension = 'com_modules';
        $base_dir = JPATH_ADMINISTRATOR;
        $language_tag = 'en-GB';
        $reload = true;
        $lang->load($extension, $base_dir, $language_tag, $reload);
        /*
            JHtml::_('formbehavior.chosen', '#jform_position', null, array('disable_search_threshold' => 0 ));
            JHtml::_('formbehavior.chosen', 'select');
        */
    }

    /**
     * function for getSubscribedEvents : new Joomla 4 feature
     *
     * @return array
     *
     * @since   4.3.0
     */
    public static function getSubscribedEvents(): array
    {
        return self::$enabled ? [
            'onContentBeforeSave'  => 'onContentBeforeSave',
            'onContentAfterSave'   => 'onContentAfterSave',
            'onContentPrepareForm' => 'onContentPrepareForm'
        ] : [];
    }

    /**
     * The save event.
     *
     * @param   Event  $event
     *
     * @return  boolean
     */
    public function onContentBeforeSave(Event $event): void
    {
        [$context, $object, $isNew, $data] = array_values($event->getArguments());

        // Check if we're saving a new menu item:
        if ($context != 'com_menus.item' || !$isNew) {
        #if ($context != 'com_menus.item') {
            return;
        }

        $item_params = new Registry;
        $item_params->loadString($object->params);
        $disable_inheritance = (bool) $item_params->get('disable_inheritance', false);
        if ($disable_inheritance == true) {
            return;
        }

        // Only run for items where there is a parent to inherit from:
        if ($object->parent_id == 1) {
            return;
        }

        // Get the parent menu item:
        $site = Factory::getContainer()->get(\Joomla\CMS\Application\SiteApplication::class);
        $menu = $site->getMenu();

        $parent_item = $menu->getItem($object->parent_id);
        $params = new Registry;
        $params->loadString($this->params);

        // Check the want to inherit the template style and one hasn't already been specified:
        if ($params->get('inherit_templates') == 1 && empty($object->template_style_id)) {
            $object->template_style_id = $parent_item->template_style_id;
        }

        // Check the want to inherit access and that it hasn't already been specified:
        if ($params->get('inherit_access') == 1 && $object->access == 1) {
            $object->access = $parent_item->access;
        }

        // Check the want to inherit the language and that a one hasn't already been specified:
        if ($params->get('inherit_lang') == 1 && $object->language == '*') {
            $object->language = $parent_item->language;
        }

        return;
    }

        /**
     *
     * @param   Event  $event
     *
     * @return  void
     */
    public function onContentAfterSave(Event $event): void
    {
        [$context, $object, $isNew] = array_values($event->getArguments());

        // Check if we're saving a new menu item:
        if ($context != 'com_menus.item' || !$isNew) {
        #if ($context != 'com_menus.item') {
            return;
        }

        // Don't run if menu inheritance is disabled for this menu item:
        $item_params = new Registry;
        $item_params->loadObject($object->params);
        $disable_inheritance = $item_params->get('disable_inheritance', false);

        if ($disable_inheritance == true) {
            return;
        }


        // Only run for items where there is a parent to inherit from:
        if ($object->parent_id == 1) {
            return;
        }

        $inherit_module_positions = (array) $this->params->get('inherit_module_positions', false);

        // If there are no positions specified, we need go no further:
        if (!$inherit_module_positions) {
            return;
        }

        $positions = [];
        foreach ($inherit_module_positions as $position) {
            $positions[] = $position->position;
        }

        // Check that we want to inherit modules:
        // (needs to be AfterSave as we need the new menuitem id)
        if ($this->params->get('inherit_modules') == 1) {
            $db = Factory::getDBO();

            $query = '
                SELECT moduleid, m.position
                FROM #__modules_menu
                JOIN #__modules m ON moduleid = m.id
                WHERE menuid = ' . $object->parent_id;

            $db->setQuery($query);
            $modules = $db->loadAssocList();
            if (empty($modules)) {
                return;
            }

            $query = 'INSERT IGNORE INTO #__modules_menu (moduleid,menuid) VALUES ';
            foreach ($modules as $module) {
                if (!in_array($module['position'], $positions)) {
                    continue;
                }
                $query .= '(' . $module['moduleid'] . ',' . $object->id . '),';
            }
            $query = trim($query, ',');
            $db->setQuery($query);
            $db->execute();
        }
    }

    /**
     * Prepare form and add my field.
     *
     * @param   Form  $form  The form to be altered.
     * @param   mixed  $data  The associated data for the form.
     *
     * @return  boolean
     *
     * @since   <your version>
     */
    public function onContentPrepareForm(Event $event): void
    {
        $args    = $event->getArguments();
        $form    = $args[0];
        $data    = $args[1];

        if (!($form instanceof \Joomla\CMS\Form\Form)) {
            throw new GenericDataException(Text::_('JERROR_NOT_A_FORM'), 500);
            return;
        }

        $app    = Factory::getApplication();
        $option = $app->input->get('option');

        if ($app->isClient('administrator') && $option == 'com_plugins') {
            # [1] (see note above)
            HTMLHelper::getServiceRegistry()->register('modules', new Modules());
        }

        if ($app->isClient('administrator') && $option == 'com_menus') {
            FormHelper::addFormPath(dirname(dirname(__DIR__)) . '/forms');
            $form->loadFile('menu_item', false);
        }

    }
}