<?php
/**
 * @package      Crowdfunding
 * @subpackage   Projects
 * @author       Todor Iliev
 * @copyright    Copyright (C) 2016 Todor Iliev <todor@itprism.com>. All rights reserved.
 * @license      GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Crowdfunding;

use Prism\Database;
use Joomla\Utilities\ArrayHelper;
use Psr\Log\InvalidArgumentException;

defined('JPATH_PLATFORM') or die;

/**
 * This class provides functionality that manage projects.
 *
 * @package      Crowdfunding
 * @subpackage   Projects
 */
class Projects extends Database\Collection
{
    /**
     * Load projects from database by IDs.
     *
     * <code>
     * $options = array(
     *    "ids"       => array(1,2,3),
     *    "published" => Prism\Constants::PUBLISHED,
     *    "approved"  => Prism\Constants::APPROVED,
     *    "users_ids" => array(10, 11, 12)
     * );
     *
     * $projects    = new Crowdfunding\Projects(\JFactory::getDbo());
     * $projects->load($options);
     *
     * foreach ($projects as $project) {
     *      echo $project["title"];
     *      echo $project["funding_start"];
     * }
     * </code>
     *
     * @param array $options
     *
     * @return array
     * @throws \InvalidArgumentException
     */
    public function load(array $options = array())
    {
        $ids = (!array_key_exists('ids', $options)) ? array() : (array)$options['ids'];
        $ids = ArrayHelper::toInteger($ids);

        $usersIds = (!array_key_exists('users_ids', $options)) ? array() : (array)$options['users_ids'];
        $usersIds = ArrayHelper::toInteger($usersIds);
        
        if (!$ids and !$usersIds) {
            throw new \InvalidArgumentException(\JText::_('LIB_CROWDFUNDING_INVALID_PARAMETERS_IDS_USER_IDS'));
        }
        
        // Prepare and return main query.
        $query = $this->getQuery();

        // Prepare the query to load project by IDs.
        if (count($ids) > 0) {
            $query->where('a.id IN ( ' . implode(',', $ids) . ' )');
        }

        // Prepare the query to load project by users IDs.
        if (count($usersIds) > 0) {
            $query->where('a.user_id IN ( ' . implode(',', $usersIds) . ' )');
        }

        // Prepare project states in the query.
        $this->prepareQueryStates($query, $options);

        $this->db->setQuery($query);

        $this->items = (array)$this->db->loadAssocList();
    }

    /**
     * Load projects from database.
     *
     * <code>
     * $phrase  = "Gamification";
     *
     * $options = array(
     *     "published" => Prism\Constants::PUBLISHED,
     *     "approved" => Prism\Constants::APPROVED
     * );
     *
     * $projects    = new Crowdfunding\Projects(\JFactory::getDbo());
     * $projects->loadByString($phrase, $options);
     *
     * foreach ($projects as $project) {
     *      echo $project->title;
     *      echo $project->funding_start;
     * }
     * </code>
     *
     * @param string $phrase
     * @param array $options
     *
     * @return array
     */
    public function loadByString($phrase, array $options = array())
    {
        $results = array();

        if ($phrase !== '') {

            // Prepare and return main query.
            $query = $this->getQuery();

            // Prepare LIKE filter.
            $escaped = $this->db->escape($phrase, true);
            $quoted  = $this->db->quote('%' . $escaped . '%', false);
            $query->where('a.title LIKE ' . $quoted);

            // Prepare project states in the query.
            $this->prepareQueryStates($query, $options);

            $query->order('a.title ASC');

            $this->db->setQuery($query);
            $results = (array)$this->db->loadAssocList();
        }

        $this->items = $results;
    }

    /**
     * Prepare the main query.
     *
     * @return \JDatabaseQuery
     */
    protected function getQuery()
    {
        // Load project data
        $query = $this->db->getQuery(true);

        $query
            ->select(
                'a.id, a.title, a.alias, a.image, a.image_small, a.image_square, ' .
                $query->concatenate(array('a.id', 'a.alias'), ':') . ' AS slug, ' .
                $query->concatenate(array('b.id', 'b.alias'), ':') . ' AS catslug'
            )
            ->from($this->db->quoteName('#__crowdf_projects', 'a'))
            ->innerJoin($this->db->quoteName('#__categories', 'b') . ' ON a.catid = b.id');

        return $query;
    }

    /**
     * Prepare the state of the project in where clause of the query.
     *
     * @param \JDatabaseQuery $query
     * @param array $options
     */
    protected function prepareQueryStates(&$query, array $options = array())
    {
        // Filter by state published.
        $value = ArrayHelper::getValue($options, 'published');
        if ($value !== null and is_numeric($value)) {
            $query->where('a.published = ' . (int)$value);
        }

        // Filter by state approved.
        $value = ArrayHelper::getValue($options, 'approved');
        if ($value !== null and is_numeric($value)) {
            $query->where('a.approved = ' . (int)$value);
        }
    }

    /**
     * Count and return rewards number of the projects.
     *
     * <code>
     * $projectsIds = array(1,2,3);
     *
     * $projects    = new Crowdfunding\Projects(\JFactory::getDbo());
     * $projects->load($projectsIds);
     *
     * $rewardsNumber = $projects->getRewardsNumber();
     * </code>
     *
     * @param array $ids Projects IDs
     *
     * @return array
     */
    public function getRewardsNumber(array $ids = array())
    {
        // If it is missing IDs as parameter, get the IDs of the current items.
        if (!$ids and count($this->items) > 0) {
            $ids = $this->getKeys();
        }

        $results = array();

        // If there are no IDs, return empty array.
        if (count($ids) > 0) {

            // Create a new query object.
            $query = $this->db->getQuery(true);

            $query
                ->select('a.project_id, COUNT(*) as number')
                ->from($this->db->quoteName('#__crowdf_rewards', 'a'))
                ->where('a.project_id IN (' . implode(',', $ids) . ')')
                ->group('a.project_id');

            $this->db->setQuery($query);

            $results = (array)$this->db->loadAssocList('project_id');
        }

        return $results;
    }

    /**
     * Count and return transactions number.
     *
     * <code>
     * $projectsIds = array(1,2,3);
     *
     * $projects           = new Crowdfunding\Projects(\JFactory::getDbo());
     * $transactionsNumber = $projects->getTransactionsNumber($projectsIds);
     * </code>
     *
     * @param array $ids Projects IDs
     *
     * @return array
     */
    public function getTransactionsNumber(array $ids = array())
    {
        // If it is missing IDs as parameter, get the IDs of the current items.
        if (!$ids and count($this->items) > 0) {
            $ids = $this->getKeys();
        }

        $results = array();

        // If there are no IDs, return empty array.
        if (count($ids) > 0) {

            // Create a new query object.
            $query = $this->db->getQuery(true);

            $query
                ->select('a.project_id, COUNT(*) as number')
                ->from($this->db->quoteName('#__crowdf_transactions', 'a'))
                ->where('a.project_id IN (' . implode(',', $ids) . ')')
                ->group('a.project_id');

            $this->db->setQuery($query);

            $results = (array)$this->db->loadAssocList('project_id');
        }

        return $results;
    }

    /**
     * Return project object by ID.
     *
     * <code>
     * $phrase = "Gamification";
     * $projectId = 1;
     *
     * $projects   = new Crowdfunding\Projects(\JFactory::getDbo());
     * $projects->loadByString($phrase);
     *
     * $project = $projects->getProject($projectId);
     * </code>
     *
     * @param int $id
     *
     * @return null|Project
     */
    public function getProject($id)
    {
        if (!$id) {
            throw new \UnexpectedValueException(\JText::_('LIB_CROWDFUNDING_INVALID_PROJECT_ID'));
        }

        $project = null;

        foreach ($this->items as $item) {
            if ((int)$id === (int)$item['id']) {
                $project = new Project($this->db);
                $project->bind($item);
                break;
            }
        }

        return $project;
    }
}
