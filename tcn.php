<?php
/**
 * @author ufec blog@ufec.cn
 * @copyright Ufec
 * @link https://github.com/ufec/shortUrlApi
 * @version 1.0
 */
header("Content-Type:text/json; charset=utf-8");
class Url
{
    private $cookie = '';
    private $source = "209678993";
    private $userId = "2735327001";//微博安全中心
    private $headers = [
        "Host: api.weibo.com",
        "Referer: https://api.weibo.com/chat/",
        "Connection: keep-alive",
        "Pragma: no-cache",
        "Cache-Control: no-cache",
        "Accept: application/json, text/plain, */*",
        "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/81.0.4044.122 Safari/537.36",
        "Sec-Fetch-Site: same-origin",
        "Sec-Fetch-Mode: cors",
        "Sec-Fetch-Dest: empty",
        "Accept-Encoding: gzip, deflate, br",
        "Accept-Language: zh-CN,zh;q=0.9,en;q=0.8,en-GB;q=0.7,en-US;q=0.6",
    ];

    public function __construct()
    {
        if (!$this->cookie) {
            $readCookieFile = @file_get_contents('weiboCookie.txt');
            if (!$readCookieFile) {
                die("未找到weiboCookie文件或weiboCookie文件为空");
            }
            $this->cookie = $readCookieFile;
        }
        echo $this->sendMsg("https://www.ufec.cn");
    }

    // 获取消息列表
    private function getContactsJson()
    {   
        $api = "https://api.weibo.com/webim/2/direct_messages/contacts.json?special_source=3&add_virtual_user=3,4&is_include_group=0&need_back=0,0&count=50&source=209678993&t=".time()*1000;
        $header = $this->headers;
        $header[] = "Cookie:" . $this->cookie;
        $params = [
            'header' => $header,
        ];
        $json = $this->curl($api, 'GET', $params);
        $data = json_decode($json, 1);
        if (!array_key_exists('contacts', $data)) {
            return $this->returnMsg($data['error_code'], $data['error'], "empty");
        }
        $res = array();
        for ($i=0; $i < count($data['contacts']); $i++) {
            if (array_key_exists('idstr', $data['contacts'][$i]['user'])) {
                if ($data['contacts'][$i]['user']['idstr'] == $this->userId) {
                    $res = $data['contacts'][$i];
                }
            }
        }
        if (!$res) {
            return $this->returnMsg(000, "你未关注此人或未给他发消息", "empty");
        }
        if(!array_key_exists( "clientid", $res['message']['ext_text']) || !$res['message']['ext_text']['clientid']){
            return $this->returnMsg(000, "与此人没有消息往来，无法获取会话id", "empty");
        }
        $clientid = $res['message']['ext_text']['clientid'];
        file_put_contents("contacts.txt", $clientid);
        return $clientid;
    }

    /**
     * @param string $text 要生成的链接
     * @access private
     * @return string
     */
    private function sendMsg($text)
    {
        $api = "https://api.weibo.com/webim/2/direct_messages/new.json";
        if(!file_exists('contacts.txt') || !file_get_contents('contacts.txt')){
            $clientid = $this->getContactsJson();
        }else {
            $clientid = file_get_contents('contacts.txt');
        }
        $header = $this->headers;
        array_push($header, "Cookie:" . $this->cookie, "Content-Type: application/x-www-form-urlencoded");
        $postData = [
            'text' => $text,
            'uid'  => $this->userId,
            'extensions' => '{"clientid":"'.$clientid.'"}',
            'is_encoded' => 0,
            'decodetime' => 1,
            'source' => $this->source,
        ];
        $str = http_build_query($postData);
        $params = [
            'header' => $header,
            'postData' => $str,
        ];
        $json = $this->curl($api, 'POST', $params);
        $data = json_decode($json, 1);
        //系统内置错误
        if(array_key_exists('error_code' , $data) && array_key_exists('error' , $data)){
            return $this->returnMsg($data['error_code'], $data['error'], "empty");
        }
        //其他未知错误
        if (!array_key_exists('id', $data) || !array_key_exists('recipient_id', $data) || $data['recipient_id'] != $this->userId) {
            return $this->returnMsg(001, "发送失败", "empty");
        }
        $id = $data['id'];
        $returnData = [
            'short_url' => $data['text'], 
            ['long_url' => $text]
        ];
        if(!$this->recallMsg($id)){
            return $this->returnMsg(002, "生成成功，消息撤回失败", $returnData); 
        }
        return $this->returnMsg(0, "生成成功，消息撤回成功", $returnData);
    }

    /**
     * @param int|string $id 消息id
     * @access private
     * @return bool
     */
    private function recallMsg($id)
    {
        $api = "https://api.weibo.com/webim/2/direct_messages/recall.json";
        $header = $this->headers;
        array_push($header, "Cookie:" . $this->cookie, "Content-Type: application/x-www-form-urlencoded");
        $postData = [
            'id' => $id,
            'source' => $this->source,
        ];
        $params = [
            'header' => $header,
            'postData' => http_build_query($postData),
        ];
        $json = $this->curl($api, 'POST', $params);
        $data = json_decode($json, 1);
        if (!array_key_exists('id', $data) || !array_key_exists('text', $data)) {
            return false;
        }
        return true;
    }

    private function curl($url, $method='GET', $params=array(), $getinfo=false)
    {
        $header = array();
        if(isset($params["header"])){
            $header = array_merge($header,$params["header"]);
        }
        $user_agent = empty($params["ua"]) ? 0 : $params["ua"] ;
        $ch = curl_init();                                                     
        curl_setopt($ch, CURLOPT_URL, $url);                                   
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        if($params["ref"]){
            curl_setopt($ch, CURLOPT_REFERER, $params["ref"]);
        }
        if (array_key_exists('responseHeader', $params) && $params['responseHeader']) {
            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_NOBODY, true);
        }else {
            curl_setopt($ch, CURLOPT_NOBODY, false);
        }
        curl_setopt($ch, CURLOPT_USERAGENT,$user_agent);                                                  
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);                        
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);                       
        curl_setopt($ch, CURLOPT_TIMEOUT, 3600);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);                       
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);                       
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);                        
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);                           
        curl_setopt($ch, CURLOPT_ENCODING, '');                        
        if($method == 'POST'){
            curl_setopt($ch, CURLOPT_POST, true);              
            curl_setopt($ch, CURLOPT_POSTFIELDS, $params["postData"]);               
        }
        $res = curl_exec($ch);
        if ($getinfo) {
            $data = curl_getinfo($ch,CURLINFO_EFFECTIVE_URL);
        }else {
            $data = $res;
        }
        curl_close($ch);                                                       
        return $data;
    }

    private function returnMsg($code, $msg, $data)
    {
        return json_encode(['code'=>$code, 'msg'=>$msg, 'data'=>$data], JSON_UNESCAPED_UNICODE);
    }
}

new Url();