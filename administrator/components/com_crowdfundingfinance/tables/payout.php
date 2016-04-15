<?php
/**
 * @package      Crowdfundingfinance
 * @subpackage   Components
 * @author       Todor Iliev
 * @copyright    Copyright (C) 2016 Todor Iliev <todor@itprism.com>. All rights reserved.
 * @license      GNU General Public License version 3 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

class CrowdfundingfinanceTablePayout extends JTable
{
    /**
     * @param JDatabaseDriver $db
     */
    public function __construct($db)
    {
        parent::__construct('#__cffinance_payouts', 'id', $db);
    }
}
