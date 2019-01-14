<?php
/**
 * @package     Joomla.Plugins
 * @subpackage  system.plg_futurepublish
 *
 * @copyright   Copyright (C) NPEU 2018.
 * @license     MIT License; see LICENSE.md
 */

defined('_JEXEC') or die;

/**
 * Checks for the existence of Custom Fields and creates them if not present.
 */
class plgSystemFuturePublishInstallerScript
{
    /**
     * This method is called after a component is installed.
     *
     * @param  \stdClass $parent - Parent object calling this method.
     *
     * @return void
     */
    public function install($parent)
    {
        $fields_definition_xml = file_get_contents(__DIR__ . '/fields_definition.xml');
        $fields_definition     = new SimpleXMLElement($fields_definition_xml);
        $d = $fields_definition;

        // Check that the necessary fields exist:

        JModelLegacy::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_fields/models', 'FieldsModel');

        // Group:

        $field_groups_model = JModelLegacy::getInstance('Groups', 'FieldsModel', array('ignore_request' => true));
        $field_groups_model->setState('filter.context', 'com_content.article');
        $field_groups = $field_groups_model->getItems();

        $projects_group = false;
        foreach ($field_groups as $group) {
            if ($group->title == $d->group_title) {
                $projects_group = $group;
                break;
            }
        }

        // If there's no matching group, make it:
        if (!$projects_group) {

            $field_group_model = JModelLegacy::getInstance('Group', 'FieldsModel', array('ignore_request' => true));

            $new_group_data = array(
                'context'     => 'com_content.article',
                'title'       => (string) $d->group_title,
                'description' => (string) $d->group_desc,
                'state'       => 1,
                'params'      => '{"display_readonly":"1"}',
                'language'    => '*'
            );

            $field_group_model->save($new_group_data);
            $item = $field_group_model->getItem();
            $group_id = $item->get('id');

            // Add the fields:
            $new_fields = array();
            foreach ($d->fields as $field) {
                $field->id       = null;
                $field->label    = $field->title;
                $field->language = '*';
                $field->group_id = $group_id;
                $field->context  = 'com_content.article';
                if (!isset($field->name)) {
                    $field->name = JApplication::stringURLSafe($field->title);
                }
                if (!isset($field->state)) {
                    $field->state = 1;
                }
                if (!isset($field->required)) {
                    $field->required = 0;
                }

                $field_model = JModelLegacy::getInstance('Field', 'FieldsModel', array('ignore_request' => true));
                $field_model->save($field);
                $item = $field_model->getItem();
                $field_id = $item->get('id');

                // Store a copy of the fields ID and Name:
                $new_fields[$field_id] = (string) $field->name;
            }

            // Store the fields id/name map so we have an immutable reference to each field when the
            // plugin is in use. The reason for this is that as we're using Custom Fields, not a
            // hard-coded form definition file, it's possible that the title, name or order of the
            // Custom Fields will be changed by the user, and we need to have something that's
            // immutable so that code can run properly. Without this, things could break.
            $fields_definition_dir = JPATH_PLUGINS . '/system/futurepublish/';

            if (!is_writable($fields_definition_dir)) {
                echo 'Dir not writable: ' . $fields_definition_dir;
                exit;
            }
            file_put_contents($fields_definition_dir . '/fields_id_name_map.json', json_encode($new_fields));
        }

        // Note: if there's already a group of the correct name, I'm assuming it already has the
        // necessary fields, for now.

        // Update the JS and CSS to use the new (correct) ID:
        /*$css = file_get_contents(JPATH_PLUGINS . '/system/projects/css/projects-categories.css');
        $css = str_replace('{{ ID }}', $group_id, $css);
        file_put_contents(JPATH_PLUGINS . '/system/projects/css/projects-categories.css', $css);

        $js = file_get_contents(JPATH_PLUGINS . '/system/projects/js/projects-categories.js');
        $js = str_replace('{{ ID }}', $group_id, $js);
        file_put_contents(JPATH_PLUGINS . '/system/projects/js/projects-categories.js', $js);*/
    }

    /**
     * This method is called after a component is uninstalled.
     *
     * @param  \stdClass $parent - Parent object calling this method.
     *
     * @return void
     */
    public function uninstall($parent)
    {
    }

    /**
     * This method is called after a component is updated.
     *
     * @param  \stdClass $parent - Parent object calling object.
     *
     * @return void
     */
    public function update($parent)
    {
    }

    /**
     * Runs just before any installation action is preformed on the component.
     * Verifications and pre-requisites should run in this function.
     *
     * @param  string    $type   - Type of PreFlight action. Possible values are:
     *                           - * install
     *                           - * update
     *                           - * discover_install
     * @param  \stdClass $parent - Parent object calling object.
     *
     * @return void
     */
    public function preflight($type, $parent)
    {
    }

    /**
     * Runs right after any installation action is preformed on the component.
     *
     * @param  string    $type   - Type of PostFlight action. Possible values are:
     *                           - * install
     *                           - * update
     *                           - * discover_install
     * @param  \stdClass $parent - Parent object calling object.
     *
     * @return void
     */
    public function postflight($type, $parent)
    {
    }
}