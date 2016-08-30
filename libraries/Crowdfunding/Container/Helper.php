<?php
/**
 * @package      Crowdfunding
 * @subpackage   Helpers
 * @author       Todor Iliev
 * @copyright    Copyright (C) 2016 Todor Iliev <todor@itprism.com>. All rights reserved.
 * @license      GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Crowdfunding\Container;

use Joomla\DI\Container;
use Joomla\Registry\Registry;
use Crowdfunding\Currency;
use Crowdfunding\Project;
use Prism\Money\Money;

defined('JPATH_PLATFORM') or die;

/**
 * This class provides functionality that returns objects from the container.
 * This class uses helper traits of the container to prepare and fetch the objects.
 *
 * @package      Crowdfunding
 * @subpackage   Helpers
 */
class Helper
{
    use MoneyHelper;
    use NumberHelper;
    use ProjectHelper;

    /**
     * Return currency object.
     *
     * <code>
     * $helper   = new Crowdfunding\Container\Helper();
     * $currency = $this->fetchCurrency($container, $params);
     * </code>
     *
     * @param Container $container
     * @param Registry $params
     *
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     * @throws \OutOfBoundsException
     *
     * @return Currency
     */
    public function fetchCurrency($container, $params)
    {
        return $this->getCurrency($container, $params);
    }

    /**
     * Return money formatter.
     *
     * <code>
     * $helper   = new Crowdfunding\Container\Helper();
     * $currency = $this->fetchMoneyFormatter($container, $params);
     * </code>
     *
     * @param Container $container
     * @param Registry $params
     *
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     * @throws \OutOfBoundsException
     *
     * @return Money
     */
    public function fetchMoneyFormatter($container, $params)
    {
        return $this->getMoneyFormatter($container, $params);
    }

    /**
     * Return money formatter.
     *
     * <code>
     * $projectId = 1;
     *
     * $helper   = new Crowdfunding\Container\Helper();
     * $project  = $this->fetchProject($container, $projectId);
     * </code>
     *
     * @param Container $container
     * @param int $projectId
     *
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     * @throws \OutOfBoundsException
     *
     * @return Project
     */
    public function fetchProject($container, $projectId)
    {
        return $this->getProject($container, $projectId);
    }
}
