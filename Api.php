<?php
header("Content-Type:text/json;charset=UTF-8");
class Chrise
{
    private $bd_token = "";

    public function __construct($url, $type="t")
    {
        switch ($type) {
            case 't':
                echo $this->t($url);
                break;
            case 'url':
                echo $this->url($url);
                break;
            case 'dwz':
                echo $this->dwz($url);
                break;
        }
    }

    private function t($url)
    {
        $content = file_get_contents("http://service.weibo.com/share/share.php?url=".urlencode($url)."&title=%E6%B5%8B%E8%AF%95&pic=https%3A%2F%2Fbkimg.cdn.bcebos.com%2Fpic%2Fb21bb051f819861824b822dc48ed2e738ad4e6ef%3Fx-bce-process%3Dimage%2Fresize%2Cm_lfit%2Cw_268%2Climit_1&appkey=936491597#_loginLayer_1584789130659");
        preg_match_all("/scope.short_url = \" (.*?) \";/",$content, $res, PREG_SET_ORDER);
        try {
            $shortUrl = $res[0][1];
            return $this->returnMsg(101, "生成成功！", ['short_url' => $shortUrl, 'long_url' => $url]);
        } catch (\Throwable $th) {
            return $this->returnMsg(102, "生成失败！", "error");
        }
    }

    private function url($url)
    {
        $content = file_get_contents("https://vip.video.qq.com/fcgi-bin/comm_cgi?name=short_url&need_short_url=1&url=https://c.pc.qq.com/middleb.html?pfurl=$url");
        $content = mb_substr($content,stripos($content, '{'), strripos($content, '}')-stripos($content, '{')+1, 'UTF-8');
        $data = json_decode($content, 1);
        if ($data['msg'] == 'ok' && $data['short_url']) {
            return $this->returnMsg(101, "生成成功！", ['short_url' => $data['short_url'], 'long_url' => $url]);
        }
        return $this->returnMsg(102, "生成失败！", "error");
    }

    private function dwz($url)
    {
        $api = "https://dwz.cn/admin/v2/create";
        if (!$this->bd_token) {
            return $this->returnMsg(102, "生成失败！", "未配置token");
        }
        $params = [
            'header' => [
                'Content-Type: application/json',
                'Token: '. $this->bd_token,
            ],
            'postData' => [
                'Url'=> $url
            ],
        ];
        $res = $this->curl($api, 'POST', $params);
        $data = json_decode($res, 1);
        if (!$data['Code']) {
            return $this->returnMsg(101, "生成成功！", ['short_url' => $data['ShortUrl'], 'long_url' => $url]);
        }
        return $this->returnMsg(102, "生成失败！", "error");
    }

    private function returnMsg($code, $msg, $data)
    {
        return json_encode(['code'=>$code, 'msg'=>$msg, 'data'=>$data], JSON_UNESCAPED_UNICODE);
    }

    private function curl($url, $method='GET', $params=array(), $getinfo=false)
	{
		$ip = empty($params["ip"]) ? $this->rand_ip() : $params["ip"]; 
		$header = array('X-FORWARDED-FOR:'.$ip,'CLIENT-IP:'.$ip);
		if(isset($params["header"])){
		  $header = array_merge($header,$params["header"]);
		}
		$user_agent = empty($params["ua"]) ? 0 : $params["ua"] ;
		$ch = curl_init();                                                     
		curl_setopt($ch, CURLOPT_URL, $url);                                   
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		if(array_key_exists('ref', $params) && $params["ref"]){
			curl_setopt($ch, CURLOPT_REFERER, $params["ref"]);
		}             
		curl_setopt($ch, CURLOPT_USERAGENT,$user_agent);                       
		curl_setopt($ch, CURLOPT_NOBODY, false);                               
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
            if (is_array($params["postData"])) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params["postData"]));
            }else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $params["postData"]);  
            }   
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

    private function rand_ip()
	{
		$ip_long = array(
			array('607649792', '608174079'),
			array('1038614528', '1039007743'),
			array('1783627776', '1784676351'),
			array('2035023872', '2035154943'),
			array('2078801920', '2079064063'),
			array('-1950089216', '-1948778497'),
			array('-1425539072', '-1425014785'),
			array('-1236271104', '-1235419137'),
			array('-770113536', '-768606209'),
			array('-569376768', '-564133889')
		);
		$rand_key = mt_rand(0, 9);
		$ip = long2ip(mt_rand($ip_long[$rand_key][0], $ip_long[$rand_key][1]));
		return $ip;
	}
}

new Chrise("https://www.ufec.cn", "url");