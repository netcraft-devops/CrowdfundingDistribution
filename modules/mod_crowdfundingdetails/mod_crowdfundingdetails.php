<?php
/**
 * @package      Crowdfunding
 * @subpackage   Modules
 * @author       Todor Iliev
 * @copyright    Copyright (C) 2016 Todor Iliev <todor@itprism.com>. All rights reserved.
 * @license      GNU General Public License version 3 or later; see LICENSE.txt
 */

// no direct access
defined('_JEXEC') or die;

jimport('Prism.init');
jimport('Crowdfunding.init');

$moduleclassSfx = htmlspecialchars($params->get('moduleclass_sfx'));

$option = $app->input->get('option');
$view   = $app->input->get('view');

$allowedViews = array('backing', 'embed', 'report');

// If option is not 'com_crowdfunding' and view is not one of allowed,
// do not display anything.
if ((strcmp($option, 'com_crowdfunding') !== 0) or (!in_array($view, $allowedViews, true))) {
    echo JText::_('MOD_CROWDFUNDINGDETAILS_ERROR_INVALID_VIEW');
    return;
}

$projectId = $app->input->getInt('id');
if (!$projectId) {
    return;
}

$container  = Prism\Container::getContainer();
/** @var  $container Joomla\DI\Container */

// Get Project object from container.
$projectHash = Prism\Utilities\StringHelper::generateMd5Hash(Crowdfunding\Constants::CONTAINER_PROJECT, $projectId);
if (!$container->exists($projectHash)) {
    $project = new Crowdfunding\Project(JFactory::getDbo());
    $project->load($projectId);
    $container->set($projectHash, $project);
} else {
    $project     = $container->get($projectHash);
}

if (!$project->getId()) {
    return;
}

// Get component params
$componentParams = JComponentHelper::getParams('com_crowdfunding');
/** @var  $componentParams Joomla\Registry\Registry */

$socialPlatform  = $componentParams->get('integration_social_platform');
$imageFolder     = $componentParams->get('images_directory', 'images/crowdfunding');
$imageWidth      = $componentParams->get('image_width', 200);
$imageHeight     = $componentParams->get('image_height', 200);

// Get Currency object from container.
$currencyId   = $componentParams->get('project_currency');
$currencyHash = Prism\Utilities\StringHelper::generateMd5Hash(Crowdfunding\Constants::CONTAINER_CURRENCY, $currencyId);
if (!$container->exists($currencyHash)) {
    $currency = new Crowdfunding\Currency(JFactory::getDbo());
    $currency->load($currencyId);
    $container->set($currencyHash, $currency);
} else {
    $currency     = $container->get($currencyHash);
}

$amount = new Crowdfunding\Amount($componentParams);
$amount->setCurrency($currency);

// Get social platform and a link to the profile
$config = new Joomla\Registry\Registry(
    array(
        'platform' => $socialPlatform,
        'user_id' => $project->getUserId()
    )
);
$socialBuilder     = new Prism\Integration\Profile\Factory($config);
$socialProfile     = $socialBuilder->create();
$socialProfileLink = (!$socialProfile) ? null : $socialProfile->getLink();

// Get amounts
$fundedAmount = $amount->setValue($project->getGoal())->formatCurrency();
$raised       = $amount->setValue($project->getFunded())->formatCurrency();

// Prepare the value that I am going to display
$fundedPercents = JHtml::_('crowdfunding.funded', $project->getFundedPercent());

$user = JFactory::getUser($project->getUserId());

require JModuleHelper::getLayoutPath('mod_crowdfundingdetails', $params->get('layout', 'default'));