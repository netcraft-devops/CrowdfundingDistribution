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

class CrowdfundingModelRewards extends JModelList
{
    /**
     * Returns a reference to the a Table object, always creating it.
     *
     * @param   string $type    The table type to instantiate
     * @param   string $prefix A prefix for the table class name. Optional.
     * @param   array  $config Configuration array for model. Optional.
     *
     * @return  JTable  A database object
     * @since   1.6
     */
    public function getTable($type = 'Reward', $prefix = 'CrowdfundingTable', $config = array())
    {
        return JTable::getInstance($type, $prefix, $config);
    }

    protected function populateState($ordering = null, $direction = null)
    {
        parent::populateState();

        $app = JFactory::getApplication();
        /** @var $app JApplicationSite */

        // Get the pk of the record from the request.
        $this->setState($this->getName() . '.project_id', $app->input->getInt('id'));

        // Load the parameters.
        $value = $app->getParams($this->option);
        $this->setState('params', $value);
    }

    public function getItems()
    {
        $projectId = (int)$this->getState($this->getName() . '.project_id');

        $db    = $this->getDbo();
        $query = $db->getQuery(true);

        $query
            ->select('a.id, a.amount, a.title, a.description, a.number, a.distributed, a.delivery, a.image_thumb')
            ->from($db->quoteName('#__crowdf_rewards', 'a'))
            ->where('a.project_id = ' . (int)$projectId)
            ->where('a.published = 1')
            ->order('a.ordering ASC');

        $db->setQuery($query);

        return $db->loadAssocList();
    }

    public function validate($data)
    {
        if (!is_array($data) or count($data) === 0) {
            throw new InvalidArgumentException(JText::_('COM_CROWDFUNDING_ERROR_INVALID_REWARDS'));
        }

        $filter = JFilterInput::getInstance();

        $params = JComponentHelper::getParams('com_crowdfunding');
        /** @var  $params Joomla\Registry\Registry */

        // Create a currency object.
        $currency = Crowdfunding\Currency::getInstance(JFactory::getDbo(), $params->get('project_currency'));

        // Create the object 'amount'.
        $amount = new Crowdfunding\Amount($params);
        $amount->setCurrency($currency);

        foreach ($data as $key => &$item) {

            $item['amount'] = $amount->setValue($item['amount'])->parse();

            // Filter data
            if (!is_numeric($item['amount'])) {
                $item['amount'] = 0;
            }

            $item['title'] = $filter->clean($item['title'], 'string');
            $item['title'] = JString::trim($item['title']);
            $item['title'] = JString::substr($item['title'], 0, 128);

            $item['description'] = $filter->clean($item['description'], 'string');
            $item['description'] = JString::trim($item['description']);
            $item['description'] = JString::substr($item['description'], 0, 500);

            $item['number'] = (int)$item['number'];

            $item['delivery'] = JString::trim($item['delivery']);
            $item['delivery'] = $filter->clean($item['delivery'], 'string');

            if (!empty($item['delivery'])) {
                $date     = new JDate($item['delivery']);
                $unixTime = $date->toUnix();
                if ($unixTime < 0) {
                    $item['delivery'] = '';
                }
            }

            if (!$item['title']) {
                throw new RuntimeException(JText::_('COM_CROWDFUNDING_ERROR_INVALID_TITLE'));
            }

            if (!$item['description']) {
                throw new RuntimeException(JText::_('COM_CROWDFUNDING_ERROR_INVALID_DESCRIPTION'));
            }

            if (!$item['amount']) {
                throw new RuntimeException(JText::_('COM_CROWDFUNDING_ERROR_INVALID_AMOUNT'));
            }
        }

        unset($item);

        return $data;
    }

    /**
     * Method to save the form data.
     *
     * @param array $data
     * @param int $projectId
     *
     * @return    mixed        The record id on success, null on failure.
     *
     * @throws Exception
     *
     * @since    1.6
     */
    public function save($data, $projectId)
    {
        $ids = array();

        $ordering = 1;

        foreach ($data as $item) {

            // Load a record from the database
            $row    = $this->getTable();
            $itemId = Joomla\Utilities\ArrayHelper::getValue($item, 'id', 0, 'int');

            if ($itemId > 0) {
                $keys = array('id' => $itemId, 'project_id' => $projectId);
                $row->load($keys);

                if (!$row->get('id')) {
                    throw new Exception(JText::_('COM_CROWDFUNDING_ERROR_INVALID_REWARD'));
                }
            }

            $amount      = Joomla\Utilities\ArrayHelper::getValue($item, 'amount');
            $title       = Joomla\Utilities\ArrayHelper::getValue($item, 'title');
            $description = Joomla\Utilities\ArrayHelper::getValue($item, 'description');
            $number      = Joomla\Utilities\ArrayHelper::getValue($item, 'number');
            $delivery    = Joomla\Utilities\ArrayHelper::getValue($item, 'delivery');

            $row->set('amount', $amount);
            $row->set('title', $title);
            $row->set('description', $description);
            $row->set('number', $number);
            $row->set('delivery', $delivery);
            $row->set('project_id', $projectId);
            $row->set('ordering', $ordering);

            $row->store();

            $ids[] = $row->get('id');

            // Increase the number of ordering.
            $ordering++;
        }

        return $ids;
    }

    public function remove($rewardId, $imagesFolder)
    {
        // Get reward row.
        /** @var $table CrowdfundingTableReward */
        $table = $this->getTable();
        $table->load($rewardId);

        if (!$table->get('id')) {
            throw new RuntimeException(JText::_('COM_CROWDFUNDING_ERROR_INVALID_REWARD'));
        }

        // Delete the images from filesystem.
        $this->deleteImages($table, $imagesFolder);

        $table->delete();
    }

    /**
     * Upload images.
     *
     * @param  array $files
     * @param  array $rewardsIds
     * @param  array $options
     * @param  Joomla\Registry\Registry $params
     *
     * @return array
     */
    public function uploadImages(array $files, array $rewardsIds, array $options, $params)
    {
        // Joomla! media extension parameters
        $mediaParams = JComponentHelper::getParams('com_media');
        /** @var  $mediaParams Joomla\Registry\Registry */

        $KB = 1024 * 1024;

        $uploadMaxSize   = $mediaParams->get('upload_maxsize') * $KB;
        $mimeTypes       = explode(',', $mediaParams->get('upload_mime'));
        $imageExtensions = explode(',', $mediaParams->get('image_extensions'));

        $images          = array();
        $rewardsIds      = Joomla\Utilities\ArrayHelper::toInteger($rewardsIds);

        jimport('Prism.libs.Flysystem.init');
        $temporaryAdapter    = new League\Flysystem\Adapter\Local($options['temporary_path']);
        $storageAdapter      = new League\Flysystem\Adapter\Local($options['destination_path']);
        $temporaryFilesystem = new League\Flysystem\Filesystem($temporaryAdapter);
        $storageFilesystem   = new League\Flysystem\Filesystem($storageAdapter);

        $manager = new League\Flysystem\MountManager([
            'temporary' => $temporaryFilesystem,
            'storage'   => $storageFilesystem
        ]);

        foreach ($files as $rewardId => $image) {

            // If the image is set to not valid reward, continue to next one.
            // It is impossible to store image to missed reward.
            if (!in_array((int)$rewardId, $rewardsIds, true)) {
                continue;
            }

            $uploadedFile = Joomla\Utilities\ArrayHelper::getValue($image, 'tmp_name');
            $uploadedName = JString::trim(Joomla\Utilities\ArrayHelper::getValue($image, 'name'));
            $errorCode    = Joomla\Utilities\ArrayHelper::getValue($image, 'error');

            $fileOptions  = new \Joomla\Registry\Registry(array('filename_length' => 12));
            $file         = new Prism\File\Image($image, $options['temporary_path'], $fileOptions);

            $result       = array('image' => '', 'thumb' => '', 'square' => '');
            
            if (JString::strlen($uploadedName) > 0) {

                // Prepare size validator.
                $fileSize = (int)Joomla\Utilities\ArrayHelper::getValue($image, 'size');

                // Prepare file size validator.
                $sizeValidator = new Prism\File\Validator\Size($fileSize, $uploadMaxSize);

                // Prepare server validator.
                $serverValidator = new Prism\File\Validator\Server($errorCode, array(UPLOAD_ERR_NO_FILE));

                // Prepare image validator.
                $imageValidator = new Prism\File\Validator\Image($uploadedFile, $uploadedName);

                // Get allowed mime types from media manager options
                $imageValidator->setMimeTypes($mimeTypes);

                // Get allowed image extensions from media manager options
                $imageValidator->setImageExtensions($imageExtensions);

                $file
                    ->addValidator($sizeValidator)
                    ->addValidator($imageValidator)
                    ->addValidator($serverValidator);

                // Validate the file
                if (!$file->isValid()) {
                    continue;
                }

                // Upload it in the temp folder.
                $fileData = $file->upload();

                if ($manager->has('temporary://'.$fileData['filename'])) {
                    
                    // Copy original image.
                    $originalFile    = $fileData['filename'];
                    $result['image'] = 'reward_'.$originalFile;
                    $manager->copy('temporary://'.$originalFile, 'storage://'.$result['image']);

                    // Create thumbnail.
                    $resizeOptions     = array(
                        'width'       => $params->get('rewards_image_thumb_width', 200),
                        'height'      => $params->get('rewards_image_thumb_height', 200),
                        'scale'       => $params->get('rewards_image_resizing_scale', JImage::SCALE_INSIDE)
                    );
                    $fileData = $file->resize($resizeOptions, Prism\Constants::DO_NOT_REPLACE, 'reward_thumb_');
                    $manager->move('temporary://'.$fileData['filename'], 'storage://'.$fileData['filename']);
                    $result['thumb'] = $fileData['filename'];

                    // Create square image.
                    $resizeOptions     = array(
                        'width'       => $params->get('rewards_image_square_width', 50),
                        'height'      => $params->get('rewards_image_square_height', 50),
                        'scale'       => $params->get('rewards_image_resizing_scale', JImage::SCALE_INSIDE),
                    );
                    $fileData = $file->resize($resizeOptions, Prism\Constants::DO_NOT_REPLACE, 'reward_square_');
                    $manager->move('temporary://'.$fileData['filename'], 'storage://'.$fileData['filename']);
                    $result['square'] = $fileData['filename'];

                    // Remove the original file from temporary folder.
                    $manager->delete('temporary://'.$originalFile);

                    $images[$rewardId] = $result;
                }
                
            }
        }

        return $images;
    }

    /**
     * Save reward images to the reward.
     *
     * @param array $images
     * @param string $imagesFolder
     *
     * @throws InvalidArgumentException
     */
    public function storeImages($images, $imagesFolder)
    {
        if (!$images or !is_array($images)) {
            throw new InvalidArgumentException(JText::_('COM_CROWDFUNDING_ERROR_INVALID_IMAGES'));
        }

        foreach ($images as $rewardId => $pictures) {

            // Get reward row.
            /** @var $table CrowdfundingTableReward */
            $table = $this->getTable();
            $table->load($rewardId);

            if (!$table->get('id')) {
                continue;
            }

            // Delete old reward image ( image, thumb and square ) from the filesystem.
            $this->deleteImages($table, $imagesFolder);

            // Store the new one.
            $image  = Joomla\Utilities\ArrayHelper::getValue($pictures, 'image');
            $thumb  = Joomla\Utilities\ArrayHelper::getValue($pictures, 'thumb');
            $square = Joomla\Utilities\ArrayHelper::getValue($pictures, 'square');

            $table->set('image', $image);
            $table->set('image_thumb', $thumb);
            $table->set('image_square', $square);

            $table->store();
        }
    }

    public function removeImage($rewardId, $imagesFolder)
    {
        // Get reward row.
        /** @var $table CrowdfundingTableReward */
        $table = $this->getTable();
        $table->load($rewardId);

        if (!$table->get('id')) {
            throw new RuntimeException(JText::_('COM_CROWDFUNDING_ERROR_INVALID_REWARD'));
        }

        // Delete the images from filesystem.
        $this->deleteImages($table, $imagesFolder);

        $table->set('image', null);
        $table->set('image_thumb', null);
        $table->set('image_square', null);

        $table->store(true);
    }

    /**
     * Remove images from the filesystem.
     *
     * @param CrowdfundingTableReward $table
     * @param string $imagesFolder
     */
    protected function deleteImages(&$table, $imagesFolder)
    {
        // Remove image.
        if ($table->get('image')) {
            $fileSource = $imagesFolder . DIRECTORY_SEPARATOR . $table->get('image');
            if (JFile::exists($fileSource)) {
                JFile::delete($fileSource);
            }
        }

        // Remove thumbnail.
        if ($table->get('image_thumb')) {
            $fileSource = $imagesFolder . DIRECTORY_SEPARATOR . $table->get('image_thumb');
            if (JFile::exists($fileSource)) {
                JFile::delete($fileSource);
            }
        }

        // Remove square image.
        if ($table->get('image_square')) {
            $fileSource = $imagesFolder . DIRECTORY_SEPARATOR . $table->get('image_square');
            if (JFile::exists($fileSource)) {
                JFile::delete($fileSource);
            }
        }
    }
}
