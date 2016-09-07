<?php
/**
 * @package      Crowdfunding\Projects
 * @subpackage   Validators
 * @author       Todor Iliev
 * @copyright    Copyright (C) 2016 Todor Iliev <todor@itprism.com>. All rights reserved.
 * @license      GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Crowdfunding\Validator\Project;

use Prism\Validator\ValidatorInterface;

defined('JPATH_BASE') or die;

/**
 * This class provides functionality to check if project exists in database.
 *
 * @package      Crowdfunding\Projects
 * @subpackage   Validators
 */
class Record implements ValidatorInterface
{
    protected $projectId;

    /**
     * Database driver.
     *
     * @var \JDatabaseDriver
     */
    protected $db;
    
    /**
     * Initialize the object.
     *
     * <code>
     * $projectId = 1;
     *
     * $record = new Crowdfunding\Validator\Project\Record(\JFactory::getDbo(), $projectId);
     * </code>
     *
     * @param \JDatabaseDriver $db        Database object.
     * @param int             $projectId Project ID.
     */
    public function __construct(\JDatabaseDriver $db, $projectId)
    {
        $this->db        = $db;
        $this->projectId = (int)$projectId;
    }

    /**
     * Validate project record.
     *
     * <code>
     * $projectId = 1;
     *
     * $record = new Crowdfunding\Validator\Project\Record(\JFactory::getDbo(), $projectId);
     * if(!$record->isValid()) {
     * //......
     * }
     * </code>
     *
     * @throws \RuntimeException
     * @return bool
     */
    public function isValid()
    {
        $query = $this->db->getQuery(true);

        $query
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__crowdf_projects', 'a'))
            ->where('a.id = ' . (int)$this->projectId);

        $this->db->setQuery($query, 0, 1);

        return (bool)$this->db->loadResult();
    }
}
