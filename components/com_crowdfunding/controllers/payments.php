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
 * This controller provides functionality
 * that helps to payment plugins to prepare their payment data.
 *
 * @package        Crowdfunding
 * @subpackage     Payments
 */
class CrowdfundingControllerPayments extends JControllerLegacy
{
    protected $log;

    protected $paymentProcessContext;
    protected $paymentProcess;

    protected $projectId;

    protected $app;

    /**
     * Tasks that needs form token.
     *
     * @var array
     */
    protected $tokenTasks = array('checkout');

    public function __construct($config = array())
    {
        parent::__construct($config);

        $this->app = JFactory::getApplication();
        /** @var $app JApplicationSite */

        // Get project id.
        $this->projectId = $this->input->getUint('pid');
        if (!$this->projectId) {
            $this->projectId = (int)$this->app->getUserState('payments.pid');
        }

        // Prepare logger object.
        $file = JPath::clean($this->app->get('log_path') . DIRECTORY_SEPARATOR . 'com_crowdfunding.php');

        $this->log = new Prism\Log\Log();
        $this->log->addAdapter(new Prism\Log\Adapter\Database(JFactory::getDbo(), '#__crowdf_logs'));
        $this->log->addAdapter(new Prism\Log\Adapter\File($file));

        // Create an object that contains data used during the payment process.
        $this->paymentProcessContext = Crowdfunding\Constants::PAYMENT_SESSION_CONTEXT . $this->projectId;
        $this->paymentProcess        = $this->app->getUserState($this->paymentProcessContext);

        // Set payment service name.
        if (!isset($this->paymentProcess->paymentService)) {
            $this->paymentProcess->paymentService = '';
        }

        // Local executing tasks. It needs to provide form token.
        $this->registerTask('checkout', 'process');

        // Remote executing tasks. It does not need to provide form token.
        $this->registerTask('doCheckout', 'process');
        $this->registerTask('completeCheckout', 'process');
    }

    /**
     * Method to get a model object, loading it if required.
     *
     * @param    string $name   The model name. Optional.
     * @param    string $prefix The class prefix. Optional.
     * @param    array  $config Configuration array for model. Optional.
     *
     * @return    CrowdfundingModelPayments    The model.
     * @since    1.5
     */
    public function getModel($name = 'Payments', $prefix = 'CrowdfundingModel', $config = array('ignore_request' => true))
    {
        $model = parent::getModel($name, $prefix, $config);
        return $model;
    }

    /**
     * Task used for user authorization in their payment gateways.
     *
     * @throws Exception
     */
    public function authorize()
    {
        // Get component parameters
        $params = JComponentHelper::getParams('com_crowdfunding');
        /** @var  $params Joomla\Registry\Registry */

        // Check for disabled payment functionality
        if ($params->get('debug_payment_disabled', 0)) {
            throw new Exception(JText::_('COM_CROWDFUNDING_ERROR_PAYMENT_HAS_BEEN_DISABLED_MESSAGE'));
        }

        // Get payment gateway name.
        $paymentService = $this->input->getCmd('payment_service');
        if (!$paymentService and !$this->paymentProcess->paymentService) {
            throw new UnexpectedValueException(JText::_('COM_CROWDFUNDING_ERROR_INVALID_PAYMENT_GATEWAY'));
        }

        // Set the name of the payment service to session.
        if (JString::strlen($paymentService) > 0) {
            $this->paymentProcess->paymentService = $paymentService;

            // Store the payment process data into the session.
            $this->app->setUserState($this->paymentProcessContext, $this->paymentProcess);
        }

        $output = array();

        // Trigger the event
        try {

            $context = 'com_crowdfunding.payments.authorize.' . JString::strtolower($this->paymentProcess->paymentService);

            // Import Crowdfunding Payment Plugins
            $dispatcher = JEventDispatcher::getInstance();
            JPluginHelper::importPlugin('crowdfundingpayment');

            // Trigger the event.
            $results = $dispatcher->trigger('onPaymentsAuthorize', array($context, &$params));

            // Get the result, that comes from the plugin.
            if (is_array($results) and count($results) > 0) {
                foreach ($results as $result) {
                    if ($result !== null and is_array($result)) {
                        $output = $result;
                        break;
                    }
                }
            }

        } catch (UnexpectedValueException $e) {

            $this->setMessage($e->getMessage(), 'notice');
            $this->setRedirect(JRoute::_(CrowdfundingHelperRoute::getDiscoverRoute(), false));
            return;

        } catch (Exception $e) {

            // Store log data in the database
            $this->log->add(
                JText::_('COM_CROWDFUNDING_ERROR_SYSTEM'),
                'CONTROLLER_PAYMENTS_DOCHECKOUT_ERROR',
                $e->getMessage()
            );

            throw new Exception(JText::_('COM_CROWDFUNDING_ERROR_SYSTEM'));
        }

        $redirectUrl = Joomla\Utilities\ArrayHelper::getValue($output, 'redirect_url');
        $message     = Joomla\Utilities\ArrayHelper::getValue($output, 'message');
        if (!$redirectUrl) {
            throw new UnexpectedValueException(JText::_('COM_CROWDFUNDING_ERROR_INVALID_REDIRECT_URL'));
        }

        if (!$message) {
            $this->setRedirect($redirectUrl);
        } else {
            $this->setRedirect($redirectUrl, $message, 'notice');
        }
    }

    /**
     * Process action triggering an event that comes from remote server.
     *
     * Actions:
     * * authorize - Authorize or obtain access token from payment gateways.
     * * doCheckout - Authorize or obtain access token from payment gateways.
     *
     * @throws Exception
     */
    public function process()
    {
        // Get the task.
        $task    = JString::strtolower($this->input->getCmd('task'));
        if (!$task) {
            throw new Exception(JText::_('COM_CROWDFUNDING_ERROR_INVALID_TASK'));
        }

        // Check for request forgeries.
        if (in_array($task, $this->tokenTasks, true)) {
            JSession::checkToken() or jexit(JText::_('JINVALID_TOKEN'));
        }

        // Get component parameters
        $params = JComponentHelper::getParams('com_crowdfunding');
        /** @var  $params Joomla\Registry\Registry */

        // Check for disabled payment functionality
        if ($params->get('debug_payment_disabled', 0)) {
            throw new Exception(JText::_('COM_CROWDFUNDING_ERROR_PAYMENT_HAS_BEEN_DISABLED_MESSAGE'));
        }

        // Get payment gateway name.
        $paymentService = $this->input->getCmd('payment_service');
        if (!$paymentService and !$this->paymentProcess->paymentService) {
            throw new UnexpectedValueException(JText::_('COM_CROWDFUNDING_ERROR_INVALID_PAYMENT_GATEWAY'));
        }

        // Set the name of the payment service to session.
        if (JString::strlen($paymentService) > 0) {
            $this->paymentProcess->paymentService = $paymentService;

            // Store the payment process data into the session.
            $this->app->setUserState($this->paymentProcessContext, $this->paymentProcess);
        }

        $output = array();

        $model   = $this->getModel();

        // Trigger the event
        try {

            // Prepare project object.
            $item    = $model->prepareItem($this->projectId, $params, $this->paymentProcess);

            $context = 'com_crowdfunding.payments.'.$task.'.' . JString::strtolower($this->paymentProcess->paymentService);
            
            // Import Crowdfunding Payment Plugins
            $dispatcher = JEventDispatcher::getInstance();
            JPluginHelper::importPlugin('crowdfundingpayment');

            // Trigger the event.
            $results = $dispatcher->trigger('onPayments'. JString::ucwords($task), array($context, &$item, &$params));

            // Get the result, that comes from the plugin.
            if (is_array($results) and count($results) > 0) {
                foreach ($results as $result) {
                    if ($result !== null and is_array($result)) {
                        $output = $result;
                        break;
                    }
                }
            }

        } catch (UnexpectedValueException $e) {

            $this->setMessage($e->getMessage(), 'notice');
            $this->setRedirect(JRoute::_(CrowdfundingHelperRoute::getDiscoverRoute(), false));
            return;

        } catch (Exception $e) {

            // Store log data in the database
            $this->log->add(
                JText::_('COM_CROWDFUNDING_ERROR_SYSTEM'),
                'CONTROLLER_PAYMENTS_DOCHECKOUT_ERROR',
                $e->getMessage()
            );

            throw new Exception(JText::_('COM_CROWDFUNDING_ERROR_SYSTEM'));
        }

        $redirectUrl = Joomla\Utilities\ArrayHelper::getValue($output, 'redirect_url');
        $message     = Joomla\Utilities\ArrayHelper::getValue($output, 'message');
        if (!$redirectUrl) {
            throw new UnexpectedValueException(JText::_('COM_CROWDFUNDING_ERROR_INVALID_REDIRECT_URL'));
        }

        if (!$message) {
            $this->setRedirect($redirectUrl);
        } else {
            $this->setRedirect($redirectUrl, $message, 'warning');
        }
    }
}
