<?php
function main_handler($event, $context) {
    date_default_timezone_set('Asia/Shanghai');
    $event = json_decode(json_encode($event), true);
    $context = json_decode(json_encode($context), true);
    echo urldecode(json_encode($event)) . '
 
' . urldecode(json_encode($context)) . '
 
';
    $function_name = $context['function_name'];
    $host_name = $event['headers']['host'];
    $serviceId = $event['requestContext']['serviceId'];
    if ( $serviceId === substr($host_name,0,strlen($serviceId)) ) {
        $path = substr($event['path'], strlen('/' . $function_name . '/')); 
        // 使用API网关长链接时
    } else {
        $path = substr($event['path'], strlen($event['requestContext']['path']=='/'?'/':$event['requestContext']['path'].'/')); 
        // 使用自定义域名时
    }
    if ($path=='regist') return welcome(json_decode($event['body'],true)['data']);

    unset($_GET);
    unset($_POST);
    $_GET = $event['queryString'];
    $_POSTbody = explode("&",$event['body']);
    foreach ($_POSTbody as $postvalues){
        $pos = strpos($postvalues,"=");
        $_POST[urldecode(substr($postvalues,0,$pos))]=urldecode(substr($postvalues,$pos+1));
    }
    if ($_POST['uid']!='') return sendmessage($_POST['uid'], $_POST['content']);
    if ($_GET['uid']!='') return sendmessage($_GET['uid'], $_GET['content']);

    return default_page();
}

// 单UID的程序，想要多UID可以自己弄
function get_data($uid, $content = '', $url = null, $contentType = 1, $topicId = 123)
{
    $content = str_replace('\n','
',$content);
    // $data['content'] = $content;
    // 如果不需要加入时间，去掉上一行最前面的双杠，并从这行开始删
    $bodyarry = explode('
',$content);
    $title = $bodyarry[0];
    $body='';
    if (count($bodyarry)>1) {
        for ($x=1;$x<count($bodyarry);$x++) {
            $body .= '
' . $bodyarry[$x];
        }
    }
    $data['content'] = $title . '
        ' . date("Y-m-d H:i:s");
    if ($body!='') $data['content'] .= $body;
    // 如果不需要加入时间，删到这里
    $data['appToken'] = getenv('APP_TOKEN');
    $data['contentType'] = $contentType;
    $data['topicIds'][0] = $topicId;
    $data['uids'][0] = $uid;
    $data['url'] = $url;
    //return json_encode($data, JSON_PRETTY_PRINT);
    return json_encode($data);
}

function sendmessage($uid, $content)
{
    $api_url = 'http://wxpusher.zjiecode.com/api/send/message';
    $data=get_data($uid, $content);
    while (!$response['stat']) $response=curl_post($api_url, $data);
    return output($response['body'], $response['stat']);
}

function welcome($backdata)
{
    return sendmessage($backdata['uid'], '感谢关注『'.$backdata['appName'].'』\n本功能基于WxPusher\n你的UID为'.$backdata['uid']);
}

function default_page()
{
    $statusCode=200;
    @ob_start();
?>
<!DOCTYPE html>
<head>
    <title>wxpusher</title>
    <meta charset=utf-8>
    <meta name="viewport" content="width=device-width,initial-scale=1"/>
</head>
<body>
<table>
    <tr>
        <td><?php echo '<img src="'.getenv('jpgurl').'">'; ?></td>
        <td>
            <form name="form1" method="POST" action="" target="showed">
            <label>用微信扫描二维码获取UID<br>UID:</label><input name="uid" type="text" placeholder="输入你的UID"><br>
            <textarea name="content" cols="45" rows="8" placeholder="输入要发送的内容，或把uid、content通过GET或POST到本页地址"><?php echo $value;?></textarea>
            <input name="Submit1" type="submit" value="发送" onclick="document.getElementById('showed').innerText='';">
            </form>
        </td>
    </tr>
</table>
<iframe id="showed" name="showed" width="100%"></iframe>
</body>
</html>
<?php
    // 返回html网页
    $html=ob_get_clean();

    return output($html, $statusCode);
}

function output($body, $statusCode = 200, $isBase64Encoded = false, $headers = ['Content-Type' => 'text/html'])
{
    //$headers['Access-Control-Allow-Origin']='*';
    return [
        'isBase64Encoded' => $isBase64Encoded,
        'statusCode' => $statusCode,
        'headers' => $headers,
        'body' => $body
    ];
}

function curl_post($url, $data = '')
{
    echo $data;
    $method='POST';
    $headers['Content-Type'] = 'application/json';
    $sendHeaders = array();
    foreach ($headers as $headerName => $headerVal) {
        $sendHeaders[] = $headerName . ': ' . $headerVal;
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST,$method);
    curl_setopt($ch, CURLOPT_POSTFIELDS,$data);

    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); // 返回获取的输出文本流
    curl_setopt($ch, CURLOPT_HEADER, 0);         // 将头文件的信息作为数据流输出
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $sendHeaders);
    $response['body'] = curl_exec($ch);
    $response['stat'] = curl_getinfo($ch,CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $response;
}
