<?php

namespace Hujing\Ali;

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
	* 上传文件到对象存储
	*/
	private function uploadFile($filePath, $fileExtension){
		// 文件名称
		$object = UUID::generate()->string . '.' . $fileExtension;
		Log::info($object);
		try{
		    $ossClient = new OssClient($this->config->get('ali.access_key_id'), $this->config->get('ali.access_key_secret'), $this->config->get('ali.end_point'));

		    $res = $ossClient->uploadFile($this->config->get('ali.bucket'), $object, $filePath);
		    Log::info($res);
		} catch(OssException $e) {
			Log::error($e->getMessage());
		    return [ErrorCode::$CallException, $e->getMessage()];
		}

	    return [ErrorCode::$OK, $res['oss-request-url']];		
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
