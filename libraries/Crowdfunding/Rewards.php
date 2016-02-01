<?php
/**
 * @package      Crowdfunding
 * @subpackage   Rewards
 * @author       Todor Iliev
 * @copyright    Copyright (C) 2015 Todor Iliev <todor@itprism.com>. All rights reserved.
 * @license      GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Crowdfunding;

use Prism;
use Joomla\Utilities\ArrayHelper;

defined('JPATH_PLATFORM') or die;

/**
 * This class provides functionality that manage rewards.
 *
 * @package      Crowdfunding
 * @subpackage   Rewards
 */
class Rewards extends Prism\Database\ArrayObject
{
    protected static $instances = array();

    /**
     * Create and initialize an object.
     *
     * <code>
     * $options = array(
     *     "project_id" => 1,
     *     "state" => Prism\Constants::PUBLISHED
     * );
     *
     * $rewards   = Crowdfunding\Rewards::getInstance(\JFactory::getDbo(), $options);
     * </code>
     *
     * @param \JDatabaseDriver $db
     * @param array            $options
     *
     * @return null|self
     */
    public static function getInstance(\JDatabaseDriver $db, array $options = array())
    {
        $projectId = (!array_key_exists('project_id', $options)) ? 0 : (int)$options['project_id'];

        if (!array_key_exists($projectId, self::$instances)) {
            $item = new Rewards($db);
            $item->load($options);

            self::$instances[$projectId] = $item;
        }

        return self::$instances[$projectId];
    }

    /**
     * Load rewards data from database, by project ID.
     *
     * <code>
     * $options = array(
     *     "project_id" => 1,
     *     "state" => Prism\Constants::PUBLISHED
     * );
     *
     * $rewards   = new Crowdfunding\Rewards(\JFactory::getDbo());
     * $rewards->load($options);
     *
     * foreach($rewards as $reward) {
     *   echo $reward->title;
     *   echo $reward->amount;
     * }
     * </code>
     *
     * @param array $options
     */
    public function load($options = array())
    {
        $projectId = (!array_key_exists('project_id', $options)) ? 0 : (int)$options['project_id'];

        if ($projectId > 0) {
            $query = $this->db->getQuery(true);

            $query
                ->select(
                    'a.id, a.title, a.description, a.amount, a.number, a.distributed, ' .
                    'a.delivery, a.image, a.image_thumb, a.image_square'
                )
                ->from($this->db->quoteName('#__crowdf_rewards', 'a'))
                ->where('a.project_id = ' . (int)$projectId);

            // Get state
            $state = ArrayHelper::getValue($options, 'state');
            if ($state !== null) {
                $query->where('a.published = ' . (int)$state);
            }

            $this->db->setQuery($query);
            $this->items = (array)$this->db->loadAssocList();
        }
    }

    /**
     * Get number of people who have to receive rewards current rewards.
     *
     * <code>
     * $options = array(
     *     "project_id" => 1
     * );
     *
     * $rewards   = new Crowdfunding\Rewards(\JFactory::getDbo());
     * $rewards->load($options);
     *
     * $receiversNumber = $rewards->countReceivers();
     * </code>
     *
     * @return array
     */
    public function countReceivers()
    {
        $keys = $this->getKeys();
        $keys = ArrayHelper::toInteger($keys);

        $result = array();

        if (count($keys) > 0) {
            $query = $this->db->getQuery(true);

            $query
                ->select('a.reward_id, COUNT(a.id) AS funders')
                ->from($this->db->quoteName('#__crowdf_transactions', 'a'))
                ->group('a.reward_id')
                ->where('a.reward_id IN ( ' . implode(',', $keys) . ' )');

            $this->db->setQuery($query);
            $result = (array)$this->db->loadAssocList('reward_id');

            foreach ($this->items as &$item) {
                $item['funders'] = (!array_key_exists($item['id'], $result)) ? 0 : (int)$result[$item['id']]['funders'];
            }

            unset($item);
        }

        return $result;
    }
}
