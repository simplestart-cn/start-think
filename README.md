# Library for StartCMS
start-think 是一个基于ThinkORM封装的一套多应用管理类库，方便快速构建 WEB 应用。

## 包含组件
* 通用模型（快速信息查询，分页查询，列表查询，数据安全删除处理：is_deleted 字段存在则自动软删除)
* 通用服务 (模型自动关联，快速信息查询，分页查询，列表查询，数据更新)
* 通用控制器 (快速输入验证，自动化CSRF安全验证)
* 通用文件存储 (本地存储 + 阿里云OSS存储 + 腾讯云COS存储 + 七牛云存储)
* 通用网络请求 （支持 get 及 post，可配置请求证书等）
* UTF8加密算法支持（安全URL参数传参数）
* 接口CORS跨域默认支持（输出 JSON 标准化）

## 代码仓库
 start-think 为 Apache 协议开源项目，安装使用或二次开发不受约束，欢迎 fork 项目。
 部分代码来自互联网，若有异议可以联系作者进行删除。

 * Github仓库地址：https://github.com/simplestart-cn/start-think

## 使用说明
* start-think 需要 Composer 支持
* 安装命令 ` composer require simplestart/start-think 1.0.0`
* 案例代码：
控制器需要继承 `start\Controller`，然后`$this`就可能使用全部功能
```php
// 定义 MyController 控制器
class MyController extend start\Controller {
	// 快速验证,参数(验证规则：array(), 请求方法：'post./get.', 严格模式：true/fasle)
	// 开启严格模式后只会接收经过规则验证的参数
    $input = $this->formValidate([
    	'id.require'    => 'ID不能为空',
        'title.require' => '名字不能为空'
    ]);
}
```

#### 文件存储组件（ cos 、 oss 及 qiniu 需要配置参数,无配置参数则执行本地存储）
```php
// 实例化存储驱动()
$config = [
	'engine' => 'aliyun',
	'domain' => '',
	'bucket' => '',
	'access_key_id' => '',
	'access_key_secret' => ''
];
$StorageDriver = new \start\Storage($config);
// 设置上传文件的信息
$StorageDriver->setUploadFile('iFile');
// 上传图片
if (!$StorageDriver->upload()) {
    return json(['code' => 0, 'msg' => '图片上传失败' . $StorageDriver->getError()]);
}

// 图片上传路径
$fileName = $StorageDriver->getFileName();
// 图片信息
$fileInfo = $StorageDriver->getFileInfo();
// 添加文件库记录
// some code ....
// 图片上传成功
return json(['code' => 1, 'msg' => '图片上传成功', 'data' => $uploadFile]);
```

#### 通用网络请求
```php
// 发起get请求
$result = http_get($url, $query, $options);

// 发起post请求
$result = http_post($url, $data, $options);
```

#### 系统参数配置（基于 admin_config 数据表）
```php
// 设置参数
conf($keyname, $keyvalue);

// 获取参数
$keyvalue = conf($kename);
```

#### UTF8加密算法
```php
// 字符串加密操作
$string = encode($content);

// 加密字符串解密
$content = decode($string);
```

#### 应用菜单构建
```php
// 构建全部应用菜单
php think start:menu

// 构建单个应用菜单
php think start:menu [appName]
```
## 1.0.3 更新说明
* 添加菜单构建命令
* 调整基础查询参数结构
* 取消快速排序属性

## 1.0.4 更新说明
* 快速验证添加严格模式，开启后将只接收经过验证的参数
* 添加全局获取当前管理员方法get_admin_id(),get_admin_name()
* 优化模型快速查询方法，支持关联及操作符查询
* 修复一些已知问题