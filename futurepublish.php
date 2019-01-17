<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.FuturePublish
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
    protected $fields_map_file;
    protected $fields_id_name_map;
    protected $future_fields = array();

    /**
     * Checks for queued / future content.
     *
     * @param   string   $context  The context of the content being passed to the plugin
     * @param   object   &$item    The article object
     * @param   object   &$params  The article params
     * @param   integer  $page     The 'page' number
     *
     * @return  string|boolean  HTML string containing code for the votes if in com_content else boolean false
     */
    protected function checkFutureContent($context, &$item, &$params, $page = 0)
    {
        $fields_definition_xml = file_get_contents(__DIR__ . '/fields_definition.xml');
        $fields_definition     = new SimpleXMLElement($fields_definition_xml);
        $d = $fields_definition;

        // Make a list of all valid fields:
        $this->fields_map_file = JPATH_PLUGINS . '/system/futurepublish/fields_id_name_map.json';
        if (!file_exists($this->fields_map_file)) {
            JError::raiseNotice(500, 'Coultd not find file: ' . $this->fields_map_file);
            return false;
        }

        $this->fields_id_name_map = json_decode(file_get_contents($this->fields_map_file), true);
        
        foreach ($item->jcfields as $jcfield) {
            if ($jcfield->group_title == $d->group_title && array_key_exists($jcfield->id, $this->fields_id_name_map)) {
                $this->future_fields[$this->fields_id_name_map[$jcfield->id]] = $jcfield->rawvalue;
            }
        }

        // Compare current time with future publish time:
        if (empty($this->future_fields['future-publish-date']) || strtotime(new JDate('now')) < strtotime($this->future_fields['future-publish-date'])) {
            // Future date not yet arrived. Do nothing.
            return false;
        }

        return true;
    }

    /**
     * Updates an article and saves it back to the database.
     *
     * @param   string   $context  The context of the content being passed to the plugin
     * @param   object   &$item    The article object
     * @param   object   &$params  The article params
     * @param   integer  $page     The 'page' number
     *
     * @return  string|boolean  HTML string containing code for the votes if in com_content else boolean false
     */
    protected function updateArticle($context, &$item, &$params, $page = 0)
    {
        $text = explode('<hr id="system-readmore" />', $this->future_fields['future-content']);
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
        $sql = "DELETE FROM `#__fields_values` WHERE `field_id` = " . array_keys($this->fields_id_name_map, 'future-publish-date')[0] . " AND `item_id` = " . $item->id;
        $db->setQuery($sql);
        $db->query();

        $sql = "DELETE FROM `#__fields_values` WHERE `field_id` = " . array_keys($this->fields_id_name_map, 'future-content')[0] . " AND `item_id` = " . $item->id;
        $db->setQuery($sql);
        $db->query();

        return;
    }

    /**
     * Checks for future content and if found and publish time has passed, update the content and
     * save it back to the database.
     *
     * @param   string   $context  The context of the content being passed to the plugin
     * @param   object   &$item    The article object
     * @param   object   &$params  The article params
     * @param   integer  $page     The 'page' number
     *
     * @return  string|boolean  HTML string containing code for the votes if in com_content else boolean false
     */
    public function onContentPrepare($context, &$item, &$params, $page = 0)
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
        #checks[] = 'ITEM: <pre>' . print_r(array_keys(get_object_vars($item)), true) . '</pre>';
        #$checks[] = 'PARAMS: <pre>' . print_r(array_keys(get_object_vars($params)), true) . '</pre>';
        #$checks[] = 'ITEM: <pre>' . print_r($item, true) . '</pre>';
        #$checks[] = 'PARAMS: <pre>' . print_r($params, true) . '</pre>';


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

        if ($this->checkFutureContent($context, $item, $params, $page)) {
            // Ok, so the time has passed, we need to update the article:
            $this->updateArticle($context, $item, $params, $page);
            return;
        }
    }


    /**
     * Checks we're loading an article form and adds necessary resources.
     *
     * @param   string   $context  The context of the content being passed to the plugin
     * @param   object   $data     The data object
     *
     * @return  string|boolean  HTML string containing code for the votes if in com_content else boolean false
     */
    public function onContentPrepareData($context, $data)
    {
        // KEEP THIS. It's useful for understanding what's going on.
        /*
        $checks = array();
        $checks[] = 'CONTEXT: ' . $context;
        $checks[] = 'CONTEXT CONTENT: ' . strpos($context, 'com_content');
        $checks[] = 'DATA: <pre>' . print_r(array_keys(get_object_vars($data)), true) . '</pre>';
        #$checks[] = 'DATA: <pre>' . print_r($data) . '</pre>';


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
        if (!isset($data->introtext)) {
            return;
        }

        $document = JFactory::getDocument();

        #$document->addStyleSheet('/css/admin-adjust.css');

        $document->addScript('/plugins/system/futurepublish/js/future-publish.js');
    }
    
    /**
     * Hack for adding future-publish event listener to Joomla calendar custom field.
     * Note I haven't found a way to do this in JS and the 'correct' way to specify event handlers
     * is actually in the markup anyway, so more of a fudge and a hack.
     *
     */
    public function onAfterRender()
    {
        $response     = JResponse::getBody();
        $search       = 'id="jform_com_fields_future_publish_date"';
        $replace      = 'onchange="FuturePublish.joomlaFieldCalendarUpdateAction()" id="jform_com_fields_future_publish_date"';
        $response     = str_replace($search, $replace, $response);
        JResponse::setBody($response);
    }
}