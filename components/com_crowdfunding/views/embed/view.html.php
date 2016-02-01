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

class CrowdfundingViewEmbed extends JViewLegacy
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

    protected $item;

    protected $amount;
    protected $imageFolder;
    protected $embedLink;
    protected $socialProfileLink;
    protected $displayCreator;
    protected $embedCode;
    protected $form;

    protected $option;

    protected $pageclass_sfx;

    public function display($tpl = null)
    {
        $app = JFactory::getApplication();
        /** @var $app JApplicationSite */

        $this->option = JFactory::getApplication()->input->get('option');
        
        $this->state  = $this->get('State');
        $this->item   = $this->get('Item');

        // Get params
        $this->params = $this->state->get('params');
        /** @var  $this->params Joomla\Registry\Registry */

        $this->imageFolder = $this->params->get('images_directory', 'images/crowdfunding');

        if (!$this->item) {
            $app->enqueueMessage(JText::_('COM_CROWDFUNDING_ERROR_INVALID_PROJECT'), 'notice');
            $app->redirect(JRoute::_(CrowdfundingHelperRoute::getDiscoverRoute(), false));
            return;
        }

        // Get currency
        // Get currency
        $currency     = Crowdfunding\Currency::getInstance(JFactory::getDbo(), $this->params->get('project_currency'));
        $this->amount = new Crowdfunding\Amount($this->params);
        $this->amount->setCurrency($currency);

        // Integrate with social profile.
        $this->displayCreator = $this->params->get('integration_display_creator', true);

        // Prepare integration. Load avatars and profiles.
        if ($this->displayCreator and (is_object($this->item) and $this->item->user_id > 0)) {
            $socialProfilesBuilder = new Prism\Integration\Profile\Builder(
                array(
                    'social_platform' => $this->params->get('integration_social_platform'),
                    'user_id' => $this->item->user_id
                )
            );
            $socialProfilesBuilder->build();

            $socialProfile = $socialProfilesBuilder->getProfile();
            $this->socialProfileLink  = (!$socialProfile) ? null : $socialProfile->getLink();
        }

        // Set a link to project page
        $uri              = JUri::getInstance();
        $host             = $uri->toString(array('scheme', 'host'));
        $this->item->link = $host . JRoute::_(CrowdfundingHelperRoute::getDetailsRoute($this->item->slug, $this->item->catslug), false);

        // Set a link to image
        $this->item->link_image = $host . '/' . $this->imageFolder . '/' . $this->item->image;

        $layout = $this->getLayout();
        
        if ($this->getLayout() === 'email') {
            if (!$this->params->get('security_display_friend_form', 0)) {
                $app->enqueueMessage(JText::_('COM_CROWDFUNDING_ERROR_CANT_SEND_MAIL'), 'notice');
                $app->redirect(JRoute::_($this->item->link, false));

                return;
            }

            $this->prepareEmailForm($this->item);
        } else {
            $this->embedCode = $this->prepareEmbedCode($this->item, $host);
        }

        $this->prepareDocument();

        parent::display($tpl);
    }

    /**
     * Generate HTML code for embeding.
     *
     * @param object $item
     * @param string $host
     *
     * @return string
     */
    protected function prepareEmbedCode($item, $host)
    {
        // Generate embed link
        $embedLink = $host . JRoute::_(CrowdfundingHelperRoute::getEmbedRoute($item->slug, $item->catslug) . '&layout=widget&tmpl=component', false);

        return '<iframe src="' . $embedLink . '"" width="280px" height="560px" frameborder="0" scrolling="no"></iframe>';
    }

    /**
     * Display a form that will be used for sending mail to friend
     *
     * @param stdClass $item
     */
    protected function prepareEmailForm($item)
    {
        $model = JModelLegacy::getInstance('FriendMail', 'CrowdfundingModel', $config = array('ignore_request' => false));

        // Prepare default content of the form
        $formData = array(
            'id'      => $item->id,
            'subject' => JText::sprintf('COM_CROWDFUNDING_SEND_FRIEND_DEFAULT_SUBJECT', $item->title),
            'message' => JText::sprintf('COM_CROWDFUNDING_SEND_FRIEND_DEFAULT_MESSAGE', $item->link)
        );

        // Set user data
        $user = JFactory::getUser();
        if ((int)$user->get('id') > 0) {
            $formData['sender_name'] = $user->name;
            $formData['sender']      = $user->email;
        }

        $this->form = $model->getForm($formData);

        // Scripts
        JHtml::_('behavior.tooltip');
        JHtml::_('behavior.formvalidation');
    }

    /**
     * Prepare the document
     */
    protected function prepareDocument()
    {
        $app = JFactory::getApplication();
        /** @var $app JApplicationSite */

        // Escape strings for HTML output
        $this->pageclass_sfx = htmlspecialchars($this->params->get('pageclass_sfx'));

        // Prepare page heading
        $this->preparePageHeading();

        // Prepare page heading
        $this->preparePageTitle();

        if ($this->params->get('menu-meta_description')) {
            $this->document->setDescription($this->params->get('menu-meta_description'));
        } else {
            $this->document->setDescription($this->item->short_desc);
        }

        if ($this->params->get('menu-meta_keywords')) {
            $this->document->setMetaData('keywords', $this->params->get('menu-meta_keywords'));
        }

        if ($this->params->get('robots')) {
            $this->document->setMetaData('robots', $this->params->get('robots'));
        }

        // Breadcrumb
        $pathway           = $app->getPathway();
        $currentBreadcrumb = JHtmlString::truncate($this->item->title, 16);
        $pathway->addItem($currentBreadcrumb, '');

        // Add scripts
        JHtml::_('jquery.framework');
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
            $this->params->def('page_heading', JText::sprintf('COM_CROWDFUNDING_DETAILS_DEFAULT_PAGE_TITLE', $this->item->title));
        }
    }

    private function preparePageTitle()
    {
        $app = JFactory::getApplication();
        /** @var $app JApplicationSite */

        // Prepare page title
        if (strcmp('email', $this->getLayout()) === 0) {
            $title = $this->item->title . ' | ' . JText::_('COM_CROWDFUNDING_EMAIL_TO_FRIEND');
        } else {
            $title = $this->item->title . ' | ' . JText::_('COM_CROWDFUNDING_EMBED_CODE');
        }

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
}
