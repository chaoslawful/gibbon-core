# Packaging 目录

此目录包含 Gibbon Core 发布打包相关的脚本和文档。

## 文件说明

- **package_release.sh** - 主打包脚本，用于从开发目录创建发布版本的 tarball
- **PACKAGING.md** - 打包说明和使用指南
- **FILE_PATTERNS.md** - 文件模式分析文档，说明 v30.0.00 的文件结构

## 快速开始

### 使用默认设置

```bash
cd /home/wxz/src/gibbon-core/packaging
./package_release.sh
```

默认情况下：
- 源目录：脚本所在目录的父目录（如果脚本在 `packaging/` 子目录中）
- 输出目录：源目录的父目录

### 指定自定义目录

```bash
# 指定源目录和输出目录
./package_release.sh -s /path/to/gibbon-core -o /tmp/releases

# 或使用长选项
./package_release.sh --source /home/user/gibbon-core --output /home/user/releases

# 只指定源目录（输出目录使用默认值）
./package_release.sh -s /path/to/gibbon-core

# 只指定输出目录（源目录使用默认值）
./package_release.sh -o /tmp/releases
```

### 查看帮助

```bash
./package_release.sh -h
# 或
./package_release.sh --help
```

## 详细说明

请参阅 `PACKAGING.md` 了解详细的打包规则和使用方法。

