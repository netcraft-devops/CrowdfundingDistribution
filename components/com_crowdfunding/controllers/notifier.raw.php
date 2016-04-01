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

/**
 * This controller receives requests from the payment gateways.
 *
 * @package        Crowdfunding
 * @subpackage     Payments
 */
class CrowdfundingControllerNotifier extends JControllerLegacy
{
    protected $log;

    protected $paymentProcessContext;
    protected $paymentProcess;

    protected $projectId;
    protected $context;

    /**
     * @var Joomla\Registry\Registry
     */
    protected $params;

    /**
     * @var JApplicationSite
     */
    protected $app;

    public function __construct($config = array())
    {
        parent::__construct($config);

        $this->app = JFactory::getApplication();

        // Get project id.
        $this->projectId = $this->input->getUint('pid');

        // Prepare logger object.
        $file = JPath::clean($this->app->get('log_path') . DIRECTORY_SEPARATOR . 'com_crowdfunding.php');

        $this->log = new Prism\Log\Log();
        $this->log->addAdapter(new Prism\Log\Adapter\Database(JFactory::getDbo(), '#__crowdf_logs'));
        $this->log->addAdapter(new Prism\Log\Adapter\File($file));

        // Create an object that contains a data used during the payment process.
        $this->paymentProcessContext = Crowdfunding\Constants::PAYMENT_SESSION_CONTEXT . $this->projectId;
        $this->paymentProcess        = $this->app->getUserState($this->paymentProcessContext);

        // Prepare context
        $filter         = new JFilterInput();
        $paymentService = JString::trim(JString::strtolower($this->input->getCmd('payment_service')));
        $paymentService = $filter->clean($paymentService, 'ALNUM');
        $this->context  = (JString::strlen($paymentService) > 0) ? 'com_crowdfunding.notify.' . $paymentService : 'com_crowdfunding.notify';

        // Prepare params
        $this->params   = JComponentHelper::getParams('com_crowdfunding');
    }

    /**
     * Method to get a model object, loading it if required.
     *
     * @param    string $name   The model name. Optional.
     * @param    string $prefix The class prefix. Optional.
     * @param    array  $config Configuration array for model. Optional.
     *
     * @return    CrowdfundingModelNotifier    The model.
     * @since    1.5
     */
    public function getModel($name = 'Notifier', $prefix = 'CrowdfundingModel', $config = array('ignore_request' => true))
    {
        $model = parent::getModel($name, $prefix, $config);
        return $model;
    }

    /**
     * Catch a response from payment service and store data about transaction.
     */
    public function notify()
    {
        // Check for disabled payment functionality
        if ($this->params->get('debug_payment_disabled', 0)) {
            $error = JText::_('COM_CROWDFUNDING_ERROR_PAYMENT_HAS_BEEN_DISABLED');
            $error .= '\n' . JText::sprintf('COM_CROWDFUNDING_TRANSACTION_DATA', var_export($_REQUEST, true));
            $this->log->add($error, 'CONTROLLER_NOTIFIER_ERROR');
            return;
        }

        // Get model object.
        $model = $this->getModel();

        $transaction        = null;
        $project            = null;
        $reward             = null;
        $paymentSession     = null;
        $responseToService  = null;

        // Save data
        try {

            // Events
            $dispatcher = JEventDispatcher::getInstance();

            // Event Notify
            JPluginHelper::importPlugin('crowdfundingpayment');
            $results = $dispatcher->trigger('onPaymentNotify', array($this->context, &$this->params));

            if (is_array($results) and count($results) > 0) {
                foreach ($results as $result) {
                    if (is_array($result) and array_key_exists('transaction', $result)) {
                        $transaction        = Joomla\Utilities\ArrayHelper::getValue($result, 'transaction');
                        $project            = Joomla\Utilities\ArrayHelper::getValue($result, 'project');
                        $reward             = Joomla\Utilities\ArrayHelper::getValue($result, 'reward');
                        $paymentSession     = Joomla\Utilities\ArrayHelper::getValue($result, 'payment_session');
                        $responseToService  = Joomla\Utilities\ArrayHelper::getValue($result, 'response');
                        break;
                    }
                }
            }

            // If there is no transaction data, the status might be pending or another one.
            // So, we have to stop the script execution.
            if ($transaction === null) {
                // Remove the record of the payment session from database.
                $model->closePaymentSession($paymentSession);
                return;
            }

            // Event After Payment
            $dispatcher->trigger('onAfterPayment', array($this->context, &$transaction, &$this->params, &$project, &$reward, &$paymentSession));

        } catch (Exception $e) {

            $error     = 'NOTIFIER ERROR: ' .$e->getMessage() .'\n';
            $errorData = 'INPUT:' . var_export($this->app->input, true) . '\n';
            $this->log->add($error, 'CONTROLLER_NOTIFIER_ERROR', $errorData);

            // Send notification about the error to the administrator.
            $model = $this->getModel();
            $model->sendMailToAdministrator();

        }

        // Remove the record of the payment session from database.
//        $model = $this->getModel();
//        $model->closePaymentSession($paymentSession);

        // Send a specific response to a payment service.
        if (is_string($responseToService) and $responseToService !== '') {
            echo $responseToService;
        }

        // Stop the execution of the script.
        $this->app->close();
    }

    /**
     * Catch a request from payment plugin via AJAX and process a transaction.
     */
    public function notifyAjax()
    {
        $response = new Prism\Response\Json();

        // Check for disabled payment functionality
        if ($this->params->get('debug_payment_disabled', 0)) {

            // Log the error.
            $error  = JText::_('COM_CROWDFUNDING_ERROR_PAYMENT_HAS_BEEN_DISABLED') .'\n';
            $error .= JText::sprintf('COM_CROWDFUNDING_TRANSACTION_DATA', var_export($_REQUEST, true));
            $this->log->add($error, 'CONTROLLER_NOTIFIER_AJAX_ERROR');

            // Send response to the browser
            $response
                ->setTitle(JText::_('COM_CROWDFUNDING_FAIL'))
                ->setText(JText::_('COM_CROWDFUNDING_ERROR_PAYMENT_HAS_BEEN_DISABLED_MESSAGE'))
                ->failure();

            echo $response;
            $this->app->close();
        }

        // Get model object.
        $model = $this->getModel();

        $transaction    = null;
        $project        = null;
        $reward         = null;
        $paymentSession = null;
        $redirectUrl    = null;
        $message        = null;

        // Trigger the event
        try {

            // Import Crowdfunding Payment Plugins
            JPluginHelper::importPlugin('crowdfundingpayment');

            // Trigger onPaymentNotify event.
            $dispatcher = JEventDispatcher::getInstance();
            $results    = $dispatcher->trigger('onPaymentNotify', array($this->context, &$this->params));

            if (is_array($results) and count($results) > 0) {
                foreach ($results as $result) {
                    if (is_array($result) and array_key_exists('transaction', $result)) {
                        $transaction        = Joomla\Utilities\ArrayHelper::getValue($result, 'transaction');
                        $project            = Joomla\Utilities\ArrayHelper::getValue($result, 'project');
                        $reward             = Joomla\Utilities\ArrayHelper::getValue($result, 'reward');
                        $paymentSession     = Joomla\Utilities\ArrayHelper::getValue($result, 'payment_session');
                        $redirectUrl        = Joomla\Utilities\ArrayHelper::getValue($result, 'redirect_url');
                        $message            = Joomla\Utilities\ArrayHelper::getValue($result, 'message');
                        break;
                    }
                }
            }

            // If there is no transaction data, the status might be pending or another one.
            // So, we have to stop the script execution.
            if (!$transaction) {

                // Remove the record of the payment session from database.
                $model->closePaymentSession($paymentSession);

                // Send response to the browser
                $response
                    ->setTitle(JText::_('COM_CROWDFUNDING_FAIL'))
                    ->setText(JText::_('COM_CROWDFUNDING_TRANSACTION_NOT_PROCESSED_SUCCESSFULLY'))
                    ->failure();

                echo $response;
                $this->app->close();
            }

            // Trigger the event onAfterPayment
            $dispatcher->trigger('onAfterPayment', array($this->context, &$transaction, &$this->params, &$project, &$reward, &$paymentSession));

            // Remove the record of the payment session from database.
            $model->closePaymentSession($paymentSession);

        } catch (Exception $e) {

            // Store log data to the database.
            $error     = 'AJAX NOTIFIER ERROR: ' .$e->getMessage() .'\n';
            $errorData = 'INPUT:' . var_export($this->app->input, true) . '\n';

            $this->log->add($error, 'CONTROLLER_NOTIFIER_AJAX_ERROR', $errorData);

            // Remove the record of the payment session from database.
            $model->closePaymentSession($paymentSession);

            // Send response to the browser
            $response
                ->failure()
                ->setTitle(JText::_('COM_CROWDFUNDING_FAIL'))
                ->setText(JText::_('COM_CROWDFUNDING_ERROR_SYSTEM'));

            // Send notification about the error to the administrator.
            $model->sendMailToAdministrator();

            echo $response;
            $this->app->close();
        }

        // Generate redirect URL
        if (!$redirectUrl) {
            $uri         = JUri::getInstance();
            $redirectUrl = $uri->toString(array('scheme', 'host')) . JRoute::_(CrowdfundingHelperRoute::getBackingRoute($project->slug, $project->catslug, 'share'));
        }

        if (!$message) {
            $message = JText::_('COM_CROWDFUNDING_TRANSACTION_PROCESSED_SUCCESSFULLY');
        }

        // Send response to the browser
        $response
            ->success()
            ->setTitle(JText::_('COM_CROWDFUNDING_SUCCESS'))
            ->setText($message)
            ->setRedirectUrl($redirectUrl);

        echo $response;
        $this->app->close();
    }
}
