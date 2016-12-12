<?php
/**
 * Prepares a minimalist framework for unit testing.
 *
 * @copyright    Copyright (C) 2016 Todor Iliev <todor@itprism.com>. All rights reserved.
 * @license      GNU General Public License version 3 or later; see LICENSE.txt
 */

$testsFolder = str_replace(DIRECTORY_SEPARATOR. 'crowdfunding' . DIRECTORY_SEPARATOR . 'unit', '', __DIR__);
define('JOOMLA_TESTS_FOLDER_UNIT', $testsFolder . DIRECTORY_SEPARATOR . 'unit'. DIRECTORY_SEPARATOR);
define('CROWDFUNDING_TESTS_FOLDER', $testsFolder . DIRECTORY_SEPARATOR . 'crowdfunding'. DIRECTORY_SEPARATOR);
define('CROWDFUNDING_TESTS_FOLDER_UNIT', CROWDFUNDING_TESTS_FOLDER . 'unit'. DIRECTORY_SEPARATOR);
define('CROWDFUNDING_TESTS_FOLDER_SCHEMA', CROWDFUNDING_TESTS_FOLDER_UNIT . 'schema'. DIRECTORY_SEPARATOR);
define('CROWDFUNDING_TESTS_FOLDER_STUBS_DATA', CROWDFUNDING_TESTS_FOLDER_UNIT . 'stubs'. DIRECTORY_SEPARATOR . 'data'. DIRECTORY_SEPARATOR);
define('CROWDFUNDING_TESTS_FOLDER_STUBS_DATABASE', CROWDFUNDING_TESTS_FOLDER_UNIT . 'stubs'. DIRECTORY_SEPARATOR . 'database'. DIRECTORY_SEPARATOR);

/**
 * Include the main bootstrap and config file.
 */
require_once JOOMLA_TESTS_FOLDER_UNIT . 'bootstrap.php';
//include_once CROWDFUNDING_TESTS_FOLDER_UNIT . 'config.php';

jimport('Prism.init');
jimport('Crowdfunding.init');

// Register the core Joomla test classes.
JLoader::registerPrefix('CrowdfundingTest', __DIR__ . '/core');