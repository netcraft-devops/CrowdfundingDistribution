<?php
/**
 * @package      Crowdfunding
 * @subpackage   Components
 * @author       Todor Iliev
 * @copyright    Copyright (C) 2015 Todor Iliev <todor@itprism.com>. All rights reserved.
 * @license      GNU General Public License version 3 or later; see LICENSE.txt
 */

use Joomla\DI\ContainerAwareInterface;
use Joomla\DI\ContainerAwareTrait;
use Joomla\Utilities\ArrayHelper;
use Joomla\Registry\Registry;

// no direct access
defined('_JEXEC') or die;

class CrowdfundingModelProject extends JModelForm implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    /**
     * Returns a reference to the a Table object, always creating it.
     *
     * @param   string $type   The table type to instantiate
     * @param   string $prefix A prefix for the table class name. Optional.
     * @param   array  $config Configuration array for model. Optional.
     *
     * @return  CrowdfundingTableProject|bool  A database object
     * @since   1.6
     */
    public function getTable($type = 'Project', $prefix = 'CrowdfundingTable', $config = array())
    {
        return JTable::getInstance($type, $prefix, $config);
    }

    /**
     * Method to auto-populate the model state.
     *
     * Note. Calling getState in this method will result in recursion.
     *
     * @throws \Exception
     */
    protected function populateState()
    {
        parent::populateState();

        $app = JFactory::getApplication();
        /** @var $app JApplicationSite */

        // Get the pk of the record from the request.
        $itemId = $app->input->getInt('id');
        $this->setState($this->getName() . '.id', $itemId);

        // Load the parameters.
        $value = $app->getParams($this->option);
        $this->setState('params', $value);
    }

    /**
     * Method to get the profile form.
     *
     * The base form is loaded from XML and then an event is fired
     * for users plugins to extend the form with extra fields.
     *
     * @param    array   $data     An optional array of data for the form to interrogate.
     * @param    boolean $loadData True if the form is to load its own data (default case), false if not.
     *
     * @return   JForm|bool    A JForm object on success, false on failure
     * @since    1.6
     */
    public function getForm($data = array(), $loadData = true)
    {
        // Get the form.
        $form = $this->loadForm($this->option . '.project', 'project', array('control' => 'jform', 'load_data' => $loadData));
        if (!$form) {
            return false;
        }

        return $form;
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @throws   \Exception
     * @return   mixed        The data for the form.
     * @since    1.6
     */
    protected function loadFormData()
    {
        $app = JFactory::getApplication();
        /** @var $app JApplicationSite */

        $data = $app->getUserState($this->option . '.edit.project.data', array());
        if (!$data) {
            $itemId = (int)$this->getState($this->getName() . '.id');
            $userId = JFactory::getUser()->get('id');

            $data = $this->getItem($itemId, $userId);

            if ((int)$data->location_id > 0) {
                // Load location from database.
                $location = new Crowdfunding\Location(JFactory::getDbo());
                $location->load($data->location_id);
                $locationName = $location->getName(true);

                // Set the name to the form element.
                if ($locationName !== null and $locationName !== '') {
                    $data->location = $locationName;
                }
            }
        }

        return $data;
    }

    /**
     * Method to get a single record.
     *
     * @param   integer $pk     The id of the primary key.
     * @param   integer $userId The id of the user.
     *
     * @return  stdClass  Object on success, false on failure.
     *
     * @throws  \Exception
     *
     * @since   11.1
     */
    public function getItem($pk, $userId)
    {
        // Initialise variables.
        $table = $this->getTable();

        if ($pk > 0 and $userId > 0) {
            $keys = array(
                'id'      => $pk,
                'user_id' => $userId
            );

            // Attempt to load the row.
            $return = $table->load($keys);

            // Check for a table object error.
            if ($return === false) {
                throw new Exception(JText::_('COM_CROWDFUNDING_ERROR_INVALID_PROJECT'));
            }
        }

        // Convert to the JObject before adding other data.
        $properties = $table->getProperties();
        $item       = ArrayHelper::toObject($properties);

        if (property_exists($item, 'params')) {
            $registry = new Registry;
            /** @var  $registry Registry */

            $registry->loadString($item->params);
            $item->params = $registry->toArray();
        }

        return $item;
    }

    /**
     * Method to save the form data.
     *
     * @param    array $data The form data.
     *
     * @throws Exception
     *
     * @return int
     * @since    1.6
     */
    public function save($data)
    {
        $id         = ArrayHelper::getValue($data, 'id');
        $title      = ArrayHelper::getValue($data, 'title');
        $shortDesc  = ArrayHelper::getValue($data, 'short_desc');
        $catId      = ArrayHelper::getValue($data, 'catid');
        $locationId = ArrayHelper::getValue($data, 'location_id');
        $typeId     = ArrayHelper::getValue($data, 'type_id');

        // Load a record from the database
        $row = $this->getTable();
        /** @var $row CrowdfundingTableProject */

        $row->load($id);

        // Set a flag for a new item.
        $isNew = $row->get('id') ? true : false;

        $row->set('title', $title);
        $row->set('short_desc', $shortDesc);
        $row->set('catid', $catId);
        $row->set('location_id', $locationId);
        $row->set('type_id', $typeId);

        $this->prepareTable($row);

        $row->store();

        // Load the data and initialize some parameters.
        if ($isNew) {
            $row->prepareData();
        }

        // Trigger the event onContentAfterSave.
        $this->triggerEventAfterSave($row, 'basic', $isNew);

        return $row->get('id');
    }

    /**
     * This method executes the event onContentAfterSave.
     *
     * @param CrowdfundingTableProject $table
     * @param string                   $step
     * @param bool                     $isNew
     *
     * @throws Exception
     */
    protected function triggerEventAfterSave($table, $step, $isNew = false)
    {
        // Get properties
        $project = $table->getProperties();
        $project = ArrayHelper::toObject($project);

        // Generate context
        $context = $this->option . '.' . $step;

        // Include the content plugins for the change of state event.
        $dispatcher = JEventDispatcher::getInstance();
        JPluginHelper::importPlugin('content');

        // Trigger the onContentAfterSave event.
        $results = $dispatcher->trigger('onContentAfterSave', array($context, &$project, $isNew));

        if (in_array(false, $results, true)) {
            throw new RuntimeException(JText::_('COM_CROWDFUNDING_ERROR_DURING_PROJECT_CREATING_PROCESS'));
        }
    }

    /**
     * Prepare and sanitise the table prior to saving.
     *
     * @param  CrowdfundingTableProject $table
     *
     * @throws Exception
     *
     * @since    1.6
     */
    protected function prepareTable($table)
    {
        $userId = (int)JFactory::getUser()->get('id');

        if (!$table->get('id')) {
            // Get maximum order number
            // Set ordering to the last item if not set
            if (!$table->get('ordering')) {
                $db    = $this->getDbo();
                $query = $db->getQuery(true);

                $query
                    ->select('MAX(ordering)')
                    ->from($db->quoteName('#__crowdf_projects'));

                $db->setQuery($query, 0, 1);
                $max = $db->loadResult();

                $table->set('ordering', $max + 1);
            }

            // Set state to unpublished.
            $table->set('published', Prism\Constants::UNPUBLISHED);

            // Set user ID
            $table->set('user_id', $userId);

        } else {
            if ($userId !== (int)$table->get('user_id')) {
                throw new Exception(JText::_('COM_CROWDFUNDING_ERROR_INVALID_USER'));
            }
        }

        // If an alias does not exist, I will generate the new one using the title.
        if (!$table->get('alias')) {
            $table->set('alias', $table->get('title'));
        }
        $table->set('alias', JApplicationHelper::stringURLSafe($table->get('alias')));
    }

    /**
     * Upload and resize the image.
     *
     * @param array  $uploadedFileData
     * @param string $destinationFolder
     *
     * @throws Exception
     *
     * @return array
     */
    public function uploadImage($uploadedFileData, $destinationFolder)
    {
        $uploadedFile = ArrayHelper::getValue($uploadedFileData, 'tmp_name');
        $uploadedName = ArrayHelper::getValue($uploadedFileData, 'name');
        $errorCode    = ArrayHelper::getValue($uploadedFileData, 'error');

        // Joomla! media extension parameters
        $mediaParams = JComponentHelper::getParams('com_media');
        /** @var  $mediaParams Registry */

        // Prepare size validator.
        $KB            = pow(1024, 2);
        $fileSize      = ArrayHelper::getValue($uploadedFileData, 'size', 0, 'int');
        $uploadMaxSize = $mediaParams->get('upload_maxsize') * $KB;

        // Prepare file size validator
        $fileSizeValidator = new Prism\File\Validator\Size($fileSize, $uploadMaxSize);

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

        // Prepare image size validator.
        $params             = JComponentHelper::getParams($this->option);
        $imageSizeValidator = new Prism\File\Validator\Image\Size($uploadedFile);
        $imageSizeValidator->setMinWidth($params->get('image_width', 200));
        $imageSizeValidator->setMinHeight($params->get('image_height', 200));

        $file = new Prism\File\File($uploadedFile);
        $file
            ->addValidator($fileSizeValidator)
            ->addValidator($serverValidator)
            ->addValidator($imageValidator)
            ->addValidator($imageSizeValidator);

        // Validate the file
        if (!$file->isValid()) {
            throw new RuntimeException($file->getError());
        }

        // Upload the file in temporary folder.
        $options = new Registry(array(
            'filename_length'  => 16,
            'image_type'       => \JFile::getExt($uploadedName)
        ));

        $file = new Prism\File\Image($uploadedFile);
        $fileData = $file->toFile($destinationFolder, $options);

        if (!JFile::exists($fileData['filepath'])) {
            throw new RuntimeException('COM_CROWDFUNDING_ERROR_FILE_CANT_BE_UPLOADED');
        }

        return $fileData;
    }

    /**
     * Crop the image and generates smaller ones.
     *
     * @param string $file The temporary file of the image that will be cropped.
     * @param array  $options
     * @param Registry  $params
     *
     * @throws Exception
     *
     * @return array
     */
    public function cropImage($file, array $options, Registry $params)
    {
        // Resize image
        $image = new \Prism\File\Image($file);

        $destinationFolder = ArrayHelper::getValue($options, 'destination');

        // Generate temporary file name
        $generatedName = Prism\Utilities\StringHelper::generateRandomString(32);

        // Create main image
        $imageOptions = new Registry;
        $imageOptions->set('filename', $generatedName);

        $width  = ArrayHelper::getValue($options, 'width', 200);
        $width  = ($width < 25) ? 50 : $width;
        $imageOptions->set('width', $width);

        $height = ArrayHelper::getValue($options, 'height', 200);
        $height = ($height < 25) ? 50 : $height;
        $imageOptions->set('height', $height);

        $left   = ArrayHelper::getValue($options, 'x', 0);
        $imageOptions->set('x', $left);
        $top    = ArrayHelper::getValue($options, 'y', 0);
        $imageOptions->set('y', $top);

        // Crop the image.
        $fileData = $image->crop($destinationFolder, $imageOptions);
        $croppedImageFilepath = $fileData['filepath'];

        $names = array(
            'image'        => '',
            'image_small'  => '',
            'image_square' => ''
        );

        $image    = new \Prism\File\Image($croppedImageFilepath);

        // Resize to general size.
        $imageOptions->set('suffix', '_image');
        $width  = $params->get('image_width', 200);
        $width  = ($width < 25) ? 50 : $width;
        $imageOptions->set('width', $width);
        $height = $params->get('image_height', 200);
        $height = ($height < 25) ? 50 : $height;
        $imageOptions->set('height', $height);

        $fileData  = $image->resize($destinationFolder, $imageOptions);
        $names['image'] = $fileData['filename'];

        // Create small image
        $imageOptions->set('suffix', '_small');
        $imageOptions->set('width', $params->get('image_small_width', 100));
        $imageOptions->set('height', $params->get('image_small_height', 100));
        $fileData  = $image->resize($destinationFolder, $imageOptions);
        $names['image_small'] = $fileData['filename'];

        // Create square image
        $imageOptions->set('suffix', '_square');
        $imageOptions->set('width', $params->get('image_square_width', 50));
        $imageOptions->set('height', $params->get('image_square_height', 50));
        $fileData   = $image->resize($destinationFolder, $imageOptions);
        $names['image_square'] = $fileData['filename'];

        // Remove the temporary file.
        if (JFile::exists($file)) {
            JFile::delete($file);
        }

        // Remove the temporary cropped file.
        if (JFile::exists($croppedImageFilepath)) {
            JFile::delete($croppedImageFilepath);
        }

        return $names;
    }

    /**
     * Delete image only
     *
     * @param integer $id     Item id
     * @param integer $userId User id
     *
     * @throws Exception
     */
    public function removeImage($id, $userId)
    {
        $keys = array(
            'id'      => $id,
            'user_id' => $userId
        );

        // Load category data
        $row = $this->getTable();
        $row->load($keys);

        // Delete old image if I upload the new one
        if ($row->get('image')) {
            $params = JComponentHelper::getParams($this->option);
            /** @var  $params Registry */

            $imagesFolder = $params->get('images_directory', 'images/crowdfunding');

            // Remove an image from the filesystem
            $fileImage  = $imagesFolder .'/'. $row->get('image');
            $fileSmall  = $imagesFolder .'/'. $row->get('image_small');
            $fileSquare = $imagesFolder .'/'. $row->get('image_square');

            if (JFile::exists($fileImage)) {
                JFile::delete($fileImage);
            }

            if (JFile::exists($fileSmall)) {
                JFile::delete($fileSmall);
            }

            if (JFile::exists($fileSquare)) {
                JFile::delete($fileSquare);
            }
        }

        $row->set('image', '');
        $row->set('image_small', '');
        $row->set('image_square', '');
        $row->store();
    }

    /**
     * Store the temporary images to project record.
     * Remove the old images and move the new ones from temporary folder to the images folder.
     *
     * @param array  $images
     * @param array  $options
     * @param Registry $params
     *
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     * @throws RuntimeException
     */
    public function updateImages($images, $options, Registry $params)
    {
        $projectId    = ArrayHelper::getValue($options, 'project_id');
        $sourceFolder = ArrayHelper::getValue($options, 'source_folder');
            
        $project = new Crowdfunding\Project(JFactory::getDbo());
        $project->load($projectId);
        if (!$project->getId()) {
            throw new InvalidArgumentException(JText::_('COM_CROWDFUNDING_ERROR_INVALID_PROJECT'));
        }

        // Prepare the path to the pictures.
        $fileImage  = $sourceFolder .'/'. $images['image'];
        $fileSmall  = $sourceFolder .'/'. $images['image_small'];
        $fileSquare = $sourceFolder .'/'. $images['image_square'];

        if (is_file($fileImage) and is_file($fileSmall) and is_file($fileSquare)) {
            $destination = JPath::clean(JPATH_ROOT .'/'. $params->get('images_directory', 'images/crowdfunding'), '/');

            // Remove an image from the filesystem
            $oldFileImage  = $destination .'/'. $project->getImage();
            $oldFileSmall  = $destination .'/'. $project->getSmallImage();
            $oldFileSquare = $destination .'/'. $project->getSquareImage();

            if (JFile::exists($oldFileImage)) {
                JFile::delete($oldFileImage);
            }

            if (JFile::exists($oldFileSmall)) {
                JFile::delete($oldFileSmall);
            }

            if (JFile::exists($oldFileSquare)) {
                JFile::delete($oldFileSquare);
            }

            // Move the new files to the images folder.
            $newFileImage  = $destination .'/'. $images['image'];
            $newFileSmall  = $destination .'/'. $images['image_small'];
            $newFileSquare = $destination .'/'. $images['image_square'];

            JFile::move($fileImage, $newFileImage);
            JFile::move($fileSmall, $newFileSmall);
            JFile::move($fileSquare, $newFileSquare);

            // Store the newest pictures.
            $project->bind($images);
            $project->store();
        }
    }

    /**
     * Remove the temporary images that have been stored in the temporary folder,
     * during the process of cropping.
     *
     * @param array  $images  The names of the pictures.
     * @param string $folder Path to the temporary folder.
     */
    public function removeTemporaryImages(array $images, $folder)
    {
        foreach ($images as $filename) {
            $filepath = $folder . '/' . basename($filename);

            if (JFile::exists($filepath)) {
                JFile::delete($filepath);
            }
        }
    }
}
