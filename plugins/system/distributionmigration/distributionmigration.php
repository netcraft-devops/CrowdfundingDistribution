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

        if (strcmp($option, 'com_installer') === 0 and strcmp($view, 'database') === 0 and JComponentHelper::isInstalled('com_crowdfunding')) {
            $this->loadLanguage();
            $this->updateSchemas();
        }
    }

    protected function updateSchemas()
    {
        $db = JFactory::getDbo();

        // Crowdfunding Platform
        $query = $db->getQuery(true);
        $query
            ->select('a.extension_id, a.element, a.manifest_cache, b.version_id')
            ->from($db->quoteName('#__extensions', 'a'))
            ->leftJoin($db->quoteName('#__schemas', 'b') . ' ON a.extension_id = b.extension_id')
            ->where('a.element = ' . $db->quote('com_crowdfunding'));

        $db->setQuery($query);
        $result = (array)$db->loadAssoc();

        if (count($result) > 0) {
            $this->updateCrowdfunding($result, $db);
        }

        // Crowdfunding Finance
        $query = $db->getQuery(true);
        $query
            ->select('a.extension_id, a.element, a.manifest_cache, b.version_id')
            ->from($db->quoteName('#__extensions', 'a'))
            ->leftJoin($db->quoteName('#__schemas', 'b') . ' ON a.extension_id = b.extension_id')
            ->where('a.element = ' . $db->quote('com_crowdfundingfinance'));

        $db->setQuery($query);
        $result = (array)$db->loadAssoc();

        if (count($result) > 0) {
            $this->updateCrowdfundingFinance($result, $db);
        }

        // Crowdfunding Finance
        $query = $db->getQuery(true);
        $query
            ->select('a.extension_id, a.element, a.manifest_cache, b.version_id')
            ->from($db->quoteName('#__extensions', 'a'))
            ->leftJoin($db->quoteName('#__schemas', 'b') . ' ON a.extension_id = b.extension_id')
            ->where('a.element = ' . $db->quote('com_emailtemplates'));

        $db->setQuery($query);
        $result = (array)$db->loadAssoc();

        if (count($result) > 0) {
            $this->updateEmailTemplates($result, $db);
        }

        // Prism Library
        $query = $db->getQuery(true);
        $query
            ->select('a.extension_id, a.element, a.manifest_cache')
            ->from($db->quoteName('#__extensions', 'a'))
            ->where('a.element = ' . $db->quote('lib_prism'));

        $db->setQuery($query);
        $result = (array)$db->loadAssoc();

        if (count($result) > 0) {
            $this->updatePrismLibrary($result, $db);
        }

        // Clear updates cache.
        $db->setQuery('TRUNCATE TABLE #__updates');
        $db->execute();
    }

    /**
     * Update schemas.
     *
     * @param array $result
     * @param JDatabaseDriver $db
     *
     * @throws Exception
     */
    protected function updateCrowdfunding($result, $db)
    {
        JLoader::register('Crowdfunding\\Version', JPATH_LIBRARIES . '/Crowdfunding/Version.php');

        $extension = 'com_crowdfunding';
        $version   = new Crowdfunding\Version();

        $manifestCache = new \Joomla\Registry\Registry($result['manifest_cache']);
        $manifestVersion = $manifestCache->get('version');

        if (version_compare($result['version_id'], $version->getShortVersion(), '<') or version_compare($manifestVersion, $version->getShortVersion(), '<')) {
            // Migrate schemas
            if ($this->params->get('migrate_schemas', false)) {
                $this->migrateSchemas($extension, $result['version_id']);
            }

            // Update the version of the component.
            $this->updateComponentVersion($db, $result['extension_id'], $version->getShortVersion());
            $msg = JText::sprintf('PLG_SYSTEM_DISTRIBUTION_MIGRATION_UPDATED_SCHEMAS_S', $extension, $result['extension_id'], $result['version_id'], $version->getShortVersion());
            JFactory::getApplication()->enqueueMessage($msg);

            // Update the version of the package.
            $this->updatePackageVersion($db, $version->getShortVersion(), 'pkg_crowdfunding');
            $msg = JText::sprintf('PLG_SYSTEM_DISTRIBUTION_MIGRATION_UPDATED_MANIFEST_CACHE_S', $extension, $result['extension_id'], $manifestVersion, $version->getShortVersion());
            JFactory::getApplication()->enqueueMessage($msg);
        }
    }

    /**
     * Update schemas of com_crowdfundingfinance.
     *
     * @param array           $result
     * @param JDatabaseDriver $db
     *
     * @throws Exception
     */
    protected function updateCrowdfundingFinance($result, $db)
    {
        JLoader::register('Crowdfundingfinance\\Version', JPATH_LIBRARIES . '/Crowdfundingfinance/Version.php');

        $extension = 'com_crowdfundingfinance';
        $version   = new Crowdfundingfinance\Version();

        $manifestCache = new \Joomla\Registry\Registry($result['manifest_cache']);
        $manifestVersion = $manifestCache->get('version');

        if (version_compare($result['version_id'], $version->getShortVersion(), '<') or version_compare($manifestVersion, $version->getShortVersion(), '<')) {
            // Migrate schemas
            if ($this->params->get('migrate_schemas', false)) {
                $this->migrateSchemas($extension, $result['version_id']);
            }

            // Update the version of the component.
            $this->updateComponentVersion($db, $result['extension_id'], $version->getShortVersion());
            $msg = JText::sprintf('PLG_SYSTEM_DISTRIBUTION_MIGRATION_UPDATED_SCHEMAS_S', $extension, $result['extension_id'], $result['version_id'], $version->getShortVersion());
            JFactory::getApplication()->enqueueMessage($msg);

            // Update the version of the package.
            $this->updatePackageVersion($db, $version->getShortVersion(), 'pkg_crowdfundingfinance');
            $msg = JText::sprintf('PLG_SYSTEM_DISTRIBUTION_MIGRATION_UPDATED_MANIFEST_CACHE_S', $extension, $result['extension_id'], $manifestVersion, $version->getShortVersion());
            JFactory::getApplication()->enqueueMessage($msg);
        }
    }

    /**
     * Update schemas of com_emailtemplates.
     *
     * @param array           $result
     * @param JDatabaseDriver $db
     *
     * @throws Exception
     */
    protected function updateEmailTemplates($result, $db)
    {
        JLoader::register('Emailtemplates\\Version', JPATH_LIBRARIES . '/Emailtemplates/Version.php');

        $extension = 'com_emailtemplates';
        $version   = new Emailtemplates\Version();

        $manifestCache = new \Joomla\Registry\Registry($result['manifest_cache']);
        $manifestVersion = $manifestCache->get('version');
        
        if (version_compare($result['version_id'], $version->getShortVersion(), '<') or version_compare($manifestVersion, $version->getShortVersion(), '<')) {
            // Migrate schemas
            if ($this->params->get('migrate_schemas', false)) {
                $this->migrateSchemas($extension, $result['version_id']);
            }

            // Update the version of the component.
            $this->updateComponentVersion($db, $result['extension_id'], $version->getShortVersion());
            $msg = JText::sprintf('PLG_SYSTEM_DISTRIBUTION_MIGRATION_UPDATED_SCHEMAS_S', $extension, $result['extension_id'], $result['version_id'], $version->getShortVersion());
            JFactory::getApplication()->enqueueMessage($msg);

            // Update the version of the package.
            $this->updatePackageVersion($db, $version->getShortVersion(), 'pkg_emailtemplates');
            $msg = JText::sprintf('PLG_SYSTEM_DISTRIBUTION_MIGRATION_UPDATED_MANIFEST_CACHE_S', $extension, $result['extension_id'], $manifestVersion, $version->getShortVersion());
            JFactory::getApplication()->enqueueMessage($msg);
        }
    }

    /**
     * Update schemas of lib_prism.
     *
     * @param array           $result
     * @param JDatabaseDriver $db
     *
     * @throws Exception
     */
    protected function updatePrismLibrary($result, $db)
    {
        JLoader::register('Prism\\Version', JPATH_LIBRARIES . '/Prism/Version.php');

        $extension = 'lib_prism';
        $version   = new Prism\Version();

        $manifestCache = new \Joomla\Registry\Registry($result['manifest_cache']);
        $manifestVersion = $manifestCache->get('version');

        if (version_compare($manifestVersion, $version->getShortVersion(), '<')) {
            // Update the version of the library.
            $this->updatePrismLibraryVersion($db, $result['extension_id'], $version->getShortVersion());
            $msg = JText::sprintf('PLG_SYSTEM_DISTRIBUTION_MIGRATION_UPDATED_SCHEMAS_S', $extension, $result['extension_id'], $manifestVersion, $version->getShortVersion());
            JFactory::getApplication()->enqueueMessage($msg);
        }
    }

    protected function updateComponentVersion(JDatabaseDriver $db, $componentId, $version)
    {
        // Update the version in schemas.
        $query = $db->getQuery(true);
        $query
            ->update($db->quoteName('#__schemas'))
            ->set($db->quoteName('version_id') .'='. $db->quote($version))
            ->where($db->quoteName('extension_id') .'='. (int)$componentId);

        $db->setQuery($query);
        $db->execute();

        // Update the version in manifest cache.
        $query = $db->getQuery(true);
        $query
            ->select('a.manifest_cache')
            ->from($db->quoteName('#__extensions', 'a'))
            ->where($db->quoteName('extension_id') .'='. (int)$componentId);

        $db->setQuery($query);
        $resultManifestCache = $db->loadResult();

        if ($resultManifestCache !== null) {
            $manifestCache = new Joomla\Registry\Registry($resultManifestCache);
            $manifestCache->set('version', $version);

            // Store changed manifest cache.
            $query = $db->getQuery(true);
            $query
                ->update($db->quoteName('#__extensions', 'a'))
                ->set($db->quoteName('manifest_cache') . '=' . $db->quote($manifestCache->toString()))
                ->where($db->quoteName('extension_id') . '=' . (int)$componentId);

            $db->setQuery($query);
            $db->execute();
        }
    }

    protected function updatePrismLibraryVersion(JDatabaseDriver $db, $libraryId, $version)
    {
        // Update the version in manifest cache.
        $query = $db->getQuery(true);
        $query
            ->select('a.manifest_cache')
            ->from($db->quoteName('#__extensions', 'a'))
            ->where($db->quoteName('extension_id') .'='. (int)$libraryId);

        $db->setQuery($query);
        $resultManifestCache = $db->loadResult();

        if ($resultManifestCache !== null) {
            $manifestCache = new Joomla\Registry\Registry($resultManifestCache);
            $manifestCache->set('version', $version);

            // Store changed manifest cache.
            $query = $db->getQuery(true);
            $query
                ->update($db->quoteName('#__extensions', 'a'))
                ->set($db->quoteName('manifest_cache') . '=' . $db->quote($manifestCache->toString()))
                ->where($db->quoteName('extension_id') . '=' . (int)$libraryId);

            $db->setQuery($query);
            $db->execute();
        }
    }

    protected function updatePackageVersion(JDatabaseDriver $db, $version, $extension)
    {
        $query = $db->getQuery(true);
        $query
            ->select('a.extension_id, a.manifest_cache')
            ->from($db->quoteName('#__extensions', 'a'))
            ->where($db->quoteName('element') .'='. $db->quote($extension))
            ->where($db->quoteName('type') .'='. $db->quote('package'));

        $db->setQuery($query);
        $resultManifestCache = $db->loadObject();

        if ($resultManifestCache !== null) {
            $manifestCache = new Joomla\Registry\Registry($resultManifestCache->manifest_cache);
            $manifestCache->set('version', $version);

            // Store changed manifest cache.
            $query = $db->getQuery(true);
            $query
                ->update($db->quoteName('#__extensions', 'a'))
                ->set($db->quoteName('manifest_cache') . '=' . $db->quote($manifestCache->toString()))
                ->where($db->quoteName('extension_id') . '=' . (int)$resultManifestCache->extension_id);

            $db->setQuery($query);
            $db->execute();
        }
    }

    protected function migrateSchemas($extension, $currentVersion)
    {
        jimport('joomla.filesystem.file');
        jimport('joomla.filesystem.folder');
        jimport('joomla.filesystem.path');

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
