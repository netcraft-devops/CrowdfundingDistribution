<?php
/**
 * @package      ITPrism Plugins
 * @subpackage   Plugins
 * @author       Todor Iliev
 * @copyright    Copyright (C) 2016 Todor Iliev <todor@itprism.com>. All rights reserved.
 * @license      http://www.gnu.org/copyleft/gpl.html GNU/GPLv3
 */

// no direct access
defined('_JEXEC') or die;

class plgSystemDistributionMigration extends JPlugin
{
    /**
     * After initialise.
     *
     * @return  void
     *
     * @since   1.6
     */
    public function onAfterInitialise()
    {
        $app = JFactory::getApplication();
        /** @var $app JApplicationSite */

        if (!$app->isAdmin()) {
            return;
        }

        $document = JFactory::getDocument();
        /** @var $document JDocumentHtml */

        $type = $document->getType();
        if (strcmp('html', $type) !== 0) {
            return;
        }

        $option = $app->input->getCmd('option');
        $view   = $app->input->getCmd('view');

        if (strcmp($option, 'com_installer') !== 0 and strcmp($view, 'database') !== 0) {
            return;
        }

        $this->loadLanguage();
        $this->updateSchemas();
    }

    protected function updateSchemas()
    {
        $db = JFactory::getDbo();

        $query = $db->getQuery(true);

        $query
            ->select('a.extension_id, a.element, b.version_id')
            ->from($db->quoteName('#__extensions', 'a'))
            ->leftJoin($db->quoteName('#__schemas', 'b') . ' ON a.extension_id = b.extension_id')
            ->where('a.element = ' . $db->quote('com_crowdfunding'), 'OR')
            ->where('a.element = ' . $db->quote('com_crowdfundingfinance'), 'OR')
            ->where('a.element = ' . $db->quote('com_emailtemplates'), 'OR');

        $db->setQuery($query);
        $results = $db->loadAssocList('element');

        if (array_key_exists('com_crowdfunding', $results)) {
            $this->updateCrowdfunding($results, $db);
        }

        if (array_key_exists('com_crowdfundingfinance', $results)) {
            $this->updateCrowdfundingFinance($results, $db);
        }

        if (array_key_exists('com_emailtemplates', $results)) {
            $this->updateEmailTemplates($results, $db);
        }
    }

    /**
     * Update schemas of com_crowdfunding.
     *
     * @param array           $results
     * @param JDatabaseDriver $db
     *
     * @throws Exception
     */
    protected function updateCrowdfunding($results, $db)
    {
        JLoader::register('Crowdfunding\\Version', JPATH_LIBRARIES . '/Crowdfunding/Version.php');

        $extension  = 'com_crowdfunding';
        $version    = new Crowdfunding\Version();

        if (version_compare($results[$extension]['version_id'], $version->getShortVersion(), '<')) {
            // Migrate schemas
            $this->migrateSchemas($extension, $results[$extension]['version_id']);

            $query = $db->getQuery(true);
            $query
                ->update($db->quoteName('#__schemas'))
                ->set($db->quoteName('version_id') . '=' . $db->quote($version->getShortVersion()))
                ->where($db->quoteName('extension_id') . ' = ' . $db->quote($results[$extension]['extension_id']));

            $db->setQuery($query);
            $db->execute();

            $msg = JText::sprintf('PLG_SYSTEM_DISTRIBUTION_MIGRATION_UPDATED_SCHEMAS_S', $extension, $results[$extension]['extension_id'], $results[$extension]['version_id'], $version->getShortVersion());
            JFactory::getApplication()->enqueueMessage($msg);
        }
    }

    /**
     * Update schemas of com_crowdfunding finance.
     *
     * @param array           $results
     * @param JDatabaseDriver $db
     *
     * @throws Exception
     */
    protected function updateCrowdfundingFinance($results, $db)
    {
        JLoader::register('Crowdfundingfinance\\Version', JPATH_LIBRARIES . '/Crowdfundingfinance/Version.php');
        $extension  = 'com_crowdfundingfinance';
        $version    = new Crowdfundingfinance\Version();

        if (version_compare($results[$extension]['version_id'], $version->getShortVersion(), '<')) {
            // Migrate schemas
            $this->migrateSchemas($extension, $results[$extension]['version_id']);

            $query = $db->getQuery(true);
            $query
                ->update($db->quoteName('#__schemas'))
                ->set($db->quoteName('version_id') . '=' . $db->quote($version->getShortVersion()))
                ->where($db->quoteName('extension_id') . ' = ' . $db->quote($results[$extension]['extension_id']));

            $db->setQuery($query);
            $db->execute();

            $msg = JText::sprintf('PLG_SYSTEM_DISTRIBUTION_MIGRATION_UPDATED_SCHEMAS_S', $extension, $results[$extension]['extension_id'], $results[$extension]['version_id'], $version->getShortVersion());
            JFactory::getApplication()->enqueueMessage($msg);
        }
    }

    /**
     * Update schemas of com_emailtemplates.
     *
     * @param array           $results
     * @param JDatabaseDriver $db
     *
     * @throws Exception
     */
    protected function updateEmailTemplates($results, $db)
    {
        JLoader::register('Emailtemplates\\Version', JPATH_LIBRARIES . '/Emailtemplates/Version.php');
        
        $extension = 'com_emailtemplates';
        $version   = new Emailtemplates\Version();

        if (version_compare($results[$extension]['version_id'], $version->getShortVersion(), '<')) {
            // Migrate schemas
            $this->migrateSchemas($extension, $results[$extension]['version_id']);

            $query = $db->getQuery(true);
            $query
                ->update($db->quoteName('#__schemas'))
                ->set($db->quoteName('version_id') . '=' . $db->quote($version->getShortVersion()))
                ->where($db->quoteName('extension_id') . ' = ' . $db->quote($results[$extension]['extension_id']));

            $db->setQuery($query);
            $db->execute();

            $msg = JText::sprintf('PLG_SYSTEM_DISTRIBUTION_MIGRATION_UPDATED_SCHEMAS_S', $extension, $results[$extension]['extension_id'], $results[$extension]['version_id'], $version->getShortVersion());
            JFactory::getApplication()->enqueueMessage($msg);
        }
    }
    
    protected function migrateSchemas($extension, $currentVersion)
    {
        $versions = array();
        $releases = array();
        $folder   = JPath::clean(JPATH_ADMINISTRATOR . '/components/'.$extension.'/sql/updates', '/');

        $files    = JFolder::files($folder, '\.sql$', false, false, array('.svn', 'CVS', '.DS_Store', '__MACOSX'), array('^\..*', '.*~'), true);

        // Prepare the version of files that I have to execute.
        foreach ($files as $file) {
            $filename   = basename($file);
            $version    = JFile::stripExt($filename);
            if (version_compare($version, $currentVersion, '>')) {
                $versions[] = $version;
                sort($versions);
            }
        }

        // Prepare path to the update files.
        foreach ($versions as $version) {
            $releases[] = JPath::clean($folder .'/'. $version. '.sql', '/');
        }

        // Execute update queries.
        if (count($releases) > 0) {
            $db    = JFactory::getDbo();

            foreach ($releases as $file) {
                $content = file_get_contents($file);
                $queries = explode(';', $content);
                $queries = array_map('trim', $queries);
                $queries = array_filter($queries);

                if (count($queries) > 0) {
                    foreach ($queries as $sql) {
                        $db->setQuery($sql);
                        $db->execute();
                    }

                    $msg = JText::sprintf('PLG_SYSTEM_DISTRIBUTION_MIGRATION_EXECUTED_QUERIES_S', $file);
                    JFactory::getApplication()->enqueueMessage($msg);
                }
            }
        }
    }
}
