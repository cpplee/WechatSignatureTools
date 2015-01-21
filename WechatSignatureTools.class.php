<?php
/**
 * 
 * 微信JS-SDK 1.0.0权限签名算法工具类
 * @author Snake<snakejordan@gmail.com>
 * @copyright 20150119
 * @version 1.0
 * @internal Gotodo.cc
 * @example
 * <br>
 * $wechatSignatureTools = new WechatSignatureTools($your_app_id, $your_app_secret);<br>
 * $config = $wechatSignatureTools->getConfig();<br>
 * <br>
 * 说明：<br>
 * 1. 由于appid和appsecret存在接口请求次数限制，所以本工具会默认进行文件缓存，缓存文件默认保存在本类相同的文件夹内，命名为md5后的appid+appsecret字符串；<br>
 * 2. 返回值为Array格式，参考：array('debug'=>false, 'appId'=>'w32k2iod893jllaf23', 'timestamp'=>100000, 'nonceStr'=>'1a2B3c4D5e6F7g8H', 'signature'=>'slk323cjofoaew32jsafw3fewqfa24fewslfe32a');<br>
 * <br>
 * exception:<br> 
 * 5001: appId or appSecret is not set<br>
 * 5002: access_token gets a fail<br>
 * 5003: jsapi_ticket gets a fail<br>
 * 5004: cache is not write
 *
 */
class WechatSignatureTools {
	
	/**
	 * 开启调试模式，调用的所有api的返回值会在客户端alert出来，若要查看传入的参数，可以在pc端打开，参数信息会通过log打出，仅在pc端时才会打印。
	 * @var boolean
	 */
	private $debug;
	
	/**
	 * 公众号的唯一标识
	 * @var string
	 */
	private $appId;
	
	/**
	 * 应用密钥
	 * @var string
	 */
	private $appSecret;
	
	/**
	 * 请求接口页面URL
	 * @var string
	 */
	private $url;
	
	/**
	 * 公众号全局唯一票据
	 * @var string
	 */
	private $accessToken;
	
	/**
	 * 微信JS接口临时票据
	 * @var string
	 */
	private $jsapiTicket;
	
	/**
	 * 生成签名的时间戳
	 * @var string
	 */
	private $timestamp;
	
	/**
	 * 生成签名的随机串
	 * @var string
	 */
	private $nonceStr;
	
	/**
	 * 签名字符串
	 * @var string
	 */
	private $signature;
	
	/**
	 * 是否缓存
	 * @var boolean
	 */
	private $isCache;
	
	/**
	 * 缓存文件路径
	 * @var string
	 */
	private $cachePath;
	
	/**
	 * 缓存文件名
	 * @var string
	 */
	private $cacheFileName;
	
	/**
	 * 构造方法可以传入公众号唯一标识（appid）及应用密钥（appsecret）
	 * @param string $appId 公众号的唯一标识
	 * @param string $appSecret 应用密钥
	 * @param string $url 请求网页的URL（必须带入http或https部分，不包含#及其后面部分），默认为当前请求的URL地址。
	 * @param boolean $debug 是否打开调试模式，默认FALSE。
	 * @param boolean $isCache 是否打开缓存模式，默认TRUE。
	 * @param string $cachePath 缓存文件存放目录，需要此目录存在且可写，默认为类文件同目录。
	 * @throws Exception message: appId or appSecret is not set; code: 5001.
	 */
	public function __construct($appId, $appSecret, $url = '', $debug = FALSE, $isCache = TRUE, $cachePath = '') {
		// 为了时间准确设置时区为PRC
		date_default_timezone_set('PRC');
		// 检测必要传入参数是否存在
		if(!isset($appId) || !isset($appSecret)) {
			throw new Exception('appId or appSecret is not set', 5001);
		}
		$this->appId 			= $appId;
		$this->appSecret 		= $appSecret;
		$this->url				= empty($url) ? (@$_SERVER['HTTPS'] != "on" ? 'http://' : 'https://').$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'] : $url;
		$this->debug 			= $debug;
		$this->isCache 			= $isCache;
		$this->timestamp 		= time();
		$this->cachePath	 	= empty($cachePath) ? dirname(__FILE__) . DIRECTORY_SEPARATOR : $cachePath;
		$this->cacheFileName 	= md5($this->appId.$this->appSecret);
		// 计算签名的随机串
		$this->makeNonceStr();
	}

	/**
	 * 计算公众号全局唯一票据<br>
	 * 参考：微信公众平台开发者文档<br>
	 * 网址：<a href="http://mp.weixin.qq.com/wiki/15/54ce45d8d30b6bf6758f68d2e95bc627.html">获取access token</a>
	 * @throws Exception message: access_token gets a fail; code: 5002.
	 */
	private function makeAccessToken() {
		// 获取access_token请求地址
		$accessTokenURL = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$this->appId}&secret={$this->appSecret}";
		// 调用curl获取返回数据
		$accessTokenTemp = $this->curl($accessTokenURL);
		// 返回数据为FALSE证明获取失败抛出异常
		if(FALSE === $accessTokenTemp) {
			throw new Exception('access_token gets a fail', 5002);
		}
		// 对返回数据进行JSON解码，输出为array格式。
		$accessTokenJSON = json_decode($accessTokenTemp, TRUE);
		// 判断返回数据中是否存在errcode字段，存在表示请求返回了错误，抛出异常。
		if(isset($accessTokenJSON['errcode'])) {
			throw new Exception('access_token gets a fail', 5002);
		} else {
			$this->accessToken = $accessTokenJSON['access_token'];
		}
	}
	
	/**
	 * 计算微信JS接口的临时票据<br>
	 * 参考：微信JS-SDK说明文档 - 附录1-JS-SDK使用权限签名算法<br>
	 * 网址：<a href="http://mp.weixin.qq.com/wiki/7/aaa137b55fb2e0456bf8dd9148dd613f.html#.E9.99.84.E5.BD.951-JS-SDK.E4.BD.BF.E7.94.A8.E6.9D.83.E9.99.90.E7.AD.BE.E5.90.8D.E7.AE.97.E6.B3.95">附录1-JS-SDK使用权限签名算法</a>
	 * @throws Exception message: jsapi_ticket gets a fail; code: 5003.
	 */
	private function makeJsapiTicket() {
		// 获取jsapi_ticket请求地址
		$jsapiTicketURL = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token={$this->accessToken}&type=jsapi";
		// 调用curl获取返回数据
		$jsapiTicketTemp = $this->curl($jsapiTicketURL);
		// 返回数据为FALSE证明获取失败抛出异常
		if(FALSE === $jsapiTicketTemp) {
			throw new Exception('jsapi_ticket gets a fail', 5003);
		}
		// 对返回数据进行JSON解码，输出为array格式。
		$jsapiTicketJSON = json_decode($jsapiTicketTemp, TRUE);
		// 判断返回数据中是否存在errcode字段且errcode字段不为0，表示请求返回了错误，抛出异常。
		if(isset($jsapiTicketJSON['errcode']) && intval($jsapiTicketJSON['errcode']) != 0) {
			throw new Exception('jsapi_ticket gets a fail', 5003);
		} else {
			$this->jsapiTicket = $jsapiTicketJSON['ticket'];
		}
	}
	
	/**
	 * 生成16位签名用随机串
	 */
	private function makeNonceStr() {
		// 数字及大小字符种子
		$salt = '1234567890abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$nonceArr = array();
		// 循环16次生成随机取出的数字或字符
		for($i = 0; $i < 16; $i++) {
			$nonceArr[$i] = $salt[mt_rand(0, strlen($salt)-1)];
		}
		// 对随机取出的数字数组进行拼接转为字符串
		$nonceStr = implode($nonceArr, '');
		$this->nonceStr = $nonceStr;
	}
	
	/**
	 * 写入缓存文件
	 * @throws Exception message: cache is not write; code: 5004.
	 */
	private function writeCache() {
		// 判断类库所在目录是否存在及是否可写
		if(file_exists($this->cachePath) && is_writable($this->cachePath)) {
			// 封装缓存数据，包括access_token、ticket和生成时间。
			$data = array();
			$data['access_token'] 	= $this->accessToken;
			$data['ticket'] 		= $this->jsapiTicket;
			$data['time'] 			= $this->timestamp;
			// 对缓存对象进行序列号及base64编码后存入缓存文件，文件名为appid加appsecret拼接后字符串的md5值。
			if(FALSE !== @file_put_contents($this->cachePath.$this->cacheFileName, base64_encode(serialize($data)))) {
				return TRUE;
			} else {
				return FALSE;
			}
		} else {
			// 缓存目录不存在或不可写时抛出异常
			throw new Exception('cache is not write', 5004);
		}
	}
	
	/**
	 * 读取缓存文件
	 */
	private function readCache() {
		$data = array();
		// 判断类库所在目录下的缓存文件是否存在及是否可读
		if(file_exists($this->cachePath.$this->cacheFileName) && is_readable($this->cachePath.$this->cacheFileName)) {
			// 读取缓存文件后进行base64解码及反序列化
			if(FALSE !== ($temp = @file_get_contents($this->cachePath.$this->cacheFileName))) {
				$data = unserialize(base64_decode($temp));
			}
		}
		// 返回读取并处理后的缓存数据
		return $data;
	}
	
	/**
	 * 检测缓存是否过期，超期时间为7200秒。
	 * @param array $data
	 */
	private function checkCache($data) {
		// 判断是否设置传入$data及$data['time']是否超过当前时间
		if(!isset($data['time']) || ($data['time']+7200)<$this->timestamp) {
			return FALSE;
		} else {
			return TRUE;
		}
	}

	/**
	 * 计算权限签名<br>
	 * 参考：微信JS-SDK说明文档 - 附录1-JS-SDK使用权限签名算法<br>
	 * 网址：<a href="http://mp.weixin.qq.com/wiki/7/aaa137b55fb2e0456bf8dd9148dd613f.html#.E9.99.84.E5.BD.951-JS-SDK.E4.BD.BF.E7.94.A8.E6.9D.83.E9.99.90.E7.AD.BE.E5.90.8D.E7.AE.97.E6.B3.95">附录1-JS-SDK使用权限签名算法</a>
	 */
	private function makeSignature() {
		// 封装验证数据
		$signatureArr = array();
		$signatureArr['url'] 			= $this->url;
		$signatureArr['jsapi_ticket'] 	= $this->jsapiTicket;
		$signatureArr['timestamp'] 		= $this->timestamp;
		$signatureArr['noncestr'] 		= $this->nonceStr;
		// 对所有待签名参数按照字段名的ASCII码从小到大排序（字典序）
		ksort($signatureArr, SORT_STRING);
		// 使用URL键值对的格式（即key1=value1&key2=value2…）拼接成字符串
		$signature = urldecode(http_build_query($signatureArr));
		// 对$signature进行sha1签名
		$this->signature = sha1($signature);
	}
	
	/**
	 * 封装curl函数
	 * @param string $url
	 */
	private function curl($url) {
		// 初始化curl函数
		$curl = curl_init();
		// 注：由于微信API接口采用https协议，所以需要配置curl的https相关参数。
		// 传入参数，不验证https证书是否有效及是否通过CA颁发。
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
		// 传入参数，不验证证书中是否设置了域名及是否主机名与证书相符。
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
		// 传入参数，获得返回值不直接输入。
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
		// 传入参数，curl请求超时时间为500秒。
		curl_setopt($curl, CURLOPT_TIMEOUT, 500);
		// 传入参数，curl请求地址为$url。
		curl_setopt($curl, CURLOPT_URL, $url);
		// 执行curl并获得返回值
		$result = curl_exec($curl);
		// 关闭curl连接
		curl_close($curl);
		// 返回curl得到的返回值
		return $result;
	}
	
	/**
	 * 最终获得微信JS-SDK的配置文件
	 * @return array
	 */
	public function getConfig() {
		// 封装返回数据
		$config = array();
		$config['debug'] 		= $this->debug;
		$config['appId'] 		= $this->appId;
		$config['timestamp'] 	= $this->timestamp;
		$config['nonceStr']	 	= $this->nonceStr;
		// 如何打开缓存开关则进行缓存相关操作
		if($this->isCache) {
			// 读取缓存
			$data = $this->readCache();
			// 验证缓存
			if($this->checkCache($data)) {
				// 对accessToken和jsapiTicket进行赋值
				$this->accessToken 	= $data['access_token'];
				$this->jsapiTicket 	= $data['ticket'];
				$this->timestamp 	= $data['time'];
			} else {
				// 计算公众号全局唯一票据
				$this->makeAccessToken();
				// 计算微信JS接口的临时票据
				$this->makeJsapiTicket();
				// 写入缓存
				$this->writeCache();
			}
		} else {
			// 计算公众号全局唯一票据
			$this->makeAccessToken();
			// 计算微信JS接口的临时票据
			$this->makeJsapiTicket();
		}
		// 计算权限签名
		$this->makeSignature();
		$config['signature'] 	= $this->signature;
		// 返回配置文件
		return $config;
	}

}
?>