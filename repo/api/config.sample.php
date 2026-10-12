<?php
/**
 * 拼豆工坊 · PHP 代理配置文件（模板）
 *
 * 用法：
 *   1) 复制本文件为同目录 config.php（或任何被 BEAN_CONFIG 环境变量指向的路径）
 *   2) 填入真实值
 *   3) 生产环境请将 config.php 放到 web 根目录之外（如 /etc/bean/config.php），并 chmod 600
 *   4) 切勿把真实 config.php 提交到 Git（已加入 .gitignore）
 */
return [
    'owner'     => '你的GitHub用户名',
    'repo'      => 'beandiy',
    'branch'    => 'main',

    // 服务器专用 PAT-A：fine-grained，仅本仓库 Contents Read&Write
    // （覆盖 repo/** 资源，含 repo/data/** 与 repo/admin/beep.wav 等）
    'pat_a'     => 'github_pat_服务器专用_全仓库读写',

    // 可选 Admin Key：留空则不启用写操作密钥闸门；
    // 填入随机串后，所有 PUT 写操作必须在请求头携带 X-Admin-Key: 该串
    'admin_key' => '',
];
