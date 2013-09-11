<?php

/**
 * Kike application
 *
 * @copyright  Christian Hent
 * @author Christian Hent <hent.dev@googlemail.com>
 * @license    WTFPL
 */

require 'vendor/autoload.php';
 
use Joomla\Application\AbstractWebApplication as WebApp;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\File;
use Joomla\Image\Image;
use Joomla\Registry\Registry;
use Joomla\Log\Log;
use Joomla\Log\LogEntry;

// Define required paths
$dir = explode('/', dirname(__FILE__));
define('JPATH_SITE', implode('/', $dir));
define('JPATH_ROOT', JPATH_SITE);
define('JPATH_FRAMEWORK', JPATH_SITE);
define('JPATH_CONFIGURATION', JPATH_SITE . '/config/');
define('JPATH_IMAGE_FOLDER', JPATH_SITE . '/data/');

class KiKeApp extends WebApp {

	// @vars
	protected $inputParams;	
	protected $imageParams;
	protected $imageFolder = JPATH_IMAGE_FOLDER;
	protected $folderHandler;
	protected $width;
	protected $height;
	protected $max_width;
	protected $max_height;
	protected $images;
	protected $cacheFile;
	protected $cacheHours;
	protected $num;
	protected $image;
	protected $filter = '';
	protected $scaleMethod = array('crop', 'inside', 'outside', 'fill');
	protected $filters = array(
		'',
		'random',
		'bw',
		'sepia',
		'pixelate',
		'sketchy',
		'emboss',
		'smooth',
		'brightness',
		'negate',
		'contrast',
		'edgedetect',
		'cordovan',
		'coffee',
		'mahagony',
		'sinopia'
	);

	public function __construct()
	{
		
		// Set logger options
		$errorLoggerOptions = array(
			'text_file' => 'error.php',
			'text_file_path' => JPATH_IMAGE_FOLDER,
			'text_file_no_php' => false
		);

		// Add the errors logger
		Log::addLogger($errorLoggerOptions, Joomla\Log\Log::ERROR, 'KiKe');

		// Run the parent constructor
		parent::__construct();

		// Load the configuration object.
		$this->loadConfiguration();

		// Get the input paramaters from request
		$this->inputParams = $this->input->get('parameter', null, 'CMD');

		// Assign the maximum width and height
		$this->max_width = $this->config->get('max_width');
    	$this->max_height = $this->config->get('max_height');

	}

	protected function doExecute()
    {
		// Check if image folder exists and is writeable
		if(!Folder::exists($this->imageFolder) || !is_writable($this->imageFolder)) {

			Joomla\Log\Log::add('Cannot find or write to the images folder', Joomla\Log\Log::ERROR, 'Kike');

			throw new \RuntimeException('Cannot find or write to the images folder');
		}
		
		/*
		 * Check if there are parameters.
		 *
		 * In case of, continue with image processing.
		 *
		 * Otherwise do nothing.
		*/
		if (isset($this->inputParams)) {		
			
			$this->imageParams = explode('-', $this->inputParams);
			
		    // Set the image dimensions
		    $this->setImageDimensions();
  
		    // Set the image resize method
		    $this->setImageScale();

		    // Set the image filter
		    $this->setImageFilter();
				
			// Create and output the requested image
			$this->createImage($this->width,$this->height);

		} else {

			// Do nothing. Uncomment the next line to set a document body
			//$this->setBody(file_get_contents(JPATH_SITE.'/test.html'));

		}
    }

    public function setImageDimensions()
    {
    	
    	if (is_array($this->imageParams) && count($this->imageParams) > 1){

			/*
			 * If the given with or height parameter are valid and
			 * meets the minimum size requirement, assign the values.
			 *
			 * Else throw an exception.
			*/
				
			if (is_numeric($this->imageParams[0]) && is_numeric($this->imageParams[1])) {

				//if ($this->imageParams[0] >= 16 && $this->imageParams[1] >= 16) {
				if ($this->imageParams[0] >= 16 && $this->imageParams[1] >= 16 && $this->imageParams[0] <= $this->max_width && $this->imageParams[1] <= $this->max_height) {
						
					$this->width = $this->imageParams[0];

					$this->height = $this->imageParams[1];

				} else {

					Joomla\Log\Log::add('With or height parameter do not meet the minimum or maximum size requirement', Joomla\Log\Log::ERROR, 'Kike');

					throw new \RuntimeException('With or height parameter do not meet the minimum or maximum size requirement', 400);
				}

			} else {
					
				Joomla\Log\Log::add('With or height parameter is not a number', Joomla\Log\Log::ERROR, 'Kike');

				throw new \RuntimeException('With or height parameter is not a number', 400);
			}

		} else {
			
			Joomla\Log\Log::add('Wrong or no width and height parameter entered', Joomla\Log\Log::ERROR, 'Kike');

			throw new \RuntimeException('Wrong or no width and height parameter entered', 400);
		}
    }

    public function setImageFilter()
	{
		/*
		* If the given 2nd, 3rd or 4th parameter is a valid filter
		* assign the given filter.
		*
		* Else no filter.
		*/

		if (isset($this->imageParams[2]) && !empty($this->imageParams[2]) && in_array(strtolower($this->imageParams[2]), $this->filters) )
		{

			$this->filter = $this->imageParams[2];

		}

		if (isset($this->imageParams[3]) && !empty($this->imageParams[3]) && in_array(strtolower($this->imageParams[3]), $this->filters) )
		{

			$this->filter = $this->imageParams[3];

		}

		if (isset($this->imageParams[4]) && !empty($this->imageParams[4]) && in_array(strtolower($this->imageParams[4]), $this->filters) )
		{

			$this->filter = $this->imageParams[4];
			
		}
	}

	public function setImageScale()
	{
		
		/*
		* If the given 2nd, 3rd or 4th parameter is a valid scale method
		* assign the given scale method.
		*
		* Else assign the preconfigured scale method.
		*/

		if (isset($this->imageParams[2]) && !empty($this->imageParams[2]) && in_array(strtolower($this->imageParams[2]), $this->scaleMethod)) {
			
			$this->scaleMethod = $this->imageParams[2];

		} elseif (isset($this->imageParams[3]) && !empty($this->imageParams[3]) && in_array(strtolower($this->imageParams[3]), $this->scaleMethod)) {
			
			$this->scaleMethod = $this->imageParams[3];

		} elseif (isset($this->imageParams[4]) && !empty($this->imageParams[4]) && in_array(strtolower($this->imageParams[4]), $this->scaleMethod)) {
			
			$this->scaleMethod = $this->imageParams[4];

		}else {
			
			$this->scaleMethod = $this->config->get('scale_method');

		}	
	}

	public function loadConfiguration()
	{
		$file = JPATH_CONFIGURATION . '/config.json';

		if (!is_readable($file))
		{
			throw new \RuntimeException('Configuration file does not exist or is unreadable.');
		}

		$config = json_decode(file_get_contents($file));

		if ($config === null)
		{
			throw new \RuntimeException(sprintf('Unable to parse the configuration file %s.', $file));
		}

		$this->config->loadObject($config);

		return $this;
	}
	
	private function getRandomImage() 
	{
		// Assign the cache time value
		$this->cacheHours = $this->config->get('cache_hours');

		// Assign the cache file
		$this->cacheFile = $this->imageFolder.'cache.json';

		// Check to see if the cache file exists
		if(File::exists($this->cacheFile)) {
			
			$stat = stat($this->cacheFile);

			// Compare cache file mod time with current time
			if ($stat[9] > (time() - ((60 * 60) * $this->cacheHours))) {
				
				// Get json data from cache file
				$json = file_get_contents($this->cacheFile);
				
				// Images array from json data
				$this->images = json_decode($json, true);

			} else {
				
				// Cache file to old, create a new one.
				$this->imageCache();
			}

		} else {
			
			// No cache file found, create a new one
			$this->imageCache();
		}

		// Pick a random number
		$this->num = array_rand($this->images);

		// Pick the appropriate image file
		$this->image = $this->images[$this->num];
		
		// Return the absolute path for the specified image
		return $this->imageFolder.$this->image; 
	}

	private function imageCache()
	{
		// Open directory. Read the filenames
		$this->folderHandler = opendir($this->imageFolder);
		
		while (false !== ($file = readdir($this->folderHandler))) {
		
			// Add image files to the images[]
			$ext = File::getExt($file);

			if ($ext === 'png' || $ext === 'jpg' || $ext === 'gif') {
				 $this->images[] = $file;	 
			}
		}
		
		// Write the cache file
   		file_put_contents($this->cacheFile, json_encode($this->images));

		// Close the handler
		closedir($this->folderHandler);

		// Return array of images
		return $this->images;	
	}
	
	private function createImage($width, $height)
	{
		// Create a new image object, passing it an image path
		$this->imagePlaceholder = new Image($this->getRandomImage());

		//Apply filter manipulations
		if ($this->filter != null) {

			// Check first for random filter
			if ($this->filter == 'random') {

				$this->num = array_rand($this->filters);
					
				$this->filter = $this->filters[$this->num];
					
			}

			// Apply the choosen filter	
			switch ($this->filter) {
				case 'bw':
					$this->imagePlaceholder->filter('GRAYSCALE');
					break;

				case 'sepia':
					$this->imagePlaceholder->filter('COLORIZE', array(IMG_FILTER_COLORIZE => 'sepia'));
					break;
					
				case 'cordovan':
					$this->imagePlaceholder->filter('COLORIZE', array(IMG_FILTER_COLORIZE => 'cordovan'));
					break;
					
				case 'coffee':
					$this->imagePlaceholder->filter('COLORIZE', array(IMG_FILTER_COLORIZE => 'coffee'));
					break;
					
				case 'mahagony':
					$this->imagePlaceholder->filter('COLORIZE', array(IMG_FILTER_COLORIZE => 'mahagony'));
					break;
					
				case 'sinopia':
					$this->imagePlaceholder->filter('COLORIZE', array(IMG_FILTER_COLORIZE => 'sinopia'));
					break;
						
				case 'emboss':
					$this->imagePlaceholder->filter('EMBOSS');
					break;
						
				case 'smooth':
					$this->imagePlaceholder->filter('SMOOTH', array(IMG_FILTER_SMOOTH => -1));
					break;
					
				case 'brightness':
					$this->imagePlaceholder->filter('BRIGHTNESS', array(IMG_FILTER_BRIGHTNESS => -25));
					break;

				case 'sketchy':
					$this->imagePlaceholder->filter('SKETCHY');
					break;

				case 'negate':
					$this->imagePlaceholder->filter('NEGATE', array(IMG_FILTER_NEGATE => 25));
					break;
					
				case 'contrast':
					$this->imagePlaceholder->filter('CONTRAST', array(IMG_FILTER_CONTRAST => 25));
					break;
						
				case 'pixelate':
					$this->imagePlaceholder->filter('PIXELATE', array(IMG_FILTER_PIXELATE => 12));
					break;
						
				case 'edgedetect':
					$this->imagePlaceholder->filter('EDGEDETECT');
					break;
					
				default:	
					break;
			}
				
		}

		/*
		 * Adjust the header
		 *
		 * Resize the image
		 *
		 * Output the resized image to the browser
		*/

		$imgProperties = $this->imagePlaceholder->getImageFileProperties($this->imagePlaceholder->getPath());

		header('Last-Modified: '.gmdate('D, d M Y H:i:s \G\M\T', time()));
		header('Content-Type: ' . $imgProperties->mime);
			
		switch ($this->scaleMethod) {
			case 'outside':
				$this->imagePlaceholder->resize($width, $height, true, Image::SCALE_OUTSIDE)->toFile(null)->destroy();
				break;

			case 'inside':
				$this->imagePlaceholder->resize($width, $height, true, Image::SCALE_INSIDE)->toFile(null)->destroy();
				break;
						
			case 'fill':
				$this->imagePlaceholder->resize($width, $height, true, Image::SCALE_FILL)->toFile(null)->destroy();
				break;
						
			case 'crop':
				$this->imagePlaceholder->crop($width, $height, true)->toFile(null)->destroy();
				break;
				
			default:		
				break;
		}
	}
}

try
{
	//Execute the web application
	$application = new KiKeApp;
	$application->execute();

} catch(Exception $e)
{	
	// Set server response code.
	header('Status: 500', true, 500);

	// Exception caught, echo the message and exit.
	echo json_encode(array('message' => $e->getMessage(), 'code' => $e->getCode()));
}