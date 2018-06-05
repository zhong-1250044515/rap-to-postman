<?php
/**
 * Convert the json exported by RAP into the json format imported by Postman
 *
 * @author wujunzhong <1250044515@qq.com>
 */

namespace RapToPostman;

class RapToPostman
{
    /**
     * 运行
     *
     * @param $rapSrc Rap 备份路径
     * @param  $savePath 生成文件保存路径
     */
    public function run($rapBackupSrc, $savePath)
    {
        /*rap文档数据获取*/
        $data = file_get_contents($rapSrc);
        $data = json_decode($data, true);
        $data['modelJSON'] = str_replace("\'", '\"', $data['modelJSON']);
        $data = json_decode($data['modelJSON'], true);
        /*postman格式数据整理*/
        $postMan = [];
        $postMan['info']['name'] = $data['name']; // 项目名称
        $postMan['info']['_postman_id'] = md5(microtime(true));
        $postMan['info']['schema'] = "https://schema.getpostman.com/json/collection/v2.0.0/collection.json";
        $postMan['item'] = [];
        foreach ($data['moduleList'] as &$module) {
            // 接口分组
            foreach ($module['pageList'] as &$page) {
                $p = [];
                $p['name'] = $page['name'];
                $p['description'] = $page['introduction'];
                // 接口列表
                foreach ($page['actionList'] as &$action) {
                    $a = [];
                    $a['name'] = $action['name']; // 接口名称
                    $a['event'] = $this->event(); // 接口测试反馈event
                    // 请求信息
                    $a['request']['url'] = $this->requestUrl($action['requestUrl']); // 接口地址
                    $a['request']['method'] = $this->requestType($action['requestType']); // 请求方式
                    $a['request']['header'] = $this->requestHeader(); // 请求头部header
                    $a['request']['description'] = $action['description'];// 接口描述
                    // 请求体body
                    $a['request']['body']['mode'] = 'urlencoded';
                    $list = [];
                    foreach ($action['requestParameterList'] as $param) {
                        $r = [];
                        $this->param($param, $param['identifier'], $r);
                        $list = array_merge($list, $r);
                    }
                    $a['request']['body']['urlencoded'] = $list;
                    $p['item'][] = $a;
                }
                $postMan['item'][] = $p;
            }
        }

        if (!file_exists($savePath)) {
            mkdir($savePath);
            chmod($savePath, 0777);
        }
        file_put_contents($savePath."/postman.json", json_encode($postMan));
    }

    /**
     * 接口路径参数转换处理
     *
     * @param string $url 接口路径
     */
    private function requestUrl($url)
    {
        $pattern = '/:\w+/';
        $param = [];
        preg_match_all($pattern, $url, $param);
        if ($param) {
            foreach ($param[0] as $v) {
                $re = str_replace(':', '{{', $v) . '}}';
                $url = str_replace($v, $re, $url);
            }
        }
        return $url;
    }

    /**
     * 请求参数转换
     */
    private function param($param, $code='', &$list)
    {
        if (!$param) {
            return true;
        }
        $r = [
            'type'    => 'text',
            'enabled' => true
        ];
        $type = $param['dataType'];
        switch ($type) {
            case 'object':
                foreach ($param['parameterList'] as $v) {
                    $this->param($v, $code.'['. $v['identifier'] .']', $list);
                }
                break;
            case 'array<object>':
                foreach ($param['parameterList'] as $v) {
                    $this->param($v, $code.'[0]['. $v['identifier'] .']', $list);
                }
                break;
            case 'number':
            case 'string':
            case 'boolean':
                $r['key'] = $code;
                list($remark, $mock) = explode('@mock=', $param['remark']);
                $r['value'] = $mock ? $mock : '';
                $list[] = $r;
                break;
            default:
                $r['key'] = $code . '[0]';
                list($remark, $mock) = explode('@mock=', $param['remark']);
                $r['value'] = $mock ? $mock : '';
                $list[] = $r;
                break;
        }

        return true;
    }

    /**
     * 请求方式
     */
    private function requestType($type)
    {
        $request = '';
        switch ($type) {
            case '1':
                $request = 'GET';break;
            case '2':
                $request = 'POST';break;
            case '3':
                $request = 'PUT';break;
            case '4':
                $request = 'DELETE';break;
        }
        return $request;
    }

    /**
     * 请求头部 header 生成
     */
    private function requestHeader()
    {
        $data = [
            'Authorization'   => ['Bearer {{access_token}}', '访问令牌'],
            'X-Requested-With' => ['XMLHttpRequest', '异步访问']
        ];
        $header = [];
        foreach ($data as $k => $v) {
            $header[] = [
                "key"         => $k,
                "value"       => $v[0],
                "description" => $v[1]
            ];
        }
        return $header;
    }

    /**
     * 接口测试反馈 event
     */
    private function event()
    {
        $event[] = [
                "listen" => "test",
                "script" => [
                    "type" => "text/javascript",
                    "exec" => [
                        "if (responseCode.code == 200) {",
                        "    var jsonData = JSON.parse(responseBody);",
                        "    tests['success'] = responseCode.code == 201;",
                        "    postman.setEnvironmentVariable('access_token', jsonData.data.access_token); // environment variable";
                        "}"
                    ]
                ]
        ];
        return $event;
    }
}
?>
