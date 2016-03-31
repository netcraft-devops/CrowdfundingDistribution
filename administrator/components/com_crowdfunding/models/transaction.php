<?php
/**
 * @package      Crowdfunding
 * @subpackage   Components
 * @author       Todor Iliev
 * @copyright    Copyright (C) 2016 Todor Iliev <todor@itprism.com>. All rights reserved.
 * @license      GNU General Public License version 3 or later; see LICENSE.txt
 */

// no direct access
defined('_JEXEC') or die;

class CrowdfundingModelTransaction extends JModelAdmin
{
    protected $event_transaction_change_state;

    public function __construct($config = array())
    {
        parent::__construct($config);

        if (isset($config['event_transaction_change_state'])) {
            $this->event_transaction_change_state = $config['event_transaction_change_state'];
        } elseif (empty($this->event_transaction_change_state)) {
            $this->event_transaction_change_state = 'onTransactionChangeState';
        }
    }

    /**
     * Returns a reference to the a Table object, always creating it.
     *
     * @param   string $type    The table type to instantiate
     * @param   string $prefix A prefix for the table class name. Optional.
     * @param   array  $config Configuration array for model. Optional.
     *
     * @return  CrowdfundingTableTransaction  A database object
     * @since   1.6
     */
    public function getTable($type = 'Transaction', $prefix = 'CrowdfundingTable', $config = array())
    {
        return JTable::getInstance($type, $prefix, $config);
    }

    /**
     * Method to get the record form.
     *
     * @param   array   $data     An optional array of data for the form to interrogate.
     * @param   boolean $loadData True if the form is to load its own data (default case), false if not.
     *
     * @return  JForm   A JForm object on success, false on failure
     * @since   1.6
     */
    public function getForm($data = array(), $loadData = true)
    {
        // Get the form.
        $form = $this->loadForm($this->option . '.transaction', 'transaction', array('control' => 'jform', 'load_data' => $loadData));
        if (empty($form)) {
            return false;
        }

        return $form;
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @return  mixed   The data for the form.
     * @since   1.6
     */
    protected function loadFormData()
    {
        // Check the session for previously entered form data.
        $data = JFactory::getApplication()->getUserState($this->option . '.edit.transaction.data', array());
        if (empty($data)) {
            $data = $this->getItem();
        }

        return $data;
    }

    /**
     * Save data into the DB
     *
     * @param array $data   The data of item
     *
     * @return    int      Item ID
     */
    public function save($data)
    {
        $id              = Joomla\Utilities\ArrayHelper::getValue($data, 'id', 0, 'int');
        $txnAmount       = Joomla\Utilities\ArrayHelper::getValue($data, 'txn_amount');
        $txnCurrency     = Joomla\Utilities\ArrayHelper::getValue($data, 'txn_currency');
        $txnStatus       = Joomla\Utilities\ArrayHelper::getValue($data, 'txn_status');
        $txnDate         = Joomla\Utilities\ArrayHelper::getValue($data, 'txn_date');
        $txnId           = Joomla\Utilities\ArrayHelper::getValue($data, 'txn_id');
        $parentTxnId     = Joomla\Utilities\ArrayHelper::getValue($data, 'parent_txn_id');
        $serviceProvider = Joomla\Utilities\ArrayHelper::getValue($data, 'service_provider');
        $serviceAlias    = Joomla\Utilities\ArrayHelper::getValue($data, 'service_alias');
        $investorId      = Joomla\Utilities\ArrayHelper::getValue($data, 'investor_id', 0, 'int');
        $receiverId      = Joomla\Utilities\ArrayHelper::getValue($data, 'receiver_id', 0, 'int');
        $projectId       = Joomla\Utilities\ArrayHelper::getValue($data, 'project_id', 0, 'int');
        $rewardId        = Joomla\Utilities\ArrayHelper::getValue($data, 'reward_id', 0, 'int');

        $dateValidator = new Prism\Validator\Date($txnDate);
        if (!$dateValidator->isValid()) {
            $timezone        = JFactory::getApplication()->get('offset');
            $currentDate     = new JDate('now', $timezone);
            $txnDate         = $currentDate->toSql();
        }

        // Load a record from the database.
        $row = $this->getTable();
        $row->load($id);

        $this->prepareStatus($row, $txnStatus);

        // Store the transaction data.
        $row->set('txn_amount', $txnAmount);
        $row->set('txn_currency', $txnCurrency);
        $row->set('txn_status', $txnStatus);
        $row->set('txn_date', $txnDate);
        $row->set('txn_id', $txnId);
        $row->set('parent_txn_id', $parentTxnId);
        $row->set('service_provider', $serviceProvider);
        $row->set('service_alias', $serviceAlias);
        $row->set('investor_id', $investorId);
        $row->set('receiver_id', $receiverId);
        $row->set('project_id', $projectId);
        $row->set('reward_id', $rewardId);

        $row->store();

        return $row->get('id');
    }

    protected function prepareStatus(&$row, $newStatus)
    {
        // Check for changed transaction status.
        $oldStatus = $row->txn_status;

        if (strcmp($oldStatus, $newStatus) !== 0) {

            // Include the content plugins for the on save events.
            JPluginHelper::importPlugin('crowdfundingpayment');

            // Trigger the onTransactionChangeStatus event.
            $dispatcher = JEventDispatcher::getInstance();
            $dispatcher->trigger($this->event_transaction_change_state, array($this->option . '.' . $this->name, &$row, $oldStatus, $newStatus));
        }
    }

    public function changeRewardsState($id, $state)
    {
        $state = (!$state) ? Prism\Constants::NOT_SENT : Prism\Constants::SENT;

        $db = $this->getDbo();
        $query = $db->getQuery(true);

        $query
            ->update($db->quoteName('#__crowdf_transactions'))
            ->set($db->quoteName('reward_state') .'='. (int)$state)
            ->where($db->quoteName('id') .'='. (int)$id);

        $db->setQuery($query);
        $db->execute();
    }
}
