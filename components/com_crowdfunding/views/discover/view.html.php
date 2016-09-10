<?php
/**
 * @package      Crowdfunding
 * @subpackage   Components
 * @author       Todor Iliev
 * @copyright    Copyright (C) 2015 Todor Iliev <todor@itprism.com>. All rights reserved.
 * @license      GNU General Public License version 3 or later; see LICENSE.txt
 */

// no direct access
defined('_JEXEC') or die;

class CrowdfundingViewDiscover extends JViewLegacy
{
    use Crowdfunding\Container\MoneyHelper;

    /**
     * @var JDocumentHtml
     */
    public $document;

    /**
     * @var Joomla\Registry\Registry
     */
    protected $state;

    /**
     * @var Joomla\Registry\Registry
     */
    protected $params;

    protected $items;
    protected $pagination;

    protected $money;
    protected $filterPaginationLimit;
    protected $socialProfiles;
    protected $layoutData;

    protected $option;

    protected $pageclass_sfx;

    public function display($tpl = null)
    {
        $container         = Prism\Container::getContainer();

        $this->option      = JFactory::getApplication()->input->getCmd('option');
        
        $this->state       = $this->get('State');
        $this->items       = $this->get('Items');
        $this->pagination  = $this->get('Pagination');

        $this->params      = $this->state->get('params');

        $this->prepareItems($this->items);

        // Prepare social integration.
        $showAuthor                = CrowdfundingHelper::isShowAuthor($this->items, $this->params);
        if ($showAuthor) {
            $usersIds              = Prism\Utilities\ArrayHelper::getIds($this->items, 'user_id');
            $this->socialProfiles  = CrowdfundingHelper::prepareIntegration($this->params->get('integration_social_platform'), $usersIds);
        }

        $this->layoutData                 = new stdClass;
        $this->layoutData->items          = $this->items;
        $this->layoutData->params         = $this->params;
        $this->layoutData->money          = $this->getMoneyFormatter($container, $this->params);
        $this->layoutData->socialProfiles = $this->socialProfiles;
        $this->layoutData->imageFolder    = $this->params->get('images_directory', 'images/crowdfunding');

        $this->prepareDocument();

        parent::display($tpl);
    }

    /**
     * Prepares the document
     */
    protected function prepareDocument()
    {
        // Prepare page suffix
        $this->pageclass_sfx = htmlspecialchars($this->params->get('pageclass_sfx'));

        // Prepare page heading
        $this->preparePageHeading();

        // Prepare page heading
        $this->preparePageTitle();

        // Meta Description
        if ($this->params->get('menu-meta_description')) {
            $this->document->setDescription($this->params->get('menu-meta_description'));
        }

        // Meta keywords
        if ($this->params->get('menu-meta_keywords')) {
            $this->document->setMetaData('keywords', $this->params->get('menu-meta_keywords'));
        }
    }

    private function preparePageHeading()
    {
        $app = JFactory::getApplication();
        /** @var $app JApplicationSite */

        // Because the application sets a default page title,
        // we need to get it from the menu item itself
        $menus = $app->getMenu();
        $menu  = $menus->getActive();

        // Prepare page heading
        if ($menu) {
            $this->params->def('page_heading', $this->params->get('page_title', $menu->title));
        } else {
            $this->params->def('page_heading', JText::_('COM_CROWDFUNDING_DISCOVER_DEFAULT_PAGE_TITLE'));
        }
    }

    private function preparePageTitle()
    {
        $app = JFactory::getApplication();
        /** @var $app JApplicationSite */

        // Prepare page title
        $title = $this->params->get('page_title', '');

        // Add title before or after Site Name
        if (!$title) {
            $title = $app->get('sitename');
        } elseif ((int)$app->get('sitename_pagetitles', 0) === 1) {
            $title = JText::sprintf('JPAGETITLE', $app->get('sitename'), $title);
        } elseif ((int)$app->get('sitename_pagetitles', 0) === 2) {
            $title = JText::sprintf('JPAGETITLE', $title, $app->get('sitename'));
        }

        $this->document->setTitle($title);
    }

    private function prepareItems($items)
    {
        $options   = array();

        $helperBus = new Prism\Helper\HelperBus($items);
        $helperBus->addCommand(new Crowdfunding\Helper\PrepareItemsHelper());

        // Count the number of funders.
        if (strcmp('items_grid_two', $this->params->get('grid_layout')) === 0) {
            $helperBus->addCommand(new Crowdfunding\Helper\PrepareItemFundersHelper(JFactory::getDbo()));
        }

        $helperBus->handle($options);
    }
}
