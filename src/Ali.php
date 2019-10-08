<?php

namespace Hujing\Ali;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Session\SessionManager;
use Illuminate\Config\Repository;
use UUID;

use OSS\OssClient;
use OSS\Core\OssException;
use AlibabaCloud\Client\AlibabaCloud;
use AlibabaCloud\Client\Exception\ClientException;
use AlibabaCloud\Client\Exception\ServerException;

use Hujing\Ali\Classes\ErrorCode;
use Hujing\Ali\Lib\CURL;

use AliyunVodUploader;
use UploadVideoRequest;

require_once __DIR__ . "/Lib/voduploadsdk/Autoloader.php";

class Ali
{
	private $config;
	private $session;

	/**
	* @param config
	* @param session
	* @return void
	*/
	public function __construct(Repository $config, SessionManager $session){
		$this->config = $config;
		$this->session = $session;
	}

	/**
	* 上传图片到对象存储OSS
	*/
	public function uploadImage($filePath, $fileExtension){
		return $this->uploadFile($filePath, $fileExtension);
	}

	/**
	* 上传语音到对象存储
	*/
	public function uploadAudio($filePath, $fileExtension){
		return $this->uploadFile($filePath, $fileExtension);
	}

	/**
	* 上传语音到点播服务
	*/
	public function uploadVodAudio($path, $extension){
		$res = null;

		rename($path, $path . '.' . $extension);
	    try {
	        $uploader = new AliyunVodUploader($this->config->get('ali.access_key_id'), $this->config->get('ali.access_key_secret'));
	        $uploadVideoRequest = new UploadVideoRequest($path . '.' . $extension, 'Aliyun Audio');
	        // $userData = array(
	        //     "MessageCallback"=>array("CallbackURL"=>"https://demo.sample.com/ProcessMessageCallback"),
	        //     "Extend"=>array("localId"=>"xxx", "test"=>"www")
	        // );
	        // $uploadVideoRequest->setUserData(json_encode($userData));
	        $res = $uploader->uploadLocalVideo($uploadVideoRequest);
	    } catch (Exception $e) {
	    	Log::info($e->getMessage());
		    return [ErrorCode::$CallException, $e->getMessage()];
	    }			

	    return [ErrorCode::$OK, $res];
	}

	/**
	* 上传文件到对象存储
	*/
	private function uploadFile($filePath, $fileExtension){
		$res = null;

		// 文件名称
		$object = UUID::generate()->string . '.' . $fileExtension;
		try{
		    $ossClient = new OssClient($this->config->get('ali.access_key_id'), $this->config->get('ali.access_key_secret'), $this->config->get('ali.end_point'));

		    $res = $ossClient->uploadFile($this->config->get('ali.bucket'), $object, $filePath);
		    Log::info($res);
		} catch(OssException $e) {
			Log::error($e->getMessage());
		    return [ErrorCode::$CallException, $e->getMessage()];
		}

	    return [ErrorCode::$OK, 'https://' . $this->config->get('ali.oss_domain') . '/' . $object];		
	}


	/**
	* 获取上传视频授权
	*/
	public function authCreateUploadVideo($title, $coverUrl){
		AlibabaCloud::accessKeyClient(config('ali.access_key_id'), config('ali.access_key_secret'))
		            ->regionId('cn-shanghai')
		            ->asDefaultClient();

		$fileName = UUID::generate()->string . '.mp4';
		try {
		    $result = AlibabaCloud::rpc()
		                          ->product('vod')
		                          ->version('2017-03-21')
		                          ->action('CreateUploadVideo')
		                          ->method('POST')
		                          ->host('vod.cn-shanghai.aliyuncs.com')
		                          ->options([
		                                        'query' => [
		                                          'RegionId' => "cn-shanghai",
		                                          'Title' => $title,
		                                          'FileName' => $fileName,
		                                        ],
		                                    ])
		                          ->request();

		    $result->FileName = $fileName;
		    return [ErrorCode::$OK, $result->toArray()];
		} 
		catch (Exception $e) {
		    return [ErrorCode::$CreateObjectFailed, $e->getMessage()];
		}

		return [ErrorCode::$OK];
	}

	/**
	* 发送短信
	* @param $mobile，手机号
	* @param $content, 模板参数内容
	* @param $signName, 签名名称
	* @param $templateId, 短信模板id
	*/
	public function sendSms($mobile, $content, $signName, $templateId){
		AlibabaCloud::accessKeyClient(config('ali.access_key_id'), config('ali.access_key_secret'))
		                        ->regionId('cn-shanghai')
		                        ->asDefaultClient();

		try {
		    $result = AlibabaCloud::rpc()
		                          ->product('Dysmsapi')
		                          // ->scheme('https') // https | http
		                          ->version('2017-05-25')
		                          ->action('SendSms')
		                          ->method('POST')
		                          ->host('dysmsapi.aliyuncs.com')
		                          ->options([
		                                        'query' => [
		                                          	'RegionId' => "default",
                                          			'PhoneNumbers' => $mobile,
                                          			'SignName' => $signName,
                                          			'TemplateCode' => $templateId,
                                          			'TemplateParam' => $content
		                                        ],
		                                    ])
		                          ->request();
		    return [ErrorCode::$OK, $result->toArray()];
		} catch (Exception $e) {
		    return [ErrorCode::$CreateObjectFailed, $e->getMessage()];
		}

		return [ErrorCode::$OK];
	}
}
