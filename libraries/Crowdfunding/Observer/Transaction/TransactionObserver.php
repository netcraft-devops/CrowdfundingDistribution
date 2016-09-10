<?php
/**
 * @package      Crowdfunding
 * @subpackage   Observers
 * @author       Todor Iliev
 * @copyright    Copyright (C) 2016 Todor Iliev <todor@itprism.com>. All rights reserved.
 * @license      GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Crowdfunding\Observer\Transaction;

use Joomla\Utilities\ArrayHelper;
use Crowdfunding\Transaction\Transaction;
use Crowdfunding\Container\Helper;
use Crowdfunding\Reward;
use Prism\Constants;
use Prism\Container;

defined('JPATH_PLATFORM') or die;

/**
 * Abstract class defining methods that can be
 * implemented by an Observer class of a JTable class (which is an Observable).
 * Attaches $this Observer to the $table in the constructor.
 * The classes extending this class should not be instantiated directly, as they
 * are automatically instantiated by the JObserverMapper
 *
 * @package      Crowdfunding
 * @subpackage   Observers
 * @link         http://docs.joomla.org/JTableObserver
 * @since        3.1.2
 */
class TransactionObserver extends Observer
{
    /**
     * Context that are allowed to be processed.
     *
     * @var array
     */
    protected $allowedContext = array('com_crowdfunding.transaction', 'com_crowdfunding.payment');

    /**
     * The pattern for this table's TypeAlias
     *
     * @var    string
     * @since  3.1.2
     */
    protected $typeAliasPattern;

    /**
     * Creates the associated observer instance and attaches it to the $observableObject
     * $typeAlias can be of the form '{variableName}.type', automatically replacing {variableName} with table-instance variables variableName
     *
     * @param   \JObservableInterface $observableObject The subject object to be observed
     * @param   array                $params           ( 'typeAlias' => $typeAlias )
     *
     * @throws  \InvalidArgumentException
     * @return  self
     *
     * @since   3.1.2
     */
    public static function createObserver(\JObservableInterface $observableObject, $params = array())
    {
        $observer = new self($observableObject);
        $observer->typeAliasPattern = ArrayHelper::getValue($params, 'typeAlias');

        return $observer;
    }

    /**
     * Pre-processor for $transactionManager->process($context, $options)
     *
     * @param   string        $context
     * @param   Transaction   $transaction
     * @param   array         $options
     *
     * @throws  \RuntimeException
     * @throws  \InvalidArgumentException
     * @throws  \UnexpectedValueException
     * @throws  \OutOfBoundsException
     *
     * @return  void
     */
    public function onAfterProcessTransaction($context, Transaction $transaction, array $options = array())
    {
        // Check for allowed context.
        if (!in_array($context, $this->allowedContext, true)) {
            return;
        }

        $completedOrPending            = Constants::PAYMENT_STATUS_COMPLETED | Constants::PAYMENT_STATUS_PENDING;
        $canceledOrRefundedOrFialed    = Constants::PAYMENT_STATUS_CANCELED | Constants::PAYMENT_STATUS_REFUNDED | Constants::PAYMENT_STATUS_FAILED;

        $statuses = array(
            'completed' => Constants::PAYMENT_STATUS_COMPLETED,
            'pending'   => Constants::PAYMENT_STATUS_PENDING,
            'canceled'  => Constants::PAYMENT_STATUS_CANCELED,
            'refunded'  => Constants::PAYMENT_STATUS_REFUNDED,
            'failed'    => Constants::PAYMENT_STATUS_FAILED
        );

        $oldStatus     = ArrayHelper::getValue($options, 'old_status');
        $newStatus     = ArrayHelper::getValue($options, 'new_status');

        $oldStatusBit  = ($oldStatus and array_key_exists($oldStatus, $statuses)) ? $statuses[$oldStatus] : null;
        $newStatusBit  = ($newStatus and array_key_exists($newStatus, $statuses)) ? $statuses[$newStatus] : null;

        // Check if it is new record.
        $isNew = false;
        if ($oldStatusBit === null and $newStatusBit !== null) {
            $isNew = true;
        }

        $container        = Container::getContainer();
        $containerHelper  = new Helper();

        // Add funds when create new transaction record, and it is completed and pending.
        if ($isNew and $transaction->getProjectId() > 0 and ($transaction->isCompleted() or $transaction->isPending())) {
            $project = $containerHelper->fetchProject($container, $transaction->getProjectId());

            $project->addFunds($transaction->getAmount());
            $project->storeFunds();

            if ($transaction->getRewardId()) {
                $reward = $containerHelper->fetchReward($container, $transaction->getRewardId(), $transaction->getProjectId());
                $this->increaseDistributedReward($transaction, $reward);
            }

        } else {
            // If someone change the status from completed/pending to another one, remove funds.
            if (($completedOrPending & $oldStatusBit) and ($canceledOrRefundedOrFialed & $newStatusBit)) {
                $project = $containerHelper->fetchProject($container, $transaction->getProjectId());

                $project->removeFunds($transaction->getAmount());
                $project->storeFunds();

                if ($transaction->getRewardId()) {
                    $reward = $containerHelper->fetchReward($container, $transaction->getRewardId(), $transaction->getProjectId());
                    $this->decreaseDistributedReward($transaction, $reward);
                }

            } // If someone change the status to completed/pending from canceled, refunded or failed, add funds.
            elseif (($canceledOrRefundedOrFialed & $oldStatusBit) and ($completedOrPending & $newStatusBit)) {
                $project = $containerHelper->fetchProject($container, $transaction->getProjectId());

                $project->addFunds($transaction->getAmount());
                $project->storeFunds();

                if ($transaction->getRewardId()) {
                    $reward = $containerHelper->fetchReward($container, $transaction->getRewardId(), $transaction->getProjectId());
                    $this->increaseDistributedReward($transaction, $reward);
                }
            }
        }
    }

    /**
     * Increase the number of distributed to a user rewards.
     *
     * @param Transaction $transaction
     * @param Reward|null $reward
     *
     * @throws  \RuntimeException
     * @throws  \InvalidArgumentException
     * @throws  \UnexpectedValueException
     *
     * @return void
     */
    protected function increaseDistributedReward(Transaction $transaction, $reward)
    {
        // Check for valid reward.
        if ($reward === null or !$reward->getId()) {
            return;
        }

        // Check for valida amount between reward value and payed by user
        $txnAmount = $transaction->getAmount();
        if ($txnAmount < $reward->getAmount()) {
            return;
        }

        // Check for available rewards.
        if ($reward->isLimited() and !$reward->hasAvailable()) {
            return;
        }

        // Increase the number of distributed rewards.
        $reward->increaseDistributed();
        $reward->updateDistributed();
    }

    /**
     * Decrease the number of distributed to a user rewards.
     *
     * @param Transaction $transaction
     * @param Reward|null $reward
     *
     * @throws  \RuntimeException
     * @throws  \InvalidArgumentException
     * @throws  \UnexpectedValueException
     *
     * @return void
     */
    protected function decreaseDistributedReward(Transaction $transaction, $reward)
    {
        // Check for valid reward.
        if ($reward === null or !$reward->getId()) {
            return;
        }

        // Check for valida amount between reward value and payed by user
        $txnAmount = $transaction->getAmount();
        if ($txnAmount < $reward->getAmount()) {
            return;
        }

        // Decrease the number of distributed rewards.
        $reward->decreaseDistributed();
        $reward->updateDistributed();
    }
}
