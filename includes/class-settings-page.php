<?php
class AICO_Settings_Page {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_init', [$this, 'settings_init']);
    }

    public function add_menu() {
        add_options_page(
            'AI 内容优化器设置',
            'AI Optimizer',
            'manage_options',
            'aico-settings',
            [$this, 'render']
        );
    }

    public function settings_init() {
        register_setting('aico_settings', 'aico_openrouter_api_key');
        register_setting('aico_settings', 'aico_default_model', ['default' => 'openai/gpt-oss-20b']);
        register_setting('aico_settings', 'aico_brands', [
            'default' => ['RoadPro', 'TrailGear', 'NomadSupply']
        ]);
    }

    public function render() {
        ?>
        <div class="wrap">
            <h1>AI Content Optimizer 设置</h1>
            <form method="post" action="options.php">
                <?php settings_fields('aico_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th><label>OpenRouter API Key</label></th>
                        <td><input name="aico_openrouter_api_key" type="password" value="<?php echo esc_attr(get_option('aico_openrouter_api_key')); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th><label>默认模型</label></th>
                        <td>
                            <select name="aico_default_model" class="regular-text">
                                <option value="openai/gpt-oss-20b" <?php selected(get_option('aico_default_model'), 'openai/gpt-oss-20b'); ?>>gpt-oss-20b (推荐)</option>
                                <option value="openai/gpt-oss-120b" <?php selected(get_option('aico_default_model'), 'openai/gpt-oss-120b'); ?>>gpt-oss-120b</option>
                                <option value="mistralai/mixtral-8x7b-instruct" <?php selected(get_option('aico_default_model'), 'mistralai/mixtral-8x7b-instruct'); ?>>Mixtral 8x7B</option>
                                <option value="meta-llama/llama-3-70b-instruct" <?php selected(get_option('aico_default_model'), 'meta-llama/llama-3-70b-instruct'); ?>>Llama 3 70B</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label>管理品牌</label></th>
                        <td>
                            <?php $brands = get_option('aico_brands', ['RoadPro', 'TrailGear', 'NomadSupply']); ?>
                            <input name="aico_brands[]" value="<?php echo esc_attr($brands[0]); ?>" class="regular-text" /><br/>
                            <input name="aico_brands[]" value="<?php echo esc_attr($brands[1]); ?>" class="regular-text" /><br/>
                            <input name="aico_brands[]" value="<?php echo esc_attr($brands[2]); ?>" class="regular-text" />
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}

new AICO_Settings_Page();