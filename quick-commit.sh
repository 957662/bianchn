#!/bin/bash
# 快速提交脚本 - 跳过所有交互式确认

# 检查是否提供了提交信息
if [ -z "$1" ]; then
  echo "❌ 请提供提交信息"
  echo "用法: ./quick-commit.sh '提交信息'"
  echo "示例: ./quick-commit.sh 'feat: 添加新功能'"
  exit 1
fi

# 添加所有更改
git add -A

# 直接提交，跳过 commitizen 和交互式提示
git commit -m "$1"

echo "✅ 提交成功！"
echo "提交信息: $1"
