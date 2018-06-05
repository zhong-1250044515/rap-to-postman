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
        $data = file_get_contents($rapBackupSrc);
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
                    $a['event'] = $this->event(); // 接口测试反馈 event
                    // 请求信息
                    $a['request']['method'] = $this->requestType($action['requestType']); // 请求方式
                    $a['request']['header'] = $this->requestHeader(); // 请求头部 header
                    $a['request']['description'] = $action['description']; // 接口描述
                    $url = "{{api_url}}" . $this->requestUrl($action['requestUrl']); // 接口地址
                    // 请求体 or 查询参数
                    $list = [];
                    foreach ($action['requestParameterList'] as $param) {
                        $r = [];
                        $this->param($param, $param['identifier'], $r);
                        $list = array_merge($list, $r);
                    }
                    switch ($a['request']['method']) {
                        case 'GET':
                            $a['request']['url']['query'] = $list;
                            $a['request']['url']['raw'] = $a['request']['url']['host'] = $url;
                            break;
                        case 'PUT':
                            $a['request']['body']['mode'] = 'urlencoded';
                            $a['request']['body']['urlencoded'] = $list;
                            $a['request']['url'] = $url;
                            break;
                        case 'POST':
                            $a['request']['body']['mode'] = 'formdata';
                            $a['request']['body']['formdata'] = $list;
                            $a['request']['url'] = $url;
                            break;
                        case 'DELETE':
                            $a['request']['url'] = $url;
                            break;
                    }
                    $p['item'][] = $a;
                }
                $postMan['item'][] = $p;
            }
        }

        if (!file_exists($savePath)) {
            mkdir($savePath);
            chmod($savePath, 0777);
        }
        file_put_contents($savePath . "/postman.json", json_encode($postMan));

        return $savePath . "/postman.json";
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
    private function param($param, $code = '', &$list)
    {
        if (!$param) {
            return true;
        }
        $r = [
            'type' => 'text',
            'enabled' => true,
            'description' => '',
            'value' => '',
        ];
        $type = $param['dataType'];
        switch ($type) {
            case 'object':
                foreach ($param['parameterList'] as $v) {
                    $this->param($v, $code . '[' . $v['identifier'] . ']', $list);
                }
                break;
            case 'array<object>':
                foreach ($param['parameterList'] as $v) {
                    $this->param($v, $code . '[0][' . $v['identifier'] . ']', $list);
                }
                break;
            case 'number':
            case 'string':
            case 'boolean':
                $r['key'] = $code;
                $remark = explode('@mock=', $param['remark']);
                $r['description'] = $param['name'] . '。' . $remark[0];
                $r['value'] = isset($remark[1]) ? $remark[1] : '';
                $list[] = $r;
                break;
            default:
                $r['key'] = $code . '[0]';
                $remark = explode('@mock=', $param['remark']);
                $r['description'] = $param['name'] . '。' . $remark[0];
                $r['value'] = isset($remark[1]) ? $remark[1] : '';
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
        $requestType = [
            1 => 'GET',
            2 => 'POST',
            3 => 'PUT',
            4 => 'DELETE',
        ];

        return $requestType[$type];
    }

    /**
     * 请求头部 header 生成
     */
    private function requestHeader()
    {
        $data = [
            'Authorization' => ['Bearer {{access_token}}', '访问令牌'],
            'X-Requested-With' => ['XMLHttpRequest', '异步访问'],
        ];
        $header = [];
        foreach ($data as $k => $v) {
            $header[] = [
                "key" => $k,
                "value" => $v[0],
                "description" => $v[1],
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
                    "if (responseCode.code >= 200 && responseCode.code < 300) {",
                    "    var jsonData = JSON.parse(responseBody);",
                    "    tests['success'] = responseCode.code >= 200 && responseCode.code < 300;",
                    "    postman.setEnvironmentVariable('access_token', jsonData.data.access_token); // environment variable",
                    "}",
                ],
            ],
        ];

        return $event;
    }
}
