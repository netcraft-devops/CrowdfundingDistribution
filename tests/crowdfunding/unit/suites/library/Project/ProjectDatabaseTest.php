<?php
/**
 * @package     Crowdfunding\UnitTest
 * @subpackage  Projects
 * @author      Todor Iliev
 * @copyright   Copyright (C) 2016 Todor Iliev <todor@itprism.com>. All rights reserved.
 * @license     GNU General Public License version 3 or later; see LICENSE.txt
 */

use Crowdfunding\Project;

/**
 * Test class for Crowdfunding\UnitTest.
 *
 * @package     Crowdfunding\UnitTest
 * @subpackage  Projects
 */
class ProjectDatabaseTest extends CrowdfundingTestCaseDatabase
{
    /**
     * @var    Project
     */
    protected $object;

    /**
     * Test the storeFunds method.
     *
     * @return  void
     * @covers  Project::storeFunds
     */
    public function testStoreFunds()
    {
        $this->object->addFunds(100);
        $this->object->storeFunds();

        $this->object->loadFunds();

        $this->assertEquals(
            600,
            $this->object->getFunded()
        );
    }

    /**
     * Test the loadFunds method.
     *
     * @return  void
     * @covers  Project::loadFunds
     */
    public function testLoadFunds()
    {
        $this->object->loadFunds();

        $this->assertEquals(
            500,
            $this->object->getFunded()
        );
    }

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     *
     * @return  void
     */
    protected function setUp()
    {
        parent::setUp();

        $db = self::$driver;

        // Remove rows.
        $query = $db->getQuery(true);
        $query->delete($db->quoteName('jos_crowdf_projects'));
        $db->setQuery($query);
        $db->execute();

        // Add rows.
        $sqlData = file_get_contents(CROWDFUNDING_TESTS_FOLDER_STUBS_DATABASE.'jos_crowdf_projects.sql');
        $db->setQuery($sqlData);
        $db->execute();

        $projectId      = 2; // Goal: 1000 / Funded: 500
        $this->object   = new Project(JFactory::getDbo());
        $this->object->load($projectId);
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     *
     * @return void
     *
     * @see     PHPUnit_Framework_TestCase::tearDown()
     */
    protected function tearDown()
    {
        unset($this->object);
        parent::tearDown();
    }
}
