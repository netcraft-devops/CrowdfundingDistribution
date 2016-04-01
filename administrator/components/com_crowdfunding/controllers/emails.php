<?php
/**
 * @package      Crowdfunding
 * @subpackage   Component
 * @author       Todor Iliev
 * @copyright    Copyright (C) 2016 Todor Iliev <todor@itprism.com>. All rights reserved.
 * @license      GNU General Public License version 3 or later; see LICENSE.txt
 */

// No direct access
defined('_JEXEC') or die;

/**
 * Crowdfunding emails controller class
 *
 * @package     Crowdfunding
 * @subpackage  Components
 */
class CrowdfundingControllerEmails extends Prism\Controller\Admin
{
    public function getModel($name = 'Email', $prefix = 'CrowdfundingModel', $config = array('ignore_request' => true))
    {
        $model = parent::getModel($name, $prefix, $config);

        return $model;
    }
}
