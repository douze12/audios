<?php
/**
 * ownCloud - Pinit
 *
 * @author Sebastian Doell
 * @copyright 2014 sebastian doell sebastian@libasys.de
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */
 
namespace OCA\Audios\Controller;

use \OCP\AppFramework\Controller;
use \OCP\AppFramework\Http\JSONResponse;
use \OCP\AppFramework\Http\TemplateResponse;
use \OCP\IRequest;

class PhotoController extends Controller {
	
	private $l10n;
	private $helperController;
	public function __construct($appName, IRequest $request, $l10n) {
		parent::__construct($appName, $request);
		
		$this->l10n = $l10n;
	
		
	}
	
	/**
	 * @NoAdminRequired
	 */
	
	public function cropPhoto(){
		
		$id = $this -> params('id');	
		$tmpkey = $this -> params('tmpkey');	
		
		$params=array(
		 'tmpkey' => $tmpkey,
		 'id' => $id,
		);	
		$csp = new \OCP\AppFramework\Http\ContentSecurityPolicy();
		$csp->addAllowedImageDomain('data:');
		
		$response = new TemplateResponse('audios', 'part.cropphoto', $params, '');
	 	$response->setContentSecurityPolicy($csp);
	  
	   return $response;
	}
	
	/**
	 * @NoAdminRequired
	 */
	 
	public function clearPhotoCache(){
		//$id = $this -> params('id');
		$tmpkey = $this -> params('tmpkey');		
		$data = \OC::$server->getCache()->get($tmpkey);
		//\OCP\Util::writeLog('pinit','cleared.'.$tmpkey,\OCP\Util::DEBUG);		
		if($data) {
			
			\OC::$server->getCache()->remove($tmpkey);
		}
	}
	
	/**
	 * @NoAdminRequired
	 */
	public function saveCropPhoto(){
		$id = $this -> params('id');
		$tmpkey = $this -> params('tmpkey');			
		$x = $this -> params('x1', 0);	
		$y = $this -> params('y1', 0);	
		$w = $this -> params('w', -1);	
		$h = $this -> params('h', -1);	
		
		$image = null;
		
		//\OCP\Util::writeLog('audios','MIMI'.$tmpkey,\OCP\Util::DEBUG);	
		$data = \OC::$server->getCache()->get($tmpkey);
		if($data) {
			
			$image = new \OCP\Image();
			if($image->loadFromdata($data)) {
				$w = ($w !== -1 ? $w : $image->width());
				$h = ($h !== -1 ? $h : $image->height());
				
				if($image->crop($x, $y, $w, $h)) {
					if(($image->width() <= 300 && $image->height() <= 300) || $image->resize(300)) {
					
					$imgString = $image->__toString();
						
						$resultData=array(
							'id' => $id,
							'width' => $image->width(),
							'height' => $image->height(),
							'dataimg' =>$imgString,
							'mimetype' =>$image->mimeType()
						);
						
						 \OC::$server->getCache()->remove($tmpkey);
						 \OC::$server->getCache()->set($tmpkey, $image->data(), 600);
						 $response = new JSONResponse();
						  $response -> setData($resultData);
						  
						return $response;
					}
				}
			}
		}
		
		
	}
	
	/**
	 * @NoAdminRequired
	 */
	public function getImageFromCloud(){
		$id = $this -> params('id');	
		$path = $this -> params('path');	
		
		$localpath = \OC\Files\Filesystem::getLocalFile($path);
		$tmpkey = 'audios-photo-' . $id;
		$size = getimagesize($localpath, $info);
		$exif = @exif_read_data($localpath);
		$image = new \OCP\Image();
		$image -> loadFromFile($localpath);
		if ($image -> width() > 300 || $image -> height() > 300) {
			$image -> resize(300);
		}
		$image -> fixOrientation();
		
		$imgString = $image -> __toString();
		$imgMimeType = $image -> mimeType();
		if (\OC::$server->getCache()->set($tmpkey, $image -> data(), 600)) {
	 	
		
			
	    $resultData = array(
		     'id' =>$id,
		     'tmp' => $tmpkey,
		     'imgdata' => $imgString,
		     'mimetype' => $imgMimeType,
	      );
		  
		  $response = new JSONResponse();
		  $response -> setData($resultData);
		  
		return $response;
	
} 
		
	}
/**
	 * @NoAdminRequired
	 */
	public function uploadPhoto(){
		//$type = $this->request->getHeader('Content-Type');
		$id = $this -> params('id');
		$file = $this->request->getUploadedFile('imagefile');
		
		$error = $file['error'];
		if($error !== UPLOAD_ERR_OK) {
			$errors = array(
				0=>$this->l10n->t("There is no error, the file uploaded with success"),
				1=>$this->l10n->t("The uploaded file exceeds the upload_max_filesize directive in php.ini").ini_get('upload_max_filesize'),
				2=>$this->l10n->t("The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form"),
				3=>$this->l10n->t("The uploaded file was only partially uploaded"),
				4=>$this->l10n->t("No file was uploaded"),
				6=>$this->l10n->t("Missing a temporary folder")
			);
			\OCP\Util::writeLog('audios','Uploaderror: '.$errors[$error],\OCP\Util::DEBUG);	
		}

		if(file_exists($file['tmp_name'])) {
			$tmpkey = 'audios-photo-'.md5(basename($file['tmp_name']));
			$size = getimagesize($file['tmp_name'], $info);
		    $exif = @exif_read_data($file['tmp_name']);
			$image = new \OCP\Image();
			if($image->loadFromFile($file['tmp_name'])) {
				
				if($image->width() > 300 || $image->height() > 300) {
					$image->resize(300); // Prettier resizing than with browser and saves bandwidth.
				}
				if(!$image->fixOrientation()) { // No fatal error so we don't bail out.
					\OCP\Util::writeLog('audios','Couldn\'t save correct image orientation: '.$tmpkey,\OCP\Util::DEBUG);
				}
				
					if(\OC::$server->getCache()->set($tmpkey, $image->data(), 600)) {
					$imgString=$image->__toString();
					
					

                      $resultData=array(
							'mime'=>$file['type'],
							'size'=>$file['size'],
							'name'=>$file['name'],
							'id'=>$id,
							'tmp'=>$tmpkey,
							'imgdata' =>$imgString,
					);
					
					 $response = new JSONResponse();
					  $response -> setData($resultData);
					  
					return $response;
					
				}
				
				
			}
			
			
			
		}
		
		
	}

	
}