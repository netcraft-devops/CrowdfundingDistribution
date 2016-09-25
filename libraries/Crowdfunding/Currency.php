<?php
/**
 * @package      Crowdfunding
 * @subpackage   Currencies
 * @author       Todor Iliev
 * @copyright    Copyright (C) 2016 Todor Iliev <todor@itprism.com>. All rights reserved.
 * @license      GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Crowdfunding;

use Prism;

defined('JPATH_PLATFORM') or die;

/**
 * This class contains methods that are used for managing currency.
 *
 * @package      Crowdfunding
 * @subpackage   Currencies
 */
class Currency extends Prism\Database\TableImmutable implements Prism\Money\CurrencyInterface
{
    protected $id;
    protected $title;
    protected $code;
    protected $symbol;
    protected $position;

    protected static $instances = array();

    /**
     * Create an object or return existing one.
     *
     * <code>
     * $currencyId = 1;
     *
     * $currency   = Crowdfunding\Currency::getInstance(\JFactory::getDbo(), $currencyId);
     * </code>
     *
     * @param \JDatabaseDriver $db
     * @param int             $id
     * @param array           $options
     *
     * @return null|self
     *
     * @deprecated v2.8 Use the container.
     */
    public static function getInstance(\JDatabaseDriver $db, $id, array $options = array())
    {
        if (!array_key_exists($id, self::$instances)) {
            $item = new Currency($db);
            $item->load($id, $options);

            self::$instances[$id] = $item;
        }

        return self::$instances[$id];
    }

    /**
     * Load currency data from database by ID.
     *
     * <code>
     * $keys = array(
     *     "id" => 1,
     *     "code" => "EUR"
     * );
     *
     * $currency   = new Crowdfunding\Currency(\JFactory::getDbo());
     * $currency->load($keys);
     * </code>
     *
     * @param int|array $keys
     * @param array $options
     *
     * @throws \RuntimeException
     */
    public function load($keys, array $options = array())
    {
        $query = $this->db->getQuery(true);
        $query
            ->select('a.id, a.title, a.code, a.symbol, a.position')
            ->from($this->db->quoteName('#__crowdf_currencies', 'a'));

        if (is_array($keys)) {
            foreach ($keys as $key => $value) {
                $query->where($this->db->quoteName('a.'.$key) .' = ' . $this->db->quote($value));
            }
        } else {
            $query->where('a.id = ' . (int)$keys);
        }

        $this->db->setQuery($query);
        $result = (array)$this->db->loadAssoc();

        $this->bind($result);
    }

    /**
     * Return currency ID.
     *
     * <code>
     * $currencyId  = 1;
     *
     * $currency    = new Crowdfunding\Currency(\JFactory::getDbo());
     * $currency->load($currencyId);
     *
     * if (!$currency->getId()) {
     * ....
     * }
     * </code>
     *
     * @return int
     */
    public function getId()
    {
        return (int)$this->id;
    }

    /**
     * Return currency title.
     *
     * <code>
     * $currencyId  = 1;
     *
     * $currency    = new Crowdfunding\Currency(\JFactory::getDbo());
     * $currency->load($currencyId);
     *
     * echo $currency->getTitle();
     * </code>
     *
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * Return currency code (abbreviation).
     *
     * <code>
     * $currencyId  = 1;
     *
     * $currency    = new Crowdfunding\Currency(\JFactory::getDbo());
     * $currency->load($currencyId);
     *
     * // Return GBP
     * $code = $currency->getCode();
     * </code>
     *
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Return currency symbol.
     *
     * <code>
     * $currencyId  = 1;
     *
     * $currency    = new Crowdfunding\Currency(\JFactory::getDbo());
     * $currency->load($currencyId);
     *
     * // Return £
     * $symbol = $currency->getSymbol();
     * </code>
     *
     * @return string
     */
    public function getSymbol()
    {
        return $this->symbol;
    }

    /**
     * Return the position of currency symbol.
     *
     * <code>
     * $currencyId  = 1;
     *
     * $currency    = new Crowdfunding\Currency(\JFactory::getDbo());
     * $currency->load($currencyId);
     *
     * // Return 0 = beginning; 1 = end;
     * if (0 == $currency->getPosition()) {
     * ...
     * }
     * </code>
     *
     * @return int
     */
    public function getPosition()
    {
        return (int)$this->position;
    }
}
