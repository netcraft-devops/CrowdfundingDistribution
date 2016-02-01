<?php
/**
 * @package      Crowdfunding
 * @subpackage   Locations
 * @author       Todor Iliev
 * @copyright    Copyright (C) 2015 Todor Iliev <todor@itprism.com>. All rights reserved.
 * @license      GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Crowdfunding;

use Prism;
use Joomla\Utilities\ArrayHelper;

defined('JPATH_PLATFORM') or die;

/**
 * This class provides functionality that manage locations.
 *
 * @package      Crowdfunding
 * @subpackage   Locations
 */
class Locations extends Prism\Database\ArrayObject
{
    /**
     * Load locations data by ID from database.
     *
     * <code>
     * $options = array(
     *      "ids" => array(1,2,3,4,5)
     * );
     *
     * $locations   = new Crowdfunding\Locations(\JFactory::getDbo());
     * $locations->load($options);
     *
     * foreach($locations as $location) {
     *   echo $location["id"];
     *   echo $location["name"];
     * }
     *
     * </code>
     *
     * @param array $options
     */
    public function load($options = array())
    {
        // Load project data
        $query = $this->db->getQuery(true);

        $query
            ->select('a.id, a.name, a.latitude, a.longitude, a.country_code, a.state_code, a.timezone, a.published')
            ->from($this->db->quoteName('#__crowdf_locations', 'a'));

        $ids = (!array_key_exists('ids', $options)) ? null : (array)$options['ids'];
        if ($ids !== null and is_array($ids)) {
            $ids = ArrayHelper::toInteger($ids);
            $query->where('a.id IN ( ' . implode(',', $ids) . ' )');
        }

        $this->db->setQuery($query);
        $this->items = (array)$this->db->loadAssocList();
    }

    /**
     * Load locations data by string from database.
     *
     * <code>
     * $string = "Plovdiv";
     * 
     * $locations   = new Crowdfunding\Locations(\JFactory::getDbo());
     * $locations->loadByString($string);
     *
     * foreach($locations as $location) {
     *   echo $location["id"];
     *   echo $location["name"];
     * }
     * </code>
     *
     * @param string $string
     * @param int $mode  Filter mode.
     *
     * Example:
     *
     * # Filter modes
     * 0 = "string";
     * 1 = "string%";
     * 2 = "%string";
     * 3 = "%string%";
     */
    public function loadByString($string, $mode = 1)
    {
        $query  = $this->db->getQuery(true);

        switch ($mode) {

            case 1: // Beginning
                $searchFilter = $this->db->escape($string, true) . '%';
                break;

            case 2: // End
                $searchFilter =  '%'. $this->db->escape($string, true);
                break;

            case 3: // Both
                $searchFilter =  '%' . $this->db->escape($string, true) . '%';
                break;

            default: // NONE
                $searchFilter = $this->db->escape($string, true);
                break;
        }

        $search = $this->db->quote($searchFilter);

        $caseWhen = ' CASE WHEN ';
        $caseWhen .= $query->charLength('a.state_code', '!=', '0');
        $caseWhen .= ' THEN ';
        $caseWhen .= $query->concatenate(array('a.name', 'a.state_code', 'a.country_code'), ', ');
        $caseWhen .= ' ELSE ';
        $caseWhen .= $query->concatenate(array('a.name', 'a.country_code'), ', ');
        $caseWhen .= ' END as name';

        $query
            ->select('a.id, ' . $caseWhen)
            ->from($this->db->quoteName('#__crowdf_locations', 'a'))
            ->where($this->db->quoteName('a.name') . ' LIKE ' . $search);

        $this->db->setQuery($query, 0, 8);
        $this->items = (array)$this->db->loadAssocList();
    }
}
