<?php
/**
 * @package      Crowdfunding
 * @subpackage   Components
 * @author       Todor Iliev
 * @copyright    Copyright (C) 2016 Todor Iliev <todor@itprism.com>. All rights reserved.
 * @license      GNU General Public License version 3 or later; see LICENSE.txt
 */

use Joomla\DI\ContainerAwareInterface;
use Joomla\DI\ContainerAwareTrait;

// no direct access
defined('_JEXEC') or die;

JObserverMapper::addObserverClassToClass('Crowdfunding\\Observer\\Transaction\\TransactionObserver', 'Crowdfunding\\Transaction\\TransactionManager', array('typeAlias' => 'com_crowdfunding.transaction'));

class CrowdfundingModelTransaction extends JModelAdmin
{
    use Crowdfunding\Helper\MoneyHelper;

    protected $event_transaction_change_state;

    public function __construct($config = array())
    {
        parent::__construct($config);

        if (array_key_exists('event_transaction_change_state', $config)) {
            $this->event_transaction_change_state = $config['event_transaction_change_state'];
        } elseif (!$this->event_transaction_change_state) {
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
     * @return  CrowdfundingTableTransaction|bool  A database object
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
     * @return  JForm|bool   A JForm object on success, false on failure
     * @since   1.6
     */
    public function getForm($data = array(), $loadData = true)
    {
        // Get the form.
        $form = $this->loadForm($this->option . '.transaction', 'transaction', array('control' => 'jform', 'load_data' => $loadData));
        if (!$form) {
            return false;
        }

        return $form;
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @throws \RuntimeException
     * @throws \UnexpectedValueException
     * @throws \InvalidArgumentException
     * @throws \OutOfBoundsException
     *
     * @return  mixed   The data for the form.
     * @since   1.6
     */
    protected function loadFormData()
    {
        $app  = JFactory::getApplication();
        // Check the session for previously entered form data.
        $data = $app->getUserState($this->option . '.edit.transaction.data', array());
        
        if (count($data) === 0) {
            $data = $this->getItem();

            $params         = JComponentHelper::getParams('com_crowdfunding');
            /** @var  $params Joomla\Registry\Registry */

            $moneyFormatter = $this->getMoneyFormatter($params);

            // If it is new record, set default values.
            if (!$data->id) {
                $currency               = $moneyFormatter->getCurrency();

                $data->txn_currency     = $currency->getCode();

                $data->txn_id           = strtoupper(Prism\Utilities\StringHelper::generateRandomString(13, 'TXN'));
                $data->service_provider = 'Cash';
                $data->service_alias    = 'cash';
                $data->txn_status       = 'completed';

                $timezone               = $app->get('offset');
                $currentDate            = new JDate('now', $timezone);
                $data->txn_date         = $currentDate->toSql();

                $data->update_project   = 1;
            }

            $data->txn_amount = $moneyFormatter->setAmount($data->txn_amount)->format();
        }

        return $data;
    }

    /**
     * Save data into the DB
     *
     * @param array $data   The data of item
     *
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     * @throws \UnexpectedValueException
     * @throws \OutOfBoundsException
     *
     * @return    int      Item ID
     */
    public function save($data)
    {
        $context        = $this->option . '.' . $this->name;

        $id             = Joomla\Utilities\ArrayHelper::getValue($data, 'id', 0, 'int');
        $txnStatus      = Joomla\Utilities\ArrayHelper::getValue($data, 'txn_status');
        $txnDate        = Joomla\Utilities\ArrayHelper::getValue($data, 'txn_date');

        // Parse the amount.
        $params         = JComponentHelper::getParams($this->option);
        /** @var  $params Joomla\Registry\Registry */

        $moneyFormatter = $this->getMoneyFormatter($params);

        $amount         = Joomla\Utilities\ArrayHelper::getValue($data, 'txn_amount');
        $amount         = $moneyFormatter->setAmount($amount)->parse();

        $cleanData = array(
            'txn_amount'       => $amount,
            'txn_currency'     => Joomla\Utilities\ArrayHelper::getValue($data, 'txn_currency'),
            'txn_status'       => $txnStatus,
            'txn_date'         => $txnDate,
            'txn_id'           => Joomla\Utilities\ArrayHelper::getValue($data, 'txn_id'),
            'parent_txn_id'    => Joomla\Utilities\ArrayHelper::getValue($data, 'parent_txn_id'),
            'service_provider' => Joomla\Utilities\ArrayHelper::getValue($data, 'service_provider'),
            'service_alias'    => Joomla\Utilities\ArrayHelper::getValue($data, 'service_alias'),
            'investor_id'      => Joomla\Utilities\ArrayHelper::getValue($data, 'investor_id', 0, 'int'),
            'receiver_id'      => Joomla\Utilities\ArrayHelper::getValue($data, 'receiver_id', 0, 'int'),
            'project_id'       => Joomla\Utilities\ArrayHelper::getValue($data, 'project_id', 0, 'int'),
            'reward_id'        => Joomla\Utilities\ArrayHelper::getValue($data, 'reward_id', 0, 'int')
        );
        
        $dateValidator = new Prism\Validator\Date($txnDate);
        if (!$dateValidator->isValid()) {
            $timezone               = JFactory::getApplication()->get('offset');
            $currentDate            = new JDate('now', $timezone);
            $cleanData['txn_date']  = $currentDate->toSql();
        }

        $transaction = new Crowdfunding\Transaction\Transaction(JFactory::getDbo());
        $transaction->load($id);

        // Check for changed transaction status and trigger the event onTransactionChangeState.
        $oldStatus = $transaction->getStatus();
        if (($oldStatus !== null and $oldStatus !== '') and strcmp($oldStatus, $txnStatus) !== 0) {
            $this->triggerOnTransactionChangeState($transaction, $oldStatus, $txnStatus);
        }

        $options = array(
            'old_status' => $oldStatus,
            'new_status' => $txnStatus
        );

        // Bind data.
        $transaction->bind($cleanData);

        // Process transaction.
        $transactionManager = new Crowdfunding\Transaction\TransactionManager(JFactory::getDbo());
        $transactionManager->setTransaction($transaction);
        $transactionManager->process($context, $options);

        return $transaction->getId();
    }

    /**
     * @param Crowdfunding\Transaction\Transaction $transaction
     * @param string $oldStatus
     * @param string $newStatus
     */
    protected function triggerOnTransactionChangeState(&$transaction, $oldStatus, $newStatus)
    {
        // Include the content plugins for the on save events.
        JPluginHelper::importPlugin('crowdfundingpayment');

        // Trigger the onTransactionChangeStatus event.
        $dispatcher = JEventDispatcher::getInstance();
        $dispatcher->trigger($this->event_transaction_change_state, array($this->option . '.' . $this->name, &$transaction, $oldStatus, $newStatus));
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

    public function changeTransactionStatus($id, $newStatus)
    {
        $allowedStatuses = array('pending', 'completed', 'canceled', 'refunded', 'failed');
        if (!in_array($newStatus, $allowedStatuses, true)) {
            throw new RuntimeException(JText::_('COM_CROWDFUNDING_ERROR_INVALID_STATUS'));
        }

        $transaction = new Crowdfunding\Transaction\Transaction(JFactory::getDbo());
        $transaction->load($id);

        $oldStatus = $transaction->getStatus();
        if (!$transaction->getId() or !$oldStatus) {
            throw new RuntimeException(JText::_('COM_CROWDFUNDING_ERROR_INVALID_TRANSACTION'));
        }

        if (strcmp($oldStatus, $newStatus) === 0) {
            throw new RuntimeException(JText::_('COM_CROWDFUNDING_ERROR_INVALID_STATUS'));
        }

        // Set the new status.
        $transaction->setStatus($newStatus);

        // Trigger the event onTransactionChangeState.
        $this->triggerOnTransactionChangeState($transaction, $oldStatus, $newStatus);

        $context = $this->option . '.' . $this->name;
        $options = array(
            'old_status' => $oldStatus,
            'new_status' => $newStatus
        );

        // Process transaction.
        $transactionManager = new Crowdfunding\Transaction\TransactionManager(JFactory::getDbo());
        $transactionManager->setTransaction($transaction);
        $transactionManager->process($context, $options);
    }
}
