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

    protected $amount;
    protected $numberInRow;
    protected $imageFolder;
    protected $displayCreator;
    protected $filterPaginationLimit;
    protected $displayFilters;
    protected $socialProfiles;
    protected $titleLength;
    protected $descriptionLength;
    protected $layoutData;

    protected $option;

    protected $pageclass_sfx;

    protected $helperBus;

    public function display($tpl = null)
    {
        $this->option     = JFactory::getApplication()->input->getCmd('option');
        
        $this->state      = $this->get('State');
        $this->items      = $this->get('Items');
        $this->pagination = $this->get('Pagination');

        $this->params      = $this->state->get('params');

        $this->numberInRow = (int)$this->params->get('items_row', 3);

        $this->prepareItems($this->items);

        // Get the folder with images
        $this->imageFolder = $this->params->get('images_directory', 'images/crowdfunding');

        // Get currency
        $currency     = Crowdfunding\Currency::getInstance(JFactory::getDbo(), $this->params->get('project_currency'));
        $this->amount = new Crowdfunding\Amount($this->params);
        $this->amount->setCurrency($currency);

        $this->displayCreator = (bool)$this->params->get('display_creator', true);

        // Prepare social integration.
        if ($this->displayCreator !== false) {
            $usersIds              = CrowdfundingHelper::fetchIds($this->items, 'user_id');
            $this->socialProfiles  = CrowdfundingHelper::prepareIntegrations($this->params->get('integration_social_platform'), $usersIds);
        }

        $this->layoutData = array(
            'items' => $this->items,
            'params' => $this->params,
            'amount' => $this->amount,
            'socialProfiles' => $this->socialProfiles,
            'imageFolder' => $this->imageFolder,
            'titleLength' => $this->params->get('discover_title_length', 0),
            'descriptionLength' => $this->params->get('discover_description_length', 0),
            'span'  => ($this->numberInRow > 0) ? round(12 / $this->numberInRow) : 4
        );

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
