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

class JFormFieldCfText extends JFormFieldText
{
    /**
     * The form field type.
     *
     * @var    string
     *
     * @since  11.1
     */
    protected $type = 'cftext';

    /**
     * Layout to render the label
     *
     * @var  string
     */
    protected $renderLabelLayout = 'prism.field.renderlabel';

    /**
     * Allow to override renderer include paths in child fields
     *
     * @return  array
     *
     * @since   3.5
     */
    protected function getLayoutPaths()
    {
        return array(
            JPath::clean(PRISM_PATH_LIBRARY.'/layouts')
        );
    }
}
