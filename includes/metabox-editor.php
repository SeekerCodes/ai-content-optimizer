<?php
// 添加 meta box
add_action('add_meta_boxes', function () {
    $post_types = ['post', 'page']; // 可改为你的商品类型
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

// 渲染 meta box
function aico_render_metabox($post) {
    wp_nonce_field('aico_save', 'aico_nonce');
    $brands = get_option('aico_brands', ['RoadPro', 'TrailGear', 'NomadSupply']);
    $meta = get_post_custom($post->ID);
    ?>
    <style>.aico-field{margin:10px 0;}</style>
    <div class="aico-field">
        <label>所属品牌</label>
        <select name="aico_brand" class="regular-text">
            <?php foreach ($brands as $b): ?>
                <option value="<?= esc_attr($b) ?>" <?= selected($meta['_ai_brand'][0] ?? '', $b, false) ?>>
                    <?= esc_html($b) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="aico-field">
        <label>核心关键词</label>
        <input name="aico_keywords" value="<?= esc_attr($meta['_ai_keywords'][0] ?? '') ?>" class="regular-text"/>
    </div>
    <div class="aico-field">
        <label>主要功能（逗号分隔）</label>
        <textarea name="aico_features" class="regular-text" rows="3"><?= esc_textarea($meta['_ai_features'][0] ?? '') ?></textarea>
    </div>
    <div class="aico-field">
        <label>使用场景（如：winter, road trip）</label>
        <input name="aico_scenarios" value="<?= esc_attr($meta['_ai_scenarios'][0] ?? '') ?>" class="regular-text"/>
    </div>
    <button type="button" id="aico-optimize-btn" class="button button-primary">🔍 一键优化标题与描述</button>
    <div id="aico-result" style="margin-top:15px; display:none;">
        <h4>AI 建议</h4>
        <p><strong>标题：</strong><span id="aico-new-title"></span></p>
        <p><strong>描述预览：</strong><div id="aico-new-desc"></div></p>
        <button id="aico-insert-btn" class="button">✅ 插入内容</button>
    </div>
    <script>
    jQuery('#aico-optimize-btn').on('click', function() {
        const data = {
            action: 'aico_optimize',
            post_id: <?= $post->ID ?>,
            brand: jQuery('[name=aico_brand]').val(),
            keywords: jQuery('[name=aico_keywords]').val(),
            features: jQuery('[name=aico_features]').val(),
            scenarios: jQuery('[name=aico_scenarios]').val(),
            aico_nonce: '<?= wp_create_nonce("aico_save") ?>' // ✅ 添加 nonce
        };
        jQuery.post(ajaxurl, data, function(res) {
            if (res.success) {
                jQuery('#aico-new-title').text(res.data.title);
                jQuery('#aico-new-desc').html('<ul>' + res.data.bullets.map(b => 
                    `<li><strong>${b.feature}</strong>: ${b.benefit}</li>`
                ).join('') + '</ul>');
                jQuery('#aico-result').show();
                window.aicoNewContent = res.data;
            } else {
                alert('Error: ' + (res.data || res.error || 'Unknown'));
            }
        }).fail(function() {
            alert('请求失败，请检查网络或 API 配置');
        });
    });

    jQuery('#aico-insert-btn').on('click', function() {
        if (window.aicoNewContent) {
            // 更新标题
            jQuery('#title').val(window.aicoNewContent.title);
            
            // 更新内容（仅经典编辑器）
            const editor = jQuery('#content');
            const newContent = '\n\n### Why You’ll Love It\n' + 
                window.aicoNewContent.bullets.map(b => `- **${b.feature}**: ${b.benefit}`).join('\n');
            editor.val(editor.val() + newContent);
            
            alert('已插入内容！注意：仅支持经典编辑器');
        }
    });
    </script>
    <?php
}

// AJAX 处理
add_action('wp_ajax_aico_optimize', function () {
    // ✅ 1. 验证 nonce
    check_ajax_referer('aico_save', 'aico_nonce');

    // ✅ 2. 权限检查
    if (!current_user_can('edit_post', $_POST['post_id'])) {
        wp_send_json_error('权限不足');
    }

    // ✅ 3. 过滤输入
    $brand = sanitize_text_field($_POST['brand'] ?? '');
    $features = sanitize_text_field($_POST['features'] ?? '');
    $scenarios = sanitize_text_field($_POST['scenarios'] ?? '');

    $data = [
        'brand' => $brand,
        'product' => get_the_title($_POST['post_id']),
        'features' => $features ? array_map('sanitize_text_field', explode(',', $features)) : [],
        'scenarios' => $scenarios ? array_map('sanitize_text_field', explode(',', $scenarios)) : [],
        'audience' => 'drivers and travelers in North America'
    ];

    // ✅ 4. 调用 AI
    $prompt = aico_build_automotive_prompt($data);
    $messages = [
        ['role' => 'system', 'content' => 'You are a senior copywriter. Respond in JSON only.'],
        ['role' => 'user', 'content' => $prompt]
    ];

    $res = AICO_OpenRouter_API::call(get_option('aico_default_model'), $messages);

    // ✅ 5. 安全解析 JSON
    if (isset($res['choices'][0]['message']['content'])) {
        $raw_content = $res['choices'][0]['message']['content'];
        $json = json_decode($raw_content, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            wp_send_json_success($json);
        } else {
            error_log('AI 返回非 JSON 内容: ' . $raw_content);
            wp_send_json_error('AI 返回格式错误，请重试');
        }
    } else {
        wp_send_json_error($res['error'] ?? 'AI 调用失败');
    }
});