#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
合并 .po 文件翻译脚本
从旧文件中读取翻译，按 msgid 匹配替换到模板文件中
"""
import argparse
import sys
import re


def read_multiline_field(lines, i, prefix):
    """读取多行字段（msgid 或 msgstr）"""
    if i >= len(lines) or not lines[i].startswith(prefix):
        return [], i
    
    field_lines = [lines[i]]
    i += 1
    
    # 读取多行（以引号开头的行）
    while i < len(lines) and lines[i].strip().startswith('"'):
        field_lines.append(lines[i])
        i += 1
    
    return field_lines, i


def extract_text(field_lines):
    """从字段行中提取文本内容（处理转义）"""
    if not field_lines:
        return ""
    
    text_parts = []
    for line in field_lines:
        stripped = line.strip()
        # 提取引号内的内容
        match = re.search(r'"(.*)"', stripped)
        if match:
            text_parts.append(match.group(1))
    
    # 合并并处理转义
    text = ''.join(text_parts)
    return text.replace('\\n', '\n').replace('\\"', '"').replace('\\\\', '\\')


def skip_header(lines):
    """跳过文件头，返回第一个条目的索引
    
    文件头特征：
    - 以 msgid "" 开始
    - 紧接着 msgstr ""
    - msgstr 后有多行以引号开头的元数据
    - 以空行结束
    """
    i = 0
    
    # 找到文件头开始（msgid ""）
    while i < len(lines) and lines[i].strip() != 'msgid ""':
        i += 1
    
    if i >= len(lines):
        return i
    
    # 确认下一行是 msgstr ""
    i += 1
    if i >= len(lines) or lines[i].strip() != 'msgstr ""':
        return i
    
    # 跳过 msgstr "" 行
    i += 1
    
    # 跳过所有以引号开头的元数据行
    while i < len(lines) and lines[i].strip().startswith('"'):
        i += 1
    
    # 跳过文件头结束的空行
    if i < len(lines) and lines[i].strip() == '':
        i += 1
    
    return i


def parse_translations(filepath):
    """解析 .po 文件，返回 msgid 到 msgstr 行的映射"""
    with open(filepath, 'r', encoding='utf-8') as f:
        lines = f.readlines()
    
    translations = {}
    i = skip_header(lines)
    
    # 解析条目
    while i < len(lines):
        # 跳过注释和空行
        while i < len(lines) and (lines[i].startswith('#') or lines[i].strip() == ''):
            i += 1
        
        if i >= len(lines):
            break
        
        # 读取 msgid
        msgid_lines, i = read_multiline_field(lines, i, 'msgid ')
        if not msgid_lines:
            i += 1
            continue
        
        # 读取 msgstr
        msgstr_lines, i = read_multiline_field(lines, i, 'msgstr ')
        if not msgstr_lines:
            continue
        
        # 提取 msgid 文本用于匹配
        msgid_text = extract_text(msgid_lines)
        if msgid_text:
            translations[msgid_text] = msgstr_lines
    
    return translations


def process_entry(lines, i, translations):
    """处理一个条目，返回处理后的行和新的索引"""
    entry_lines = []
    matched = False
    
    # 收集注释
    while i < len(lines) and lines[i].startswith('#'):
        entry_lines.append(lines[i])
        i += 1
    
    # 读取 msgid
    msgid_lines, i = read_multiline_field(lines, i, 'msgid ')
    if not msgid_lines:
        # 不是有效条目，直接复制剩余内容
        if i < len(lines):
            entry_lines.append(lines[i])
            i += 1
        return entry_lines, i, False
    
    entry_lines.extend(msgid_lines)
    
    # 读取 msgstr
    msgstr_lines, i = read_multiline_field(lines, i, 'msgstr ')
    
    # 检查是否有匹配的翻译
    msgid_text = extract_text(msgid_lines)
    if msgid_text and msgid_text in translations:
        entry_lines.extend(translations[msgid_text])
        matched = True
    elif msgstr_lines:
        # 保持原样
        entry_lines.extend(msgstr_lines)
    
    # 添加空行（如果有）
    if i < len(lines) and lines[i].strip() == '':
        entry_lines.append(lines[i])
        i += 1
    
    return entry_lines, i, matched


def merge_po_files(template_file, old_file, output_file=None):
    """合并 .po 文件"""
    # 从旧文件读取翻译
    print(f"正在读取 {old_file}...", file=sys.stderr)
    translations = parse_translations(old_file)
    print(f"从旧文件读取了 {len(translations)} 个翻译条目", file=sys.stderr)
    
    # 读取模板文件
    print(f"正在读取 {template_file}...", file=sys.stderr)
    with open(template_file, 'r', encoding='utf-8') as f:
        template_lines = f.readlines()
    
    output_lines = []
    i = 0
    matched_count = 0
    
    # 处理文件头
    header_end = skip_header(template_lines)
    output_lines.extend(template_lines[:header_end])
    i = header_end
    
    # 处理其他条目
    while i < len(template_lines):
        entry_lines, i, matched = process_entry(template_lines, i, translations)
        output_lines.extend(entry_lines)
        if matched:
            matched_count += 1
    
    # 输出结果
    if output_file:
        print(f"正在写入 {output_file}...", file=sys.stderr)
        with open(output_file, 'w', encoding='utf-8') as f:
            f.writelines(output_lines)
        print(f"匹配并替换了 {matched_count} 个翻译条目", file=sys.stderr)
        print(f"结果已保存到 {output_file}", file=sys.stderr)
    else:
        sys.stdout.writelines(output_lines)
        print(f"匹配并替换了 {matched_count} 个翻译条目", file=sys.stderr)


if __name__ == '__main__':
    parser = argparse.ArgumentParser(
        description='合并 .po 文件翻译：从旧文件中读取翻译，按 msgid 匹配替换到模板文件中',
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog='''
示例:
  # 输出到 stdout
  %(prog)s template.po old.po
  
  # 输出到指定文件
  %(prog)s template.po old.po -o output.po
  %(prog)s template.po old.po --output output.po
        '''
    )
    
    parser.add_argument('template', help='模板文件路径（.po 文件）')
    parser.add_argument('old', help='包含翻译的旧文件路径（.po 文件）')
    parser.add_argument('-o', '--output', dest='output', default=None,
                       help='输出文件路径（如果不指定则输出到 stdout）')
    
    args = parser.parse_args()
    merge_po_files(args.template, args.old, args.output)
