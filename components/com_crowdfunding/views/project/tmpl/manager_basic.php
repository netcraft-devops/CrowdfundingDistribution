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
?>
<div class="panel panel-info">
    <div class="panel-heading">
        <span class="fa fa-bar-chart"></span> <?php echo JText::_('COM_CROWDFUNDING_BASIC_STATISTICS'); ?>
    </div>
    <table class="table table-striped">
        <tbody>
            <tr>
                <td><?php echo JText::_('COM_CROWDFUNDING_HITS'); ?></td>
                <td><?php echo $this->item->hits;?></td>
            </tr>
            <tr>
                <td><?php echo JText::_('COM_CROWDFUNDING_UPDATES'); ?></td>
                <td><?php echo $this->statistics['updates'];?></td>
            </tr>
            <tr>
                <td><?php echo JText::_('COM_CROWDFUNDING_COMMENTS'); ?></td>
                <td><?php echo $this->statistics['comments'];?></td>
            </tr>
            <tr>
                <td><?php echo JText::_('COM_CROWDFUNDING_FUNDERS'); ?></td>
                <td><?php echo $this->statistics['funders'];?></td>
            </tr>
            <tr>
                <td><?php echo JText::_('COM_CROWDFUNDING_RAISED'); ?></td>
                <td><?php echo $this->raised;?></td>
            </tr>
        </tbody>
    </table>
</div>

<div class="panel panel-info">
    <div class="panel-heading">
        <span class="fa fa-info-circle"></span> <?php echo JText::_('COM_CROWDFUNDING_BASIC_INFORMATION'); ?>
    </div>
    <table class="table table-striped">
        <tbody>
            <tr>
                <td><?php echo JText::_('COM_CROWDFUNDING_STARTING_DATE'); ?></td>
                <td><?php echo JHtml::_('date', $this->item->funding_start, JText::_('DATE_FORMAT_LC3'));?></td>
            </tr>
            <tr>
                <td><?php echo JText::_('COM_CROWDFUNDING_ENDING_DATE'); ?></td>
                <td><?php echo JHtml::_('date', $this->item->funding_end, JText::_('DATE_FORMAT_LC3'));?></td>
            </tr>
            <tr>
                <td><?php echo JText::_('COM_CROWDFUNDING_APPROVED'); ?></td>
                <td><?php echo JHtml::_('crowdfunding.approved', $this->item->approved); ?></td>
            </tr>
        </tbody>
    </table>
</div>
