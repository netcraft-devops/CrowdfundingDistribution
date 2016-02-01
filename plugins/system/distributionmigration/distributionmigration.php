<?php
/**
 * @package      ITPrism Plugins
 * @subpackage   Plugins
 * @author       Todor Iliev
 * @copyright    Copyright (C) 2016 Todor Iliev <todor@itprism.com>. All rights reserved.
 * @license      http://www.gnu.org/copyleft/gpl.html GNU/GPL
 */

// no direct access
defined('_JEXEC') or die;

class plgSystemDistributionMigration extends JPlugin {

	/**
	 * After initialise.
	 *
	 * @return  void
	 *
	 * @since   1.6
	 */
	public function onAfterInitialise()
	{
		$app = JFactory::getApplication();
		/** @var $app JApplicationSite */

		if ($app->isAdmin()) {
			return;
		}

		$document = JFactory::getDocument();
		/** @var $document JDocumentHtml */

		$type = $document->getType();
		if (strcmp('html', $type) !== 0) {
			return;
		}

		$option = $app->input->getCmd('option');
		$view   = $app->input->getCmd('view');

		if (strcmp($option, 'com_installer') !== 0) {
			return;
		}

		if (strcmp($view, 'database') !== 0) {
			return;
		}

		// Check component enabled
		/*if (!JComponentHelper::isInstalled('com_crowdfunding', true)) {
			return;
		}*/
	}
	
}
