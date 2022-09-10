# Library for StartCMS
start-think 是一个基于ThinkORM封装的一套多应用管理类库，方便快速构建 WEB 应用。

## 主要特性
- 全局事件：跨应用事件监听、订阅自动化，无需手动绑定
- 代码生成：内置优雅代码生成器，一键生成CURD相关接口
- 注解文档：集成[APIDOC](https://apidocjs.com)注解文档，一键生成可调试接口文档
- 注解权限：接口注释添加@super, @auth, @admin, @login等标签，一键生成权限菜单
- 通用模型：内置快速关联查询，分页查询，列表查询，详情查询，数据更新及删除
- 通用服务：模型自动关联，内置快速分页查询，列表查询，详情查询，数据更新及删除
- 通用控制器：快速输入验证，可自动化CSRF安全验证
- 通用网络请求 （支持 get 及 post，可配置请求证书等）
- UTF8加密算法支持（安全URL参数传参数）
- 接口CORS跨域默认支持（输出 JSON 标准化） 
## 代码仓库
start-think 为 Apache 协议开源项目，安装使用或二次开发不受约束，欢迎 fork 项目。  
部分代码来自互联网，若有异议可以联系作者进行删除。  
Github仓库地址：https://github.com/simplestart-cn/start-think

## 使用说明
* start-think 需要 Composer 支持
* 安装命令 ` composer require simplestart/start-think`