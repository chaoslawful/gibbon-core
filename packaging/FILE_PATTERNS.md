# Gibbon Core v30.0.00 文件模式分析

## 目录对比总结

### 包含的目录和文件

#### 根目录文件
- 所有 `.php` 入口文件（index.php, gibbon.php, login.php 等）
- 数据库脚本：`gibbon.sql`, `gibbon_demo.sql`
- 文档：`CHANGELOG.txt`, `README.md`, `LICENSE`
- 配置文件：`.htaccess`, `robots.txt`, `favicon.ico`
- Composer 文件：`composer.json`, `composer.lock`
- 版本文件：`version.php`

#### 主要目录结构
```
gibbon-core-v30.0.00/
├── cli/                    # 命令行脚本
├── installer/              # 安装程序
├── lib/                    # 第三方库
│   ├── ace/               # ACE 编辑器
│   ├── Chart.js/          # 图表库
│   ├── htmx/              # HTMX
│   ├── jquery/            # jQuery
│   ├── jquery-ui/         # jQuery UI
│   ├── tinymce/           # TinyMCE 编辑器
│   └── ...                # 其他库
├── modules/                # 功能模块
│   ├── Activities/
│   ├── Finance/
│   ├── Students/
│   └── ...                # 所有模块
├── resources/              # 资源文件
│   ├── assets/            # CSS, JS, 字体等
│   ├── imports/           # 导入模板
│   └── templates/         # 模板文件
├── src/                    # 核心源代码
│   ├── Auth/
│   ├── Database/
│   ├── Domain/
│   ├── Forms/
│   ├── Services/
│   └── ...                # 所有源代码
├── themes/                 # 主题
│   ├── Default/
│   └── Legacy/
├── uploads/                # 上传目录（仅结构）
├── vendor/                 # Composer 依赖
└── i18n/                   # 国际化（排除 zh_CN）
```

### 排除的内容

#### 开发工具和配置
- `.git/` - Git 仓库
- `.github/` - GitHub 配置
- `.gitlab-ci.yml` - GitLab CI 配置
- `.gitignore`, `.gitattributes`, `.gitmodules` - Git 配置文件
- `.editorconfig` - 编辑器配置
- `phpstan.neon` - PHPStan 配置

#### 测试和开发脚本
- `tests/` - 测试目录
- `scripts/` - 开发脚本目录
- `merge_po_translations.py` - Python 脚本
- `xgettextGenerationCommands.sh` - Shell 脚本

#### 特定语言包
- `i18n/zh_CN/` - 中文语言包（v30.0.00 中不包含）

### 文件统计

- **v30.0.00 文件数**（排除 vendor）: ~3,116 个文件
- **当前开发版本文件数**（排除 vendor）: ~4,143 个文件
- **差异**: 主要是开发工具、测试文件和新增的 zh_CN 语言包

### 打包规则

1. **包含所有运行时必需的文件**
   - PHP 源代码
   - 模块文件
   - 资源文件（CSS, JS, 图片等）
   - 第三方库
   - Composer 依赖

2. **排除开发相关文件**
   - Git 相关
   - 测试文件
   - 开发脚本
   - 代码分析工具配置

3. **特殊处理**
   - `uploads/` - 只保留目录结构
   - `i18n/zh_CN/` - 排除（v30 中没有）

### 验证清单

打包完成后验证：

- [ ] 包含 `gibbon.php`, `index.php`, `functions.php`
- [ ] 包含所有 `modules/` 目录
- [ ] 包含 `vendor/` 目录
- [ ] 包含 `lib/` 目录
- [ ] 包含 `src/` 目录
- [ ] 不包含 `.git/` 目录
- [ ] 不包含 `tests/` 目录
- [ ] 不包含 `scripts/` 目录
- [ ] 不包含 `i18n/zh_CN/` 目录
- [ ] 包含 `composer.json` 和 `composer.lock`

