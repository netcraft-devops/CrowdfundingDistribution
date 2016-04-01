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

        // Check component enabled
        /*if (!JComponentHelper::isInstalled('com_crowdfunding', true)) {
            return;
        }*/
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
            $this->updateCrowdfundingFinance($results, $db);
            $this->updateEmailTemplates($results, $db);
        }
    }

    /**
     * Update schemas of com_crowdfunding.
     *
     * @param array $results
     * @param JDatabaseDriver $db
     *
     * @throws Exception
     */
    protected function updateCrowdfunding($results, $db)
    {
        $extensions = 'com_crowdfunding';

        JLoader::import('Crowdfunding.Version');
        $version = new Crowdfunding\Version();

        if (version_compare($results[$extensions]['version_id'], $version->getShortVersion(), '<')) {
            $query = $db->getQuery(true);
            $query
                ->update($db->quoteName('#__schemas'))
                ->set($db->quoteName('version_id') . '='. $db->quote($version->getShortVersion()))
                ->where($db->quoteName('extension_id') .' = ' . $db->quote($results[$extensions]['extension_id']));

            $db->setQuery($query);
            $db->execute();

            $msg = JText::sprintf('PLG_SYSTEM_DISTRIBUTION_MIGRATION_UPDATED_SCHEMAS_S', $extensions, $results[$extensions]['extension_id'], $results[$extensions]['version_id'], $version->getShortVersion());
            JFactory::getApplication()->enqueueMessage($msg);
        }
    }

    /**
     * Update schemas of com_crowdfunding finance.
     *
     * @param array $results
     * @param JDatabaseDriver $db
     *
     * @throws Exception
     */
    protected function updateCrowdfundingFinance($results, $db)
    {
        $extensions = 'com_crowdfundingfinance';
        JLoader::import('Crowdfundingfinance.Version');
        $version = new Crowdfundingfinance\Version();

        if (version_compare($results[$extensions]['version_id'], $version->getShortVersion(), '<')) {
            $query = $db->getQuery(true);
            $query
                ->update($db->quoteName('#__schemas'))
                ->set($db->quoteName('version_id') . '='. $db->quote($version->getShortVersion()))
                ->where($db->quoteName('extension_id') .' = ' . $db->quote($results[$extensions]['extension_id']));

            $db->setQuery($query);
            $db->execute();

            $msg = JText::sprintf('PLG_SYSTEM_DISTRIBUTION_MIGRATION_UPDATED_SCHEMAS_S', $extensions, $results[$extensions]['extension_id'], $results[$extensions]['version_id'], $version->getShortVersion());
            JFactory::getApplication()->enqueueMessage($msg);
        }
    }

    /**
     * Update schemas of com_emailtemplates.
     *
     * @param array $results
     * @param JDatabaseDriver $db
     *
     * @throws Exception
     */
    protected function updateEmailTemplates($results, $db)
    {
        $extensions = 'com_emailtemplates';
        JLoader::import('Emailtemplates.Version');
        $version = new Emailtemplates\Version();

        if (version_compare($results[$extensions]['version_id'], $version->getShortVersion(), '<')) {
            $query = $db->getQuery(true);
            $query
                ->update($db->quoteName('#__schemas'))
                ->set($db->quoteName('version_id') . '='. $db->quote($version->getShortVersion()))
                ->where($db->quoteName('extension_id') .' = ' . $db->quote($results[$extensions]['extension_id']));

            $db->setQuery($query);
            $db->execute();

            $msg = JText::sprintf('PLG_SYSTEM_DISTRIBUTION_MIGRATION_UPDATED_SCHEMAS_S', $extensions, $results[$extensions]['extension_id'], $results[$extensions]['version_id'], $version->getShortVersion());
            JFactory::getApplication()->enqueueMessage($msg);
        }
    }
}
