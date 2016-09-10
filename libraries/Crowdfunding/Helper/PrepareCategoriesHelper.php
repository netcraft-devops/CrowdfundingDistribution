<?php
/**
 * @package      Crowdfunding
 * @subpackage   Helpers
 * @author       Todor Iliev
 * @copyright    Copyright (C) 2016 Todor Iliev <todor@itprism.com>. All rights reserved.
 * @license      GNU General Public License version 3 or later; see LICENSE.txt
 */

namespace Crowdfunding\Helper;

use Prism\Helper\HelperInterface;
use Prism\Utilities\MathHelper;
use Crowdfunding;

defined('JPATH_PLATFORM') or die;

/**
 * This class provides functionality to prepare the category items.
 *
 * @package      Crowdfunding
 * @subpackage   Helpers
 */
final class PrepareCategoriesHelper implements HelperInterface
{
    /**
     * Prepare category items.
     *
     * @param array $data
     * @param array $options
     */
    public function handle(&$data, array $options = array())
    {
        foreach ($data as $key => $item) {
            // Decode parameters
            if ($item->params !== null and $item->params !== '') {
                $item->params = json_decode($item->params);

                // Generate a link to the picture.
                if (is_object($item->params) and isset($item->params->image) and $item->params->image !== '') {
                    $item->image_link = \JUri::base().$item->params->image;
                }
            }
        }
    }
}
