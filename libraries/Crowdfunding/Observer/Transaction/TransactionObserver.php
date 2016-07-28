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
use Crowdfunding\Project;

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
    protected $allowedContext = array('com_crowdfunding.transaction');

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
     *
     * @return  void
     */
    public function onAfterProcessTransaction($context, Transaction $transaction, array $options = array())
    {
        // Check for allowed context.
        if (!in_array($context, $this->allowedContext, true)) {
            return;
        }

        $updateProject = ArrayHelper::getValue($options, 'update_project', false, 'bool');
        $projectId     = $transaction->getProjectId();

        // Add funds when create new transaction record manually.
        if ($updateProject and $projectId > 0 and $transaction->isCompleted()) {
            $project = new Project(\JFactory::getDbo());
            $project->load($projectId);

            $project->addFunds($transaction->getAmount());
            $project->storeFunds();
        }
    }
}
