# WechatSignatureTools
微信JS-SDK 1.0.0权限签名算法工具类，采用PHP语言编写。
@author Snake<snakejordan@gmail.com

@copyright 20150119
@version 1.0
@internal Gotodo.cc
@example
$wechatSignatureTools = new WechatSignatureTools($your_app_id, $your_app_secret);
$config = $wechatSignatureTools->getConfig();

说明：<br>
1. 由于appid和appsecret存在接口请求次数限制，所以本工具会默认进行文件缓存，缓存文件默认保存在本类相同的文件夹内，命名为md5后的appid+appsecret字符串；
2. 返回值为Array格式，参考：array('debug'=>false, 'appId'=>'w32k2iod893jllaf23', 'timestamp'=>100000, 'nonceStr'=>'1a2B3c4D5e6F7g8H', 'signature'=>'slk323cjofoaew32jsafw3fewqfa24fewslfe32a');

exception:
5001: appId or appSecret is not set
5002: access_token gets a fail
5003: jsapi_ticket gets a fail
5004: cache is not write
