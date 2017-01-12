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

class CrowdfundingModelPayments extends JModelLegacy
{
    /**
     * @param int $projectId
     * @param Joomla\Registry\Registry $params
     * @param stdClass $paymentSession
     *
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     * @throws RuntimeException
     *
     * @return stdClass
     */
    public function prepareItem($projectId, $params, $paymentSession)
    {
        $container        = Prism\Container::getContainer();
        $containerHelper  = new Crowdfunding\Container\Helper();

        $project          = $containerHelper->fetchProject($container, $projectId);

        if ($project === null or !$project->getId()) {
            throw new UnexpectedValueException(JText::_('COM_CROWDFUNDING_ERROR_INVALID_PROJECT'));
        }

        if ($project->isCompleted()) {
            throw new UnexpectedValueException(JText::_('COM_CROWDFUNDING_ERROR_COMPLETED_PROJECT'));
        }

        // Get currency
        $money      = $containerHelper->fetchMoneyFormatter($container, $params);
        $currency   = $money->getCurrency();

        $item = new stdClass();

        $item->id             = $project->getId();
        $item->title          = $project->getTitle();
        $item->slug           = $project->getSlug();
        $item->catslug        = $project->getCatSlug();
        $item->starting_date  = $project->getFundingStart();
        $item->ending_date    = $project->getFundingEnd();
        $item->user_id        = $project->getUserId();
        $item->rewardId       = $paymentSession->rewardId;

        $item->amount         = $paymentSession->amount;
        $item->currencyCode   = $currency->getCode();

        $item->amountFormated = $money->setAmount($item->amount)->format();
        $item->amountCurrency = $money->setAmount($item->amount)->formatCurrency();

        return $item;
    }
}
