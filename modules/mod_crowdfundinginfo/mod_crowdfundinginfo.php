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

// If option is not 'com_crowdfunding' and view is not 'details',
// do not display anything.
if ((strcmp($option, 'com_crowdfunding') !== 0) or (strcmp($view, 'details') !== 0)) {
    echo JText::_('MOD_CROWDFUNDINGINFO_ERROR_INVALID_VIEW');
    return;
}

$projectId = $app->input->getInt('id');
if (!$projectId) {
    echo JText::_('MOD_CROWDFUNDINGINFO_ERROR_INVALID_PROJECT');
    return;
}

$componentParams = JComponentHelper::getParams('com_crowdfunding');
/** @var  $componentParams Joomla\Registry\Registry */

$container    = Prism\Container::getContainer();
/** @var  $container Joomla\DI\Container */

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

// Get Project object from container.
$projectHash = Prism\Utilities\StringHelper::generateMd5Hash(Crowdfunding\Constants::CONTAINER_PROJECT, $projectId);
if (!$container->exists($projectHash)) {
    $project = new Crowdfunding\Project(JFactory::getDbo());
    $project->load($projectId);
    $container->set($projectHash, $project);
} else {
    $project     = $container->get($projectHash);
}

$amount       = new Crowdfunding\Amount($componentParams);
$amount->setCurrency($currency);

$fundedAmount = $amount->setValue($project->getGoal())->formatCurrency();

require JModuleHelper::getLayoutPath('mod_crowdfundinginfo', $params->get('layout', 'default'));