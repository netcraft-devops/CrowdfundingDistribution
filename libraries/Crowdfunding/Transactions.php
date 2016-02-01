<?php
/**
 * @package      Crowdfunding
 * @subpackage   Transactions
 * @author       Todor Iliev
 * @copyright    Copyright (C) 2015 Todor Iliev <todor@itprism.com>. All rights reserved.
 * @license      GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Crowdfunding;

use Prism;
use Joomla\Utilities\ArrayHelper;

defined('JPATH_PLATFORM') or die;

/**
 * This class provides functionality that manage transactions.
 *
 * @package      Crowdfunding
 * @subpackage   Transactions
 */
class Transactions extends Prism\Database\ArrayObject
{
    /**
     * Load transactions from database.
     *
     * <code>
     * $options = array(
     *     "ids" => array(1,2,3),
     *     "txn_status" => "completed"
     * );
     *
     * $transactions    = new Crowdfunding\Transactions(\JFactory::getDbo());
     * $transactions->load($options);
     *
     * foreach($transactions as $transaction) {
     *   echo $transaction->txn_id;
     *   echo $transaction->txn_amount;
     * }
     *
     * </code>
     *
     * @param array $options
     *
     * @throws \UnexpectedValueException
     */
    public function load($options = array())
    {
        $ids = (!array_key_exists('ids', $options)) ? array() : (array)$options['ids'];
        $ids = ArrayHelper::toInteger($ids);

        $results = array();

        if (count($ids) > 0) {

            // Load project data
            $query = $this->db->getQuery(true);

            $query
                ->select(
                    'a.id, a.txn_date, a.txn_id, a.txn_amount, a.txn_currency, a.txn_status, ' .
                    'a.extra_data, a.status_reason, a.project_id, a.reward_id, a.investor_id, ' .
                    'a.receiver_id, a.service_provider, a.service_alias, a.reward_state'
                )
                ->from($this->db->quoteName('#__crowdf_transactions', 'a'))
                ->where('a.id IN ( ' . implode(',', $ids) . ' )');

            // Filter by status.
            $status = ArrayHelper::getValue($options, 'txn_status', null, 'cmd');
            if ($status !== null) {
                $query->where('a.txn_status = ' . $this->db->quote($status));
            }

            $this->db->setQuery($query);
            $results = (array)$this->db->loadAssocList();

            // Convert JSON string into an array.
            if (count($results) > 0) {
                foreach ($results as $key => &$result) {
                    if (!empty($result['extra_data'])) {
                        $result['extra_data'] = json_decode($result['extra_data'], true);
                    }
                }

                unset($result);
            }
        }

        $this->items = $results;
    }
}
