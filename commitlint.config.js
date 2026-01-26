module.exports = {
  extends: ['@commitlint/config-conventional'],
  rules: {
    'type-enum': [
      2,
      'always',
      [
        'feat',      // 新功能
        'fix',       // 修复 bug
        'docs',      // 文档变更
        'style',     // 代码样式变更（不影响代码含义）
        'refactor',  // 代码重构（不是 fix 也不是 feat）
        'perf',      // 性能优化
        'test',      // 测试相关
        'chore',     // 构建系统、依赖版本等变更
        'ci',        // CI/CD 配置变更
        'revert',    // 恢复之前的提交
        'security',  // 安全修复
        'deps',      // 依赖更新
        'locale',    // 国际化和本地化
        'config',    // 配置文件变更
        'release'    // 发布版本
      ]
    ],
    'type-case': [2, 'always', 'lower-case'],
    'type-empty': [2, 'never'],
    'subject-empty': [2, 'never'],
    'subject-full-stop': [2, 'never', '.'],
    'subject-case': [
      2,
      'never',
      ['start-case', 'pascal-case', 'upper-case']
    ],
    'body-leading-blank': [2, 'always'],
    'body-max-line-length': [2, 'always', 100],
    'footer-leading-blank': [2, 'always'],
    'footer-max-line-length': [2, 'always', 100]
  },
  prompt: {
    settings: {
      scopeEnumSeparator: ','
    },
    messages: {
      skip: ':skip',
      max: 'upper %s chars',
      min: '%s chars at least',
      emptyNotAllowed: 'empty not allowed',
      upperLimitExceeded: 'upper limit exceeded',
      commitMessage: '%s\n\n类型:\n%s\n\n范围 (可选):\n%s\n\n主题:\n%s\n\n体 (可选):\n%s\n\n页脚 (可选):\n%s\n\n',
      hasBreakingChanges: '有破坏性变更',
      isBreakingChange: '破坏性变更',
      breakingHeader: '破坏性变更',
      footer: '页脚 (可选)',
      footerPrefixes: [
        { value: 'BREAKING CHANGE', name: 'BREAKING CHANGE: ' },
        { value: 'BREAKING-CHANGE', name: 'BREAKING-CHANGE: ' },
        { value: 'Closes', name: 'Closes: ' },
        { value: 'Refs', name: 'Refs: ' }
      ],
      generatedBy: ' (by commitizen)',
      confirmCommit: '确认提交?'
    },
    questions: {
      type: {
        description: '选择您要提交的更改类型:',
        enum: {
          feat: {
            description: '新功能',
            emoji: '✨'
          },
          fix: {
            description: '修复错误',
            emoji: '🐛'
          },
          docs: {
            description: '文档变更',
            emoji: '📝'
          },
          style: {
            description: '代码风格变更（不影响代码含义）',
            emoji: '💄'
          },
          refactor: {
            description: '代码重构（不是 fix 也不是 feat）',
            emoji: '♻️'
          },
          perf: {
            description: '性能优化',
            emoji: '⚡'
          },
          test: {
            description: '测试相关变更',
            emoji: '🧪'
          },
          chore: {
            description: '构建系统、依赖版本等变更',
            emoji: '🔧'
          },
          ci: {
            description: 'CI/CD 配置变更',
            emoji: '🤖'
          },
          revert: {
            description: '恢复之前的提交',
            emoji: '⏮️'
          },
          security: {
            description: '安全修复',
            emoji: '🔒'
          },
          deps: {
            description: '依赖更新',
            emoji: '📦'
          },
          locale: {
            description: '国际化和本地化',
            emoji: '🌍'
          },
          config: {
            description: '配置文件变更',
            emoji: '⚙️'
          },
          release: {
            description: '发布版本',
            emoji: '🎉'
          }
        }
      },
      scope: {
        description: '此更改的范围 (可选):',
        hint: '例如: security, validation, api, ui'
      },
      subject: {
        description: '简写更改内容（命令式语气，不带句号）:',
        maxLength: 50,
        minLength: 3
      },
      body: {
        description: '提供详细的变更描述 (可选)。使用"|"表示换行:',
        maxLength: 100
      },
      isBreaking: {
        description: '是否有破坏性变更?',
        default: false
      },
      breakingBody: {
        description: '破坏性变更必须包含完整的体。请使用"|"表示换行:',
        maxLength: 100
      },
      breaking: {
        description: '描述破坏性变更:',
        maxLength: 100
      },
      isFooterRequired: {
        description: '是否需要提供页脚信息?',
        default: false
      },
      footerPrefix: {
        description: '选择要使用的页脚前缀:',
        enum: {
          'BREAKING CHANGE': 'BREAKING CHANGE',
          'BREAKING-CHANGE': 'BREAKING-CHANGE',
          'Closes': 'Closes',
          'Refs': 'Refs'
        }
      },
      footer: {
        description: '页脚信息 (例如: #123, #456):',
        maxLength: 100
      },
      confirmCommit: {
        description: '确认提交?'
      }
    }
  }
};
