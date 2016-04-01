<?php
/**
 * @package      Crowdfunding
 * @subpackage   Components
 * @author       Todor Iliev
 * @copyright    Copyright (C) 2016 Todor Iliev <todor@itprism.com>. All rights reserved.
 * @license      GNU General Public License version 3 or later; see LICENSE.txt
 */

defined('JPATH_PLATFORM') or die;

jimport('Prism.init');
jimport('Crowdfunding.init');

class JFormFieldCfreward extends JFormField
{
    /**
     * The form field type.
     *
     * @var     string
     * @since   1.6
     */
    protected $type = 'cfreward';

    /**
     * Layout to render
     *
     * @var  string
     */
    protected $layout = 'field.reward';

    /**
     * Method to get the field options.
     *
     * @return  array   The field option objects.
     * @since   1.6
     */
    protected function getOptions()
    {
        if (!$this->layout) {
            throw new UnexpectedValueException(sprintf('%s has no layout assigned.', $this->name));
        }

        return $this->getRenderer($this->layout)->render($this->getLayoutData());
    }

    /**
     * Get the data that is going to be passed to the layout
     *
     * @return  array
     */
    public function getLayoutData()
    {
        // Get the basic field data
        $data = parent::getLayoutData();

        // Load the current username if available.
        $item = new Crowdfunding\Reward(JFactory::getDbo());

        $title = '';
        if (is_numeric($this->value)) {
            $options = array(
                'fields' => array('a.id', 'a.title')
            );

            $item->load($this->value, $options);
            $title = $item->get('title');
        }

        $extraData = array(
            'rewardTitle' => $title
        );

        return array_merge($data, $extraData);
    }
}
