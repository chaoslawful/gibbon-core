# Gibbon Core 打包说明

## 文件模式总结

根据 `gibbon-core-v30.0.00` 目录的分析，发布包应包含以下内容：

### 1. 根目录文件
- 所有 `.php` 文件（入口文件、处理文件等）
- 所有 `.sql` 文件（数据库脚本）
- `CHANGELOG.txt`, `README.md`, `LICENSE`
- `favicon.ico`, `robots.txt`
- `.htaccess`（Apache 配置）
- `composer.json`, `composer.lock`

### 2. 主要目录
- **cli/** - 命令行脚本
- **installer/** - 安装程序
- **lib/** - 第三方库（jQuery, TinyMCE, Chart.js 等）
- **modules/** - 所有功能模块
- **resources/** - 资源文件（CSS, JS, 模板等）
- **src/** - 核心源代码
- **themes/** - 主题文件
- **uploads/** - 上传目录（仅结构，不含用户文件）
- **vendor/** - Composer 依赖包
- **i18n/** - 国际化文件（**排除 zh_CN**，因为 v30 中没有）

### 3. 排除的内容
以下内容**不应**包含在发布包中：

- **开发工具和配置**
  - `.git/`, `.github/`, `.gitlab-ci.yml`
  - `.gitignore`, `.gitattributes`, `.gitmodules`
  - `.editorconfig`
  - `phpstan.neon`
  
- **测试文件**
  - `tests/` 目录
  
- **开发脚本**
  - `scripts/` 目录
  - `merge_po_translations.py`
  - `xgettextGenerationCommands.sh`
  
- **特定语言包**
  - `i18n/zh_CN/`（v30.0.00 中不包含）

### 4. 特殊处理

#### uploads 目录
- 只复制目录结构
- 复制 `.htaccess` 等配置文件
- **不复制**用户上传的实际文件

#### i18n 目录
- 复制所有语言包
- **排除** `zh_CN` 目录**

## 使用方法

运行打包脚本：

```bash
./package_release.sh
```

脚本会：
1. 从 `version.php` 自动提取版本号
2. 根据上述模式复制文件
3. 验证关键文件是否存在
4. 创建 `gibbon-core-{VERSION}.tar.gz` 文件
5. 输出到 `/home/wxz/src/` 目录

## 验证

打包完成后，可以验证包的内容：

```bash
tar -tzf /home/wxz/src/gibbon-core-{VERSION}.tar.gz | head -20
```

确保：
- ✅ 包含所有必要的 PHP 文件
- ✅ 包含所有模块
- ✅ 包含 vendor 目录
- ✅ **不包含** .git 目录
- ✅ **不包含** tests 目录
- ✅ **不包含** scripts 目录
- ✅ **不包含** i18n/zh_CN

