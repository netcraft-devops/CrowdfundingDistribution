<?php
/**
 * @package      Crowdfunding
 * @subpackage   Components
 * @author       Todor Iliev
 * @copyright    Copyright (C) 2015 Todor Iliev <todor@itprism.com>. All rights reserved.
 * @license      GNU General Public License version 3 or later; see LICENSE.txt
 */

// no direct access
defined('_JEXEC') or die;

$itemSpan = ($this->subcategoriesPerRow > 0) ? round(12 / $this->subcategoriesPerRow) : 4;
?>
<div id="cf-categories-grid">
    <row class="row">
        <?php foreach ($this->categories as $item) { ?>

        <div class="col-md-<?php echo $itemSpan; ?>">
            <div class="thumbnail cf-category">
                <a href="<?php echo JRoute::_(CrowdfundingHelperRoute::getCategoryRoute($item->slug)); ?>">
                    <?php if (JString::strlen($item->image_link) > 0) { ?>
                        <img src="<?php echo $item->image_link; ?>" alt="<?php echo $this->escape($item->title); ?>" />
                    <?php } else { ?>
                        <img src="<?php echo 'media/com_crowdfunding/images/no_image.png'; ?>"
                             alt="<?php echo $this->escape($item->title); ?>" width="200"
                             height="200" />
                    <?php } ?>
                </a>

                <div class="caption height-150px absolute-bottom">
                    <h3>
                        <a href="<?php echo JRoute::_(CrowdfundingHelperRoute::getCategoryRoute($item->slug)); ?>">
                            <?php echo $this->escape($item->title); ?>
                        </a>
                        <?php
                        if ($this->displayProjectsNumber) {
                            $number = (!array_key_exists($item->id, $this->projectsNumber)) ? 0 : $this->projectsNumber[$item->id][0];
                            echo '( '. $number . ' )';
                        } ?>
                    </h3>
                    <?php
                    if ($this->params->get('categories_display_description', true)) { ?>
                        <p><?php echo JHtmlString::truncate($item->description, $this->params->get('categories_description_length', 0), true, false); ?></p>
                    <?php } ?>
                </div>
            </div>
        </div>
        <?php } ?>
    </row>
</div>