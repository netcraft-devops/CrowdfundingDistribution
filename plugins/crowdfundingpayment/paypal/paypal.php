<?php
/**
 * @package      Crowdfunding
 * @subpackage   Plugins
 * @author       Todor Iliev
 * @copyright    Copyright (C) 2016 Todor Iliev <todor@itprism.com>. All rights reserved.
 * @license      GNU General Public License version 3 or later; see LICENSE.txt
 */

use Crowdfunding\Transaction\Transaction;
use Crowdfunding\Transaction\TransactionManager;
use Crowdfunding\Reward;
use Joomla\Utilities\ArrayHelper;

// no direct access
defined('_JEXEC') or die;

jimport('Prism.init');
jimport('Crowdfunding.init');
jimport('Crowdfundingfinance.init');
jimport('Emailtemplates.init');

JObserverMapper::addObserverClassToClass(
    'Crowdfunding\\Observer\\Transaction\\TransactionObserver',
    'Crowdfunding\\Transaction\\TransactionManager',
    array('typeAlias' => 'com_crowdfunding.payment')
);

/**
 * Crowdfunding PayPal payment plugin.
 *
 * @package      Crowdfunding
 * @subpackage   Plugins
 */
class plgCrowdfundingPaymentPayPal extends Crowdfunding\Payment\Plugin
{
    public function __construct(&$subject, $config = array())
    {
        $this->serviceProvider = 'PayPal';
        $this->serviceAlias    = 'paypal';

        $this->extraDataKeys = array(
            'first_name', 'last_name', 'payer_id', 'payer_status',
            'mc_gross', 'mc_fee', 'mc_currency', 'payment_status', 'payment_type', 'payment_date',
            'txn_type', 'test_ipn', 'ipn_track_id', 'custom', 'protection_eligibility'
        );

        parent::__construct($subject, $config);
    }

    /**
     * This method prepares a payment gateway - buttons, forms,...
     * That gateway will be displayed on the summary page as a payment option.
     *
     * @param string    $context This string gives information about that where it has been executed the trigger.
     * @param stdClass  $item    A project data.
     * @param Joomla\Registry\Registry $params  The parameters of the component
     *
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     *
     * @return string
     */
    public function onProjectPayment($context, $item, $params)
    {
        if (strcmp('com_crowdfunding.payment', $context) !== 0) {
            return null;
        }

        if ($this->app->isAdmin()) {
            return null;
        }

        $doc = JFactory::getDocument();
        /**  @var $doc JDocumentHtml */

        // Check document type
        $docType = $doc->getType();
        if (strcmp('html', $docType) !== 0) {
            return null;
        }

        // This is a URI path to the plugin folder
        $pluginURI = 'plugins/crowdfundingpayment/paypal';

        $notifyUrl = $this->getCallbackUrl();
        $returnUrl = $this->getReturnUrl($item->slug, $item->catslug);
        $cancelUrl = $this->getCancelUrl($item->slug, $item->catslug);

        // DEBUG DATA
        JDEBUG ? $this->log->add(JText::_($this->textPrefix . '_DEBUG_NOTIFY_URL'), $this->debugType, $notifyUrl) : null;
        JDEBUG ? $this->log->add(JText::_($this->textPrefix . '_DEBUG_RETURN_URL'), $this->debugType, $returnUrl) : null;
        JDEBUG ? $this->log->add(JText::_($this->textPrefix . '_DEBUG_CANCEL_URL'), $this->debugType, $cancelUrl) : null;

        $html   = array();
        $html[] = '<div class="well">';

        $html[] = '<h4><img src="' . $pluginURI . '/images/paypal_icon.png" width="36" height="32" alt="PayPal" />' . JText::_($this->textPrefix . '_TITLE') . '</h4>';

        // Prepare payment receiver.
        $paymentReceiverOption = $this->params->get('paypal_payment_receiver', 'site_owner');
        $paymentReceiverInput = $this->preparePaymentReceiver($paymentReceiverOption, $item->id);
        if ($paymentReceiverInput === null) {
            $html[] = $this->generateSystemMessage(JText::_($this->textPrefix . '_ERROR_PAYMENT_RECEIVER_MISSING'));
            return implode("\n", $html);
        }

        // Display additional information.
        $html[] = '<p>' . JText::_($this->textPrefix . '_INFO') . '</p>';

        // Start the form.
        if ($this->params->get('paypal_sandbox', 1)) {
            $html[] = '<form action="' . trim($this->params->get('paypal_sandbox_url')) . '" method="post">';
        } else {
            $html[] = '<form action="' . trim($this->params->get('paypal_url')) . '" method="post">';
        }

        $html[] = $paymentReceiverInput;

        $html[] = '<input type="hidden" name="cmd" value="_xclick" />';
        $html[] = '<input type="hidden" name="charset" value="utf-8" />';
        $html[] = '<input type="hidden" name="currency_code" value="' . $item->currencyCode . '" />';
        $html[] = '<input type="hidden" name="amount" value="' . $item->amount . '" />';
        $html[] = '<input type="hidden" name="quantity" value="1" />';
        $html[] = '<input type="hidden" name="no_shipping" value="1" />';
        $html[] = '<input type="hidden" name="no_note" value="1" />';
        $html[] = '<input type="hidden" name="tax" value="0" />';

        // Title
        $title  = JText::sprintf($this->textPrefix . '_INVESTING_IN_S', htmlentities($item->title, ENT_QUOTES, 'UTF-8'));
        $html[] = '<input type="hidden" name="item_name" value="' . $title . '" />';

        // Get payment session
        $paymentSessionContext    = Crowdfunding\Constants::PAYMENT_SESSION_CONTEXT . $item->id;
        $paymentSessionLocal      = $this->app->getUserState($paymentSessionContext);

        $paymentSessionRemote = $this->getPaymentSession(array(
            'session_id'    => $paymentSessionLocal->session_id
        ));

        // Prepare custom data
        $custom = array(
            'payment_session_id' => $paymentSessionRemote->getId(),
            'gateway'            => $this->serviceAlias
        );

        $custom = base64_encode(json_encode($custom));
        $html[] = '<input type="hidden" name="custom" value="' . $custom . '" />';

        // Set a link to logo
        $imageUrl = trim($this->params->get('paypal_image_url'));
        if ($imageUrl) {
            $html[] = '<input type="hidden" name="image_url" value="' . $imageUrl . '" />';
        }

        // Set URLs
        $html[] = '<input type="hidden" name="cancel_return" value="' . $cancelUrl . '" />';
        $html[] = '<input type="hidden" name="return" value="' . $returnUrl . '" />';
        $html[] = '<input type="hidden" name="notify_url" value="' . $notifyUrl . '" />';

        $this->prepareLocale($html);

        // End the form.
        $html[] = '<img alt="" border="0" width="1" height="1" src="https://www.paypal.com/en_US/i/scr/pixel.gif" >';
        $html[] = '</form>';

        // Display a sticky note if the extension works in sandbox mode.
        if ($this->params->get('paypal_sandbox', 1)) {
            $html[] = '<div class="bg-info p-10-5"><span class="fa fa-info-circle"></span> ' . JText::_($this->textPrefix . '_WORKS_SANDBOX') . '</div>';
        }

        $html[] = '</div>';

        return implode("\n", $html);
    }

    /**
     * This method processes transaction data that comes from PayPal instant notifier.
     *
     * @param string    $context This string gives information about that where it has been executed the trigger.
     * @param Joomla\Registry\Registry $params  The parameters of the component
     *
     * @throws \InvalidArgumentException
     * @throws \OutOfBoundsException
     * @throws \RuntimeException
     * @throws \UnexpectedValueException
     *
     * @return null|stdClass
     */
    public function onPaymentNotify($context, $params)
    {
        if (strcmp('com_crowdfunding.notify.'.$this->serviceAlias, $context) !== 0) {
            return null;
        }

        if ($this->app->isAdmin()) {
            return null;
        }

        $doc = JFactory::getDocument();
        /**  @var $doc JDocumentHtml */

        // Check document type
        $docType = $doc->getType();
        if (strcmp('raw', $docType) !== 0) {
            return null;
        }

        // Validate request method
        $requestMethod = $this->app->input->getMethod();
        if (strcmp('POST', $requestMethod) !== 0) {
            $this->log->add(
                JText::_($this->textPrefix . '_ERROR_INVALID_REQUEST_METHOD'),
                $this->debugType,
                JText::sprintf($this->textPrefix . '_ERROR_INVALID_TRANSACTION_REQUEST_METHOD', $requestMethod)
            );

            return null;
        }

        // DEBUG DATA
        JDEBUG ? $this->log->add(JText::_($this->textPrefix . '_DEBUG_RESPONSE'), $this->debugType, $_POST) : null;

        // Decode custom data
        $custom = ArrayHelper::getValue($_POST, 'custom');
        $custom = json_decode(base64_decode($custom), true);

        // DEBUG DATA
        JDEBUG ? $this->log->add(JText::_($this->textPrefix . '_DEBUG_CUSTOM'), $this->debugType, $custom) : null;

        // Verify gateway. Is it PayPal?
        $gateway = ArrayHelper::getValue($custom, 'gateway');
        if (!$this->isValidPaymentGateway($gateway)) {
            $this->log->add(
                JText::_($this->textPrefix . '_ERROR_INVALID_PAYMENT_GATEWAY'),
                $this->debugType,
                array('custom' => $custom, '_POST' => $_POST)
            );

            return null;
        }

        // Get PayPal URL
        if ($this->params->get('paypal_sandbox', 1)) {
            $url = trim($this->params->get('paypal_sandbox_url', 'https://www.sandbox.paypal.com/cgi-bin/webscr'));
        } else {
            $url = trim($this->params->get('paypal_url', 'https://www.paypal.com/cgi-bin/webscr'));
        }

        $paypalIpn       = new Prism\Payment\PayPal\Ipn($url, $_POST);
        $loadCertificate = (bool)$this->params->get('paypal_load_certificate', 0);
        $paypalIpn->verify($loadCertificate);

        // DEBUG DATA
        JDEBUG ? $this->log->add(JText::_($this->textPrefix . '_DEBUG_VERIFY_OBJECT'), $this->debugType, $paypalIpn) : null;

        // Prepare the array that have to be returned by this method.
        $paymentResult = new stdClass;
        $paymentResult->project         = null;
        $paymentResult->reward          = null;
        $paymentResult->transaction     = null;
        $paymentResult->paymentSession  = null;
        $paymentResult->serviceProvider = $this->serviceProvider;
        $paymentResult->serviceAlias    = $this->serviceAlias;

        if ($paypalIpn->isVerified()) {
            $containerHelper  = new Crowdfunding\Container\Helper();
            $currency         = $containerHelper->fetchCurrency($this->container, $params);

            // Get payment session data
            $paymentSessionId       = ArrayHelper::getValue($custom, 'payment_session_id', 0, 'int');
            $paymentSessionRemote   = $this->getPaymentSession(array('id' => $paymentSessionId));

            // Check for valid payment session.
            if (!$paymentSessionRemote->getId()) {
                $this->log->add(JText::_($this->textPrefix . '_ERROR_PAYMENT_SESSION'), $this->errorType, $paymentSessionRemote->getProperties());
                return null;
            }

            // DEBUG DATA
            JDEBUG ? $this->log->add(JText::_($this->textPrefix . '_DEBUG_PAYMENT_SESSION'), $this->debugType, $paymentSessionRemote->getProperties()) : null;

            // Validate transaction data
            $validData = $this->validateData($_POST, $currency->getCode(), $paymentSessionRemote);
            if ($validData === null) {
                return null;
            }

            // DEBUG DATA
            JDEBUG ? $this->log->add(JText::_($this->textPrefix . '_DEBUG_VALID_DATA'), $this->debugType, $validData) : null;

            // Set the receiver ID.
            $project = $containerHelper->fetchProject($this->container, $validData['project_id']);
            $validData['receiver_id'] = $project->getUserId();

            // Get reward object.
            $reward = null;
            if ($validData['reward_id']) {
                $reward = $containerHelper->fetchReward($this->container, $validData['reward_id'], $project->getId());
            }

            // Save transaction data.
            // If it is not completed, return empty results.
            // If it is complete, continue with process transaction data
            $transaction = $this->storeTransaction($validData);
            if ($transaction === null) {
                return null;
            }

            // Generate object of data, based on the transaction properties.
            $paymentResult->transaction = $transaction;

            // Generate object of data based on the project properties.
            $paymentResult->project = $project;

            // Generate object of data based on the reward properties.
            if ($reward !== null and ($reward instanceof Crowdfunding\Reward)) {
                $paymentResult->reward = $reward;
            }

            // Generate data object, based on the payment session properties.
            $paymentResult->paymentSession = $paymentSessionRemote;

            // Removing intention.
            $this->removeIntention($paymentSessionRemote, $transaction);
        } else {
            // Log error
            $this->log->add(
                JText::_($this->textPrefix . '_ERROR_INVALID_TRANSACTION_DATA'),
                $this->debugType,
                array('ERROR MESSAGE' => $paypalIpn->getError(), 'paypalVerify' => $paypalIpn, '_POST' => $_POST)
            );
        }

        return $paymentResult;
    }

    /**
     * Validate PayPal transaction.
     *
     * @param array  $data
     * @param string $currencyCode
     * @param Crowdfunding\Payment\Session  $paymentSessionRemote
     *
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     * @return array
     */
    protected function validateData($data, $currencyCode, $paymentSessionRemote)
    {
        $txnDate = ArrayHelper::getValue($data, 'payment_date');
        $date    = new JDate($txnDate);

        // Prepare transaction data
        $transactionData = array(
            'investor_id'      => $paymentSessionRemote->getUserId(),
            'project_id'       => $paymentSessionRemote->getProjectId(),
            'reward_id'        => $paymentSessionRemote->isAnonymous() ? 0 : $paymentSessionRemote->getRewardId(),
            'service_provider' => $this->serviceProvider,
            'service_alias'    => $this->serviceAlias,
            'txn_id'           => ArrayHelper::getValue($data, 'txn_id', null, 'string'),
            'txn_amount'       => ArrayHelper::getValue($data, 'mc_gross', null, 'float'),
            'txn_currency'     => ArrayHelper::getValue($data, 'mc_currency', null, 'string'),
            'txn_status'       => strtolower(ArrayHelper::getValue($data, 'payment_status', '', 'string')),
            'txn_date'         => $date->toSql(),
            'extra_data'       => $this->prepareExtraData($data)
        );

        // Check Project ID and Transaction ID
        if (!$transactionData['project_id'] or !$transactionData['txn_id']) {
            $this->log->add(JText::_($this->textPrefix . '_ERROR_INVALID_TRANSACTION_DATA'), $this->errorType, $transactionData);
            return null;
        }

        // Check if project record exists in database.
        $projectRecord = new Crowdfunding\Validator\Project\Record(JFactory::getDbo(), $transactionData['project_id']);
        if (!$projectRecord->isValid()) {
            $this->log->add(JText::_($this->textPrefix . '_ERROR_INVALID_PROJECT'), $this->errorType, $transactionData);
            return null;
        }

        // Check if reward record exists in database.
        if ($transactionData['reward_id'] > 0) {
            $rewardRecord = new Crowdfunding\Validator\Reward\Record(JFactory::getDbo(), $transactionData['reward_id'], array('state' => Prism\Constants::PUBLISHED));
            if (!$rewardRecord->isValid()) {
                $this->log->add(JText::_($this->textPrefix . '_ERROR_INVALID_REWARD'), $this->errorType, $transactionData);
                return null;
            }
        }

        // Check currency
        if (strcmp($transactionData['txn_currency'], $currencyCode) !== 0) {
            $this->log->add(JText::_($this->textPrefix . '_ERROR_INVALID_TRANSACTION_CURRENCY'), $this->errorType, array('TRANSACTION DATA' => $transactionData, 'CURRENCY' => $currencyCode));
            return null;
        }

        // Check payment receiver.
        $allowedReceivers = array(
            strtolower(ArrayHelper::getValue($data, 'business')),
            strtolower(ArrayHelper::getValue($data, 'receiver_email')),
            strtolower(ArrayHelper::getValue($data, 'receiver_id'))
        );

        // Get payment receiver.
        $paymentReceiverOption = $this->params->get('paypal_payment_receiver', 'site_owner');
        $paymentReceiver       = $this->getPaymentReceiver($paymentReceiverOption, $transactionData['project_id']);

        if (!in_array($paymentReceiver, $allowedReceivers, true)) {
            $this->log->add(JText::_($this->textPrefix . '_ERROR_INVALID_RECEIVER'), $this->errorType, array('TRANSACTION DATA' => $transactionData, 'RECEIVER' => $paymentReceiver, 'ALLOWED RECEIVERS' => $allowedReceivers));
            return null;
        }

        return $transactionData;
    }

    /**
     * Save transaction data.
     *
     * @param array     $transactionData
     *
     * @throws \RuntimeException
     * @throws \InvalidArgumentException
     * @throws \UnexpectedValueException
     *
     * @return Transaction|null
     */
    protected function storeTransaction($transactionData)
    {
        // Get transaction object by transaction ID
        $keys  = array(
            'txn_id' => ArrayHelper::getValue($transactionData, 'txn_id')
        );
        $transaction = new Transaction(JFactory::getDbo());
        $transaction->load($keys);

        // DEBUG DATA
        JDEBUG ? $this->log->add(JText::_($this->textPrefix . '_DEBUG_TRANSACTION_OBJECT'), $this->debugType, $transaction->getProperties()) : null;

        // Check for existed transaction
        // If the current status if completed, stop the payment process.
        if ($transaction->getId() and $transaction->isCompleted()) {
            return null;
        }

        // Add extra data.
        if (array_key_exists('extra_data', $transactionData)) {
            if (!empty($transactionData['extra_data'])) {
                $transaction->addExtraData($transactionData['extra_data']);
            }

            unset($transactionData['extra_data']);
        }

        // IMPORTANT: It must be before ->bind();
        $options = array(
            'old_status' => $transaction->getStatus(),
            'new_status' => $transactionData['txn_status']
        );

        // Create the new transaction record if there is not record.
        // If there is new record, store new data with new status.
        // Example: It has been 'pending' and now is 'completed'.
        // Example2: It has been 'pending' and now is 'failed'.
        $transaction->bind($transactionData);

        // Start database transaction.
        $db = JFactory::getDbo();
        $db->transactionStart();

        try {
            $transactionManager = new TransactionManager($db);
            $transactionManager->setTransaction($transaction);
            $transactionManager->process('com_crowdfunding.payment', $options);
        } catch (Exception $e) {
            $db->transactionRollback();

            $this->log->add(JText::_($this->textPrefix . '_ERROR_TRANSACTION_PROCESS'), $this->errorType, $e->getMessage());
            return null;
        }

        // Commit database transaction.
        $db->transactionCommit();

        return $transaction;
    }

    protected function prepareLocale(&$html)
    {
        // Get country
        $countryId = $this->params->get('paypal_country');
        $country   = new Crowdfunding\Country(JFactory::getDbo());
        $country->load($countryId);

        $code  = $country->getCode();
        $code4 = $country->getLocale();

        $button    = $this->params->get('paypal_button_type', 'btn_buynow_LG');
        $buttonUrl = $this->params->get('paypal_button_url');

        // Generate a button
        if (!$this->params->get('paypal_button_default', 0)) {
            if (!$buttonUrl) {
                if (strcmp('US', $code) === 0) {
                    $html[] = '<input type="image" name="submit" border="0" src="https://www.paypalobjects.com/' . $code4 . '/i/btn/' . $button . '.gif" alt="' . JText::_($this->textPrefix . '_BUTTON_ALT') . '">';
                } else {
                    $html[] = '<input type="image" name="submit" border="0" src="https://www.paypalobjects.com/' . $code4 . '/' . $code . '/i/btn/' . $button . '.gif" alt="' . JText::_($this->textPrefix . '_BUTTON_ALT') . '">';
                }
            } else {
                $html[] = '<input type="image" name="submit" border="0" src="' . $buttonUrl . '" alt="' . JText::_($this->textPrefix . '_BUTTON_ALT') . '">';
            }
        } else { // Default button
            $html[] = '<input type="image" name="submit" border="0" src="https://www.paypalobjects.com/en_US/i/btn/' . $button . '.gif" alt="' . JText::_($this->textPrefix . '_BUTTON_ALT') . '">';
        }

        // Set locale
        $html[] = '<input type="hidden" name="lc" value="' . $code . '" />';
    }

    /**
     * Prepare a form element of payment receiver.
     *
     * @param $paymentReceiverOption
     * @param $itemId
     *
     * @return null|string
     */
    protected function preparePaymentReceiver($paymentReceiverOption, $itemId)
    {
        if ($this->params->get('paypal_sandbox', 1)) {
            return '<input type="hidden" name="business" value="' . trim($this->params->get('paypal_sandbox_business_name')) . '" />';
        } else {
            if (strcmp('site_owner', $paymentReceiverOption) === 0) { // Site owner
                return '<input type="hidden" name="business" value="' . trim($this->params->get('paypal_business_name')) . '" />';
            } else {
                if (!JComponentHelper::isEnabled('com_crowdfundingfinance')) {
                    return null;
                } else {
                    $payout = new Crowdfundingfinance\Payout(JFactory::getDbo());
                    $payout->load(array('project_id' => $itemId));

                    if (!$payout->getPaypalEmail()) {
                        return null;
                    }

                    return '<input type="hidden" name="business" value="' . trim($payout->getPaypalEmail()) . '" />';
                }
            }
        }
    }

    /**
     * Return payment receiver.
     *
     * @param $paymentReceiverOption
     * @param $itemId
     *
     * @return null|string
     */
    protected function getPaymentReceiver($paymentReceiverOption, $itemId)
    {
        if ($this->params->get('paypal_sandbox', 1)) {
            return strtolower(trim($this->params->get('paypal_sandbox_business_name')));
        } else {
            if (strcmp('site_owner', $paymentReceiverOption) === 0) { // Site owner
                return strtolower(trim($this->params->get('paypal_business_name')));
            } else {
                if (!JComponentHelper::isEnabled('com_crowdfundingfinance')) {
                    return null;
                } else {
                    $payout = new Crowdfundingfinance\Payout(JFactory::getDbo());
                    $payout->load(array('project_id' => $itemId));

                    if (!$payout->getPaypalEmail()) {
                        return null;
                    }

                    return strtolower(trim($payout->getPaypalEmail()));
                }
            }
        }
    }
}
