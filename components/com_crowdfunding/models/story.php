<?php
/**
 * @package      Crowdfunding
 * @subpackage   Components
 * @author       Todor Iliev
 * @copyright    Copyright (C) 2015 Todor Iliev <todor@itprism.com>. All rights reserved.
 * @license      GNU General Public License version 3 or later; see LICENSE.txt
 */

use Joomla\Utilities\ArrayHelper;
use Joomla\Registry\Registry;

// no direct access
defined('_JEXEC') or die;

JLoader::register('CrowdfundingModelProject', CROWDFUNDING_PATH_COMPONENT_SITE . '/models/project.php');

class CrowdfundingModelStory extends CrowdfundingModelProject
{
    /**
     * Method to get the profile form.
     *
     * The base form is loaded from XML and then an event is fired
     * for users plugins to extend the form with extra fields.
     *
     * @param    array   $data     An optional array of data for the form to interrogate.
     * @param    boolean $loadData True if the form is to load its own data (default case), false if not.
     *
     * @return    JForm    A JForm object on success, false on failure
     * @since    1.6
     */
    public function getForm($data = array(), $loadData = true)
    {
        // Get the form.
        $form = $this->loadForm($this->option . '.story', 'story', array('control' => 'jform', 'load_data' => $loadData));
        if (empty($form)) {
            return false;
        }

        return $form;
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @return    mixed    The data for the form.
     * @since    1.6
     */
    protected function loadFormData()
    {
        $app = JFactory::getApplication();
        /** @var $app JApplicationSite */

        $data = $app->getUserState($this->option . '.edit.story.data', array());
        if (!$data) {
            $itemId = (int)$this->getState($this->getName() . '.id');
            $userId = JFactory::getUser()->get('id');

            $data = $this->getItem($itemId, $userId);
        }

        return $data;
    }

    /**
     * Method to save the form data.
     *
     * @param    array    $data    The form data.
     *
     * @throws \InvalidArgumentException
     * @throws \Exception
     *
     * @return    mixed        The record id on success, null on failure.
     * @since    1.6
     */
    public function save($data)
    {
        $id          = ArrayHelper::getValue($data, 'id');
        $description = ArrayHelper::getValue($data, 'description');

        $keys = array(
            'id' => $id,
            'user_id' => JFactory::getUser()->get('id'),
        );

        // Load a record from the database.
        /** @var $row CrowdfundingTableProject */
        $row = $this->getTable();
        $row->load($keys);

        $row->set('description', $description);

        $this->prepareTableData($row, $data);

        $row->store();

        // Trigger the event onContentAfterSave.
        $this->triggerEventAfterSave($row, 'story');

        return $row->get('id');
    }

    protected function prepareTableData($table, $data)
    {
        // Prepare the video
        $pitchVideo = ArrayHelper::getValue($data, 'pitch_video');
        $table->set('pitch_video', $pitchVideo);

        // Prepare the image.
        if (!empty($data['pitch_image'])) {
            // Delete old image if I upload a new one.
            if ($table->get('pitch_image') !== '') {
                $params       = JComponentHelper::getParams($this->option);
                /** @var  $params Joomla\Registry\Registry */

                $imagesFolder = $params->get('images_directory', 'images/crowdfunding');

                // Remove an image from the filesystem
                $pitchImage   = JPath::clean($imagesFolder .'/'. $table->get('pitch_image'), '/');
                if (JFile::exists($pitchImage)) {
                    JFile::delete($pitchImage);
                }
            }

            $table->set('pitch_image', $data['pitch_image']);
        }
    }

    /**
     * Upload an image
     *
     * @param  array $uploadedFileData
     * @param  string $destination
     *
     * @throws Exception
     * @return array
     */
    public function uploadImage($uploadedFileData, $destination)
    {
        $app = JFactory::getApplication();
        /** @var $app JApplicationSite */

        $uploadedFile = ArrayHelper::getValue($uploadedFileData, 'tmp_name');
        $uploadedName = ArrayHelper::getValue($uploadedFileData, 'name');
        $errorCode    = ArrayHelper::getValue($uploadedFileData, 'error');

        // Joomla! media extension parameters
        $mediaParams = JComponentHelper::getParams('com_media');
        /** @var  $mediaParams Joomla\Registry\Registry */

        // Prepare size validator.
        $KB            = pow(1024, 2);
        $fileSize      = ArrayHelper::getValue($uploadedFileData, 'size', 0, 'int');
        $uploadMaxSize = $mediaParams->get('upload_maxsize') * $KB;

        $sizeValidator = new Prism\File\Validator\Size($fileSize, $uploadMaxSize);

        // Prepare server validator.
        $serverValidator = new Prism\File\Validator\Server($errorCode, array(UPLOAD_ERR_NO_FILE));

        // Prepare image validator.
        $imageValidator = new Prism\File\Validator\Image($uploadedFile, $uploadedName);

        // Get allowed mime types from media manager options
        $mimeTypes = explode(',', $mediaParams->get('upload_mime'));
        $imageValidator->setMimeTypes($mimeTypes);

        // Get allowed image extensions from media manager options
        $imageExtensions = explode(',', $mediaParams->get('image_extensions'));
        $imageValidator->setImageExtensions($imageExtensions);

        $file = new Prism\File\File($uploadedFile);
        $file
            ->addValidator($sizeValidator)
            ->addValidator($imageValidator)
            ->addValidator($serverValidator);

        // Validate the file
        if (!$file->isValid()) {
            throw new RuntimeException($file->getError());
        }

        // Upload the file in temporary folder.
        $temporaryFolder = JPath::clean($app->get('tmp_path'), '/');
        $filesystemLocal = new Prism\Filesystem\Adapter\Local($temporaryFolder);
        $sourceFile      = $filesystemLocal->upload($uploadedFileData);

        if (!JFile::exists($sourceFile)) {
            throw new RuntimeException('COM_CROWDFUNDING_ERROR_FILE_CANT_BE_UPLOADED');
        }

        $params     = JComponentHelper::getParams($this->option);
        /** @var  $params Joomla\Registry\Registry */
        
        // Resize image
        $options = new Registry();
        $options->set('width', $params->get('pitch_image_width', 600));
        $options->set('height', $params->get('pitch_image_height', 400));
        $options->set('scale', $params->get('image_resizing_scale', JImage::SCALE_INSIDE));
        $options->set('quality', $params->get('image_quality', Prism\Constants::QUALITY_VERY_HIGH));
        $options->set('suffix', '_pimage');
        $options->set('filename_length', 32);

        $image    = new \Prism\File\Image($sourceFile);
        $fileData = $image->resize($destination, $options);

        // Remove the temporary file.
        if (JFile::exists($sourceFile)) {
            JFile::delete($sourceFile);
        }

        return $fileData['filename'];
    }

    /**
     * Delete pitch image.
     *
     * @param integer $id
     * @param integer $userId
     *
     * @throws \UnexpectedValueException
     */
    public function removeImage($id, $userId)
    {
        $keys = array(
            'id' => $id,
            'user_id' => $userId
        );

        // Load category data
        $row = $this->getTable();
        $row->load($keys);

        // Delete old image if I upload the new one
        if ($row->get('pitch_image')) {
            $params       = JComponentHelper::getParams($this->option);
            /** @var  $params Joomla\Registry\Registry */

            $imagesFolder = $params->get('images_directory', 'images/crowdfunding');

            // Remove an image from the filesystem
            $pitchImage   = JPath::clean($imagesFolder .'/'. $row->get('pitch_image'), '/');

            if (JFile::exists($pitchImage)) {
                JFile::delete($pitchImage);
            }
        }

        $row->set('pitch_image', '');
        $row->store();
    }
}
