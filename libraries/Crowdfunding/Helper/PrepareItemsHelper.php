<?php
/**
 * @package      Crowdfunding
 * @subpackage   Helpers
 * @author       Todor Iliev
 * @copyright    Copyright (C) 2016 Todor Iliev <todor@itprism.com>. All rights reserved.
 * @license      GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Crowdfunding\Helper;

use Prism\Helper\HelperAbstract;
use Prism\Utilities\MathHelper;
use Crowdfunding;

defined('JPATH_PLATFORM') or die;

/**
 * This class provides functionality to prepare the statuses of the items.
 *
 * @package      Crowdfunding
 * @subpackage   Helpers
 */
final class PrepareItemsHelper extends HelperAbstract
{
    /**
     * Prepare the statuses of the items.
     *
     * @param array $data
     * @param array $options
     */
    public function handle(&$data, array $options = array())
    {
        foreach ($data as $key => $item) {
            // Calculate funding end date
            if (is_numeric($item->funding_days) and $item->funding_days > 0) {
                $fundingStartDate  = new Crowdfunding\Date($item->funding_start);
                $endDate           = $fundingStartDate->calculateEndDate($item->funding_days);
                $item->funding_end = $endDate->format('Y-m-d');
            }

            // Calculate funded percentage.
            $item->funded_percents = (string)MathHelper::calculatePercentage($item->funded, $item->goal, 0);

            // Calculate days left
            $today           = new Crowdfunding\Date();
            $item->days_left = $today->calculateDaysLeft($item->funding_days, $item->funding_start, $item->funding_end);
        }
    }
}
