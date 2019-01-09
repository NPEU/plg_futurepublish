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
 * A plugin to allow new content to be published at a future time.
 */
class plgSystemFuturePublish extends JPlugin
{
    protected $autoloadLanguage = true;
    
    /**
     * Displays the voting area when viewing an article and the voting section is displayed before the article
     *
     * @param   string   $context  The context of the content being passed to the plugin
     * @param   object   &$item    The article object
     * @param   object   &$params  The article params
     * @param   integer  $page     The 'page' number
     *
     * @return  string|boolean  HTML string containing code for the votes if in com_content else boolean false
     */
    public function onContentBeforeDisplay($context, &$item, &$params, $page = 0)
    {
        // KEEP THIS. It's useful for understanding what's going on.
        /*
        $checks = array();
        $checks[] = 'CONTEXT: ' . $context;
        $checks[] = 'CONTEXT CONTENT: ' . strpos($context, 'com_content');
        $checks[] = 'ID: ' . $item->id;
        $checks[] = 'TITLE: ' . $item->title;
        #$checks[] = 'CLASS: ' . get_class($item);
        #$checks[] = 'CATID: ' . $item->catid;
        #checks[] = 'ITEM: <pre>' . var_print(array_keys(get_object_vars($item))) . '</pre>';
        #$checks[] = 'PARAMS: <pre>' . var_print(array_keys(get_object_vars($params))) . '</pre>';
        #$checks[] = 'ITEM: <pre>' . var_print($item) . '</pre>';
        #$checks[] = 'PARAMS: <pre>' . var_print($params) . '</pre>';


        JFactory::getApplication()->enqueueMessage(
            implode('<br>', $checks) . '<hr>',
            'notice');
        */

        // Check we're running in the right context:
        if (strpos($context, 'com_content' !== 0)) {
            return;
        }
        
        // And that the item we're dealing with is an article:
        // (this may not be the most robust check, but currently nothing else has 'introtext')
        if (!isset($item->introtext)) {
            return;
        }
        
        $fields_definition_xml = file_get_contents(__DIR__ . '/fields_definition.xml');
        $fields_definition     = new SimpleXMLElement($fields_definition_xml);
        $d = $fields_definition;

        // Make a list of all valid fields:
        $fields_id_name_map = json_decode(file_get_contents(JPATH_PLUGINS . '/system/futurepublish/fields_id_name_map.json'), true);
        $future_fields = array();

        foreach ($item->jcfields as $jcfield) {
            if ($jcfield->group_title == $d->group_title && array_key_exists($jcfield->id, $fields_id_name_map)) {
                $future_fields[$fields_id_name_map[$jcfield->id]] = $jcfield->rawvalue;
            }
        }

        // Compare current time with future publish time:
        if (empty($future_fields['future-publish-date']) || strtotime(new JDate('now')) < strtotime($future_fields['future-publish-date'])) {
            // Future date not yet arrived. Do nothing.
            return;
        }

        // Ok, so the time has passed, we need to update the article:
        $text = explode('<hr id="system-readmore" />', $future_fields['future-content']);
        $introtext = $text[0];
        $fulltext = '';
        if (isset($text[1])) {
            $fulltext = $text[1];
        }

        $item->introtext = $introtext;
        $item->fulltext  = $fulltext;
        $item->text      = implode(' ', $text);


        // And save it to the database:
        // Note we're actually re-loading it from the database first so we can use all the JTable 
        // functionality, including automatic versions.
        $article = JTable::getInstance('Content');
        $article->load($item->id);

        $article->introtext = $introtext;
        $article->fulltext  = $fulltext;
        
        if (!$article->check()) {
            JError::raiseNotice(500, $article->getError());
            return false;
        }
        
        if (!$article->store()) {
            JError::raiseNotice(500, $article->getError());
            return false;
        }
        
        // Can't load Admin version of ContentModelArticle because Site version is needed to load 
        // content properly, but this means there's no 'save' method available for updating the 
        // article. I can't find a way round this, so resorting to using database directly.

        $db = JFactory::getDbo();
        
        $sql = "UPDATE #__content SET `introtext` = " . $db->quote($item->introtext) . ", `fulltext` = " . $db->quote($item->fulltext) . ", `modified` = " . $db->quote(new JDate('now')) . ", `version` = " . $item->version++ . " WHERE id = " . $item->id;
        $db->setQuery($sql);
        $db->query();
        
        
        // And clear the Custom Field values:
        $sql = "DELETE FROM `#__fields_values` WHERE `field_id` = " . array_keys($fields_id_name_map, 'future-publish-date')[0] . " AND `item_id` = " . $item->id;
        $db->setQuery($sql);
        $db->query();
        
        $sql = "DELETE FROM `#__fields_values` WHERE `field_id` = " . array_keys($fields_id_name_map, 'future-content')[0] . " AND `item_id` = " . $item->id;
        $db->setQuery($sql);
        $db->query();
        
        return;
    }
}