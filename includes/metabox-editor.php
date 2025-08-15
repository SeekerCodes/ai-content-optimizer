<?php
/**
 * Plugin Name: AI Content Optimizer
 * Description: Adds a meta box to posts and products to optimize title and description using AI.
 * Version: 1.1
 * Author: Your Name
 */

// 防止直接访问文件
if (!defined('ABSPATH')) {
    exit;
}

// =============================================================================
// 1. Enqueue Scripts & Styles (Best Practice)
// =============================================================================
// 将 CSS 和 JS 代码从 meta box 中分离出来，通过 WordPress 的标准方式加载。
// 这样做更符合规范，也便于管理和缓存。
add_action('admin_enqueue_scripts', function ($hook) {
    // 只在文章编辑页面加载我们的脚本和样式
    if ('post.php' !== $hook && 'post-new.php' !== $hook) {
        return;
    }

    // 获取当前屏幕信息，确保只在指定文章类型下加载
    $screen = get_current_screen();
    $post_types = ['post', 'product']; // 可改为你的商品类型

    if (is_object($screen) && in_array($screen->post_type, $post_types)) {
        // 注册并加载样式
        wp_register_style('aico-admin-style', false);
        wp_enqueue_style('aico-admin-style');
        wp_add_inline_style('aico-admin-style', "
            .aico-field { margin: 15px 0; }
            .aico-field label { font-weight: bold; display: block; margin-bottom: 5px; }
            .aico-loading {
                display: inline-block;
                width: 16px; height: 16px;
                border: 2px solid rgba(4, 120, 224, 0.2);
                border-top-color: #0478e0;
                border-radius: 50%;
                animation: spin 1s linear infinite;
                margin-left: 8px;
                vertical-align: middle;
            }
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            .aico-btn-disabled { opacity: 0.6; cursor: not-allowed !important; }
            #aico-result { border-top: 1px solid #ddd; margin-top: 15px; padding-top: 15px; }
            #aico-status { margin-top: 10px; padding: 10px; border-radius: 4px; display: none; }
            #aico-status.success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
            #aico-status.error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        ");

        // 注册并加载脚本
        wp_enqueue_script('aico-admin-script', false, ['jquery', 'wp-blocks', 'wp-element', 'wp-data'], null, true);
        wp_add_inline_script('aico-admin-script', aico_get_inline_js());
    }
});


// =============================================================================
// 2. Meta Box Setup
// =============================================================================
// 添加 meta box
add_action('add_meta_boxes', function () {
    $post_types = ['post', 'product']; // 可改为你的商品类型
    foreach ($post_types as $pt) {
        add_meta_box(
            'aico-metabox',
            '🚀 AI 内容优化器',
            'aico_render_metabox',
            $pt,
            'normal',
            'high'
        );
    }
});

// 渲染 meta box 的 HTML 内容
function aico_render_metabox($post) {
    // 生成一个 nonce 字段，用于后续的安全验证
    wp_nonce_field('aico_save_meta', 'aico_nonce');

    $brands = get_option('aico_brands', ['RoadPro', 'TrailGear', 'NomadSupply']);
    $meta = get_post_meta($post->ID); // 使用 get_post_meta 替代 get_post_custom，更直接

    // 从 post meta 中获取保存的值
    $ai_brand = $meta['_ai_brand'][0] ?? '';
    $ai_keywords = $meta['_ai_keywords'][0] ?? '';
    $ai_features = $meta['_ai_features'][0] ?? '';
    $ai_scenarios = $meta['_ai_scenarios'][0] ?? '';
    ?>
    <div class="aico-field">
        <label for="aico_brand">所属品牌</label>
        <select id="aico_brand" name="aico_brand" class="regular-text">
            <?php foreach ($brands as $b): ?>
                <option value="<?= esc_attr($b) ?>" <?= selected($ai_brand, $b, false) ?>>
                    <?= esc_html($b) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="aico-field">
        <label for="aico_keywords">核心关键词</label>
        <input id="aico_keywords" name="aico_keywords" value="<?= esc_attr($ai_keywords) ?>" class="regular-text"/>
    </div>
    <div class="aico-field">
        <label for="aico_features">主要功能（逗号分隔）</label>
        <textarea id="aico_features" name="aico_features" class="widefat" rows="3"><?= esc_textarea($ai_features) ?></textarea>
    </div>
    <div class="aico-field">
        <label for="aico_scenarios">使用场景（如：winter, road trip）</label>
        <input id="aico_scenarios" name="aico_scenarios" value="<?= esc_attr($ai_scenarios) ?>" class="regular-text"/>
    </div>

    <button type="button" id="aico-optimize-btn" class="button button-primary">
        🔍 一键优化标题与描述
        <span class="aico-loading" style="display:none;"></span>
    </button>

    <div id="aico-status"></div>

    <div id="aico-result" style="display:none;">
        <h4>AI 建议</h4>
        <p><strong>标题：</strong><span id="aico-new-title"></span></p>
        <p><strong>描述预览：</strong></p>
        <div id="aico-new-desc"></div>
        <button type="button" id="aico-insert-btn" class="button">✅ 插入内容</button>
    </div>
    <?php
}

// 保存 meta box 数据
add_action('save_post', function ($post_id) {
    // 安全性检查
    if (!isset($_POST['aico_nonce']) || !wp_verify_nonce($_POST['aico_nonce'], 'aico_save_meta')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    // 保存每个字段
    $fields = ['aico_brand', 'aico_keywords', 'aico_features', 'aico_scenarios'];
    foreach ($fields as $field) {
        if (isset($_POST[$field])) {
            update_post_meta($post_id, '_' . $field, sanitize_text_field($_POST[$field]));
        }
    }
});


// =============================================================================
// 3. AJAX Handling
// =============================================================================
// AJAX 请求处理函数
add_action('wp_ajax_aico_optimize', function () {
    // 关键修复：这里的 nonce action 和 field name 必须和 JS 中传递的一致
    check_ajax_referer('aico_ajax_nonce', 'aico_nonce');

    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    if (!$post_id || !current_user_can('edit_post', $post_id)) {
        wp_send_json_error('权限不足或文章 ID 无效');
    }

    // 清理和准备数据
    $brand = sanitize_text_field($_POST['brand'] ?? '');
    $features = sanitize_text_field($_POST['features'] ?? '');
    $scenarios = sanitize_text_field($_POST['scenarios'] ?? '');

    $data = [
        'brand' => $brand,
        'product' => get_the_title($post_id),
        'features' => !empty($features) ? array_map('trim', explode(',', $features)) : [],
        'scenarios' => !empty($scenarios) ? array_map('trim', explode(',', $scenarios)) : [],
        'audience' => 'drivers and travelers in North America'
    ];

    // 调用 AI 模型
    $result = aico_call_ai_with_fallback($data);

    if (!empty($result['success'])) {
        wp_send_json_success($result['data']);
    } else {
        wp_send_json_error($result['error'] ?? '未知 AI 错误');
    }
});


// =============================================================================
// 4. AI API Call Logic
// =============================================================================
// 带有模型回退机制的 AI 调用函数
function aico_call_ai_with_fallback($data) {
    // 优化：使用 array_unique 确保模型列表不重复
    $models = array_unique([
        get_option('aico_default_model', 'openai/gpt-4o-mini'),
        'openai/gpt-4o-mini',
        'mistralai/mixtral-8x7b-instruct',
        'meta-llama/llama-3-70b-instruct'
    ]);

    $prompt = aico_build_automotive_prompt($data);

    // 关键 Bug 修复：修复了 messages 数组的结构错误。
    // 'system' 和 'user' 应该是并列的两个数组元素。
    $messages = [
        ['role' => 'system', 'content' => 'You are a senior copywriter specializing in automotive accessories. Respond in JSON format only.'],
        ['role' => 'user', 'content' => $prompt]
    ];

    foreach ($models as $model) {
        // 假设 AICO_OpenRouter_API::call 是一个已经存在的静态方法
        if (!class_exists('AICO_OpenRouter_API')) {
            return ["error" => "AICO_OpenRouter_API class not found."];
        }
        $res = AICO_OpenRouter_API::call($model, $messages);

        if (isset($res["choices"][0]["message"]["content"])) {
            $raw_content = $res["choices"][0]["message"]["content"];
            $json = aico_extract_json_from_response($raw_content);

            // 增加对返回 JSON 结构的验证
            if ($json && isset($json['title']) && isset($json['bullets']) && is_array($json['bullets'])) {
                return ["success" => true, "data" => $json];
            }
        }

        // 记录详细错误日志
        if (isset($res["error"])) {
            error_log("AI Model ($model) failed: " . (is_array($res["error"]) ? json_encode($res["error"]) : $res["error"]));
        }
    }

    return ["error" => "所有 AI 模型均调用失败，请检查 API 配置或稍后重试。"];
}

// 构建 AI 提示词
function aico_build_automotive_prompt($data) {
    $features = implode(", ", $data["features"]);
    $scenarios = implode(", ", $data["scenarios"]);

    return "Create a compelling product title and 3-4 benefit-driven bullet points for the product '{$data["brand"]} {$data["product"]}'.

    Key features: {$features}
    Use cases: {$scenarios}
    Target audience: {$data["audience"]}

    Return JSON format exactly like this, with no extra text or markdown:
    {
      \"title\": \"SEO-optimized product title under 80 characters\",
      \"bullets\": [
        {\"feature\": \"Feature Name 1\", \"benefit\": \"Benefit description 1.\"},
        {\"feature\": \"Feature Name 2\", \"benefit\": \"Benefit description 2.\"}
      ]
    }";
}

// 增强的 JSON 提取函数
function aico_extract_json_from_response($content) {
    // 移除可能的 markdown 代码块标记
    $content = preg_replace('/^```json\s*|\s*```$/i', '', trim($content));

    // 尝试直接解码
    $json = json_decode($content, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        return $json;
    }

    // 如果直接解码失败，尝试查找最外层的 '{' 和 '}'
    $start = strpos($content, '{');
    $end = strrpos($content, '}');

    if ($start !== false && $end !== false && $end > $start) {
        $json_str = substr($content, $start, $end - $start + 1);
        $json = json_decode($json_str, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $json;
        }
    }

    // 如果还是失败，返回 false
    return false;
}


// =============================================================================
// 5. Inline JavaScript for Meta Box
// =============================================================================
// 将 JS 代码放入一个单独的函数中，便于在 `wp_add_inline_script` 中调用
function aico_get_inline_js() {
    global $post;
    // 使用 ob_start 捕获输出，避免直接 echo
    ob_start();
    ?>
    document.addEventListener('DOMContentLoaded', function() {
        const $ = jQuery; // 确保 $ 是 jQuery

        const optimizeBtn = $('#aico-optimize-btn');
        const loadingSpinner = optimizeBtn.find('.aico-loading');
        const resultDiv = $('#aico-result');
        const insertBtn = $('#aico-insert-btn');
        const statusDiv = $('#aico-status');

        let aicoNewContent = null; // 存储 AI 返回的数据

        // 显示状态消息
        function showStatus(message, isError = false) {
            statusDiv.text(message)
                     .removeClass(isError ? 'success' : 'error')
                     .addClass(isError ? 'error' : 'success')
                     .show()
                     .delay(5000)
                     .fadeOut();
        }

        // 优化按钮点击事件
        optimizeBtn.on('click', function() {
            if (optimizeBtn.hasClass('aico-btn-disabled')) return;

            optimizeBtn.addClass('aico-btn-disabled');
            loadingSpinner.show();
            resultDiv.hide();
            statusDiv.hide();

            // 关键修复：从页面上的 nonce 隐藏字段获取值，而不是重新生成。
            // 并且使用新的 action name 'aico_ajax_nonce'。
            const data = {
                action: 'aico_optimize',
                post_id: <?= $post->ID ?>,
                brand: $('#aico_brand').val(),
                keywords: $('#aico_keywords').val(),
                features: $('#aico_features').val(),
                scenarios: $('#aico_scenarios').val(),
                aico_nonce: $('#aico_nonce').val() // 从隐藏字段获取 nonce
            };

            $.post(ajaxurl, data)
                .done(function(res) {
                    if (res.success) {
                        aicoNewContent = res.data; // 存储返回的数据
                        $('#aico-new-title').text(res.data.title);
                        const bulletsHtml = '<ul>' + res.data.bullets.map(b =>
                            `<li><strong>${$('<div>').text(b.feature).html()}</strong>: ${$('<div>').text(b.benefit).html()}</li>`
                        ).join('') + '</ul>';
                        $('#aico-new-desc').html(bulletsHtml);
                        resultDiv.show();
                    } else {
                        showStatus('错误: ' + (res.data || '未知错误'), true);
                    }
                })
                .fail(function() {
                    showStatus('请求失败，请检查网络或服务器配置。', true);
                })
                .always(function() {
                    optimizeBtn.removeClass('aico-btn-disabled');
                    loadingSpinner.hide();
                });
        });

        // 插入内容按钮点击事件
        insertBtn.on('click', function() {
            if (!aicoNewContent) return;

            // 更新标题
            const newTitle = aicoNewContent.title;
            const newContent = '\n\n### Why You’ll Love It\n' +
                aicoNewContent.bullets.map(b => `- **${b.feature}**: ${b.benefit}`).join('\n');

            // 判断当前是古腾堡编辑器还是经典编辑器
            if (wp && wp.data && wp.data.dispatch('core/editor')) {
                // --- 古腾堡编辑器支持 ---
                const { dispatch } = wp.data;
                dispatch('core/editor').editPost({ title: newTitle });

                const currentBlocks = wp.data.select('core/editor').getBlocks();
                const newBlock = wp.blocks.createBlock('core/paragraph', {
                    content: newContent.replace(/\n/g, '<br>') // 将换行符转为 <br>
                });
                dispatch('core/editor').insertBlocks(newBlock, currentBlocks.length);
                
                showStatus('已插入内容到区块编辑器！');

            } else if ($('#content').length) {
                // --- 经典编辑器支持 ---
                $('#title').val(newTitle).trigger('change'); // 触发 change 事件
                const editor = $('#content');
                editor.val(editor.val() + newContent);
                showStatus('已插入内容到经典编辑器！');

            } else {
                showStatus('未找到可用的编辑器。', true);
            }
        });
    });
    <?php
    return ob_get_clean();
}
