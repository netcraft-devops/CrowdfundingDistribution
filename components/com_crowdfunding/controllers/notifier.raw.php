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
    use Crowdfunding\Container\MoneyHelper;

    /**
     * @var Prism\Log\Log
     */
    protected $log;

    protected $projectId;
    protected $context;

    protected $logFile    = 'com_crowdfunding.payment.php';
    protected $logTable   = '#__crowdf_logs';

    /**
     * @var Joomla\Registry\Registry
     */
    protected $params;

    /**
     * @var JApplicationSite
     */
    protected $app;
    
    /**
     * @var Joomla\DI\Container
     */
    protected $container;

    public function __construct($config = array())
    {
        parent::__construct($config);

        $this->app = JFactory::getApplication();

        // Get project ID.
        $this->projectId = $this->input->getUint('pid');

        // Prepare log object.
        $this->log = new Prism\Log\Log();

        // Set database log adapter if Joomla! debug is enabled.
        if ($this->logTable !== null and $this->logTable !== '' and JDEBUG) {
            $this->log->addAdapter(new Prism\Log\Adapter\Database(\JFactory::getDbo(), $this->logTable));
        }

        // Set file log adapter.
        if ($this->logFile !== null and $this->logFile !== '') {
            $file = \JPath::clean($this->app->get('log_path') .DIRECTORY_SEPARATOR. basename($this->logFile));
            $this->log->addAdapter(new Prism\Log\Adapter\File($file));
        }

        // Prepare context
        $filter         = new JFilterInput();
        $paymentService = $filter->clean(trim(strtolower($this->input->getCmd('payment_service'))), 'ALNUM');
        $this->context  = (Joomla\String\StringHelper::strlen($paymentService) > 0) ? 'com_crowdfunding.notify.' . $paymentService : 'com_crowdfunding.notify';

        // Prepare params
        $this->params   = JComponentHelper::getParams('com_crowdfunding');

        // Prepare container and some of the most used objects.
        $this->container = Prism\Container::getContainer();
        $this->prepareCurrency($this->container, $this->params);
        $this->prepareMoneyFormatter($this->container, $this->params);
    }

    /**
     * Method to get a model object, loading it if required.
     *
     * @param    string $name   The model name. Optional.
     * @param    string $prefix The class prefix. Optional.
     * @param    array  $config Configuration array for model. Optional.
     *
     * @return   CrowdfundingModelNotifier|bool    The model.
     * @since    1.5
     */
    public function getModel($name = 'Notifier', $prefix = 'CrowdfundingModel', $config = array('ignore_request' => true))
    {
        return parent::getModel($name, $prefix, $config);
    }

    /**
     * Catch a response from payment service and store data about transaction.
     */
    public function notify()
    {
        // Check for disabled payment functionality
        if ($this->params->get('debug_payment_disabled', 0)) {
            $errorData = JText::sprintf('COM_CROWDFUNDING_TRANSACTION_DATA', var_export($_REQUEST, true));
            $this->log->add(JText::_('COM_CROWDFUNDING_ERROR_PAYMENT_HAS_BEEN_DISABLED'), 'ERROR_CONTROLLER_NOTIFIER', $errorData);
            return;
        }

        $paymentResult      = null;
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
                    if (is_object($result) and isset($result->transaction)) {
                        $paymentResult      = $result;
                        $responseToService  = isset($result->response) ? $result->response : null;
                        break;
                    }
                }
            }

            // If there is no transaction data, the status might be pending or another one.
            // So, we have to stop the script execution.
            if (!$paymentResult) {
                return;
            }

            // Trigger the event onAfterPaymentNotify
            $dispatcher->trigger('onAfterPaymentNotify', array($this->context, &$paymentResult, &$this->params));

            // Trigger the event onAfterPayment
            $dispatcher->trigger('onAfterPayment', array($this->context, &$paymentResult, &$this->params));

        } catch (Exception $e) {
            $error     = 'NOTIFIER ERROR: ' .$e->getMessage() ."\n";
            $errorData = 'INPUT:' . var_export($this->app->input, true) . "\n";
            $this->log->add($error, 'CONTROLLER_NOTIFIER_ERROR', $errorData);

            // Send notification about the error to the administrator.
            $model = $this->getModel();
            $model->sendMailToAdministrator();
        }

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
            $errorData = JText::sprintf('COM_CROWDFUNDING_TRANSACTION_DATA', var_export($_REQUEST, true));
            $this->log->add(JText::_('COM_CROWDFUNDING_ERROR_PAYMENT_HAS_BEEN_DISABLED'), 'ERROR_CONTROLLER_NOTIFIER_AJAX', $errorData);

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

        $paymentResult  = null;
        $redirectUrl    = null;
        $message        = null;
        $project        = null;
        /** @var Crowdfunding\Project $project */

        // Trigger the event
        try {
            // Import Crowdfunding Payment Plugins
            JPluginHelper::importPlugin('crowdfundingpayment');

            // Trigger onPaymentNotify event.
            $dispatcher = JEventDispatcher::getInstance();
            $results    = $dispatcher->trigger('onPaymentNotify', array($this->context, &$this->params));

            if (is_array($results) and count($results) > 0) {
                foreach ($results as $result) {
                    if (is_object($result) and isset($result->transaction)) {
                        $paymentResult      = $result;
                        $project            = isset($result->project) ? $result->project : null;
                        $redirectUrl        = isset($result->redirectUrl) ? $result->redirectUrl : null;
                        $message            = isset($result->message) ? $result->message : null;
                        break;
                    }
                }
            }

            // If there is no transaction data, the status might be pending or another one.
            // So, we have to stop the script execution.
            if (!$paymentResult) {
                // Send response to the browser
                $response
                    ->setTitle(JText::_('COM_CROWDFUNDING_FAIL'))
                    ->setText(JText::_('COM_CROWDFUNDING_TRANSACTION_NOT_PROCESSED_SUCCESSFULLY'))
                    ->failure();

                echo $response;
                $this->app->close();
            }

            // Trigger the event onAfterPaymentNotify
            $dispatcher->trigger('onAfterPaymentNotify', array($this->context, &$paymentResult, &$this->params));

            // Trigger the event onAfterPayment
            $dispatcher->trigger('onAfterPayment', array($this->context, &$paymentResult, &$this->params));

        } catch (Exception $e) {
            // Store log data to the database.
            $error     = 'AJAX NOTIFIER ERROR: ' .$e->getMessage() ."\n";
            $errorData = 'INPUT:' . var_export($this->app->input, true) . "\n";

            $this->log->add($error, 'ERROR_CONTROLLER_NOTIFIER_AJAX', $errorData);

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
        if (!$redirectUrl and is_object($project)) {
            $uri         = JUri::getInstance();
            $redirectUrl = $uri->toString(array('scheme', 'host')) . JRoute::_(CrowdfundingHelperRoute::getBackingRoute($project->getSlug(), $project->getCatSlug(), 'share'));
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
