# rap-to-postman
一个简单的工具类，用于将 RAP 接口管理工具的备份文件，下载成 Postman 的 Json 导入文件。
## 使用
Composer 包下载
```
composer require wujunzhong/rap-to-postman
```
代码编写
``` php
$tool = new \RapToPostman\RapToPostman();
$rapBackupSrc = "xxx"; // Rap 导出备份地址
$savePath = "./public"; // 要保存的文件夹路径

return $tool->run($rapBackupSrc, $savePath); // return ./public/postman.json
```
下载好的 postman.json 文件使用 Postman 导入即可生成相应的接口。
## License
The MIT License (MIT)

Copyright (c) 2018 吴俊钟<1250044515@qq.com>
