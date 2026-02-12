<?php
/**
 * 部署引导页面
 *
 * 仅在首次部署时显示，引导用户完成部署
 *
 * @package Xiaowu_Deployment
 * @since 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>小伍博客 - 部署向导</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 800px;
            width: 100%;
            padding: 60px 40px;
            text-align: center;
        }
        .logo {
            font-size: 64px;
            margin-bottom: 20px;
        }
        h1 {
            color: #333;
            font-size: 36px;
            margin-bottom: 20px;
            font-weight: 700;
        }
        .subtitle {
            color: #666;
            font-size: 18px;
            margin-bottom: 40px;
            line-height: 1.6;
        }
        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        .feature {
            padding: 20px;
            background: #f8f9fa;
            border-radius: 12px;
            transition: transform 0.3s ease;
        }
        .feature:hover {
            transform: translateY(-5px);
        }
        .feature-icon {
            font-size: 32px;
            margin-bottom: 10px;
        }
        .feature-title {
            color: #333;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .feature-desc {
            color: #666;
            font-size: 14px;
            line-height: 1.5;
        }
        .button-group {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-block;
            padding: 16px 40px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }
        .btn-secondary {
            background: #f8f9fa;
            color: #333;
            border: 2px solid #e9ecef;
        }
        .btn-secondary:hover {
            background: #e9ecef;
        }
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px 20px;
            margin-top: 30px;
            text-align: left;
            border-radius: 4px;
        }
        .info-box h3 {
            color: #1976D2;
            font-size: 16px;
            margin-bottom: 8px;
        }
        .info-box p {
            color: #555;
            font-size: 14px;
            line-height: 1.6;
        }
        .info-box code {
            background: rgba(0,0,0,0.05);
            padding: 2px 6px;
            border-radius: 3px;
            font-family: "Monaco", "Courier New", monospace;
            font-size: 13px;
        }
        @media (max-width: 768px) {
            .container {
                padding: 40px 20px;
            }
            h1 {
                font-size: 28px;
            }
            .features {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">🚀</div>
        <h1>欢迎使用小伍博客</h1>
        <p class="subtitle">一个现代化的 WordPress 博客系统，集成 AI 服务、3D 图库、云盘存储等强大功能</p>

        <div class="features">
            <div class="feature">
                <div class="feature-icon">🤖</div>
                <div class="feature-title">AI 服务</div>
                <div class="feature-desc">支持多个 AI 提供商，智能写作、生图、搜索</div>
            </div>
            <div class="feature">
                <div class="feature-icon">🎨</div>
                <div class="feature-title">3D 图库</div>
                <div class="feature-desc">基于 Three.js 的 3D 模型展示和管理</div>
            </div>
            <div class="feature">
                <div class="feature-icon">☁️</div>
                <div class="feature-title">云盘集成</div>
                <div class="feature-desc">阿里云盘、百度网盘等多云存储</div>
            </div>
            <div class="feature">
                <div class="feature-icon">🔍</div>
                <div class="feature-title">智能搜索</div>
                <div class="feature-desc">AI 增强的全文搜索和语义搜索</div>
            </div>
        </div>

        <div class="button-group">
            <a href="/wp-admin/install.php" class="btn btn-primary">开始安装 WordPress</a>
            <a href="/wp-admin/" class="btn btn-secondary">进入后台</a>
        </div>

        <div class="info-box">
            <h3>💡 部署说明</h3>
            <p>
                这是首次部署，请点击"开始安装 WordPress"完成系统初始化。<br>
                安装完成后，博客将自动启用，通过 <code>/wp-admin/</code> 访问管理后台。
            </p>
        </div>
    </div>
</body>
</html>
<?php
exit;
